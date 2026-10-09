<?php
namespace VTX\Media\Audit\Rules;
use VTX\Media\Audit\Context;
defined("ABSPATH") || exit();
final class FilenameAsAltRule extends BaseRule
{
    protected $id = "filename-as-alt";
    protected $category = "accessibility";
    protected $severity = "medium";
    protected $types = ["image"];
    public function evaluate(Context $c): ?array
    {
        return $c->alt !== "" &&
            Context::normalize($c->alt) === Context::normalize($c->filename)
            ? $this->finding(
                ["alt" => $c->alt, "filename" => $c->filename],
                "",
                0.9,
                "heuristic",
            )
            : null;
    }
    public function present(array $data): array
    {
        return $this->text(
            __("ALT resembles the filename", "vtx-media"),
            __(
                "The normalized ALT and filename match. This mechanical comparison does not understand the image.",
                "vtx-media",
            ),
            __(
                "Review whether the ALT conveys the intended information.",
                "vtx-media",
            ),
            ["edit-alt"],
        );
    }
}
