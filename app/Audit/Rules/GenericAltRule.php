<?php
namespace VTX\Media\Audit\Rules;
use VTX\Media\Audit\Context;
defined("ABSPATH") || exit();
final class GenericAltRule extends BaseRule
{
    protected $id = "generic-alt";
    protected $category = "accessibility";
    protected $severity = "medium";
    protected $types = ["image"];
    public function evaluate(Context $c): ?array
    {
        return preg_match(
            '/^(?:image|photo|picture|img|banner|thumbnail|untitled)(?:[ _-]*[0-9]+)?$/i',
            $c->alt,
        )
            ? $this->finding(["alt" => $c->alt], "", 0.9, "heuristic")
            : null;
    }
    public function present(array $data): array
    {
        return $this->text(
            __("Generic ALT — review recommended", "vtx-media"),
            __(
                "ALT contains only a basic generic label, optionally followed by a number.",
                "vtx-media",
            ),
            __(
                "Review the image purpose and edit ALT where useful.",
                "vtx-media",
            ),
            ["edit-alt"],
        );
    }
}
