<?php
if (!defined("WP_CLI") || !WP_CLI) {
    exit();
}
use VTX\Media\Audit\{
    AuditEngine,
    AuditRuleRegistry,
    Context,
    Settings,
    Health,
    AuditJob,
    AuditQuery,
};
use VTX\Media\Entitlements\{
    FeatureRegistry,
    EntitlementService,
    FeatureEntitlementInterface,
};
use VTX\Media\Extensions\ModuleRegistry;
use VTX\Media\Jobs\{JobRegistry, JobRunner, Logger};
use VTX\Media\Permissions\Policy;
use VTX\Media\Database\Schema;
require __DIR__ . "/fixtures/AuditExtension.php";
global $wpdb;
$admin = (int) get_users([
    "role" => "administrator",
    "number" => 1,
    "fields" => "ID",
])[0];
wp_set_current_user($admin);
$checks = 0;
$ids = [];
$users = [];
$jobs = [];
$oldSettings = get_option(Settings::OPTION, null);
$assert = static function ($ok, $label) use (&$checks) {
    if (!$ok) {
        throw new RuntimeException("FAIL: " . $label);
    }
    $checks++;
    echo "PASS: $label\n";
};
$call = static function ($method, $path, $data = [], $nonce = true) {
    $r = new WP_REST_Request($method, "/vtx-media/v1/" . $path);
    if ($method === "GET") {
        $r->set_query_params($data);
    } else {
        $r->set_header("Content-Type", "application/json");
        $r->set_body(wp_json_encode($data));
        if ($nonce) {
            $r->set_header("X-WP-Nonce", wp_create_nonce("wp_rest"));
        }
    }
    return rest_do_request($r);
};
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
$settings = new Settings();
$logger = new Logger();
$engine = new AuditEngine($rules, $settings, $logger);
$policy = new Policy();
$registry = new JobRegistry($ent);
$registry->register(new AuditJob($engine));
$runner = new JobRunner($registry, $policy, $logger);
$rule = static function ($name, $c) use ($rules) {
    return $rules->get($name)->evaluate($c);
};
try {
    $settings->save(Settings::DEFAULTS);
    $upload = wp_upload_bits(
        "vtx-audit-test-" . wp_generate_uuid4() . ".png",
        null,
        base64_decode(
            "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWZkAAAAASUVORK5CYII=",
        ),
    );
    if ($upload["error"]) {
        throw new RuntimeException($upload["error"]);
    }
    $id = wp_insert_attachment(
        [
            "post_title" => "Meaningful title",
            "post_excerpt" => "Caption",
            "post_content" => "Description",
            "post_mime_type" => "image/png",
            "post_status" => "inherit",
            "post_author" => $admin,
        ],
        $upload["file"],
    );
    $ids[] = $id;
    wp_update_attachment_metadata($id, [
        "width" => 1,
        "height" => 1,
        "file" => get_post_meta($id, "_wp_attached_file", true),
    ]);
    $c = new Context($id, $settings->get());
    $assert($rule("missing-alt", $c) !== null, "Empty ALT requires review");
    $c->decorative = true;
    $assert($rule("missing-alt", $c) === null, "Decorative empty ALT excluded");
    $c->decorative = false;
    $c->filename = "IMG_1234.JPG";
    foreach (["img-1234", "IMG_1234.jpg", "img 1234"] as $alt) {
        $c->alt = $alt;
        $assert(
            $rule("filename-as-alt", $c) !== null,
            "Filename ALT normalization: " . $alt,
        );
    }
    $c->alt = "A quiet path through a forest";
    $assert(
        !$rule("filename-as-alt", $c) && !$rule("generic-alt", $c),
        "Meaningful ALT is not mechanically flagged",
    );
    foreach (["image", "photo 2", "thumbnail", "img-12", "untitled"] as $alt) {
        $c->alt = $alt;
        $assert($rule("generic-alt", $c) !== null, "Generic ALT: " . $alt);
    }
    $c->post->post_title = "";
    $assert($rule("missing-title", $c) !== null, "Missing title");
    $c->post->post_title = "IMG_1234";
    $assert(
        $rule("missing-title", $c)["severity"] === "info",
        "Generated title has info severity",
    );
    $c->post->post_excerpt = "";
    $c->post->post_content = "";
    $assert(
        $rule("missing-caption", $c)["severity"] === "info",
        "Caption optional",
    );
    $assert(
        $rule("missing-description", $c)["severity"] === "info",
        "Description optional",
    );
    foreach (
        [
            "IMG_8472.jpg",
            "DSC_9928.jpg",
            "Screenshot-2026.png",
            "download.jpg",
            "download-7.webp",
            "image-52.webp",
            "123e4567-e89b-12d3-a456-426614174000.jpg",
            str_repeat("ab", 20) . ".png",
        ]
        as $filename
    ) {
        $c->filename = $filename;
        $assert(
            $rule("poor-filename", $c) !== null,
            "Poor filename: " . $filename,
        );
    }
    $c->filename = "mountain-walking-guide.jpg";
    $assert(!$rule("poor-filename", $c), "Descriptive filename accepted");
    $assert(
        $c->file["state"] === "available" &&
            !$rule("missing-physical-file", $c),
        "Real physical file exists",
    );
    $c->file["state"] = "missing";
    $assert(
        $rule("missing-physical-file", $c)["severity"] === "critical",
        "Missing original critical",
    );
    $c->file["state"] = "unknown";
    $assert(
        $rule("missing-physical-file", $c)["severity"] === "info",
        "Unverified source not critical",
    );
    $c->file["bytes"] = 500000;
    $assert(!$rule("oversized-file", $c), "Normal file size");
    $c->file["bytes"] = 4000000;
    $assert(
        $rule("oversized-file", $c)["severity"] === "medium",
        "Large image",
    );
    $c->file["bytes"] = 20000000;
    $assert(
        $rule("oversized-file", $c)["severity"] === "high",
        "Very large image",
    );
    $c->metadata = ["width" => 8000, "height" => 6000];
    $assert($rule("excessive-dimensions", $c) !== null, "Excessive dimensions");
    $c->metadata = ["width" => 1, "height" => 1];
    $assert(!$rule("excessive-dimensions", $c), "Normal dimensions");
    $c->metadata = "malformed";
    $assert($rule("invalid-image-metadata", $c) !== null, "Malformed metadata");
    $c->alt = str_repeat("a", 251);
    $assert($rule("long-alt", $c) !== null, "Long ALT review threshold");
    clean_post_cache($id);
    update_post_meta($id, "_wp_attachment_image_alt", "A useful description");
    $result = $engine->audit($id);
    $assert(
        $result["state"] === "complete" && $result["score"] === 100,
        "Healthy attachment scores 100",
    );
    $score = Health::calculate([
        [
            "rule_id" => "a",
            "severity" => "info",
            "category" => "metadata",
            "status" => "open",
        ],
        [
            "rule_id" => "b",
            "severity" => "info",
            "category" => "metadata",
            "status" => "open",
        ],
    ]);
    $assert($score["score"] === 98, "Optional metadata only two points");
    $many = [];
    for ($i = 0; $i < 100; $i++) {
        $many[] = [
            "rule_id" => "m" . $i,
            "severity" => "low",
            "category" => "metadata",
            "status" => "open",
        ];
    }
    $assert(
        Health::calculate($many)["score"] === 92,
        "Trivial findings capped",
    );
    $many[] = [
        "rule_id" => "broken",
        "severity" => "critical",
        "category" => "files",
        "status" => "open",
    ];
    $assert(
        Health::calculate($many)["score"] === 32,
        "Critical issue strongly affects score",
    );
    update_post_meta($id, "_wp_attachment_image_alt", "");
    $result = $engine->audit($id);
    $open = array_values(
        array_filter($result["findings"], static function ($f) {
            return $f["rule_id"] === "missing-alt";
        }),
    )[0];
    $assert(
        $open["status"] === "open" && $result["score"] === 90,
        "Persist open finding and health",
    );
    $engine->status($open["id"], "ignored");
    $assert(
        $engine->detail($id)["score"] === 100,
        "Ignored finding removes penalty",
    );
    $result = $engine->audit($id);
    $assert($result["score"] === 100, "Ignore survives identical evidence");
    $engine->status($open["id"], "open");
    $assert($engine->detail($id)["score"] === 90, "Restore reapplies penalty");
    $r = $call("POST", "audit/actions", [
        "action" => "decorative",
        "ids" => [$id],
    ]);
    $assert(
        $r->get_status() === 200 && $engine->detail($id)["score"] === 100,
        "Decorative action resolves ALT finding",
    );
    update_post_meta($id, "_wp_attachment_image_alt", "A forest path");
    $assert(
        !get_post_meta($id, "_vtx_media_decorative", true),
        "Adding ALT clears decorative intent",
    );
    $engine->audit($id);
    $detail = $engine->detail($id);
    $missing = array_values(
        array_filter($detail["findings"], static function ($f) {
            return $f["rule_id"] === "missing-alt";
        }),
    )[0];
    $assert(
        $missing["status"] === "resolved",
        "Changed metadata resolves finding",
    );
    update_post_meta($id, "_wp_attachment_image_alt", "image");
    $engine->audit($id);
    $generic = array_values(
        array_filter($engine->detail($id)["findings"], static function ($f) {
            return $f["rule_id"] === "generic-alt";
        }),
    )[0];
    $engine->status($generic["id"], "ignored");
    update_post_meta($id, "_wp_attachment_image_alt", "photo");
    $engine->audit($id);
    $generic = array_values(
        array_filter($engine->detail($id)["findings"], static function ($f) {
            return $f["rule_id"] === "generic-alt";
        }),
    )[0];
    $assert(
        $generic["status"] === "open",
        "Material evidence change reopens ignored finding",
    );
    $unknown = wp_insert_attachment([
        "post_title" => "Unanalyzed",
        "post_mime_type" => "application/pdf",
        "post_status" => "inherit",
        "post_author" => $admin,
    ]);
    $ids[] = $unknown;
    $assert(
        $engine->detail($unknown)["score"] === null,
        "Unaudited state has no score",
    );
    update_post_meta($unknown, "_wp_attached_file", "../../wp-config.php");
    $ctx = new Context($unknown, $settings->get());
    $assert(
        $ctx->file["state"] === "unknown",
        "Traversal path never inspected",
    );
    $result = $engine->audit($unknown);
    $assert(
        $result["score"] === null,
        "Unverified analysis cannot look healthy",
    );
    $assert(
        !array_filter($result["findings"], static function ($f) {
            return $f["rule_id"] === "missing-alt";
        }),
        "PDF has no ALT finding",
    );
    // Remove unsafe path before native test cleanup; never ask WP to delete arbitrary paths.
    delete_post_meta($unknown, "_wp_attached_file");
    $summary = (new AuditQuery($engine, $policy))->dashboard();
    $assert(
        $summary["total"] ===
            $summary["analyzed"] + $summary["pending"] + $summary["failed"],
        "Coverage partitions real attachments",
    );
    $settings->save(["large_bytes" => 4194304]);
    $assert(
        $engine->detail($id)["state"] === "stale",
        "Threshold change marks old audit stale",
    );
    $settings->save(Settings::DEFAULTS);
    $engine->audit($id);
    $features->register("test-extension");
    $modules = new ModuleRegistry($ent);
    $booted = false;
    $modules->register(
        "test-module",
        "test-extension",
        ["label" => "Test", "icon" => "test", "position" => 50],
        static function () use (&$booted) {
            $booted = true;
        },
    );
    $rules->register(new VTX\Media\Tests\AuditExtension());
    $assert(
        !$ent->hasFeature("test-extension") && !$modules->manifest(),
        "Production entitlement denies paid fixture by default",
    );
    $ent->addSource(
        "fixture",
        new class implements FeatureEntitlementInterface {
            public function hasFeature(string $feature): bool
            {
                return $feature === "test-extension";
            }
        },
    );
    $modules->boot([]);
    $assert(
        $booted && count($modules->manifest()) === 1,
        "Registered entitled module boots",
    );
    $result = $engine->audit($id);
    $assert(
        count(
            array_filter($result["findings"], static function ($f) {
                return $f["rule_id"] === "test-extension-rule" &&
                    $f["status"] === "open";
            }),
        ) === 1,
        "AuditEngine executes external registered rule",
    );
    $rules->unregister("test-extension-rule");
    $modules->unregister("test-module");
    $ent->removeSource("fixture");
    $result = $engine->audit($id);
    $assert(
        !$modules->all() &&
            !$ent->hasFeature("test-extension") &&
            $result["state"] === "complete",
        "Removing extension preserves Core",
    );
    $rules->register(
        new class implements \VTX\Media\Audit\AuditRuleInterface {
            public function definition(): array
            {
                return [
                    "id" => "broken-test-rule",
                    "version" => "1",
                    "category" => "files",
                    "severity" => "high",
                    "types" => ["*"],
                    "feature" => FeatureRegistry::AUDIT,
                ];
            }
            public function evaluate(Context $c): ?array
            {
                throw new RuntimeException("Test-only failure");
            }
            public function present(array $data): array
            {
                return [
                    "title" => "Fixture",
                    "explanation" => "",
                    "recommendation" => "",
                    "remediation" => [],
                ];
            }
        },
    );
    $result = $engine->audit($id);
    $assert(
        $result["state"] === "failed" &&
            $result["score"] === null &&
            in_array("broken-test-rule", $result["errors"], true),
        "Rule exception isolated and incomplete score withheld",
    );
    $rules->unregister("broken-test-rule");
    $assert(
        $engine->audit($id)["state"] === "complete",
        "Removing failed rule restores Core execution",
    );
    $badJob = new class ($id) implements \VTX\Media\Jobs\JobInterface {
        private $attachment;
        public function __construct($id)
        {
            $this->attachment = $id;
        }
        public function id(): string
        {
            return "test-failing-job";
        }
        public function feature(): string
        {
            return FeatureRegistry::AUDIT;
        }
        public function snapshot(int $author): array
        {
            return ["total" => 1, "max_id" => $this->attachment];
        }
        public function next(array $state, int $limit): array
        {
            return $state["last_id"] < $this->attachment
                ? [$this->attachment]
                : [];
        }
        public function process(int $attachment, int $job): bool
        {
            throw new RuntimeException("Test attachment failure");
        }
    };
    $registry->register($badJob);
    $failedJob = $runner->start("test-failing-job", 1);
    $jobs[] = $failedJob["id"];
    $failedState = $runner->tick($failedJob["id"]);
    $assert(
        $failedState["status"] === "completed" &&
            $failedState["processed"] === 1 &&
            $failedState["failed"] === 1,
        "Attachment exception increments failed count without killing job",
    );
    $assert(
        (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " .
                    Schema::table("job_logs") .
                    " WHERE job_id=%d",
                $failedJob["id"],
            ),
        ) >= 3,
        "Job failure diagnostics persisted",
    );
    $registry->unregister("test-failing-job");
    $brokenJob = new class implements \VTX\Media\Jobs\JobInterface {
        public function id(): string
        {
            return "test-broken-job";
        }
        public function feature(): string
        {
            return FeatureRegistry::AUDIT;
        }
        public function snapshot(int $author): array
        {
            return ["total" => 1, "max_id" => 1];
        }
        public function next(array $state, int $limit): array
        {
            throw new RuntimeException("Test batch failure");
        }
        public function process(int $attachment, int $job): bool
        {
            return true;
        }
    };
    $registry->register($brokenJob);
    $failedJob = $runner->start("test-broken-job", 1);
    $jobs[] = $failedJob["id"];
    $failedState = $runner->tick($failedJob["id"]);
    $assert(
        $failedState["status"] === "failed",
        "Batch-level exception produces recoverable terminal failure",
    );
    $registry->unregister("test-broken-job");
    $savedMeta = wp_get_attachment_metadata($id);
    $testMeta = $savedMeta;
    $testMeta["sizes"] = [
        "thumbnail" => [
            "file" => "missing-optional-" . wp_generate_uuid4() . ".png",
        ],
    ];
    wp_update_attachment_metadata($id, $testMeta);
    $assert(
        (new Context($id, $settings->get()))->file["state"] === "available",
        "Missing optional thumbnail does not mark original broken",
    );
    $testMeta["original_image"] =
        "missing-original-" . wp_generate_uuid4() . ".png";
    wp_update_attachment_metadata($id, $testMeta);
    $assert(
        (new Context($id, $settings->get()))->file["state"] ===
            "missing-original",
        "Missing physical original detected separately",
    );
    wp_update_attachment_metadata($id, $savedMeta);
    $freshContext = new Context($id, $settings->get());
    $freshContext->metadata["width"] = 2;
    $assert(
        $rule("invalid-image-metadata", $freshContext) !== null,
        "Readable file header mismatch detected",
    );
    $invalid = false;
    try {
        $modules->register("bad-module", "test-extension", [
            "label" => "Bad",
            "icon" => "test",
            "javascript" => "alert(1)",
        ]);
    } catch (InvalidArgumentException $e) {
        $invalid = true;
    }
    $assert($invalid, "Executable manifest values rejected");
    $versionRule = new class implements \VTX\Media\Audit\AuditRuleInterface {
        public $version = "1";
        public function definition(): array
        {
            return [
                "id" => "version-test-rule",
                "version" => $this->version,
                "category" => "metadata",
                "severity" => "low",
                "types" => ["*"],
                "feature" => FeatureRegistry::AUDIT,
            ];
        }
        public function evaluate(Context $c): ?array
        {
            return ["data" => ["constant" => true], "confidence" => 1];
        }
        public function present(array $data): array
        {
            return [
                "title" => "Test version",
                "explanation" => "",
                "recommendation" => "",
                "remediation" => [],
            ];
        }
    };
    $rules->register($versionRule);
    $versionResult = $engine->audit($id);
    $versionFinding = array_values(
        array_filter($versionResult["findings"], static function ($f) {
            return $f["rule_id"] === "version-test-rule";
        }),
    )[0];
    $engine->status($versionFinding["id"], "ignored");
    $versionRule->version = "2";
    $assert(
        $engine->detail($id)["state"] === "stale",
        "Rule version change marks attachment stale",
    );
    $versionResult = $engine->audit($id);
    $versionFinding = array_values(
        array_filter($versionResult["findings"], static function ($f) {
            return $f["rule_id"] === "version-test-rule";
        }),
    )[0];
    $assert(
        $versionFinding["rule_version"] === "2" &&
            $versionFinding["status"] === "open",
        "New rule version reopens old ignored evidence",
    );
    $rules->unregister("version-test-rule");
    $engine->audit($id);
    $raceSource = new class implements \VTX\Media\Media\FileSourceInterface {
        public function inspect(int $id): array
        {
            update_post_meta(
                $id,
                "_wp_attachment_image_alt",
                "Changed during canonical snapshot",
            );
            return (new \VTX\Media\Media\LocalSource())->inspect($id);
        }
    };
    $raceEngine = new AuditEngine($rules, $settings, $logger, $raceSource);
    $raced = $raceEngine->audit($id);
    $assert(
        $raced["state"] === "pending" && $raced["score"] === null,
        "Metadata change during canonical snapshot cannot be marked current",
    );
    $assert(
        $engine->audit($id)["state"] === "complete",
        "Targeted retry refreshes concurrently changed attachment",
    );
    // Dedicated author limits scan fixtures to test attachments.
    $uid = wp_insert_user([
        "user_login" => "vtx-audit-" . wp_generate_password(9, false),
        "user_pass" => wp_generate_password(32),
        "role" => "author",
    ]);
    $users[] = $uid;
    foreach (range(1, 4) as $n) {
        $aid = wp_insert_attachment([
            "post_title" => "Job fixture",
            "post_mime_type" => "application/pdf",
            "post_status" => "inherit",
            "post_author" => $uid,
        ]);
        $ids[] = $aid;
        update_post_meta(
            $aid,
            "_wp_attached_file",
            "vtx-audit-missing-" . $aid . ".pdf",
        );
    }
    wp_set_current_user($uid);
    $job = $runner->start("media-audit", 1);
    $jobs[] = $job["id"];
    $duplicate = $runner->start("media-audit", 1);
    $assert($job["id"] === $duplicate["id"], "Duplicate active scan prevented");
    $one = $runner->tick($job["id"]);
    $assert(
        $one["processed"] === 1 && $one["total"] === 4,
        "Batch progress persisted",
    );
    $runner->control($job["id"], "pause");
    $paused = $runner->tick($job["id"]);
    $assert(
        $paused["status"] === "paused" && $paused["processed"] === 1,
        "Pause stops future processing",
    );
    $runner->control($job["id"], "resume");
    $wpdb->update(
        Schema::table("jobs"),
        [
            "lease_token" => "interrupted",
            "lease_until" => gmdate("Y-m-d H:i:s", time() - 200),
            "heartbeat" => gmdate("Y-m-d H:i:s", time() - 200),
        ],
        ["id" => $job["id"]],
    );
    $assert($runner->get($job["id"])["stalled"], "Stale heartbeat exposed");
    $recovered = $runner->tick($job["id"]);
    $assert($recovered["processed"] === 2, "Expired worker lease recovers");
    while ($runner->get($job["id"])["status"] === "running") {
        $runner->tick($job["id"]);
    }
    $assert(
        $runner->get($job["id"])["processed"] === 4 &&
            $runner->get($job["id"])["status"] === "completed",
        "Scan completes all scoped media",
    );
    $job = $runner->start("media-audit", 1);
    $jobs[] = $job["id"];
    $runner->control($job["id"], "cancel");
    $assert(
        $runner->tick($job["id"])["status"] === "cancelled",
        "Cancel prevents further batches",
    );
    $assert(
        $call("POST", "audit/settings", [
            "large_bytes" => 4194304,
        ])->get_status() === 403,
        "Author cannot change global settings",
    );
    $assert(
        $call("POST", "audit/actions", [
            "action" => "rescan",
            "ids" => [$id],
        ])->get_data()["failed"] === 1,
        "Author cannot mutate another owner media",
    );
    wp_set_current_user(0);
    $assert(
        $call("POST", "jobs", ["type" => "media-audit"])->get_status() >= 400,
        "Anonymous scan denied",
    );
    $assert(
        $call("GET", "audit/overview")->get_status() >= 400,
        "Anonymous findings denied",
    );
    wp_set_current_user($admin);
    foreach (
        [
            ["jobs", ["type" => "../optimizer"]],
            [
                "audit/actions",
                ["action" => "rescan", "ids" => ["../../wp-config.php"]],
            ],
            ["audit/actions", ["action" => "fix-everything", "ids" => [$id]]],
            ["audit/settings", ["large_bytes" => 1]],
            ["audit/settings", ["VTX_MEDIA_DEV_MODE" => true]],
            ["audit/settings", ["health" => 100]],
        ]
        as [$path, $body]
    ) {
        $assert(
            $call("POST", $path, $body)->get_status() >= 400,
            "Malformed or unauthorized input rejected: " .
                $path .
                " " .
                implode(",", array_keys($body)),
        );
    }
    $assert(
        $call("GET", "audit/findings", [
            "rule_id" => "nonexistent",
        ])->get_status() === 400,
        "Unknown rule rejected",
    );
    $assert(
        $call(
            "POST",
            "jobs",
            ["type" => "media-audit"],
            false,
        )->get_status() === 403,
        "Write requires nonce",
    );
    $page = $call("GET", "audit/findings", ["per_page" => 1])->get_data();
    $assert(
        count($page["items"]) <= 1 && isset($page["pagination"]),
        "Findings pagination bounded",
    );
    $engine->queue($id);
    $assert(
        $engine->detail($id)["state"] === "pending",
        "Native attachment inherit state queues pending audit",
    );
    $engine->audit($id);
    wp_delete_attachment($unknown, true);
    $assert(
        (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " .
                    Schema::table("health") .
                    " WHERE attachment_id=%d",
                $unknown,
            ),
        ) === 0,
        "Native deletion and metadata hooks do not recreate audit rows",
    );
    $pending = [];
    for ($n = 0; $n < 12; $n++) {
        $aid = wp_insert_attachment([
            "post_title" => "Incremental fixture",
            "post_mime_type" => "application/pdf",
            "post_status" => "inherit",
            "post_author" => $admin,
        ]);
        $ids[] = $aid;
        $pending[] = $aid;
        update_post_meta(
            $aid,
            "_wp_attached_file",
            "vtx-incremental-missing-" . $aid . ".pdf",
        );
    }
    $assert(
        $engine->detail($pending[11])["state"] === "pending",
        "Uploads beyond shutdown limit remain durably queued",
    );
    do_action(\VTX\Media\Jobs\JobRunner::HOOK);
    $assert(
        $engine->detail($pending[11])["state"] === "complete",
        "Cron dispatch processes the persistent incremental queue",
    );
    echo "Completed $checks audit checks.\n";
} finally {
    wp_set_current_user($admin);
    foreach ($ids as $id) {
        // Remove the deliberately unsafe test path even when an assertion fails.
        if (isset($unknown) && $id === $unknown) {
            delete_post_meta($id, "_wp_attached_file");
        }
        wp_delete_attachment($id, true);
        $wpdb->delete(Schema::table("job_logs"), ["attachment_id" => $id]);
    }
    require_once ABSPATH . "wp-admin/includes/user.php";
    foreach ($users as $id) {
        wp_delete_user($id);
    }
    foreach ($jobs as $id) {
        $wpdb->delete(Schema::table("jobs"), ["id" => $id]);
        $wpdb->delete(Schema::table("job_logs"), ["job_id" => $id]);
    }
    $oldSettings === null
        ? delete_option(Settings::OPTION)
        : update_option(Settings::OPTION, $oldSettings, false);
}
