<?php
namespace VTX\Media\Jobs;
use VTX\Media\Database\Schema;
defined("ABSPATH") || exit();
final class Logger
{
    private static $events = 0;
    public function event(
        string $event,
        int $job = 0,
        int $attachment = 0,
        string $rule = "",
        array $context = []
    ): void {
        global $wpdb;
        if (++self::$events % 100 === 0) {
            $this->prune();
        }
        $wpdb->insert(Schema::table("job_logs"), [
            "job_id" => $job,
            "attachment_id" => $attachment,
            "rule_id" => substr(sanitize_key($rule), 0, 64),
            "event" => substr(sanitize_key($event), 0, 64),
            "created_at" => current_time("mysql", true),
            "context" => wp_json_encode([
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
            ]),
        ]);
    }
    public function prune(): void
    {
        global $wpdb;
        $table = Schema::table("job_logs");
        $wpdb->query(
            "DELETE FROM $table WHERE created_at < UTC_TIMESTAMP() - INTERVAL 30 DAY LIMIT 1000",
        );
        $cut = $wpdb->get_var(
            "SELECT id FROM $table ORDER BY id DESC LIMIT 1 OFFSET 4999",
        );
        if ($cut) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $table WHERE id < %d LIMIT 1000",
                    $cut,
                ),
            );
        }
    }
}
