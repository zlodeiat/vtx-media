<?php
namespace VTX\Media\Media;
use VTX\Media\Database\Schema;
use VTX\Media\Database\Mutation;
use VTX\Media\Permissions\Policy;
use VTX\Media\Folders\FolderRepository;
defined("ABSPATH") || exit();
final class MediaRepository
{
    private $policy;
    private $folders;
    private $facts;
    public function __construct(
        Policy $policy,
        FolderRepository $folders,
        FileFacts $facts
    ) {
        $this->policy = $policy;
        $this->folders = $folders;
        $this->facts = $facts;
    }
    public function listing(array $args): array
    {
        global $wpdb;
        $m = Schema::table("memberships");
        $f = Schema::table("favorites");
        $s = Schema::table("facts");
        $where =
            "p.post_type='attachment' AND p.post_status='inherit'" .
            $this->policy->scope();
        $join =
            " LEFT JOIN $m m ON m.attachment_id=p.ID LEFT JOIN $f f ON f.attachment_id=p.ID AND f.user_id=" .
            get_current_user_id() .
            " LEFT JOIN $s s ON s.attachment_id=p.ID";
        if ($args["search"] !== "") {
            $like = "%" . $wpdb->esc_like($args["search"]) . "%";
            $where .= $wpdb->prepare(
                " AND (p.post_title LIKE %s OR p.post_content LIKE %s OR p.post_excerpt LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id=p.ID AND pm.meta_key='_wp_attached_file' AND pm.meta_value LIKE %s))",
                $like,
                $like,
                $like,
                $like,
            );
        }
        $type = $args["type"];
        if (in_array($type, ["image", "video", "audio"], true)) {
            $where .= $wpdb->prepare(
                " AND p.post_mime_type LIKE %s",
                $type . "/%",
            );
        }
        if ($type === "document") {
            $where .=
                " AND p.post_mime_type NOT LIKE 'image/%' AND p.post_mime_type NOT LIKE 'video/%' AND p.post_mime_type NOT LIKE 'audio/%'";
        }
        if ($args["view"] === "favorites") {
            $where .= " AND f.attachment_id IS NOT NULL";
        }
        if ($args["view"] === "unorganized") {
            $where .= " AND m.attachment_id IS NULL";
        }
        if ($args["view"] === "recent") {
            $where .= $wpdb->prepare(
                " AND p.post_date_gmt >= %s",
                gmdate("Y-m-d H:i:s", time() - 30 * DAY_IN_SECONDS),
            );
        }
        if ($args["folder"]) {
            $where .= $wpdb->prepare(" AND m.folder_id=%d", $args["folder"]);
        }
        if ($args["author"]) {
            $where .= $wpdb->prepare(" AND p.post_author=%d", $args["author"]);
        }
        if ($args["date"]) {
            $start = $args["date"] . "-01";
            $end = gmdate("Y-m-d", strtotime($start . " +1 month"));
            $where .= $wpdb->prepare(
                " AND p.post_date >= %s AND p.post_date < %s",
                $start,
                $end,
            );
        }
        $where = apply_filters("vtx_media/media_where", $where, $args);
        $orders = [
            "newest" => "p.post_date DESC",
            "oldest" => "p.post_date ASC",
            "name_asc" => "p.post_title ASC",
            "name_desc" => "p.post_title DESC",
            "largest" => "s.bytes IS NULL ASC,s.bytes DESC",
            "smallest" => "s.bytes IS NULL ASC,s.bytes ASC",
        ];
        $order = $orders[$args["sort"]];
        $limit = $args["per_page"];
        $offset = ($args["page"] - 1) * $limit;
        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p $join WHERE $where",
        );
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p $join WHERE $where ORDER BY $order,p.ID DESC LIMIT %d OFFSET %d",
                $limit,
                $offset,
            ),
        );
        if ($ids) {
            _prime_post_caches($ids, false, true);
        }
        // Batch organization facts for this page; no query per card.
        $related = $this->relations($ids);
        $items = [];
        foreach ($ids as $id) {
            if ($this->policy->attachment((int) $id)) {
                $items[] = $this->present(
                    (int) $id,
                    $related[$id] ?? [],
                    false,
                );
            }
        }
        return [
            "items" => $items,
            "collection_label" => apply_filters(
                "vtx_media/collection_label",
                "",
                $args["collection"] ?? "",
            ),
            "total" => $total,
            "pages" => (int) ceil($total / $limit),
            "page" => $args["page"],
        ];
    }
    private function relations(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        global $wpdb;
        $m = Schema::table("memberships");
        $f = Schema::table("folders");
        $v = Schema::table("favorites");
        $s = Schema::table("facts");
        $marks = implode(",", array_fill(0, count($ids), "%d"));
        $user = get_current_user_id();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID,m.folder_id,f.name AS folder_name,v.attachment_id AS favorite,s.bytes FROM {$wpdb->posts} p LEFT JOIN $m m ON m.attachment_id=p.ID LEFT JOIN $f f ON f.id=m.folder_id LEFT JOIN $v v ON v.attachment_id=p.ID AND v.user_id=$user LEFT JOIN $s s ON s.attachment_id=p.ID WHERE p.ID IN ($marks)",
                $ids,
            ),
            ARRAY_A,
        );
        return array_column($rows, null, "ID");
    }
    public function detail(int $id)
    {
        if (!$this->policy->attachment($id)) {
            return new \WP_Error(
                "vtx_not_found",
                __("Media not found or inaccessible.", "vtx-media"),
                ["status" => 404],
            );
        }
        return apply_filters(
            "vtx_media/inspector",
            $this->present($id, $this->relations([$id])[$id] ?? [], true),
            $id,
        );
    }
    private function present(int $id, array $relation, bool $detail): array
    {
        $post = get_post($id);
        $meta = wp_get_attachment_metadata($id);
        $file = get_post_meta($id, "_wp_attached_file", true);
        $is_image = strpos($post->post_mime_type, "image/") === 0;
        $bytes = isset($relation["bytes"])
            ? (int) $relation["bytes"]
            : (isset($meta["filesize"])
                ? (int) $meta["filesize"]
                : null);
        $row = [
            "id" => $id,
            "title" => $post->post_title,
            "filename" => wp_basename(
                $file ?:
                (string) wp_parse_url(wp_get_attachment_url($id), PHP_URL_PATH),
            ),
            "mime" => $post->post_mime_type,
            "extension" => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
            "image" => $is_image,
            "thumbnail" => $is_image
                ? (wp_get_attachment_image_url($id, "medium") ?:
                null)
                : null,
            "width" => (int) ($meta["width"] ?? 0),
            "height" => (int) ($meta["height"] ?? 0),
            "bytes" => $bytes,
            "date" => get_post_time("c", false, $post),
            "folder_id" => (int) ($relation["folder_id"] ?? 0),
            "folder_name" => $relation["folder_name"] ?? null,
            "favorite" => !empty($relation["favorite"]),
            "editable" =>
                $this->policy->can("metadata") &&
                $this->policy->attachment($id, true),
        ];
        if ($detail) {
            $author = get_userdata((int) $post->post_author);
            $row = array_merge($row, $this->facts->local($id), [
                "url" => wp_get_attachment_url($id),
                "preview" => $is_image
                    ? (wp_get_attachment_image_url($id, "large") ?:
                    null)
                    : null,
                "author" => $author
                    ? $author->display_name
                    : __("Unknown author", "vtx-media"),
                "alt" => get_post_meta($id, "_wp_attachment_image_alt", true),
                "caption" => $post->post_excerpt,
                "description" => $post->post_content,
            ]);
        }
        return $row;
    }
    public function move(array $ids, int $folder)
    {
        if (!$this->policy->can("organize")) {
            return new \WP_Error(
                "vtx_forbidden",
                "This action is not allowed.",
                ["status" => 403],
            );
        }
        $result = Mutation::run(function () use ($ids, $folder) {
            global $wpdb;
            if ($folder && !$this->folders->get($folder)) {
                return new \WP_Error(
                    "vtx_folder",
                    __("Destination folder not found.", "vtx-media"),
                    ["status" => 404],
                );
            }
            foreach ($ids as $id) {
                if (!$this->policy->attachment($id, true)) {
                    return new \WP_Error(
                        "vtx_forbidden",
                        __(
                            "You cannot move one or more selected items.",
                            "vtx-media",
                        ),
                        ["status" => 403],
                    );
                }
            }
            foreach ($ids as $id) {
                if ($folder) {
                    Mutation::check(
                        $wpdb->replace(Schema::table("memberships"), [
                            "attachment_id" => $id,
                            "folder_id" => $folder,
                        ]),
                    );
                } else {
                    Mutation::check(
                        $wpdb->delete(Schema::table("memberships"), [
                            "attachment_id" => $id,
                        ]),
                    );
                }
            }
            return ["moved" => count($ids), "folder_id" => $folder];
        });
        if (!is_wp_error($result)) {
            do_action("vtx_media/media_moved", $ids, $folder);
        }
        return $result;
    }
    public function favorite(array $ids, bool $value)
    {
        if (!$this->policy->can("organize")) {
            return new \WP_Error(
                "vtx_forbidden",
                "This action is not allowed.",
                ["status" => 403],
            );
        }
        return Mutation::run(function () use ($ids, $value) {
            global $wpdb;
            foreach ($ids as $id) {
                if (!$this->policy->attachment($id, true)) {
                    return new \WP_Error(
                        "vtx_forbidden",
                        __(
                            "You cannot favorite one or more selected items.",
                            "vtx-media",
                        ),
                        ["status" => 403],
                    );
                }
            }
            foreach ($ids as $id) {
                $key = [
                    "user_id" => get_current_user_id(),
                    "attachment_id" => $id,
                ];
                Mutation::check(
                    $value
                        ? $wpdb->replace(
                            Schema::table("favorites"),
                            $key + [
                                "created_at" => current_time("mysql", true),
                            ],
                        )
                        : $wpdb->delete(Schema::table("favorites"), $key),
                );
            }
            return ["updated" => count($ids), "favorite" => $value];
        });
    }
    public function update(int $id, array $data)
    {
        if (!$this->policy->can("metadata")) {
            return new \WP_Error(
                "vtx_forbidden",
                "This action is not allowed.",
                ["status" => 403],
            );
        }
        if (!$this->policy->attachment($id, true)) {
            return new \WP_Error(
                "vtx_forbidden",
                __("You cannot edit this attachment.", "vtx-media"),
                ["status" => 403],
            );
        }
        $post = ["ID" => $id];
        foreach (
            [
                "title" => "post_title",
                "caption" => "post_excerpt",
                "description" => "post_content",
            ]
            as $key => $field
        ) {
            if (array_key_exists($key, $data)) {
                $post[$field] =
                    $key === "title"
                        ? sanitize_text_field($data[$key])
                        : wp_kses_post($data[$key]);
            }
        }
        if (count($post) > 1) {
            $result = wp_update_post(wp_slash($post), true);
            if (is_wp_error($result)) {
                return $result;
            }
        }
        if (array_key_exists("alt", $data)) {
            $alt = sanitize_text_field($data["alt"]);
            if (
                get_post_meta($id, "_wp_attachment_image_alt", true) !== $alt &&
                !update_post_meta(
                    $id,
                    "_wp_attachment_image_alt",
                    wp_slash($alt),
                )
            ) {
                return new \WP_Error(
                    "vtx_metadata",
                    __("ALT could not be saved. Please retry.", "vtx-media"),
                    ["status" => 500],
                );
            }
        }
        do_action("vtx_media/metadata_updated", $id, array_keys($data));
        return $this->detail($id);
    }
    public function authors(string $search): array
    {
        global $wpdb;
        $scope = $this->policy->scope();
        $like = "%" . $wpdb->esc_like($search) . "%";
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT u.ID AS id,u.display_name AS name FROM {$wpdb->users} u WHERE u.display_name LIKE %s AND EXISTS (SELECT 1 FROM {$wpdb->posts} p WHERE p.post_author=u.ID AND p.post_type='attachment' AND p.post_status='inherit' $scope) ORDER BY u.display_name LIMIT 50",
                $like,
            ),
            ARRAY_A,
        );
    }
    public static function deleted(int $id): void
    {
        global $wpdb;
        foreach (["memberships", "favorites", "facts"] as $table) {
            $wpdb->delete(Schema::table($table), ["attachment_id" => $id]);
        }
    }
    public static function userDeleted(int $id): void
    {
        global $wpdb;
        $wpdb->delete(Schema::table("favorites"), ["user_id" => $id]);
    }
}
