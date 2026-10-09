<?php
namespace VTX\Media\Audit\Rules;
use VTX\Media\Audit\Context;
defined("ABSPATH") || exit();
final class MissingDescriptionRule extends BaseRule
{
    protected $id = "missing-description";
    protected $category = "metadata";
    protected $severity = "info";
    protected $types = ["*"];
    public function evaluate(Context $c): ?array
    {
        return trim(wp_strip_all_tags($c->post->post_content)) === ""
            ? $this->finding(["description" => ""])
            : null;
    }
    public function present(array $data): array
    {
        return $this->text(
            __("No description", "vtx-media"),
            __(
                "Attachment descriptions are optional in many workflows.",
                "vtx-media",
            ),
            __(
                "Add a description if your site or workflow uses it.",
                "vtx-media",
            ),
            ["edit-description"],
        );
    }
}
