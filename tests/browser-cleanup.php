<?php
if (!defined("WP_CLI") || !WP_CLI) {
    exit();
}
$file = __DIR__ . "/artifacts/fixtures.json";
if (is_file($file)) {
    $fixtures = json_decode(file_get_contents($file), true);
    foreach ($fixtures["ids"] as $id) {
        wp_delete_attachment($id, true);
    }
    global $wpdb;
    $folder_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT id FROM " .
                VTX\Media\Database\Schema::table("folders") .
                " WHERE name LIKE %s",
            $wpdb->esc_like($fixtures["tag"]) . "%",
        ),
    );
    wp_set_current_user(
        (int) get_users([
            "role" => "administrator",
            "number" => 1,
            "fields" => "ID",
        ])[0],
    );
    $folders = new VTX\Media\Folders\FolderRepository(
        new VTX\Media\Permissions\Policy(),
    );
    foreach ($folder_ids as $id) {
        $folders->delete((int) $id);
    }
    unlink($file);
}
