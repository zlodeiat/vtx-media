<?php
namespace VTX\Media\Audit\Rules;
use VTX\Media\Audit\Context;
defined("ABSPATH") || exit();
final class InvalidImageMetadataRule extends BaseRule
{
    protected $id = "invalid-image-metadata";
    protected $category = "files";
    protected $severity = "high";
    protected $types = ["image"];
    public function evaluate(Context $c): ?array
    {
        if ($c->mime === "image/svg+xml") {
            return null;
        }
        $m = $c->metadata;
        if (
            !is_array($m) ||
            empty($m["width"]) ||
            empty($m["height"]) ||
            !is_numeric($m["width"]) ||
            !is_numeric($m["height"]) ||
            $m["width"] < 1 ||
            $m["height"] < 1
        ) {
            return $this->finding(["reason" => "missing-dimensions"]);
        }
        $actual = $c->file["dimensions"];
        return $actual &&
            ((int) $m["width"] !== $actual["width"] ||
                (int) $m["height"] !== $actual["height"])
            ? $this->finding([
                "reason" => "dimension-mismatch",
                "metadata" => [
                    "width" => (int) $m["width"],
                    "height" => (int) $m["height"],
                ],
                "file" => $actual,
            ])
            : null;
    }
    public function present(array $data): array
    {
        return $this->text(
            __("Image metadata needs review", "vtx-media"),
            __(
                "Dimensions are missing, malformed or inconsistent with the readable image header.",
                "vtx-media",
            ),
            __(
                "Check attachment metadata and the source file. Metadata is not regenerated automatically.",
                "vtx-media",
            ),
            [],
        );
    }
}
