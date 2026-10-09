<?php
namespace VTX\Media\Api;
use VTX\Media\Database\Schema;
defined("ABSPATH") || exit();
/** Read-only support views over the existing registries and job history. */
final class AdvancedController
{
    private $s;
    public function __construct(array $services)
    {
        $this->s = $services;
    }
    public function register(): void
    {
        $permission = function () {
            return $this->s["policy"]->can("advanced") &&
                $this->s["policy"]->allMedia();
        };
        $r = $this->s["routes"];
        $r->register(
            "/advanced/overview",
            "GET",
            function () {
                global $wp_version;
                $modules = array_map(static function ($m) {
                    return array_intersect_key(
                        $m,
                        array_flip(["id", "label", "feature"]),
                    );
                }, $this->s["modules"]->all());
                $features = array_map(function ($f) {
                    return $f + [
                        "entitled" => $this->s["entitlements"]->hasFeature(
                            $f["id"],
                        ),
                    ];
                }, $this->s["features"]->all());
                $extensions = [];
                foreach (
                    (array) apply_filters("vtx_media/support_versions", [])
                    as $label => $version
                ) {
                    if (is_string($label) && is_string($version)) {
                        $extensions[
                            sanitize_text_field(substr($label, 0, 80))
                        ] = sanitize_text_field(substr($version, 0, 80));
                    }
                }
                return [
                    "core" => \VTX\Media\VERSION,
                    "schema" => Schema::VERSION,
                    "wordpress" => $wp_version,
                    "php" => PHP_VERSION,
                    "developer_mode" =>
                        defined("VTX_MEDIA_DEV_MODE") &&
                        VTX_MEDIA_DEV_MODE === true,
                    "modules" => $modules,
                    "features" => $features,
                    "extensions" => $extensions,
                ];
            },
            [],
            $permission,
        );
        $r->register(
            "/advanced/rules",
            "GET",
            function () {
                return ["items" => $this->s["rules"]->manifest()];
            },
            [],
            $permission,
        );
        $r->register(
            "/advanced/jobs",
            "GET",
            function ($q) {
                global $wpdb;
                $table = Schema::table("jobs");
                $where = "1=1";
                if ($q["type"]) {
                    if (!$this->s["jobRegistry"]->get($q["type"])) {
                        return new \WP_Error(
                            "vtx_job_type",
                            __("Unknown scan type.", "vtx-media"),
                            ["status" => 400],
                        );
                    }
                    $where = $wpdb->prepare("type=%s", $q["type"]);
                }
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT 25 OFFSET %d",
                        ($q["page"] - 1) * 25,
                    ),
                    ARRAY_A,
                );
                $items = [];
                foreach ($rows as $row) {
                    if ($this->s["runner"]->canManage($row)) {
                        $items[] = $this->s["runner"]->publicState($row);
                    }
                }
                return [
                    "items" => $items,
                    "page" => (int) $q["page"],
                    "has_more" => count($rows) === 25,
                ];
            },
            [
                "page" => [
                    "type" => "integer",
                    "minimum" => 1,
                    "maximum" => 100000,
                    "default" => 1,
                ],
                "type" => [
                    "type" => "string",
                    "maxLength" => 64,
                    "default" => "",
                ],
            ],
            $permission,
        );
        $r->register(
            "/advanced/logs",
            "GET",
            function ($q) {
                global $wpdb;
                $table = Schema::table("job_logs");
                $where = ["1=1"];
                if ($q["job_id"]) {
                    $where[] = $wpdb->prepare("job_id=%d", $q["job_id"]);
                }
                if ($q["errors"]) {
                    $where[] =
                        "(event LIKE '%fail%' OR event LIKE '%exception%')";
                }
                if ($q["before"]) {
                    $where[] = $wpdb->prepare("id<%d", $q["before"]);
                }
                $sql = implode(" AND ", $where);
                // Context contains structured identifiers only; never expose arbitrary extension log text.
                $rows = $wpdb->get_results(
                    "SELECT id,job_id,attachment_id,rule_id,event,context,created_at FROM $table WHERE $sql ORDER BY id DESC LIMIT 50",
                    ARRAY_A,
                );
                foreach ($rows as &$row) {
                    $context = json_decode($row["context"] ?? "", true);
                    $row["context"] = [
                        "source_type" => substr(
                            sanitize_key($context["source_type"] ?? ""),
                            0,
                            20,
                        ),
                        "source_id" => absint($context["source_id"] ?? 0),
                        "reason" => substr(
                            sanitize_key($context["reason"] ?? ""),
                            0,
                            64,
                        ),
                    ];
                }
                unset($row);
                return [
                    "items" => $rows,
                    "next" =>
                        count($rows) === 50 ? (int) end($rows)["id"] : null,
                ];
            },
            [
                "job_id" => [
                    "type" => "integer",
                    "minimum" => 0,
                    "default" => 0,
                ],
                "before" => [
                    "type" => "integer",
                    "minimum" => 0,
                    "default" => 0,
                ],
                "errors" => ["type" => "boolean", "default" => false],
            ],
            $permission,
        );
    }
}
