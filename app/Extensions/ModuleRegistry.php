<?php
namespace VTX\Media\Extensions;
use VTX\Media\Entitlements\FeatureEntitlementInterface;
defined("ABSPATH") || exit();
final class ModuleRegistry
{
    private $items = [];
    private $entitlements;
    public function __construct(FeatureEntitlementInterface $entitlements)
    {
        $this->entitlements = $entitlements;
    }
    public function register(
        string $id,
        string $feature,
        array $navigation,
        ?callable $boot = null
    ): void {
        if (
            !preg_match('/^[a-z][a-z0-9-]{1,63}$/D', $id) ||
            isset($this->items[$id])
        ) {
            throw new \InvalidArgumentException("Invalid or duplicate module");
        }
        if (
            array_diff(array_keys($navigation), [
                "label",
                "icon",
                "position",
            ]) ||
            !isset($navigation["label"]) ||
            !is_string($navigation["label"]) ||
            strlen($navigation["label"]) > 320 ||
            !preg_match('/^[a-z0-9-]{1,40}$/D', $navigation["icon"] ?? "")
        ) {
            throw new \InvalidArgumentException("Invalid module manifest");
        }
        $this->items[$id] = [
            "id" => $id,
            "feature" => $feature,
            "label" => sanitize_text_field($navigation["label"]),
            "icon" => $navigation["icon"],
            "position" => (int) ($navigation["position"] ?? 50),
            "boot" => $boot,
        ];
    }
    public function unregister(string $id): void
    {
        unset($this->items[$id]);
    }
    public function get(string $id): ?array
    {
        return $this->items[$id] ?? null;
    }
    public function all(): array
    {
        return array_values($this->items);
    }
    public function boot(array $services): void
    {
        foreach ($this->items as $item) {
            if (
                $item["boot"] &&
                $this->entitlements->hasFeature($item["feature"])
            ) {
                $item["boot"]($services);
            }
        }
    }
    public function manifest(): array
    {
        $items = [];
        foreach ($this->items as $item) {
            if (
                $item["id"] === "advanced" &&
                (!(new \VTX\Media\Permissions\Policy())->can("advanced") ||
                    !(new \VTX\Media\Permissions\Policy())->allMedia())
            ) {
                continue;
            }
            if ($this->entitlements->hasFeature($item["feature"])) {
                unset($item["boot"], $item["feature"]);
                $items[] = $item;
            }
        }
        usort($items, static function ($a, $b) {
            return $a["position"] <=> $b["position"];
        });
        return apply_filters("vtx_media/module_manifest", $items);
    }
}
