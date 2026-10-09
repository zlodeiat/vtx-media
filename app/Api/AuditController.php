<?php
namespace VTX\Media\Api;
use VTX\Media\Audit\{AuditEngine, AuditRuleRegistry, AuditQuery, Settings};
use VTX\Media\Jobs\JobRunner;
use VTX\Media\Permissions\Policy;
use VTX\Media\Database\Schema;
defined("ABSPATH") || exit();
final class AuditController
{
    private $routes;
    private $policy;
    private $engine;
    private $rules;
    private $query;
    private $jobs;
    private $settings;
    public function __construct(
        RouteRegistrar $routes,
        Policy $policy,
        AuditEngine $engine,
        AuditRuleRegistry $rules,
        AuditQuery $query,
        JobRunner $jobs,
        Settings $settings
    ) {
        $this->routes = $routes;
        $this->policy = $policy;
        $this->engine = $engine;
        $this->rules = $rules;
        $this->query = $query;
        $this->jobs = $jobs;
        $this->settings = $settings;
    }
    public function register(): void
    {
        $r = $this->routes;
        $id = ["type" => "integer", "minimum" => 1, "required" => true];
        $r->register("/audit/overview", "GET", function () {
            return [
                "summary" => $this->query->dashboard(),
                "rules" => $this->rules->manifest(),
                "job" => $this->jobs->latest("media-audit"),
            ];
        });
        $r->register(
            "/audit/findings",
            "GET",
            function ($q) {
                if ($q["rule_id"] && !$this->rules->get($q["rule_id"])) {
                    return new \WP_Error(
                        "vtx_rule",
                        __("Unknown rule.", "vtx-media"),
                        ["status" => 400],
                    );
                }
                return $this->query->findings($q->get_params());
            },
            [
                "current" => ["type" => "boolean", "default" => false],
                "group" => [
                    "type" => "string",
                    "enum" => ["", "priority", "recommendations"],
                    "default" => "",
                ],
                "page" => ["type" => "integer", "minimum" => 1, "default" => 1],
                "per_page" => [
                    "type" => "integer",
                    "minimum" => 1,
                    "maximum" => 100,
                    "default" => 25,
                ],
                "status" => [
                    "type" => "string",
                    "enum" => ["open", "ignored", "resolved", ""],
                    "default" => "open",
                ],
                "category" => [
                    "type" => "string",
                    "maxLength" => 40,
                    "default" => "",
                ],
                "rule_id" => [
                    "type" => "string",
                    "maxLength" => 64,
                    "default" => "",
                ],
                "severity" => [
                    "type" => "string",
                    "enum" => ["", "critical", "high", "medium", "low", "info"],
                    "default" => "",
                ],
                "type" => [
                    "type" => "string",
                    "enum" => [
                        "",
                        "image",
                        "video",
                        "audio",
                        "application",
                        "text",
                    ],
                    "default" => "",
                ],
                "confidence" => [
                    "type" => "number",
                    "minimum" => 0,
                    "maximum" => 1,
                    "default" => 0,
                ],
                "search" => [
                    "type" => "string",
                    "maxLength" => 200,
                    "default" => "",
                ],
                "sort" => [
                    "type" => "string",
                    "enum" => ["severity", "health", "filename", "recent"],
                    "default" => "severity",
                ],
            ],
        );
        $r->register(
            "/audit/media/(?P<id>\d+)",
            "GET",
            function ($q) {
                return $this->engine->detail((int) $q["id"]);
            },
            ["id" => $id],
            function ($q) {
                return $this->policy->attachment((int) $q["id"]);
            },
        );
        $r->register(
            "/audit/actions",
            "POST",
            [$this, "actions"],
            [
                "action" => [
                    "type" => "string",
                    "required" => true,
                    "enum" => [
                        "rescan",
                        "decorative",
                        "remove-decorative",
                        "ignore",
                        "restore",
                    ],
                ],
                "ids" => [
                    "type" => "array",
                    "required" => true,
                    "minItems" => 1,
                    "maxItems" => 50,
                    "uniqueItems" => true,
                    "items" => ["type" => "integer", "minimum" => 1],
                ],
            ],
        );
        $r->register("/audit/settings", "GET", function () {
            return [
                "values" => $this->settings->get(),
                "defaults" => Settings::DEFAULTS,
            ];
        });
        $r->register(
            "/audit/settings",
            "POST",
            function ($q) {
                $input = $q->get_json_params();
                if (!is_array($input)) {
                    return new \WP_Error(
                        "vtx_settings",
                        __("Invalid settings.", "vtx-media"),
                        ["status" => 400],
                    );
                }
                return $this->settings->save($input);
            },
            [],
            static function () {
                return $this->policy->can("settings");
            },
        );
        $r->register("/jobs", "GET", function () {
            return ["job" => $this->jobs->latest("media-audit")];
        });
        $r->register(
            "/jobs",
            "POST",
            function ($q) {
                return $this->jobs->start($q["type"], (int) $q["batch_size"]);
            },
            [
                "type" => [
                    "type" => "string",
                    "required" => true,
                    "maxLength" => 64,
                ],
                "batch_size" => [
                    "type" => "integer",
                    "minimum" => 1,
                    "maximum" => 100,
                    "default" => 25,
                ],
            ],
        );
        $r->register(
            "/jobs/(?P<id>\d+)/control",
            "POST",
            function ($q) {
                return $this->jobs->control((int) $q["id"], $q["action"]);
            },
            [
                "id" => $id,
                "action" => [
                    "type" => "string",
                    "required" => true,
                    "enum" => ["pause", "resume", "cancel"],
                ],
            ],
        );
        $r->register(
            "/jobs/(?P<id>\d+)/tick",
            "POST",
            function ($q) {
                return $this->jobs->tick((int) $q["id"]);
            },
            ["id" => $id],
            function ($q) {
                $job = $this->jobs->get((int) $q["id"]);
                return $job && $this->jobs->canManage($job);
            },
        );
        $r->register(
            "/jobs/(?P<id>\d+)/logs",
            "GET",
            function ($q) {
                global $wpdb;
                $t = Schema::table("job_logs");
                return [
                    "items" => $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT id,attachment_id,rule_id,event,context,created_at FROM $t WHERE job_id=%d AND id>%d ORDER BY id LIMIT 50",
                            $q["id"],
                            $q["after"],
                        ),
                        ARRAY_A,
                    ),
                ];
            },
            [
                "id" => $id,
                "after" => [
                    "type" => "integer",
                    "minimum" => 0,
                    "default" => 0,
                ],
            ],
            function ($q) {
                $job = $this->jobs->get((int) $q["id"]);
                return $job && $this->jobs->canManage($job);
            },
        );
    }
    public function actions(\WP_REST_Request $request): array
    {
        return (new \VTX\Media\Audit\AuditActions(
            $this->engine,
            $this->policy,
        ))->execute($request["action"], $request["ids"]);
    }
}
