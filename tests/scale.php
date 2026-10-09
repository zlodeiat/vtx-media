<?php
/** Connection-local synthetic benchmark. Never inserts into live WordPress tables. */
if (!defined("WP_CLI") || !WP_CLI) {
    exit();
}
use VTX\Media\Database\Schema;
use VTX\Media\Media\MediaRepository;
use VTX\Media\Media\FileFacts;
use VTX\Media\Folders\FolderRepository;
use VTX\Media\Permissions\Policy;
global $wpdb;
wp_set_current_user(
    (int) get_users([
        "role" => "administrator",
        "number" => 1,
        "fields" => "ID",
    ])[0],
);
$user = get_current_user_id();
$original = [
    "posts" => $wpdb->posts,
    "postmeta" => $wpdb->postmeta,
    "prefix" => $wpdb->prefix,
];
$tables = [];
$cached = [];
$report = [];
try {
    $wpdb->prefix = $original["prefix"] . "vtx_benchmark_";
    $wpdb->posts = $wpdb->prefix . "posts";
    $wpdb->postmeta = $wpdb->prefix . "postmeta";
    foreach (
        [
            "posts" => $original["posts"],
            "postmeta" => $original["postmeta"],
            "vtx_media_folders" => $original["prefix"] . "vtx_media_folders",
            "vtx_media_memberships" =>
                $original["prefix"] . "vtx_media_memberships",
            "vtx_media_favorites" =>
                $original["prefix"] . "vtx_media_favorites",
            "vtx_media_facts" => $original["prefix"] . "vtx_media_facts",
        ]
        as $suffix => $source
    ) {
        $table = $wpdb->prefix . $suffix;
        if (
            $wpdb->query("CREATE TEMPORARY TABLE `$table` LIKE `$source`") ===
            false
        ) {
            throw new RuntimeException(
                "Cannot create isolated benchmark table",
            );
        }
        $tables[] = $table;
    }
    $wpdb->insert(Schema::table("folders"), [
        "id" => 1,
        "parent_id" => 0,
        "name" => "Benchmark",
        "position" => 0,
        "created_at" => gmdate("Y-m-d H:i:s"),
        "updated_at" => gmdate("Y-m-d H:i:s"),
    ]);
    $policy = new Policy();
    $repo = new MediaRepository(
        $policy,
        new FolderRepository($policy),
        new FileFacts(),
    );
    $count = 0;
    foreach ([1000, 10000, 50000] as $target) {
        while ($count < $target) {
            $rows = [];
            $facts = [];
            $members = [];
            $favorites = [];
            for ($i = 0; $i < 500 && $count < $target; $i++) {
                ++$count;
                $id = 900000000 + $count;
                $title = sprintf("Benchmark image %06d", $count);
                $rows[] = $wpdb->prepare(
                    "(%d,%d,'2026-01-01 12:00:00','2026-01-01 12:00:00','',%s,'','inherit','closed','closed','','','','','2026-01-01 12:00:00','2026-01-01 12:00:00','',0,%s,0,'attachment','image/png',0)",
                    $id,
                    $user,
                    $title,
                    "https://example.invalid/" . $count . ".png",
                );
                $facts[] = "($id," . $count * 1024 . ",'2026-01-01 12:00:00')";
                if ($count % 3 === 0) {
                    $members[] = "($id,1)";
                }
                if ($count % 20 === 0) {
                    $favorites[] = "($user,$id,'2026-01-01 12:00:00')";
                }
            }
            $columns =
                "ID,post_author,post_date,post_date_gmt,post_content,post_title,post_excerpt,post_status,comment_status,ping_status,post_password,post_name,to_ping,pinged,post_modified,post_modified_gmt,post_content_filtered,post_parent,guid,menu_order,post_type,post_mime_type,comment_count";
            if (
                $wpdb->query(
                    "INSERT INTO {$wpdb->posts} ($columns) VALUES " .
                        implode(",", $rows),
                ) === false
            ) {
                throw new RuntimeException("Benchmark seed failed");
            }
            $wpdb->query(
                "INSERT INTO " .
                    Schema::table("facts") .
                    " (attachment_id,bytes,checked_at) VALUES " .
                    implode(",", $facts),
            );
            if ($members) {
                $wpdb->query(
                    "INSERT INTO " .
                        Schema::table("memberships") .
                        " VALUES " .
                        implode(",", $members),
                );
            }
            if ($favorites) {
                $wpdb->query(
                    "INSERT INTO " .
                        Schema::table("favorites") .
                        " VALUES " .
                        implode(",", $favorites),
                );
            }
        }
        $defaults = [
            "search" => "",
            "type" => "all",
            "view" => "all",
            "folder" => 0,
            "author" => 0,
            "date" => "",
            "sort" => "newest",
            "page" => 1,
            "per_page" => 48,
        ];
        foreach (
            [
                "newest" => [],
                "name" => ["sort" => "name_asc"],
                "largest" => ["sort" => "largest"],
                "search" => ["search" => "image 000"],
                "unorganized" => ["view" => "unorganized"],
                "folder" => ["folder" => 1],
                "favorites" => ["view" => "favorites"],
                "deep_page" => ["page" => (int) ceil($target / 48)],
            ]
            as $name => $args
        ) {
            $started = microtime(true);
            $queries = $wpdb->num_queries;
            $result = $repo->listing(array_merge($defaults, $args));
            if (
                count($result["items"]) > 48 ||
                (!$result["total"] && $name !== "search")
            ) {
                throw new RuntimeException("Invalid benchmark page");
            }
            $report[] = [
                "attachments" => $target,
                "case" => $name,
                "milliseconds" => round((microtime(true) - $started) * 1000, 2),
                "queries" => $wpdb->num_queries - $queries,
                "returned" => count($result["items"]),
                "total" => $result["total"],
            ];
            foreach ($result["items"] as $item) {
                $cached[$item["id"]] = true;
                wp_cache_delete($item["id"], "posts");
                wp_cache_delete($item["id"], "post_meta");
            }
        }
    }
    $wpdb->query(
        "DELETE FROM " .
            Schema::table("facts") .
            " WHERE attachment_id <= 900000250",
    );
    $facts = new FileFacts();
    $first = $facts->batch($policy);
    $second = $facts->batch($policy);
    if (
        $first["processed"] !== 200 ||
        $first["remaining"] !== 50 ||
        $second["processed"] !== 50 ||
        $second["remaining"] !== 0
    ) {
        throw new RuntimeException("Size index batching or resume failed");
    }
    echo "PASS: Size index processes 200 per request and resumes the remaining 50.\n";
    file_put_contents(
        __DIR__ . "/artifacts/scale-results.json",
        wp_json_encode($report, JSON_PRETTY_PRINT),
    );
    echo wp_json_encode($report, JSON_PRETTY_PRINT) . "\n";
} finally {
    foreach (array_reverse($tables) as $table) {
        $wpdb->query("DROP TEMPORARY TABLE IF EXISTS `$table`");
    }
    $wpdb->posts = $original["posts"];
    $wpdb->postmeta = $original["postmeta"];
    $wpdb->prefix = $original["prefix"];
    foreach (array_keys($cached) as $id) {
        wp_cache_delete($id, "posts");
        wp_cache_delete($id, "post_meta");
    }
}
