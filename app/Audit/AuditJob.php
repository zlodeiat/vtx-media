<?php
namespace VTX\Media\Audit;
use VTX\Media\Jobs\JobInterface;
use VTX\Media\Entitlements\FeatureRegistry;
defined("ABSPATH") || exit();
final class AuditJob implements JobInterface
{
    private $engine;
    public function __construct(AuditEngine $engine)
    {
        $this->engine = $engine;
    }
    public function id(): string
    {
        return "media-audit";
    }
    public function feature(): string
    {
        return FeatureRegistry::AUDIT;
    }
    public function snapshot(int $author): array
    {
        global $wpdb;
        $scope = $author ? $wpdb->prepare(" AND post_author=%d", $author) : "";
        $row = $wpdb->get_row(
            "SELECT COUNT(*) total,COALESCE(MAX(ID),0) max_id FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' $scope",
            ARRAY_A,
        );
        return [
            "total" => (int) $row["total"],
            "max_id" => (int) $row["max_id"],
        ];
    }
    public function next(array $state, int $limit): array
    {
        global $wpdb;
        $scope = $state["scope_author"]
            ? $wpdb->prepare(" AND post_author=%d", $state["scope_author"])
            : "";
        $ids = array_map(
            "intval",
            $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' AND ID>%d AND ID<=%d $scope ORDER BY ID LIMIT %d",
                    $state["last_id"],
                    $state["max_id"],
                    $limit,
                ),
            ),
        );
        $this->engine->prepareBatch($ids);
        return $ids;
    }
    public function process(int $attachment, int $job): bool
    {
        $result = $this->engine->audit($attachment, $job);
        return !is_wp_error($result) && $result["state"] === "complete";
    }
}
