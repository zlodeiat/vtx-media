<?php
namespace VTX\Media\Audit\Rules;
use VTX\Media\Audit\Context;
defined("ABSPATH") || exit();
final class PoorFilenameRule extends BaseRule
{
    protected $id = "poor-filename";
    protected $category = "metadata";
    protected $severity = "low";
    protected $types = ["*"];
    public function evaluate(Context $c): ?array
    {
        return Context::poorName($c->filename)
            ? $this->finding(
                ["filename" => $c->filename],
                "",
                0.85,
                "heuristic",
            )
            : null;
    }
    public function present(array $data): array
    {
        return $this->text(
            __("Filename could be more descriptive", "vtx-media"),
            __(
                "The filename resembles a camera default, download label, screenshot label, UUID or hash.",
                "vtx-media",
            ),
            __(
                "Use descriptive filenames for future uploads. Existing files are not renamed.",
                "vtx-media",
            ),
            [],
        );
    }
}
