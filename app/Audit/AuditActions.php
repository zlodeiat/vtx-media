<?php
namespace VTX\Media\Audit;
use VTX\Media\Database\Schema;
use VTX\Media\Permissions\Policy;
defined("ABSPATH") || exit();
final class AuditActions
{
    private $engine;
    private $policy;
    public function __construct(AuditEngine $engine, Policy $policy)
    {
        $this->engine = $engine;
        $this->policy = $policy;
    }
    public function execute(string $action, array $ids): array
    {
        if (
            !in_array(
                $action,
                [
                    "rescan",
                    "decorative",
                    "remove-decorative",
                    "ignore",
                    "restore",
                ],
                true,
            ) ||
            count($ids) > 50 ||
            !$ids
        ) {
            throw new \InvalidArgumentException("Invalid audit action");
        }
        global $wpdb;
        $results = [];
        $attachments = array_combine($ids, $ids);
        if (in_array($action, ["ignore", "restore"], true)) {
            $placeholders = implode(",", array_fill(0, count($ids), "%d"));
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id,attachment_id FROM " .
                        Schema::table("findings") .
                        " WHERE id IN ($placeholders)",
                    ...$ids,
                ),
                ARRAY_A,
            );
            $attachments = array_column($rows, "attachment_id", "id");
        }
        if ($attachments) {
            _prime_post_caches(
                array_values(array_unique($attachments)),
                false,
                true,
            );
        }
        if ($action === "rescan") {
            $this->engine->prepareBatch(array_values($attachments));
        }
        foreach ($ids as $id) {
            $attachment = (int) ($attachments[$id] ?? 0);
            if (!$this->policy->attachment($attachment, true)) {
                $results[] = [
                    "id" => $id,
                    "success" => false,
                    "message" => __(
                        "Attachment is missing or you cannot edit it.",
                        "vtx-media",
                    ),
                ];
                continue;
            }
            if (in_array($action, ["decorative", "remove-decorative"], true)) {
                if (
                    strpos(
                        (string) get_post_mime_type($attachment),
                        "image/",
                    ) !== 0
                ) {
                    $results[] = [
                        "id" => $id,
                        "success" => false,
                        "message" => __(
                            "Decorative intent applies to images only.",
                            "vtx-media",
                        ),
                    ];
                    continue;
                }
                if ($action === "decorative") {
                    update_post_meta(
                        $attachment,
                        "_wp_attachment_image_alt",
                        "",
                    );
                    if (
                        (string) get_post_meta(
                            $attachment,
                            "_wp_attachment_image_alt",
                            true,
                        ) !== ""
                    ) {
                        $results[] = [
                            "id" => $id,
                            "success" => false,
                            "message" => __(
                                "ALT could not be cleared. Decorative intent was not saved.",
                                "vtx-media",
                            ),
                        ];
                        continue;
                    }
                    update_post_meta($attachment, "_vtx_media_decorative", 1);
                } else {
                    delete_post_meta($attachment, "_vtx_media_decorative");
                }
            }
            if (
                in_array($action, ["decorative", "remove-decorative"], true) &&
                (bool) get_post_meta(
                    $attachment,
                    "_vtx_media_decorative",
                    true,
                ) !==
                    ($action === "decorative")
            ) {
                $results[] = [
                    "id" => $id,
                    "success" => false,
                    "message" => __(
                        "Decorative intent could not be saved.",
                        "vtx-media",
                    ),
                ];
                continue;
            }
            $result = in_array($action, ["ignore", "restore"], true)
                ? $this->engine->status(
                    $id,
                    $action === "ignore" ? "ignored" : "open",
                )
                : $this->engine->audit($attachment);
            $ok =
                !is_wp_error($result) &&
                (!isset($result["state"]) || $result["state"] === "complete");
            $results[] = [
                "id" => $id,
                "success" => $ok,
                "message" => is_wp_error($result)
                    ? $result->get_error_message()
                    : ($ok
                        ? __("Updated.", "vtx-media")
                        : __(
                            "Analysis is incomplete; review diagnostics.",
                            "vtx-media",
                        )),
            ];
        }
        $success = count(
            array_filter($results, static function ($r) {
                return $r["success"];
            }),
        );
        return [
            "success" => $success,
            "failed" => count($results) - $success,
            "results" => $results,
        ];
    }
}
