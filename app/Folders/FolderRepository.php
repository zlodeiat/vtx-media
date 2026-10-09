<?php
namespace VTX\Media\Folders;
use VTX\Media\Database\Schema;
use VTX\Media\Database\Mutation;
use VTX\Media\Permissions\Policy;
defined("ABSPATH") || exit();
final class FolderRepository
{
    private $policy;
    public function __construct(Policy $policy)
    {
        $this->policy = $policy;
    }
    public function get(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . Schema::table("folders") . " WHERE id=%d",
                $id,
            ),
            ARRAY_A,
        );
        return $row ? $this->cast($row) : null;
    }
    private function cast(array $row): array
    {
        foreach (["id", "parent_id", "position", "count", "children"] as $key) {
            if (isset($row[$key])) {
                $row[$key] = (int) $row[$key];
            }
        }
        return $row;
    }
    public function listing(array $args): array
    {
        global $wpdb;
        $f = Schema::table("folders");
        $m = Schema::table("memberships");
        $where =
            $args["search"] !== ""
                ? $wpdb->prepare(
                    "f.name LIKE %s",
                    "%" . $wpdb->esc_like($args["search"]) . "%",
                )
                : $wpdb->prepare("f.parent_id=%d", $args["parent"]);
        $scope = $this->policy->scope();
        $offset = ($args["page"] - 1) * 50;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT f.*, parent.name AS parent_name,
(SELECT COUNT(*) FROM $f c WHERE c.parent_id=f.id) AS children,
(SELECT COUNT(*) FROM $m m JOIN {$wpdb->posts} p ON p.ID=m.attachment_id WHERE m.folder_id=f.id AND p.post_type='attachment' AND p.post_status='inherit' $scope) AS count
FROM $f f LEFT JOIN $f parent ON parent.id=f.parent_id WHERE $where ORDER BY f.position,f.name,f.id LIMIT %d OFFSET %d",
                51,
                $offset,
            ),
            ARRAY_A,
        );
        return [
            "items" => array_map([$this, "cast"], array_slice($rows, 0, 50)),
            "more" => count($rows) > 50,
        ];
    }
    private function parentError(int $id, int $parent)
    {
        $depth = 0;
        $cursor = $parent;
        while ($cursor) {
            if ($cursor === $id) {
                return new \WP_Error(
                    "vtx_cycle",
                    __(
                        "A folder cannot be moved into itself or its descendants.",
                        "vtx-media",
                    ),
                    ["status" => 400],
                );
            }
            $row = $this->get($cursor);
            if (!$row) {
                return new \WP_Error(
                    "vtx_parent",
                    __("The parent folder does not exist.", "vtx-media"),
                    ["status" => 400],
                );
            }
            $cursor = $row["parent_id"];
            if (++$depth >= 32) {
                return new \WP_Error(
                    "vtx_depth",
                    __("Folders support up to 32 levels.", "vtx-media"),
                    ["status" => 400],
                );
            }
        }
        // Bounded depth traversal; IDs only, not attachment records.
        if ($id) {
            global $wpdb;
            $frontier = [$id];
            $height = 0;
            while ($frontier) {
                $next = [];
                foreach (array_chunk($frontier, 200) as $chunk) {
                    $marks = implode(",", array_fill(0, count($chunk), "%d"));
                    $next = array_merge(
                        $next,
                        $wpdb->get_col(
                            $wpdb->prepare(
                                "SELECT id FROM " .
                                    Schema::table("folders") .
                                    " WHERE parent_id IN ($marks)",
                                $chunk,
                            ),
                        ),
                    );
                }
                if ($next && $depth + ++$height >= 32) {
                    return new \WP_Error(
                        "vtx_depth",
                        __(
                            "This move would exceed 32 folder levels.",
                            "vtx-media",
                        ),
                        ["status" => 400],
                    );
                }
                $frontier = $next;
            }
        }
        return null;
    }
    public function save(int $id, array $data)
    {
        if (!$this->policy->folders()) {
            return new \WP_Error(
                "vtx_forbidden",
                __("You cannot manage shared folders.", "vtx-media"),
                ["status" => 403],
            );
        }
        $result = Mutation::run(function () use ($id, $data) {
            global $wpdb;
            $old = $id ? $this->get($id) : null;
            if ($id && !$old) {
                return new \WP_Error(
                    "vtx_not_found",
                    __("Folder not found.", "vtx-media"),
                    ["status" => 404],
                );
            }
            $data = array_merge(
                $old ?: ["parent_id" => 0, "position" => 0],
                $data,
            );
            $name = sanitize_text_field($data["name"] ?? "");
            if ($name === "" || mb_strlen($name) > 191) {
                return new \WP_Error(
                    "vtx_name",
                    __("Enter a folder name.", "vtx-media"),
                    ["status" => 400],
                );
            }
            $error = $this->parentError($id, (int) $data["parent_id"]);
            if ($error) {
                return $error;
            }
            $fields = [
                "parent_id" => (int) $data["parent_id"],
                "position" => (int) $data["position"],
                "name" => $name,
                "updated_at" => current_time("mysql", true),
            ];
            if ($id) {
                Mutation::check(
                    $wpdb->update(Schema::table("folders"), $fields, [
                        "id" => $id,
                    ]),
                );
            } else {
                $fields["created_at"] = $fields["updated_at"];
                Mutation::check(
                    $wpdb->insert(Schema::table("folders"), $fields),
                );
                $id = (int) $wpdb->insert_id;
            }
            return $this->get($id);
        });
        if (!is_wp_error($result)) {
            do_action("vtx_media/folder_changed", $result["id"], "saved");
        }
        return $result;
    }
    public function delete(int $id)
    {
        if (!$this->policy->folders()) {
            return new \WP_Error(
                "vtx_forbidden",
                __("You cannot manage shared folders.", "vtx-media"),
                ["status" => 403],
            );
        }
        $result = Mutation::run(function () use ($id) {
            global $wpdb;
            $folder = $this->get($id);
            if (!$folder) {
                return new \WP_Error(
                    "vtx_not_found",
                    __("Folder not found.", "vtx-media"),
                    ["status" => 404],
                );
            }
            Mutation::check(
                $wpdb->update(
                    Schema::table("folders"),
                    [
                        "parent_id" => $folder["parent_id"],
                        "updated_at" => current_time("mysql", true),
                    ],
                    ["parent_id" => $id],
                ),
            );
            Mutation::check(
                $wpdb->delete(Schema::table("memberships"), [
                    "folder_id" => $id,
                ]),
            );
            Mutation::check(
                $wpdb->delete(Schema::table("folders"), ["id" => $id]),
            );
            return ["deleted" => $id];
        });
        if (!is_wp_error($result)) {
            do_action("vtx_media/folder_changed", $id, "deleted");
        }
        return $result;
    }
}
