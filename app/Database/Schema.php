<?php
namespace VTX\Media\Database;
defined("ABSPATH") || exit();
final class Schema
{
    public const VERSION = "3";
    public static function table(string $suffix): string
    {
        global $wpdb;
        if (
            !in_array(
                $suffix,
                [
                    "folders",
                    "memberships",
                    "favorites",
                    "facts",
                    "findings",
                    "health",
                    "jobs",
                    "job_logs",
                ],
                true,
            )
        ) {
            throw new \InvalidArgumentException("Invalid VTX table");
        }
        return $wpdb->prefix . "vtx_media_" . $suffix;
    }
    public static function activate($network_wide = false): void
    {
        if ($network_wide) {
            wp_die(
                esc_html__(
                    "Please activate VTX Media individually on each site.",
                    "vtx-media",
                ),
            );
        }
        self::install();
    }
    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        $collate = $wpdb->get_charset_collate();
        $definitions = [
            "folders" => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
name varchar(191) NOT NULL,
position int(11) NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY parent_order (parent_id,position,id),
KEY name (name)",
            "memberships" => "attachment_id bigint(20) unsigned NOT NULL,
folder_id bigint(20) unsigned NOT NULL,
PRIMARY KEY  (attachment_id),
KEY folder_media (folder_id,attachment_id)",
            "favorites" => "user_id bigint(20) unsigned NOT NULL,
attachment_id bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (user_id,attachment_id),
KEY attachment_id (attachment_id)",
            "facts" => "attachment_id bigint(20) unsigned NOT NULL,
bytes bigint(20) unsigned DEFAULT NULL,
checked_at datetime NOT NULL,
PRIMARY KEY  (attachment_id),
KEY size_order (bytes,attachment_id)",

            "findings" => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
attachment_id bigint(20) unsigned NOT NULL,
rule_id varchar(64) NOT NULL,
rule_version varchar(32) NOT NULL,
category varchar(40) NOT NULL,
severity varchar(12) NOT NULL,
severity_rank tinyint unsigned NOT NULL,
confidence decimal(4,3) NOT NULL,
kind varchar(16) NOT NULL,
fingerprint char(64) NOT NULL,
data longtext NOT NULL,
status varchar(12) NOT NULL DEFAULT 'open',
audited_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY attachment_rule (attachment_id,rule_id),
KEY attachment_status (attachment_id,status),
KEY rule_status (rule_id,status),
KEY category_status (category,status),
KEY severity_status (status,severity_rank,id)",
            "health" => "attachment_id bigint(20) unsigned NOT NULL,
filename varchar(255) NOT NULL DEFAULT '',
state varchar(12) NOT NULL DEFAULT 'pending',
score tinyint unsigned DEFAULT NULL,
signature char(64) NOT NULL DEFAULT '',
revision bigint(20) unsigned NOT NULL DEFAULT 0,
errors text NOT NULL,
audited_at datetime DEFAULT NULL,
PRIMARY KEY  (attachment_id),
KEY state_attachment (state,attachment_id),
KEY signature_state (signature,state),
KEY score_order (score,attachment_id)",
            "jobs" => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
type varchar(64) NOT NULL,
owner_id bigint(20) unsigned NOT NULL,
scope_author bigint(20) unsigned NOT NULL DEFAULT 0,
max_id bigint(20) unsigned NOT NULL DEFAULT 0,
last_id bigint(20) unsigned NOT NULL DEFAULT 0,
total bigint(20) unsigned NOT NULL DEFAULT 0,
processed bigint(20) unsigned NOT NULL DEFAULT 0,
failed bigint(20) unsigned NOT NULL DEFAULT 0,
skipped bigint(20) unsigned NOT NULL DEFAULT 0,
status varchar(16) NOT NULL,
active_key varchar(100) DEFAULT NULL,
batch_size smallint unsigned NOT NULL DEFAULT 25,
lease_token varchar(64) NOT NULL DEFAULT '',
lease_until datetime DEFAULT NULL,
heartbeat datetime DEFAULT NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
completed_at datetime DEFAULT NULL,
context longtext DEFAULT NULL,
PRIMARY KEY  (id),
UNIQUE KEY active_key (active_key),
KEY status_updated (status,updated_at),
KEY owner_id (owner_id,id)",
            "job_logs" => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
job_id bigint(20) unsigned NOT NULL DEFAULT 0,
attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
rule_id varchar(64) NOT NULL DEFAULT '',
event varchar(64) NOT NULL,
context text DEFAULT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY job_event (job_id,id),
KEY retention (created_at)",
        ];
        foreach ($definitions as $suffix => $columns) {
            $table = self::table($suffix);
            dbDelta(
                "CREATE TABLE $table (\n$columns\n) ENGINE=InnoDB $collate;",
            );
            if (
                $wpdb->get_var(
                    $wpdb->prepare(
                        "SHOW TABLES LIKE %s",
                        $wpdb->esc_like($table),
                    ),
                ) !== $table ||
                $wpdb->last_error
            ) {
                throw new \RuntimeException(
                    "VTX Media schema installation failed.",
                );
            }
            $expected_columns = [
                "folders" => [
                    "id",
                    "parent_id",
                    "name",
                    "position",
                    "created_at",
                    "updated_at",
                ],
                "memberships" => ["attachment_id", "folder_id"],
                "favorites" => ["user_id", "attachment_id", "created_at"],
                "facts" => ["attachment_id", "bytes", "checked_at"],
            ];
            foreach (["findings", "health", "jobs", "job_logs"] as $added) {
                preg_match_all(
                    "/^([a-z_]+) (?:bigint|varchar|tinyint|smallint|decimal|char|longtext|text|datetime)/m",
                    $definitions[$added],
                    $matches,
                );
                $expected_columns[$added] = $matches[1];
            }
            $actual_columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`");
            $indexes = $wpdb->get_results("SHOW INDEX FROM `$table`", ARRAY_A);
            $required_index = [
                "folders" => "parent_order",
                "memberships" => "folder_media",
                "favorites" => "attachment_id",
                "facts" => "size_order",
                "findings" => "attachment_rule",
                "health" => "state_attachment",
                "jobs" => "active_key",
                "job_logs" => "job_event",
            ][$suffix];
            if (
                array_diff($expected_columns[$suffix], $actual_columns) ||
                !in_array(
                    $required_index,
                    array_column($indexes, "Key_name"),
                    true,
                )
            ) {
                throw new \RuntimeException(
                    "VTX Media schema verification failed.",
                );
            }
        }
        update_option("vtx_media_schema_version", self::VERSION, false);
    }
}
