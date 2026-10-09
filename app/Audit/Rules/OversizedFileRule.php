<?php
namespace VTX\Media\Audit\Rules;
use VTX\Media\Audit\Context;
defined("ABSPATH") || exit();
final class OversizedFileRule extends BaseRule
{
    protected $id = "oversized-file";
    protected $category = "performance";
    protected $severity = "medium";
    protected $types = ["image"];
    public function evaluate(Context $c): ?array
    {
        $bytes = $c->file["bytes"];
        if ($bytes === null || $bytes <= $c->settings["large_bytes"]) {
            return null;
        }
        $very = $bytes > $c->settings["very_large_bytes"];
        return $this->finding(
            [
                "bytes" => $bytes,
                "threshold" =>
                    $c->settings[$very ? "very_large_bytes" : "large_bytes"],
                "level" => $very ? "very-large" : "large",
            ],
            $very ? "high" : "medium",
        );
    }
    public function present(array $data): array
    {
        return $this->text(
            ($data["level"] ?? "") === "very-large"
                ? __("Very large image file", "vtx-media")
                : __("Large image file", "vtx-media"),
            __(
                "This image exceeds the configured file-size review threshold. Large files can be intentional.",
                "vtx-media",
            ),
            __(
                "Review delivery size for its intended use. Optimization is not performed.",
                "vtx-media",
            ),
            [],
        );
    }
}
