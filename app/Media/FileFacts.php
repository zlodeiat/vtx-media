<?php
namespace VTX\Media\Media;
use VTX\Media\Database\Schema;
use VTX\Media\Database\Mutation;
use VTX\Media\Permissions\Policy;
defined("ABSPATH") || exit();
final class FileFacts
{
    public function local(int $id): array
    {
        $uploads = wp_get_upload_dir();
        $path = get_attached_file($id, true);
        $base = realpath($uploads["basedir"]);
        $real = $path ? realpath($path) : false;
        $safe =
            $base &&
            $real &&
            strpos($real, $base . DIRECTORY_SEPARATOR) === 0 &&
            is_file($real);
        $meta = wp_get_attachment_metadata($id);
        $bytes = $safe
            ? wp_filesize($real)
            : (isset($meta["filesize"])
                ? (int) $meta["filesize"]
                : null);
        return [
            "bytes" => $bytes,
            "local" => $safe,
            "file" => $safe
                ? str_replace(
                    DIRECTORY_SEPARATOR,
                    "/",
                    substr($real, strlen($base) + 1),
                )
                : null,
        ];
    }
    public function index(int $id): void
    {
        global $wpdb;
        $fact = $this->local($id);
        Mutation::check(
            $wpdb->replace(Schema::table("facts"), [
                "attachment_id" => $id,
                "bytes" => $fact["bytes"],
                "checked_at" => current_time("mysql", true),
            ]),
        );
    }
    public function batch(Policy $policy): array
    {
        global $wpdb;
        $f = Schema::table("facts");
        $scope = $policy->scope();
        $where = "p.post_type='attachment' AND p.post_status='inherit' AND f.attachment_id IS NULL $scope";
        $ids = $wpdb->get_col(
            "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN $f f ON f.attachment_id=p.ID WHERE $where ORDER BY p.ID LIMIT 200",
        );
        if ($ids) {
            _prime_post_caches($ids, false, true);
        }
        foreach ($ids as $id) {
            $this->index((int) $id);
        }
        $remaining = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN $f f ON f.attachment_id=p.ID WHERE $where",
        );
        return ["processed" => count($ids), "remaining" => $remaining];
    }
    public function invalidate($meta_id, $id, $key): void
    {
        if (
            in_array(
                $key,
                ["_wp_attachment_metadata", "_wp_attached_file"],
                true,
            ) &&
            get_post_type($id) === "attachment"
        ) {
            global $wpdb;
            $wpdb->delete(Schema::table("facts"), [
                "attachment_id" => (int) $id,
            ]);
        }
    }
}
