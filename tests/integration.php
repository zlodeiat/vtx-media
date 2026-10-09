<?php
/** Run with wp eval-file tests/integration.php. All fixtures are removed in finally. */
if (!defined("WP_CLI") || !WP_CLI) {
    exit();
}
use VTX\Media\Database\Schema;
use VTX\Media\Folders\FolderRepository;
use VTX\Media\Permissions\Policy;
$checks = 0;
$attachments = [];
$users = [];
$folders = [];
$admin = (int) get_users([
    "role" => "administrator",
    "number" => 1,
    "fields" => "ID",
])[0];
wp_set_current_user($admin);
$prefix = "VTX-INTEGRATION-" . wp_generate_password(8, false, false);
$assert = static function ($condition, $label) use (&$checks) {
    if (!$condition) {
        throw new RuntimeException("FAIL: " . $label);
    }
    ++$checks;
    echo "PASS: $label\n";
};
$call = static function ($method, $path, $data = []) {
    $r = new WP_REST_Request($method, "/vtx-media/v1/" . $path);
    if ($method === "GET") {
        $r->set_query_params($data);
    } else {
        $r->set_header("Content-Type", "application/json");
        $r->set_body(wp_json_encode($data));
        $r->set_header("X-WP-Nonce", wp_create_nonce("wp_rest"));
    }
    return rest_do_request($r);
};
$ok = static function ($method, $path, $data = []) use ($call) {
    $r = $call($method, $path, $data);
    if ($r->get_status() >= 400) {
        throw new RuntimeException(
            $path . ": " . wp_json_encode($r->get_data()),
        );
    }
    return $r->get_data();
};
try {
    Schema::install();
    Schema::install();
    $assert(
        get_option("vtx_media_schema_version") === Schema::VERSION,
        "Schema installation/upgrade is idempotent",
    );
    global $wpdb;
    foreach (["folders", "memberships", "favorites", "facts"] as $table) {
        $assert(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $wpdb->esc_like(Schema::table($table)),
                ),
            ) === Schema::table($table),
            "Table exists: " . $table,
        );
    }
    foreach (["author", "subscriber"] as $role) {
        $id = wp_insert_user([
            "user_login" => strtolower($prefix . "-" . $role),
            "user_pass" => wp_generate_password(32),
            "role" => $role,
        ]);
        if (is_wp_error($id)) {
            throw new RuntimeException($id->get_error_message());
        }
        $users[$role] = $id;
    }
    $mimeTypes = ["image/png", "application/pdf", "audio/mpeg", "video/mp4"];
    foreach ($mimeTypes as $i => $mime) {
        $id = wp_insert_attachment(
            [
                "post_title" => $prefix . " " . chr(65 + $i),
                "post_mime_type" => $mime,
                "post_status" => "inherit",
                "post_author" => $i === 3 ? $users["author"] : $admin,
                "post_date" => "2025-03-0" . ($i + 1) . " 12:00:00",
                "post_date_gmt" => "2025-03-0" . ($i + 1) . " 12:00:00",
            ],
            false,
            0,
            true,
        );
        if (is_wp_error($id)) {
            throw new RuntimeException($id->get_error_message());
        }
        $attachments[] = $id;
        update_post_meta(
            $id,
            "_wp_attached_file",
            "vtx-test-does-not-exist/" .
                $prefix .
                "-" .
                $i .
                "." .
                ["png", "pdf", "mp3", "mp4"][$i],
        );
        wp_update_attachment_metadata($id, [
            "width" => $i ? 0 : 120,
            "height" => $i ? 0 : 80,
            "filesize" => ($i + 1) * 1000,
        ]);
    }
    [$a, $b, $c, $d] = $attachments;
    $before = get_post_meta($a);
    $url = wp_get_attachment_url($a);
    $root = $ok("POST", "folders", ["name" => $prefix . " root"]);
    $folders[] = $root["id"];
    $child = $ok("POST", "folders", [
        "name" => $prefix . " child",
        "parent_id" => $root["id"],
    ]);
    $folders[] = $child["id"];
    $leaf = $ok("POST", "folders", [
        "name" => $prefix . " leaf",
        "parent_id" => $child["id"],
    ]);
    $folders[] = $leaf["id"];
    $assert($child["parent_id"] === $root["id"], "Nested folder creation");
    $renamed = $ok("PATCH", "folders/" . $root["id"], [
        "name" => $prefix . " renamed",
        "position" => 3,
    ]);
    $assert(
        $renamed["name"] === $prefix . " renamed" && $renamed["position"] === 3,
        "Folder rename and ordering persist",
    );
    $assert(
        $call("PATCH", "folders/" . $root["id"], [
            "parent_id" => $leaf["id"],
        ])->get_status() === 400,
        "Descendant cycle rejected",
    );
    $assert(
        $call("PATCH", "folders/" . $root["id"], [
            "parent_id" => $root["id"],
        ])->get_status() === 400,
        "Self-parent rejected",
    );
    $assert(
        $call("POST", "folders", [
            "name" => "Invalid",
            "parent_id" => 999999999,
        ])->get_status() === 400,
        "Nonexistent parent rejected",
    );
    $assert(
        $call("POST", "folders", ["name" => "   "])->get_status() === 400,
        "Blank folder name rejected",
    );
    $assert(
        $call("POST", "folders", [
            "name" => str_repeat("a", 192),
        ])->get_status() === 400,
        "Oversized folder name rejected",
    );
    $ok("POST", "move", ["ids" => [$a], "folder_id" => $root["id"]]);
    $ok("POST", "move", ["ids" => [$b, $c], "folder_id" => $child["id"]]);
    $assert(
        $ok("GET", "media", ["folder" => $child["id"]])["total"] === 2,
        "Bulk move and folder filter",
    );
    $roots = $ok("GET", "folders");
    $row = array_values(
        array_filter($roots["items"], static function ($f) use ($root) {
            return $f["id"] === $root["id"];
        }),
    )[0];
    $assert(
        $row["count"] === 1 && $row["children"] === 1,
        "Direct folder counts and child count",
    );
    $assert(
        $ok("GET", "media", ["view" => "unorganized", "search" => $prefix])[
            "total"
        ] === 1,
        "Unorganized uses absence of relationships",
    );
    $ok("POST", "favorites", ["ids" => [$a, $b], "favorite" => true]);
    $assert(
        $ok("GET", "media", ["view" => "favorites", "search" => $prefix])[
            "total"
        ] === 2,
        "Personal favorites filter",
    );
    $ok("POST", "favorites", ["ids" => [$a], "favorite" => false]);
    $assert(
        $ok("GET", "media", ["view" => "favorites", "search" => $prefix])[
            "total"
        ] === 1,
        "Unfavorite persists",
    );
    $assert(
        $ok("GET", "media", ["search" => $prefix])["total"] === 4,
        "Search returns fixture attachments",
    );
    foreach (["image", "document", "audio", "video"] as $type) {
        $assert(
            $ok("GET", "media", ["search" => $prefix, "type" => $type])[
                "total"
            ] === 1,
            "Type filter: " . $type,
        );
    }
    $assert(
        $ok("GET", "media", ["search" => $prefix, "date" => "2025-03"])[
            "total"
        ] === 4,
        "Date filter",
    );
    $assert(
        $ok("GET", "media", ["search" => $prefix, "date" => "2025-04"])[
            "total"
        ] === 0,
        "Date exclusion",
    );
    $assert(
        $ok("GET", "media", [
            "search" => $prefix,
            "author" => $users["author"],
        ])["total"] === 1,
        "Author filter",
    );
    $assert(
        $ok("GET", "media", ["search" => $prefix, "view" => "recent"])[
            "total"
        ] === 0,
        "Recently Added uses 30 days",
    );
    foreach (
        ["newest" => $d, "oldest" => $a, "name_asc" => $a, "name_desc" => $d]
        as $sort => $first
    ) {
        $assert(
            $ok("GET", "media", ["search" => $prefix, "sort" => $sort])[
                "items"
            ][0]["id"] === $first,
            "Sort: " . $sort,
        );
    }
    do {
        $batch = $ok("POST", "size-index");
    } while ($batch["remaining"]);
    foreach (["largest" => $d, "smallest" => $a] as $sort => $first) {
        $assert(
            $ok("GET", "media", ["search" => $prefix, "sort" => $sort])[
                "items"
            ][0]["id"] === $first,
            "Global indexed size sort: " . $sort,
        );
    }
    $p1 = $ok("GET", "media", [
        "search" => $prefix,
        "per_page" => 2,
        "page" => 1,
    ]);
    $p2 = $ok("GET", "media", [
        "search" => $prefix,
        "per_page" => 2,
        "page" => 2,
    ]);
    $assert(
        $p1["pages"] === 2 &&
            count($p2["items"]) === 2 &&
            !array_intersect(
                array_column($p1["items"], "id"),
                array_column($p2["items"], "id"),
            ),
        "Pagination has stable disjoint pages",
    );
    $detail = $ok("GET", "media/" . $b);
    $assert(
        !$detail["image"] &&
            $detail["mime"] === "application/pdf" &&
            !$detail["local"],
        "Non-image inspector and absent local file",
    );
    $saved = $ok("PATCH", "media/" . $a, [
        "title" => $prefix . " Edited",
        "alt" => 'A "quoted" image \\ example',
        "caption" => "<b>Caption</b><script>alert(1)</script>",
        "description" => "Description",
    ]);
    $assert(
        $saved["title"] === $prefix . " Edited" &&
            $saved["alt"] === 'A "quoted" image \\ example' &&
            $saved["description"] === "Description" &&
            strpos($saved["caption"], "<script>") === false,
        "Metadata writes native fields, preserves slashes and sanitizes HTML",
    );
    $assert(
        get_post_meta($a, "_wp_attachment_metadata", true) ===
            maybe_unserialize($before["_wp_attachment_metadata"][0]),
        "Unrelated attachment metadata preserved",
    );
    $assert(
        wp_get_attachment_url($a) === $url,
        "Organization and metadata edits preserve URL",
    );
    $assert(
        $ok("GET", "media/" . $a)["alt"] === $saved["alt"],
        "Metadata survives a fresh read",
    );
    $assert(
        $call("GET", "media", ["sort" => "SQL injection"])->get_status() ===
            400,
        "Invalid sort rejected",
    );
    $assert(
        $call("GET", "media", ["page" => 0])->get_status() === 400,
        "Invalid page rejected",
    );
    $assert(
        $call("GET", "media", ["per_page" => 101])->get_status() === 400,
        "Oversized page rejected",
    );
    $assert(
        $call("GET", "media", ["date" => "2025-13"])->get_status() === 400,
        "Invalid date rejected",
    );
    $assert(
        $call("POST", "move", [
            "ids" => [$a, 999999999],
            "folder_id" => $root["id"],
        ])->get_status() === 403,
        "Invalid attachment batch rejected atomically",
    );
    $assert(
        $call("POST", "move", [
            "ids" => [$a],
            "folder_id" => 999999999,
        ])->get_status() === 404,
        "Invalid destination rejected",
    );
    $assert(
        $call("POST", "move", ["ids" => []])->get_status() === 400,
        "Empty batch rejected",
    );
    $assert(
        $call("POST", "move", ["ids" => range(1, 101)])->get_status() === 400,
        "Oversized batch rejected",
    );
    $assert(
        $call("PATCH", "media/" . $a, [])->get_status() === 400,
        "Empty metadata update rejected",
    );
    $r = new WP_REST_Request("POST", "/vtx-media/v1/folders");
    $r->set_param("name", "No nonce");
    $assert(
        rest_do_request($r)->get_status() === 403,
        "Write without nonce rejected",
    );
    wp_set_current_user($users["author"]);
    $assert(
        $ok("GET", "media", ["search" => $prefix])["total"] === 1,
        "Author sees only own attachments",
    );
    $assert(
        $ok("GET", "media", ["search" => $prefix, "view" => "favorites"])[
            "total"
        ] === 0,
        "Favorites isolated by user",
    );
    $assert(
        $call("PATCH", "media/" . $a, [
            "title" => "Forbidden",
        ])->get_status() === 403,
        "Cross-author metadata update denied",
    );
    $assert(
        $call("GET", "media/" . $a)->get_status() === 404,
        "Cross-author inspector denied",
    );
    $assert(
        $call("POST", "folders", ["name" => "Forbidden"])->get_status() === 403,
        "Authors cannot manage shared folders",
    );
    $ok("POST", "move", ["ids" => [$d], "folder_id" => $root["id"]]);
    $ok("POST", "favorites", ["ids" => [$d], "favorite" => true]);
    $assert(
        $ok("GET", "media", ["view" => "favorites", "search" => $prefix])[
            "total"
        ] === 1,
        "Author can organize and favorite own media",
    );
    $assert(
        $call("POST", "move", [
            "ids" => [$d, $a],
            "folder_id" => $child["id"],
        ])->get_status() === 403,
        "Mixed-author batch denied",
    );
    $assert(
        $ok("GET", "media/" . $d)["folder_id"] === $root["id"],
        "Denied mixed batch leaves authorized item unchanged",
    );
    wp_set_current_user($users["subscriber"]);
    $assert($call("GET", "media")->get_status() === 403, "Subscriber denied");
    wp_set_current_user(0);
    $assert(
        $call("GET", "media")->get_status() === 401,
        "Unauthenticated access denied",
    );
    wp_set_current_user($admin);
    $ok("PATCH", "folders/" . $leaf["id"], ["parent_id" => $root["id"]]);
    $assert(
        $ok("GET", "folders/" . $leaf["id"])["parent_id"] === $root["id"],
        "Folder move persists",
    );
    $ok("PATCH", "folders/" . $leaf["id"], ["parent_id" => $child["id"]]);
    $ok("DELETE", "folders/" . $child["id"]);
    $assert(
        $ok("GET", "folders/" . $leaf["id"])["parent_id"] === $root["id"],
        "Delete reparents children",
    );
    $assert(
        $ok("GET", "media/" . $b)["folder_id"] === 0 && get_post($b) !== null,
        "Delete unorganizes media without deleting attachment",
    );
    $assert(
        $ok("GET", "media/" . $c)["folder_id"] === 0,
        "Delete handles all direct memberships",
    );
    wp_update_attachment_metadata($a, [
        "filesize" => 99000,
        "width" => 120,
        "height" => 80,
    ]);
    $assert(
        !$wpdb->get_var(
            $wpdb->prepare(
                "SELECT attachment_id FROM " .
                    Schema::table("facts") .
                    " WHERE attachment_id=%d",
                $a,
            ),
        ),
        "Native metadata update invalidates derived size",
    );
    // Native deletion cleanup, using a fixture without any physical file.
    wp_delete_attachment($d, true);
    $assert(
        !$wpdb->get_var(
            $wpdb->prepare(
                "SELECT attachment_id FROM " .
                    Schema::table("memberships") .
                    " WHERE attachment_id=%d",
                $d,
            ),
        ),
        "Native attachment deletion cleans membership",
    );
    $assert(
        !$wpdb->get_var(
            $wpdb->prepare(
                "SELECT attachment_id FROM " .
                    Schema::table("favorites") .
                    " WHERE attachment_id=%d",
                $d,
            ),
        ),
        "Native attachment deletion cleans favorites",
    );
    $native = new WP_REST_Request("GET", "/wp/v2/media");
    $native->set_query_params(["per_page" => 1]);
    $assert(
        rest_do_request($native)->get_status() === 200,
        "Native attachment REST remains functional",
    );
    $chain = [];
    $parent = 0;
    for ($depth = 1; $depth <= 32; $depth++) {
        $f = $ok("POST", "folders", [
            "name" => $prefix . " depth " . $depth,
            "parent_id" => $parent,
        ]);
        $parent = $f["id"];
        $chain[] = $parent;
        $folders[] = $parent;
    }
    $assert(
        $call("POST", "folders", [
            "name" => $prefix . " too deep",
            "parent_id" => $parent,
        ])->get_status() === 400,
        "Maximum folder depth enforced",
    );
    $assert(
        $call("PATCH", "folders/" . $root["id"], [
            "parent_id" => $chain[30],
        ])->get_status() === 400,
        "Moving a subtree cannot exceed depth limit",
    );
    for ($i = 0; $i < 51; $i++) {
        $f = $ok("POST", "folders", [
            "name" => $prefix . " pager " . sprintf("%03d", $i),
            "position" => $i,
        ]);
        $folders[] = $f["id"];
    }
    $f1 = $ok("GET", "folders", ["search" => $prefix . " pager", "page" => 1]);
    $f2 = $ok("GET", "folders", ["search" => $prefix . " pager", "page" => 2]);
    $assert(
        count($f1["items"]) === 50 &&
            $f1["more"] &&
            count($f2["items"]) === 1 &&
            !$f2["more"],
        "Folder search pagination is bounded and complete",
    );
    $assert(
        $f1["items"][0]["position"] === 0 && $f2["items"][0]["position"] === 50,
        "Folder ordering across pages",
    );
    $assert(
        $call("PATCH", "media/" . $a, ["alt" => ["invalid"]])->get_status() ===
            400,
        "Array metadata rejected by schema",
    );
    $assert(
        $ok("GET", "media", ["search" => "' OR 1=1 --"])["total"] === 0,
        "Search SQL metacharacters treated as literal text",
    );
    $ok("POST", "move", ["ids" => [$a, $b], "folder_id" => 0]);
    $assert(
        $ok("GET", "media/" . $a)["folder_id"] === 0 &&
            $ok("GET", "media/" . $b)["folder_id"] === 0,
        "Explicit bulk move to Unorganized",
    );
    echo "Completed $checks integration checks.\n";
} finally {
    wp_set_current_user($admin);
    foreach (array_reverse($folders) as $id) {
        if ((new FolderRepository(new Policy()))->get($id)) {
            (new FolderRepository(new Policy()))->delete($id);
        }
    }
    foreach ($attachments as $id) {
        if (get_post($id)) {
            wp_delete_attachment($id, true);
        }
    }
    require_once ABSPATH . "wp-admin/includes/user.php";
    foreach ($users as $id) {
        wp_delete_user($id);
    }
}
