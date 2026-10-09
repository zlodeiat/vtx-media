<?php
namespace VTX\Media\Admin;
use VTX\Media\Permissions\Policy;
defined("ABSPATH") || exit();
final class Page
{
    private $file;
    private $policy;
    private $hook;
    private $modules;
    private $entitlements;
    public function __construct(
        string $file,
        Policy $policy,
        \VTX\Media\Extensions\ModuleRegistry $modules,
        \VTX\Media\Entitlements\EntitlementService $entitlements
    ) {
        $this->modules = $modules;
        $this->entitlements = $entitlements;
        $this->file = $file;
        $this->policy = $policy;
    }
    public function hooks(): void
    {
        add_action("admin_menu", [$this, "menu"]);
        add_action("admin_enqueue_scripts", [$this, "assets"]);
    }
    public function menu(): void
    {
        $this->hook = add_media_page(
            __("VTX Media", "vtx-media"),
            __("VTX Media", "vtx-media"),
            "upload_files",
            "vtx-media",
            [$this, "render"],
        );
    }
    public function assets(string $hook): void
    {
        if ($hook !== $this->hook) {
            return;
        }
        $url = plugin_dir_url($this->file);
        wp_enqueue_style(
            "vtx-media",
            $url . "assets/dist/admin.css",
            [],
            \VTX\Media\VERSION,
        );
        wp_enqueue_script(
            "vtx-media",
            $url . "assets/dist/admin.js",
            ["wp-element", "wp-i18n", "wp-hooks"],
            \VTX\Media\VERSION,
            true,
        );
        wp_set_script_translations(
            "vtx-media",
            "vtx-media",
            dirname($this->file) . "/languages",
        );
        wp_add_inline_script(
            "vtx-media",
            "window.vtxMediaConfig=" .
                wp_json_encode(
                    apply_filters("vtx_media/admin_config", [
                        "api" => esc_url_raw(rest_url("vtx-media/v1/")),
                        "nonce" => wp_create_nonce("wp_rest"),
                        "modules" => $this->modules->manifest(),
                        "version" => \VTX\Media\VERSION,
                        "locale" => str_replace("_", "-", get_user_locale()),
                        "timezone" => wp_timezone_string(),
                        "developerMode" => $this->entitlements->development(),
                        "manageSettings" => $this->policy->can("settings"),
                        "canOrganize" => $this->policy->can("organize"),
                        "manageFolders" => $this->policy->folders(),
                        "allMedia" => $this->policy->allMedia(),
                        "logo" => $url . "assets/dist/logo-youneeddev.png",
                        "upload" => admin_url("media-new.php"),
                        "native" => admin_url("upload.php"),
                    ]),
                ) .
                ";",
            "before",
        );
        do_action("vtx_media/admin_enqueue", "vtx-media", $hook);
    }
    public function render(): void
    {
        if (!$this->policy->access()) {
            wp_die(
                esc_html__(
                    "You cannot access this media library.",
                    "vtx-media",
                ),
            );
        }
        echo '<div id="vtx-media-app" class="vtx-media"><p role="status">' .
            esc_html__("Loading your media library…", "vtx-media") .
            "</p></div>";
        echo "<noscript>" .
            esc_html__(
                "VTX Media requires JavaScript. The standard WordPress Media Library remains available.",
                "vtx-media",
            ) .
            "</noscript>";
    }
}
