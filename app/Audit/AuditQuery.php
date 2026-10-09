<?php
namespace VTX\Media\Audit;
use VTX\Media\Database\Schema;
use VTX\Media\Permissions\Policy;
defined("ABSPATH") || exit();
final class AuditQuery
{
    private $engine;
    private $policy;
    public function __construct(AuditEngine $engine, Policy $policy)
    {
        $this->engine = $engine;
        $this->policy = $policy;
    }
    public function collectionQuery(): string
    {
        global $wpdb;
        $h = Schema::table("health");
        $f = Schema::table("findings");
        return $wpdb->prepare(
            "SELECT f.attachment_id,f.rule_id,f.category,f.severity,h.score FROM $f f JOIN $h h ON h.attachment_id=f.attachment_id WHERE f.status='open' AND h.state IN ('complete','failed') AND h.signature=%s",
            $this->engine->signature(),
        );
    }
    public function dashboard(): array
    {
        global $wpdb;
        $h = Schema::table("health");
        $f = Schema::table("findings");
        $scope = $this->policy->scope();
        $signature = $this->engine->signature();
        $current = $wpdb->prepare("h.signature=%s", $signature);
        $row = $wpdb->get_row(
            "SELECT COUNT(*) total, COALESCE(SUM(h.audited_at IS NOT NULL),0) previously_analyzed, COALESCE(SUM(h.state='complete' AND $current),0) analyzed, COALESCE(SUM(h.state='failed' AND $current),0) failed, AVG(CASE WHEN h.state='complete' AND $current THEN h.score END) score FROM {$wpdb->posts} p LEFT JOIN $h h ON h.attachment_id=p.ID WHERE p.post_type='attachment' AND p.post_status='inherit' $scope",
            ARRAY_A,
        );
        foreach (
            ["total", "analyzed", "failed", "previously_analyzed"]
            as $key
        ) {
            $row[$key] = (int) $row[$key];
        }
        $row["score"] =
            $row["score"] === null ? null : (int) round($row["score"]);
        $row["pending"] = max(
            0,
            $row["total"] - $row["analyzed"] - $row["failed"],
        );
        $from = "FROM $f f JOIN {$wpdb->posts} p ON p.ID=f.attachment_id JOIN $h h ON h.attachment_id=p.ID WHERE p.post_type='attachment' AND p.post_status='inherit' $scope AND $current AND h.state IN ('complete','failed') AND f.status='open'";
        $groups = $wpdb->get_results(
            "SELECT f.category,f.rule_id,f.severity,COUNT(*) count $from GROUP BY f.category,f.rule_id,f.severity",
            ARRAY_A,
        );
        // Aggregate only rule/category groups, never individual findings in PHP.
        foreach (
            [
                "categories" => "category",
                "rules" => "rule_id",
                "severities" => "severity",
            ]
            as $key => $column
        ) {
            $totals = [];
            foreach ($groups as $group) {
                $totals[$group[$column]] =
                    ($totals[$group[$column]] ?? 0) + (int) $group["count"];
            }
            $row[$key] = [];
            foreach ($totals as $id => $count) {
                $row[$key][] = ["id" => $id, "count" => $count];
            }
        }
        $counts = $wpdb->get_row(
            "SELECT COUNT(DISTINCT f.attachment_id) attention,COALESCE(SUM(f.severity IN ('critical','high')),0) priority,COALESCE(SUM(f.severity IN ('low','info')),0) recommendations $from",
            ARRAY_A,
        );
        foreach ($counts as $key => $value) {
            $row[$key] = (int) $value;
        }
        return $row;
    }
    public function findings(array $args): array
    {
        global $wpdb;
        $f = Schema::table("findings");
        $h = Schema::table("health");
        $where =
            "p.post_type='attachment' AND p.post_status='inherit'" .
            $this->policy->scope();
        if (!empty($args["current"])) {
            $where .= $wpdb->prepare(
                " AND h.signature=%s AND h.state IN ('complete','failed')",
                $this->engine->signature(),
            );
        }
        if (($args["group"] ?? "") === "priority") {
            $where .= " AND f.severity IN ('critical','high')";
        }
        if (($args["group"] ?? "") === "recommendations") {
            $where .= " AND f.severity IN ('low','info')";
        }
        foreach (["status", "category", "severity", "rule_id"] as $key) {
            if (!empty($args[$key])) {
                $where .= $wpdb->prepare(" AND f.$key=%s", $args[$key]);
            }
        }
        if (!empty($args["type"])) {
            $where .= $wpdb->prepare(
                " AND p.post_mime_type LIKE %s",
                $args["type"] . "/%",
            );
        }
        if (!empty($args["confidence"])) {
            $where .= $wpdb->prepare(
                " AND f.confidence >= %f",
                $args["confidence"],
            );
        }
        if (!empty($args["search"])) {
            $like = "%" . $wpdb->esc_like($args["search"]) . "%";
            $where .= $wpdb->prepare(
                " AND (h.filename LIKE %s OR p.post_title LIKE %s)",
                $like,
                $like,
            );
        }
        $from = "FROM $f f JOIN {$wpdb->posts} p ON p.ID=f.attachment_id LEFT JOIN $h h ON h.attachment_id=p.ID WHERE $where";
        $total = (int) $wpdb->get_var("SELECT COUNT(*) $from");
        $mediaTotal = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT f.attachment_id) $from",
        );
        $orders = [
            "severity" => "f.severity_rank DESC,f.id DESC",
            "health" => "h.score ASC,f.id DESC",
            "filename" => "h.filename ASC,f.id ASC",
            "recent" => "f.audited_at DESC,f.id DESC",
        ];
        $order = $orders[$args["sort"] ?? "severity"] ?? $orders["severity"];
        $per = min(100, max(1, (int) ($args["per_page"] ?? 25)));
        $page = max(1, (int) ($args["page"] ?? 1));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT f.*,h.filename,h.score,h.state,h.signature,p.post_title,p.post_mime_type $from ORDER BY $order LIMIT %d OFFSET %d",
                $per,
                ($page - 1) * $per,
            ),
            ARRAY_A,
        );
        $ids = array_values(array_unique(array_column($rows, "attachment_id")));
        if ($ids) {
            _prime_post_caches($ids, false, true);
        }
        $items = [];
        $thumbs = [];
        foreach ($rows as $row) {
            $id = (int) $row["attachment_id"];
            if (!isset($thumbs[$id])) {
                $thumbs[$id] =
                    wp_get_attachment_image_url($id, "thumbnail") ?: "";
            }
            $item = $this->engine->present($row);
            $item["thumbnail"] = $thumbs[$id];
            $item["score"] =
                $row["state"] === "complete" &&
                $row["signature"] === $this->engine->signature()
                    ? (int) $row["score"]
                    : null;
            $item["stale"] =
                $item["stale"] ||
                $row["signature"] !== $this->engine->signature() ||
                $row["state"] !== "complete";
            unset($item["signature"]);
            $items[] = $item;
        }
        return [
            "items" => $items,
            "pagination" => [
                "page" => $page,
                "per_page" => $per,
                "total" => $total,
                "media_total" => $mediaTotal,
                "pages" => (int) ceil($total / $per),
            ],
        ];
    }
}
