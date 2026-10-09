<?php
namespace VTX\Media\Audit\Rules;
use VTX\Media\Audit\Context;
defined("ABSPATH") || exit();
final class LongAltRule extends BaseRule
{
    protected $id = "long-alt";
    protected $category = "accessibility";
    protected $severity = "low";
    protected $types = ["image"];
    public function evaluate(Context $c): ?array
    {
        $length = function_exists("mb_strlen")
            ? mb_strlen($c->alt)
            : strlen($c->alt);
        return $length > $c->settings["alt_length"]
            ? $this->finding(
                [
                    "length" => $length,
                    "threshold" => $c->settings["alt_length"],
                ],
                "",
                0.8,
                "heuristic",
            )
            : null;
    }
    public function present(array $data): array
    {
        return $this->text(
            __("Long ALT — review recommended", "vtx-media"),
            __(
                "ALT exceeds the configured length review threshold. Length alone does not determine quality.",
                "vtx-media",
            ),
            __(
                "Consider whether a concise alternative would convey the same information.",
                "vtx-media",
            ),
            ["edit-alt"],
        );
    }
}
