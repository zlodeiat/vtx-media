<?php
namespace VTX\Media\Database;
defined("ABSPATH") || exit();
final class Lock
{
    public static function run(string $key, callable $callback, int $wait = 2)
    {
        global $wpdb;
        $name = "vtx_" . md5(DB_NAME . $wpdb->prefix . $key);
        if (
            (string) $wpdb->get_var(
                $wpdb->prepare("SELECT GET_LOCK(%s,%d)", $name, $wait),
            ) !== "1"
        ) {
            return new \WP_Error(
                "vtx_busy",
                __(
                    "This operation is busy. Please retry shortly.",
                    "vtx-media",
                ),
                ["status" => 409],
            );
        }
        try {
            return $callback();
        } finally {
            $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $name));
        }
    }
}
