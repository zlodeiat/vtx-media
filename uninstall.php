<?php
namespace VTX\Media;
defined("WP_UNINSTALL_PLUGIN") || exit();
if (!defined("VTX_MEDIA_DELETE_DATA") || VTX_MEDIA_DELETE_DATA !== true) {
    return;
}
$remove = static function () {
    global $wpdb;
    foreach (
        [
            "folders",
            "memberships",
            "favorites",
            "facts",
            "findings",
            "health",
            "jobs",
            "job_logs",
        ]
        as $suffix
    ) {
        $table = $wpdb->prefix . "vtx_media_" . $suffix;
        $wpdb->query("DROP TABLE IF EXISTS `$table`");
    }
    delete_option("vtx_media_schema_version");
    delete_option("vtx_media_audit_settings");
    wp_clear_scheduled_hook("vtx_media_job_tick");
    delete_metadata("post", 0, "_vtx_media_decorative", "", true);
};
if (is_multisite()) {
    $offset = 0;
    do {
        $sites = get_sites([
            "fields" => "ids",
            "number" => 100,
            "offset" => $offset,
        ]);
        foreach ($sites as $site) {
            switch_to_blog($site);
            $remove();
            restore_current_blog();
        }
        $offset += 100;
    } while (count($sites) === 100);
} else {
    $remove();
}
