<?php
namespace VTX\Media\Database;
defined("ABSPATH") || exit();
/** Serializes organization mutations, including cycle checks and delete/assign races. */
final class Mutation
{
    public static function run(callable $callback)
    {
        global $wpdb;
        $lock = "vtx_media_" . md5(DB_NAME . $wpdb->prefix);
        if (
            "1" !==
            (string) $wpdb->get_var(
                $wpdb->prepare("SELECT GET_LOCK(%s, 5)", $lock),
            )
        ) {
            return new \WP_Error(
                "vtx_busy",
                __("The library is busy. Please try again.", "vtx-media"),
                ["status" => 409],
            );
        }
        try {
            self::check($wpdb->query("START TRANSACTION"));
            $result = $callback();
            self::check(
                $wpdb->query(is_wp_error($result) ? "ROLLBACK" : "COMMIT"),
            );
            return $result;
        } catch (\Throwable $error) {
            $wpdb->query("ROLLBACK");
            return new \WP_Error(
                "vtx_database",
                __(
                    "The change could not be saved. Please try again.",
                    "vtx-media",
                ),
                ["status" => 500],
            );
        } finally {
            $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock));
        }
    }
    public static function check($result): void
    {
        if ($result === false) {
            throw new \RuntimeException("Database write failed");
        }
    }
}
