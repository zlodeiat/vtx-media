<?php
namespace VTX\Media\Audit\Rules;
use VTX\Media\Audit\Context;
defined("ABSPATH") || exit();
final class MissingTitleRule extends BaseRule
{
    protected $id = "missing-title";
    protected $category = "metadata";
    protected $severity = "low";
    protected $types = ["*"];
    public function evaluate(Context $c): ?array
    {
        if (trim($c->post->post_title) === "") {
            return $this->finding(["title" => ""]);
        }
        return Context::poorName($c->filename) &&
            Context::normalize($c->post->post_title) ===
                Context::normalize($c->filename)
            ? $this->finding(
                ["title" => $c->post->post_title],
                "info",
                0.9,
                "heuristic",
            )
            : null;
    }
    public function present(array $data): array
    {
        return $this->text(
            __("Review media title", "vtx-media"),
            __(
                "The title is empty or appears to retain an automatically generated filename.",
                "vtx-media",
            ),
            __("A useful title can make media easier to find.", "vtx-media"),
            ["edit-title"],
        );
    }
}
