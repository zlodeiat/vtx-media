<?php
namespace VTX\Media\Audit\Rules;
use VTX\Media\Audit\Context;
defined("ABSPATH") || exit();
final class MissingCaptionRule extends BaseRule
{
    protected $id = "missing-caption";
    protected $category = "metadata";
    protected $severity = "info";
    protected $types = ["*"];
    public function evaluate(Context $c): ?array
    {
        return trim(wp_strip_all_tags($c->post->post_excerpt)) === ""
            ? $this->finding(["caption" => ""])
            : null;
    }
    public function present(array $data): array
    {
        return $this->text(
            __("No caption", "vtx-media"),
            __(
                "Captions are optional and are not needed in every context.",
                "vtx-media",
            ),
            __("Add a caption only when it would help readers.", "vtx-media"),
            ["edit-caption"],
        );
    }
}
