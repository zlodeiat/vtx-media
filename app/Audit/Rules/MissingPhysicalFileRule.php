<?php
namespace VTX\Media\Audit\Rules;
use VTX\Media\Audit\Context;
defined("ABSPATH") || exit();
final class MissingPhysicalFileRule extends BaseRule
{
    protected $id = "missing-physical-file";
    protected $category = "files";
    protected $severity = "critical";
    protected $types = ["*"];
    public function evaluate(Context $c): ?array
    {
        if ($c->file["state"] === "available") {
            return null;
        }
        return $this->finding(
            ["source_state" => $c->file["state"]],
            $c->file["state"] === "unknown" ? "info" : "critical",
        );
    }
    public function present(array $data): array
    {
        return $this->text(
            ($data["source_state"] ?? "") === "unknown"
                ? __("Unable to verify storage source", "vtx-media")
                : (empty($data)
                    ? __("Physical file review", "vtx-media")
                    : __("Original media file missing", "vtx-media")),
            __(
                "The local attachment or original file is missing, or its storage source cannot be safely verified. Unknown storage is not classified as broken.",
                "vtx-media",
            ),
            __(
                "Check the original upload or storage integration. No files are changed.",
                "vtx-media",
            ),
            [],
        );
    }
}
