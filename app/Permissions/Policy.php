<?php
namespace VTX\Media\Permissions;
defined("ABSPATH") || exit();
final class Policy
{
    /** Generic action policy; Pro may map actions to native WordPress capabilities. */
    public function can(string $action): bool
    {
        $defaults = [
            "view" => "upload_files",
            "organize" => "upload_files",
            "metadata" => "upload_files",
            "audit" => "upload_files",
            "settings" => "manage_options",
            "advanced" => "manage_options",
        ];
        return (bool) apply_filters(
            "vtx_media/action_allowed",
            current_user_can($defaults[$action] ?? "manage_options"),
            $action,
        );
    }
    private function capability(string $key): string
    {
        $caps = apply_filters("vtx_media/capabilities", [
            "access" => "upload_files",
            "folders" => "edit_others_posts",
            "all_media" => "edit_others_posts",
        ]);
        return $caps[$key];
    }
    public function access(): bool
    {
        return current_user_can($this->capability("access"));
    }
    public function folders(): bool
    {
        return $this->access() &&
            current_user_can($this->capability("folders"));
    }
    public function allMedia(): bool
    {
        return current_user_can($this->capability("all_media"));
    }
    public function attachment(int $id, bool $write = false): bool
    {
        $post = get_post($id);
        return $this->access() &&
            $post &&
            $post->post_type === "attachment" &&
            $post->post_status === "inherit" &&
            ($this->allMedia() ||
                (int) $post->post_author === get_current_user_id()) &&
            current_user_can($write ? "edit_post" : "read_post", $id);
    }
    public function scope(string $alias = "p"): string
    {
        global $wpdb;
        return $this->allMedia()
            ? ""
            : $wpdb->prepare(
                " AND $alias.post_author = %d",
                get_current_user_id(),
            );
    }
}
