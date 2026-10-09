<?php
namespace VTX\Media\Audit\Rules;
use VTX\Media\Audit\Context;
defined("ABSPATH") || exit();
final class ExcessiveDimensionsRule extends BaseRule
{
    protected $id = "excessive-dimensions";
    protected $category = "performance";
    protected $severity = "medium";
    protected $types = ["image"];
    public function evaluate(Context $c): ?array
    {
        $m = $c->metadata;
        if (!is_array($m)) {
            return null;
        }
        $w = (int) ($m["width"] ?? 0);
        $h = (int) ($m["height"] ?? 0);
        return $w > $c->settings["max_width"] || $h > $c->settings["max_height"]
            ? $this->finding([
                "width" => $w,
                "height" => $h,
                "max_width" => $c->settings["max_width"],
                "max_height" => $c->settings["max_height"],
            ])
            : null;
    }
    public function present(array $data): array
    {
        return $this->text(
            __("Large pixel dimensions", "vtx-media"),
            __(
                "The image exceeds a configured dimension threshold. This may be appropriate for its intended use.",
                "vtx-media",
            ),
            __(
                "Review whether these dimensions are needed. Originals remain unchanged.",
                "vtx-media",
            ),
            [],
        );
    }
}
