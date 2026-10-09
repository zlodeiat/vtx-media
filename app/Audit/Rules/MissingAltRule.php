<?php
namespace VTX\Media\Audit\Rules;
use VTX\Media\Audit\Context;
defined("ABSPATH") || exit();
final class MissingAltRule extends BaseRule
{
    protected $id = "missing-alt";
    protected $category = "accessibility";
    protected $severity = "medium";
    protected $types = ["image"];
    public function evaluate(Context $c): ?array
    {
        return $c->alt === "" && !$c->decorative
            ? $this->finding(["alt" => ""])
            : null;
    }
    public function present(array $data): array
    {
        return $this->text(
            __("ALT not reviewed", "vtx-media"),
            __(
                "Empty ALT can be correct for decorative images. Review the intended purpose.",
                "vtx-media",
            ),
            __(
                "Edit ALT or deliberately mark this image decorative.",
                "vtx-media",
            ),
            ["edit-alt", "decorative"],
        );
    }
}
