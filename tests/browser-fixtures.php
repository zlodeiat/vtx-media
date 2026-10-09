<?php
if (!defined("WP_CLI") || !WP_CLI) {
    exit();
}
wp_set_current_user(
    (int) get_users([
        "role" => "administrator",
        "number" => 1,
        "fields" => "ID",
    ])[0],
);
require_once ABSPATH . "wp-admin/includes/file.php";
require_once ABSPATH . "wp-admin/includes/media.php";
require_once ABSPATH . "wp-admin/includes/image.php";
$ids = [];
$tag = "VTX-BROWSER-" . wp_generate_password(7, false, false);
$uploads = wp_get_upload_dir();
// Native upload API exercised with a tiny, valid PNG, cleaned through WP attachment API.
$png = base64_decode(
    "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWZkAAAAASUVORK5CYII=",
);
foreach (
    ["image/png", "application/pdf", "audio/mpeg", "video/mp4"]
    as $i => $mime
) {
    $file = null;
    if (!$i) {
        $up = wp_upload_bits($tag . ".png", null, $png);
        if ($up["error"]) {
            throw new RuntimeException($up["error"]);
        }
        $file = $up["file"];
    }
    $id = wp_insert_attachment(
        [
            "post_title" => $tag . " " . chr(65 + $i),
            "post_mime_type" => $mime,
            "post_status" => "inherit",
            "post_author" => get_current_user_id(),
        ],
        $file,
        0,
        true,
    );
    if (is_wp_error($id)) {
        throw new RuntimeException($id->get_error_message());
    }
    if ($file) {
        wp_update_attachment_metadata(
            $id,
            wp_generate_attachment_metadata($id, $file),
        );
    } else {
        update_post_meta(
            $id,
            "_wp_attached_file",
            $tag . "/" . ["", "brief.pdf", "interview.mp3", "launch.mp4"][$i],
        );
    }
    $ids[] = $id;
}
file_put_contents(
    __DIR__ . "/artifacts/fixtures.json",
    wp_json_encode(["ids" => $ids, "tag" => $tag]),
);
