<?php
namespace VTX\Media\Jobs;
use VTX\Media\Database\{Schema, Lock};
use VTX\Media\Permissions\Policy;
defined("ABSPATH") || exit();
final class JobRunner
{
    public const HOOK = "vtx_media_job_tick";
    private $registry;
    private $policy;
    private $logger;
    public function __construct(
        JobRegistry $registry,
        Policy $policy,
        Logger $logger
    ) {
        $this->registry = $registry;
        $this->policy = $policy;
        $this->logger = $logger;
    }
    public function get(int $id): ?array
    {
        global $wpdb;
        $t = Schema::table("jobs");
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id),
            ARRAY_A,
        );
        if (!$row) {
            return null;
        }
        foreach (
            [
                "id",
                "owner_id",
                "scope_author",
                "max_id",
                "last_id",
                "total",
                "processed",
                "failed",
                "skipped",
                "batch_size",
            ]
            as $key
        ) {
            $row[$key] = (int) $row[$key];
        }
        $row["stalled"] =
            $row["status"] === "running" &&
            strtotime(($row["heartbeat"] ?: $row["updated_at"]) . " UTC") <
                time() - 120;
        return $row;
    }
    public function publicState(array $row): array
    {
        unset($row["lease_token"], $row["active_key"], $row["context"]);
        $author = $row["scope_author"]
            ? get_userdata($row["scope_author"])
            : null;
        $row["scope_label"] = $row["scope_author"]
            ? sprintf(
                /* translators: %s: Uploading user's display name. */ __(
                    "Media uploaded by %s",
                    "vtx-media",
                ),
                $author
                    ? $author->display_name
                    : __("Deleted user", "vtx-media"),
            )
            : __("All media", "vtx-media");
        return $row;
    }
    public function canManage(array $row): bool
    {
        return apply_filters("vtx_media/job_allowed", true, $row["type"]) &&
            $this->policy->access() &&
            $this->registry->get($row["type"]) &&
            (!(
                $this->registry->get($row["type"]) instanceof WorkJobInterface
            ) ||
                $this->registry->get($row["type"])->authorize()) &&
            ($row["owner_id"] === get_current_user_id() ||
                current_user_can("manage_options"));
    }
    public function latest(?string $type = null): ?array
    {
        global $wpdb;
        $t = Schema::table("jobs");
        $scope = current_user_can("manage_options")
            ? ""
            : $wpdb->prepare(" WHERE owner_id=%d", get_current_user_id());
        if ($type !== null) {
            $scope .=
                ($scope ? " AND " : " WHERE ") .
                $wpdb->prepare("type=%s", $type);
        }
        $id = $wpdb->get_var(
            "SELECT id FROM $t $scope ORDER BY id DESC LIMIT 1",
        );
        return $id ? $this->publicState($this->get((int) $id)) : null;
    }
    public function start(string $type, int $batch = 25)
    {
        $job = $this->registry->get($type);
        if (
            !apply_filters("vtx_media/job_allowed", true, $type) ||
            !$job ||
            ($job instanceof WorkJobInterface && !$job->authorize())
        ) {
            return new \WP_Error(
                "vtx_job_type",
                __("Unknown or unavailable job type.", "vtx-media"),
                ["status" => 400],
            );
        }
        $author = $this->policy->allMedia() ? 0 : get_current_user_id();
        return Lock::run("job-create-" . $type, function () use (
            $job,
            $type,
            $batch,
            $author
        ) {
            global $wpdb;
            $t = Schema::table("jobs");
            $key = $type . ":" . $author;
            $existing = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM $t WHERE active_key=%s", $key),
            );
            if ($existing) {
                $row = $this->get((int) $existing);
                return $this->canManage($row)
                    ? $this->publicState($row)
                    : new \WP_Error(
                        "vtx_job_active",
                        __("An audit is already active.", "vtx-media"),
                        ["status" => 409],
                    );
            }
            $snapshot = $job->snapshot($author);
            $now = current_time("mysql", true);
            $ok = $wpdb->insert(
                $t,
                array_merge($snapshot, [
                    "type" => $type,
                    "owner_id" => get_current_user_id(),
                    "scope_author" => $author,
                    "status" => "running",
                    "active_key" => $key,
                    "batch_size" => max(1, min(100, $batch)),
                    "created_at" => $now,
                    "updated_at" => $now,
                ]),
            );
            if (!$ok) {
                return new \WP_Error(
                    "vtx_job_create",
                    __("Could not create scan.", "vtx-media"),
                    ["status" => 500],
                );
            }
            $id = (int) $wpdb->insert_id;
            $this->logger->event("scan-started", $id);
            $this->schedule();
            return $this->publicState($this->get($id));
        });
    }
    public function control(int $id, string $action)
    {
        global $wpdb;
        $row = $this->get($id);
        if (!$row || !$this->canManage($row)) {
            return new \WP_Error(
                "vtx_job_forbidden",
                __("You cannot manage this scan.", "vtx-media"),
                ["status" => 403],
            );
        }
        $allowed = [
            "pause" => ["running"],
            "resume" => ["paused", "running"],
            "cancel" => ["running", "paused"],
        ];
        if (
            !isset($allowed[$action]) ||
            !in_array($row["status"], $allowed[$action], true)
        ) {
            return new \WP_Error(
                "vtx_job_state",
                __("This scan cannot perform that action.", "vtx-media"),
                ["status" => 409],
            );
        }
        $status = [
            "pause" => "paused",
            "resume" => "running",
            "cancel" => "cancelled",
        ][$action];
        $data = [
            "status" => $status,
            "updated_at" => current_time("mysql", true),
        ];
        if ($action === "cancel") {
            $data["active_key"] = null;
            $data["completed_at"] = current_time("mysql", true);
        }
        $wpdb->update(Schema::table("jobs"), $data, [
            "id" => $id,
            "status" => $row["status"],
        ]);
        $this->logger->event("scan-" . $status, $id);
        $this->schedule();
        return $this->publicState($this->get($id));
    }
    public function tick(int $id)
    {
        return Lock::run(
            "job-worker-" . $id,
            function () use ($id) {
                global $wpdb;
                $t = Schema::table("jobs");
                $state = $this->get($id);
                if (!$state || $state["status"] !== "running") {
                    return $state ? $this->publicState($state) : null;
                }
                if (
                    $state["lease_until"] &&
                    strtotime($state["lease_until"] . " UTC") > time()
                ) {
                    return $this->publicState($state);
                }
                $token = wp_generate_uuid4();
                $now = current_time("mysql", true);
                $wpdb->update(
                    $t,
                    [
                        "lease_token" => $token,
                        "lease_until" => gmdate("Y-m-d H:i:s", time() + 90),
                        "heartbeat" => $now,
                    ],
                    ["id" => $id, "status" => "running"],
                );
                $previous = get_current_user_id();
                wp_set_current_user($state["owner_id"]);
                try {
                    $job = $this->registry->get($state["type"]);
                    if (
                        !apply_filters(
                            "vtx_media/job_allowed",
                            true,
                            $state["type"],
                        ) ||
                        !$job ||
                        ($job instanceof WorkJobInterface &&
                            !$job->authorize()) ||
                        !$this->policy->access() ||
                        (!$state["scope_author"] && !$this->policy->allMedia())
                    ) {
                        throw new \RuntimeException("Job unavailable");
                    }
                    $ids = $job->next($state, $state["batch_size"]);
                    if ($job instanceof WorkJobInterface) {
                        $job->prepare($ids);
                    } elseif ($ids) {
                        _prime_post_caches($ids, false, true);
                    }
                    $start = microtime(true);
                    foreach ($ids as $attachment) {
                        $fresh = $this->get($id);
                        if (
                            $fresh["status"] !== "running" ||
                            $fresh["lease_token"] !== $token
                        ) {
                            break;
                        }
                        try {
                            $ok =
                                $job instanceof WorkJobInterface
                                    ? $job->process($attachment, $id)
                                    : $this->policy->attachment($attachment) &&
                                        $job->process($attachment, $id);
                        } catch (\Throwable $error) {
                            $ok = false;
                            $this->logger->event(
                                "attachment-failure",
                                $id,
                                $attachment,
                            );
                        }
                        if (!$ok) {
                            $this->logger->event(
                                "attachment-incomplete",
                                $id,
                                $attachment,
                            );
                        }
                        $sql = $wpdb->prepare(
                            "UPDATE $t SET last_id=%d,processed=processed+1,failed=failed+%d,heartbeat=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP(),lease_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 90 SECOND) WHERE id=%d AND lease_token=%s",
                            $attachment,
                            $ok ? 0 : 1,
                            $id,
                            $token,
                        );
                        if ($wpdb->query($sql) === false) {
                            throw new \RuntimeException("Checkpoint failed");
                        }
                        if (microtime(true) - $start >= 5) {
                            break;
                        }
                    }
                    $fresh = $this->get($id);
                    if (
                        $fresh["status"] === "running" &&
                        !$job->next($fresh, 1)
                    ) {
                        $wpdb->update(
                            $t,
                            [
                                "status" => "completed",
                                "active_key" => null,
                                "skipped" => max(
                                    0,
                                    $fresh["total"] - $fresh["processed"],
                                ),
                                "completed_at" => current_time("mysql", true),
                                "updated_at" => current_time("mysql", true),
                            ],
                            [
                                "id" => $id,
                                "lease_token" => $token,
                                "status" => "running",
                            ],
                        );
                        $this->logger->event("scan-completed", $id);
                    }
                } catch (\Throwable $error) {
                    $wpdb->update(
                        $t,
                        [
                            "status" => "failed",
                            "active_key" => null,
                            "completed_at" => current_time("mysql", true),
                        ],
                        [
                            "id" => $id,
                            "lease_token" => $token,
                            "status" => "running",
                        ],
                    );
                    $this->logger->event("batch-failure", $id, 0, "", [
                        "reason" =>
                            $error instanceof \RuntimeException &&
                            $error->getMessage() === "Job unavailable"
                                ? "job-unavailable"
                                : "worker-exception",
                    ]);
                } finally {
                    wp_set_current_user($previous);
                    $wpdb->update(
                        $t,
                        ["lease_token" => "", "lease_until" => null],
                        ["id" => $id, "lease_token" => $token],
                    );
                }
                return $this->publicState($this->get($id));
            },
            0,
        );
    }
    public function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 30, "vtx_media_minute", self::HOOK);
        }
    }
    public function background(): void
    {
        global $wpdb;
        $t = Schema::table("jobs");
        $ids = $wpdb->get_col(
            "SELECT id FROM $t WHERE status='running' ORDER BY updated_at LIMIT 3",
        );
        foreach ($ids as $id) {
            $this->tick((int) $id);
        }
        $this->logger->prune();
    }
    public static function deactivate(): void
    {
        global $wpdb;
        $t = Schema::table("jobs");
        $wpdb->query("UPDATE $t SET status='paused' WHERE status='running'");
        wp_clear_scheduled_hook(self::HOOK);
    }
}
