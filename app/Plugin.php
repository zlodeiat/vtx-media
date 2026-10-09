<?php
namespace VTX\Media;
use VTX\Media\Database\Schema;
use VTX\Media\Permissions\Policy;
use VTX\Media\Folders\FolderRepository;
use VTX\Media\Media\FileFacts;
use VTX\Media\Media\MediaRepository;
defined("ABSPATH") || exit();
final class Plugin
{
    private $file;
    public function __construct(string $file)
    {
        $this->file = $file;
    }
    public function boot(): void
    {
        if (get_option("vtx_media_schema_version") !== Schema::VERSION) {
            try {
                Schema::install();
            } catch (\Throwable $error) {
                add_action("admin_notices", static function () {
                    if (current_user_can("activate_plugins")) {
                        echo '<div class="notice notice-error"><p>' .
                            esc_html__(
                                "VTX Media could not initialize its database. Check database permissions and reactivate the plugin.",
                                "vtx-media",
                            ) .
                            "</p></div>";
                    }
                });
                return;
            }
        }
        $policy = new Policy();
        $folders = new FolderRepository($policy);
        $facts = new FileFacts();
        $media = new MediaRepository($policy, $folders, $facts);
        $api = new Api\Controller($policy, $folders, $media, $facts);
        add_action("rest_api_init", [$api, "register"]);
        $features = new Entitlements\FeatureRegistry();
        $features->register(Entitlements\FeatureRegistry::LIBRARY, true);
        $features->register(Entitlements\FeatureRegistry::AUDIT, true);
        $entitlements = new Entitlements\EntitlementService($features);
        $modules = new Extensions\ModuleRegistry($entitlements);
        $modules->register("library", Entitlements\FeatureRegistry::LIBRARY, [
            "label" => __("Library", "vtx-media"),
            "icon" => "portfolio",
            "position" => 0,
        ]);
        $modules->register("audit", Entitlements\FeatureRegistry::AUDIT, [
            "label" => __("Health", "vtx-media"),
            "icon" => "shield",
            "position" => 10,
        ]);
        if ($policy->access()) {
            $modules->register(
                "advanced",
                Entitlements\FeatureRegistry::LIBRARY,
                [
                    "label" => __("Advanced", "vtx-media"),
                    "icon" => "admin-tools",
                    "position" => 90,
                ],
            );
        }
        $rules = new Audit\AuditRuleRegistry($entitlements);
        foreach (
            [
                "MissingAlt",
                "FilenameAsAlt",
                "GenericAlt",
                "MissingTitle",
                "MissingCaption",
                "MissingDescription",
                "PoorFilename",
                "MissingPhysicalFile",
                "OversizedFile",
                "ExcessiveDimensions",
                "InvalidImageMetadata",
                "LongAlt",
            ]
            as $name
        ) {
            $class = __NAMESPACE__ . "\\Audit\\Rules\\" . $name . "Rule";
            $rules->register(new $class());
        }
        $settings = new Audit\Settings();
        $logger = new Jobs\Logger();
        $source = apply_filters(
            "vtx_media/file_source",
            new Media\LocalSource(),
        );
        if (!($source instanceof Media\FileSourceInterface)) {
            $source = new Media\LocalSource();
        }
        $engine = new Audit\AuditEngine($rules, $settings, $logger, $source);
        $jobRegistry = new Jobs\JobRegistry($entitlements);
        $jobRegistry->register(new Audit\AuditJob($engine));
        $runner = new Jobs\JobRunner($jobRegistry, $policy, $logger);
        $routes = new Api\RouteRegistrar($policy, $entitlements);
        $services = compact(
            "policy",
            "folders",
            "media",
            "facts",
            "features",
            "entitlements",
            "modules",
            "rules",
            "settings",
            "logger",
            "engine",
            "jobRegistry",
            "runner",
            "routes",
        );
        do_action("vtx_media/register_extensions", $services);
        $modules->boot($services);
        $advancedApi = new Api\AdvancedController($services);
        add_action("rest_api_init", [$advancedApi, "register"]);
        $auditApi = new Api\AuditController(
            $routes,
            $policy,
            $engine,
            $rules,
            new Audit\AuditQuery($engine, $policy),
            $runner,
            $settings,
        );
        add_action("rest_api_init", [$auditApi, "register"]);
        add_action("rest_api_init", static function () use (
            $routes,
            $services
        ) {
            do_action("vtx_media/register_routes", $routes, $services);
        });
        (new Admin\Page(
            $this->file,
            $policy,
            $modules,
            $entitlements,
        ))->hooks();
        add_filter("vtx_media/inspector", static function ($item) use (
            $engine,
            $policy
        ) {
            $item["audit"] = $policy->can("audit")
                ? $engine->detail((int) $item["id"])
                : null;
            return $item;
        });
        $incremental = new Audit\Incremental($engine);
        $incremental->hooks();
        add_filter("cron_schedules", static function ($schedules) {
            $schedules["vtx_media_minute"] = [
                "interval" => 60,
                "display" => __("VTX Media processing", "vtx-media"),
            ];
            return $schedules;
        });
        add_action(Jobs\JobRunner::HOOK, [$runner, "background"]);
        add_action(Jobs\JobRunner::HOOK, [$incremental, "background"]);
        $runner->schedule();
        add_action("delete_attachment", [MediaRepository::class, "deleted"]);
        add_action("deleted_user", [MediaRepository::class, "userDeleted"]);
        foreach (
            ["added_post_meta", "updated_post_meta", "deleted_post_meta"]
            as $hook
        ) {
            add_action($hook, [$facts, "invalidate"], 10, 3);
        }
        do_action("vtx_media/ready", $services);
    }
    public static function deactivate(): void
    {
        Jobs\JobRunner::deactivate();
        do_action("vtx_media/deactivated");
    }
}
