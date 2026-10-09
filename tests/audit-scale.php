<?php
/** Connection-local benchmark. Synthetic rows never enter live tables. */
if (!defined("WP_CLI") || !WP_CLI) {
    exit();
}
use VTX\Media\Audit\{
    AuditEngine,
    AuditRuleRegistry,
    AuditQuery,
    AuditJob,
    Settings,
};
use VTX\Media\Entitlements\{FeatureRegistry, EntitlementService};
use VTX\Media\Jobs\{Logger, JobRegistry, JobRunner};
use VTX\Media\Permissions\Policy;
use VTX\Media\Database\Schema;
global $wpdb;
$admin = (int) get_users([
    "role" => "administrator",
    "number" => 1,
    "fields" => "ID",
])[0];
wp_set_current_user($admin);
$original = [
    "posts" => $wpdb->posts,
    "postmeta" => $wpdb->postmeta,
    "prefix" => $wpdb->prefix,
];
$tables = [];
$report = [];
$features = new FeatureRegistry();
$features->register(FeatureRegistry::AUDIT, true);
$ent = new EntitlementService($features);
$rules = new AuditRuleRegistry($ent);
foreach (
    [
        "MissingAlt",
        "FilenameAsAlt",
        "GenericAlt",
        "MissingTitle",
        "MissingCaption",
        "MissingDescription",
        "PoorFilename",
        "MissingPhysicalFile",
        "OversizedFile",
        "ExcessiveDimensions",
        "InvalidImageMetadata",
        "LongAlt",
    ]
    as $name
) {
    $class = "VTX\\Media\\Audit\\Rules\\" . $name . "Rule";
    $rules->register(new $class());
}
$engine = new AuditEngine($rules, new Settings(), new Logger());
$policy = new Policy();
$query = new AuditQuery($engine, $policy);
try {
    $wpdb->prefix = $original["prefix"] . "audit_benchmark_";
    $wpdb->posts = $wpdb->prefix . "posts";
    $wpdb->postmeta = $wpdb->prefix . "postmeta";
    $sources = [
        "posts" => $original["posts"],
        "postmeta" => $original["postmeta"],
    ];
    foreach (
        [
            "folders",
            "memberships",
            "favorites",
            "facts",
            "findings",
            "health",
            "jobs",
            "job_logs",
        ]
        as $suffix
    ) {
        $sources["vtx_media_" . $suffix] =
            $original["prefix"] . "vtx_media_" . $suffix;
    }
    foreach ($sources as $suffix => $source) {
        $target = $wpdb->prefix . $suffix;
        if (
            $wpdb->query("CREATE TEMPORARY TABLE `$target` LIKE `$source`") ===
            false
        ) {
            throw new RuntimeException("Temporary table creation failed");
        }
        $tables[] = $target;
    }
    $h = Schema::table("health");
    $f = Schema::table("findings");
    $signature = $engine->signature();
    for ($start = 1; $start <= 50000; $start += 500) {
        $posts = [];
        $meta = [];
        $health = [];
        $findings = [];
        for ($n = $start; $n < $start + 500; $n++) {
            $id = 8000000 + $n;
            $posts[] = $wpdb->prepare(
                "(%d,%d,'attachment','inherit','application/pdf',%s,'','','','',UTC_TIMESTAMP(),UTC_TIMESTAMP())",
                $id,
                $admin,
                "Benchmark document " . $n,
            );
            $meta[] = $wpdb->prepare(
                "(%d,'_wp_attached_file',%s)",
                $id,
                "vtx-benchmark-missing-" . $n . ".pdf",
            );
            $health[] = $wpdb->prepare(
                "(%d,%s,'complete',96,%s,0,'[]',UTC_TIMESTAMP())",
                $id,
                "document-" . $n . ".pdf",
                $signature,
            );
            $findings[] = $wpdb->prepare(
                "(%d,'missing-description','1','metadata','info',1,1,'objective',%s,'{}','open',UTC_TIMESTAMP(),UTC_TIMESTAMP())",
                $id,
                str_repeat("a", 64),
            );
        }
        foreach (
            [
                "INSERT INTO {$wpdb->posts} (ID,post_author,post_type,post_status,post_mime_type,post_title,post_content,post_excerpt,to_ping,pinged,post_date,post_date_gmt) VALUES " .
                implode(",", $posts),
                "INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) VALUES " .
                implode(",", $meta),
                "INSERT INTO $h (attachment_id,filename,state,score,signature,revision,errors,audited_at) VALUES " .
                implode(",", $health),
                "INSERT INTO $f (attachment_id,rule_id,rule_version,category,severity,severity_rank,confidence,kind,fingerprint,data,status,audited_at,updated_at) VALUES " .
                implode(",", $findings),
            ]
            as $sql
        ) {
            if ($wpdb->query($sql) === false) {
                throw new RuntimeException($wpdb->last_error);
            }
        }
        $size = $start + 499;
        if (!in_array($size, [1000, 10000, 50000], true)) {
            continue;
        }
        $before = $wpdb->num_queries;
        $time = microtime(true);
        $summary = $query->dashboard();
        $elapsed = microtime(true) - $time;
        if ($summary["total"] !== $size || $summary["analyzed"] !== $size) {
            throw new RuntimeException("Coverage aggregation failed");
        }
        $report[] = [
            "size" => $size,
            "operation" => "dashboard",
            "queries" => $wpdb->num_queries - $before,
            "milliseconds" => round($elapsed * 1000, 2),
            "coverage" => $summary["analyzed"],
        ];
        foreach (["severity", "health", "filename", "recent"] as $sort) {
            $before = $wpdb->num_queries;
            $time = microtime(true);
            $page = $query->findings([
                "sort" => $sort,
                "status" => "open",
                "page" => (int) ceil($size / 25),
                "per_page" => 25,
            ]);
            $elapsed = microtime(true) - $time;
            if (count($page["items"]) !== 25) {
                throw new RuntimeException("Pagination failed");
            }
            $report[] = [
                "size" => $size,
                "operation" => "last-page-" . $sort,
                "queries" => $wpdb->num_queries - $before,
                "milliseconds" => round($elapsed * 1000, 2),
                "response_bytes" => strlen(wp_json_encode($page)),
            ];
        }
    }
    // Execute 1,000 genuine rule evaluations via persistent job checkpoints.
    $registry = new JobRegistry($ent);
    $registry->register(new AuditJob($engine));
    $runner = new JobRunner($registry, $policy, new Logger());
    $job = $runner->start("media-audit", 50);
    $wpdb->update(
        Schema::table("jobs"),
        ["max_id" => 8001000, "total" => 1000],
        ["id" => $job["id"]],
    );
    $time = microtime(true);
    $memory = memory_get_usage(true);
    $batches = 0;
    do {
        $state = $runner->tick($job["id"]);
        $batches++;
        if ($batches > 1100) {
            throw new RuntimeException("Scan did not terminate");
        }
    } while ($state["status"] === "running");
    if (
        $state["processed"] !== 1000 ||
        $state["failed"] !== 0 ||
        $state["status"] !== "completed"
    ) {
        throw new RuntimeException(
            "Real scan benchmark failed: " . wp_json_encode($state),
        );
    }
    $report[] = [
        "operation" => "real-1000-attachment-scan",
        "batches" => $batches,
        "seconds" => round(microtime(true) - $time, 3),
        "memory_delta_bytes" => memory_get_usage(true) - $memory,
        "peak_memory_bytes" => memory_get_peak_usage(true),
        "processed" => $state["processed"],
        "failed" => $state["failed"],
    ];
    $report[] = [
        "explain_findings" => $wpdb->get_results(
            "EXPLAIN SELECT id FROM $f WHERE attachment_id=8000001 AND status='open'",
            ARRAY_A,
        ),
        "explain_pending" => $wpdb->get_results(
            "EXPLAIN SELECT attachment_id FROM $h WHERE state='pending' ORDER BY attachment_id LIMIT 25",
            ARRAY_A,
        ),
    ];
    file_put_contents(
        __DIR__ . "/artifacts/audit-scale.json",
        wp_json_encode($report, JSON_PRETTY_PRINT),
    );
    echo wp_json_encode($report, JSON_PRETTY_PRINT) . PHP_EOL;
} finally {
    for ($id = 8000001; $id <= 8050000; $id++) {
        wp_cache_delete($id, "posts");
        wp_cache_delete($id, "post_meta");
    }
    foreach (array_reverse($tables) as $table) {
        $wpdb->query("DROP TEMPORARY TABLE IF EXISTS `$table`");
    }
    foreach ($original as $key => $value) {
        $wpdb->$key = $value;
    }
}
