<?php
namespace VTX\Media\Audit;
use VTX\Media\Database\{Schema, Lock};
use VTX\Media\Jobs\Logger;
defined("ABSPATH") || exit();
final class AuditEngine
{
    private $prepared = [];
    private $source;
    private $rules;
    private $settings;
    private $logger;
    public function __construct(
        AuditRuleRegistry $rules,
        Settings $settings,
        Logger $logger,
        ?\VTX\Media\Media\FileSourceInterface $source = null
    ) {
        $this->source = $source ?: new \VTX\Media\Media\LocalSource();
        $this->rules = $rules;
        $this->settings = $settings;
        $this->logger = $logger;
    }
    public function signature(): string
    {
        return $this->rules->signature($this->settings->get());
    }
    public function queue(int $id): void
    {
        $post = get_post($id);
        if (
            !$post ||
            $post->post_type !== "attachment" ||
            $post->post_status !== "inherit"
        ) {
            return;
        }
        global $wpdb;
        $h = Schema::table("health");
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO $h (attachment_id,state,revision,errors) VALUES (%d,'pending',1,'[]') ON DUPLICATE KEY UPDATE state='pending',revision=revision+1",
                $id,
            ),
        );
    }
    /** Capture invalidation revisions before reading a bounded canonical snapshot. */
    public function prepareBatch(array $ids): void
    {
        global $wpdb;
        $ids = array_values(array_unique(array_map("intval", $ids)));
        if (!$ids) {
            return;
        }
        if (count($ids) > 100) {
            throw new \InvalidArgumentException("Audit batch too large");
        }
        $marks = implode(",", array_fill(0, count($ids), "%d"));
        $h = Schema::table("health");
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT attachment_id,revision FROM $h WHERE attachment_id IN ($marks)",
                ...$ids,
            ),
            ARRAY_A,
        );
        $revisions = array_column($rows, "revision", "attachment_id");
        $this->prepared = [];
        foreach ($ids as $id) {
            $this->prepared[$id] = (int) ($revisions[$id] ?? 0);
            wp_cache_delete($id, "posts");
            wp_cache_delete($id, "post_meta");
        }
        _prime_post_caches($ids, false, true);
    }
    public function audit(int $id, int $job = 0)
    {
        return Lock::run("audit-" . $id, function () use ($id, $job) {
            global $wpdb;
            $h = Schema::table("health");
            $f = Schema::table("findings");
            if (!array_key_exists($id, $this->prepared)) {
                $this->prepareBatch([$id]);
            }
            $revision = $this->prepared[$id];
            unset($this->prepared[$id]);
            $settings = $this->settings->get();
            $signature = $this->rules->signature($settings);
            try {
                $c = new Context($id, $settings, $this->source);
            } catch (\Throwable $error) {
                $post = get_post($id);
                if (
                    $post &&
                    $post->post_type === "attachment" &&
                    $post->post_status === "inherit"
                ) {
                    $wpdb->query(
                        $wpdb->prepare(
                            "INSERT IGNORE INTO $h (attachment_id,errors) VALUES (%d,'[]')",
                            $id,
                        ),
                    );
                    $wpdb->update(
                        $h,
                        [
                            "state" => "failed",
                            "score" => null,
                            "signature" => $signature,
                            "errors" => '["context-failure"]',
                            "audited_at" => current_time("mysql", true),
                        ],
                        ["attachment_id" => $id, "revision" => $revision],
                    );
                }
                $this->logger->event("attachment-context-failure", $job, $id);
                return new \WP_Error(
                    "vtx_invalid_attachment",
                    __("Attachment cannot be analyzed.", "vtx-media"),
                    ["status" => 404],
                );
            }
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO $h (attachment_id,errors) VALUES (%d,'[]')",
                    $id,
                ),
            );
            $evaluated = [];
            $errors = [];
            $active = $this->rules->active();
            foreach ($active as $ruleId => $rule) {
                try {
                    $d = $rule->definition();
                    $result = $c->supports($d["types"])
                        ? $rule->evaluate($c)
                        : null;
                    if ($result !== null) {
                        if (
                            !isset($result["data"]) ||
                            !is_array($result["data"]) ||
                            strlen(wp_json_encode($result["data"])) > 12000
                        ) {
                            throw new \UnexpectedValueException(
                                "Invalid rule result",
                            );
                        }
                        $severity = $result["severity"] ?? $d["severity"];
                        if (!isset(Health::WEIGHTS[$severity])) {
                            throw new \UnexpectedValueException(
                                "Invalid severity",
                            );
                        }
                        $confidence = $result["confidence"] ?? 1;
                        if (
                            !is_numeric($confidence) ||
                            $confidence < 0 ||
                            $confidence > 1
                        ) {
                            throw new \UnexpectedValueException(
                                "Invalid confidence",
                            );
                        }
                        $result += [
                            "severity" => $severity,
                            "confidence" => $confidence,
                            "kind" => "objective",
                        ];
                    }
                    $evaluated[$ruleId] = [$d, $result];
                } catch (\Throwable $error) {
                    $errors[] = $ruleId;
                    $this->logger->event("rule-exception", $job, $id, $ruleId);
                }
            }
            if ($c->file["state"] === "unknown") {
                $errors[] = "source-unverified";
            }
            $now = current_time("mysql", true);
            $wpdb->query("START TRANSACTION");
            try {
                $old = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $f WHERE attachment_id=%d",
                        $id,
                    ),
                    OBJECT_K,
                );
                $byRule = [];
                foreach ($old as $row) {
                    $byRule[$row->rule_id] = $row;
                }
                foreach ($evaluated as $ruleId => [$d, $result]) {
                    if ($result === null) {
                        $this->checked(
                            $wpdb->query(
                                $wpdb->prepare(
                                    "UPDATE $f SET status='resolved',audited_at=%s,updated_at=%s WHERE attachment_id=%d AND rule_id=%s",
                                    $now,
                                    $now,
                                    $id,
                                    $ruleId,
                                ),
                            ),
                        );
                        continue;
                    }
                    $json = wp_json_encode($result["data"]);
                    $fingerprint = hash("sha256", $d["version"] . "|" . $json);
                    $prior = $byRule[$ruleId] ?? null;
                    $status =
                        $prior &&
                        $prior->status === "ignored" &&
                        hash_equals($prior->fingerprint, $fingerprint)
                            ? "ignored"
                            : "open";
                    $row = [
                        "attachment_id" => $id,
                        "rule_id" => $ruleId,
                        "rule_version" => $d["version"],
                        "category" => $d["category"],
                        "severity" => $result["severity"],
                        "severity_rank" => Health::WEIGHTS[$result["severity"]],
                        "confidence" => $result["confidence"],
                        "kind" =>
                            $result["kind"] === "heuristic"
                                ? "heuristic"
                                : "objective",
                        "fingerprint" => $fingerprint,
                        "data" => $json,
                        "status" => $status,
                        "audited_at" => $now,
                        "updated_at" => $now,
                    ];
                    $this->checked(
                        $prior
                            ? $wpdb->update($f, $row, ["id" => $prior->id])
                            : $wpdb->insert($f, $row),
                    );
                }
                foreach ($byRule as $ruleId => $prior) {
                    if (!isset($active[$ruleId])) {
                        $this->checked(
                            $wpdb->update(
                                $f,
                                ["status" => "resolved", "updated_at" => $now],
                                ["id" => $prior->id],
                            ),
                        );
                    }
                }
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $f WHERE attachment_id=%d",
                        $id,
                    ),
                    ARRAY_A,
                );
                $score = Health::calculate($rows)["score"];
                $this->checked(
                    $wpdb->update(
                        $h,
                        [
                            "filename" => $c->filename,
                            "state" => $errors ? "failed" : "complete",
                            "score" => $errors ? null : $score,
                            "signature" => $signature,
                            "errors" => wp_json_encode($errors),
                            "audited_at" => $now,
                        ],
                        ["attachment_id" => $id, "revision" => $revision],
                    ),
                );
                $this->checked($wpdb->query("COMMIT"));
            } catch (\Throwable $error) {
                $wpdb->query("ROLLBACK");
                $wpdb->update(
                    $h,
                    [
                        "state" => "failed",
                        "score" => null,
                        "errors" => '["persistence-failure"]',
                    ],
                    ["attachment_id" => $id],
                );
                $this->logger->event("persistence-failure", $job, $id);
                return new \WP_Error(
                    "vtx_audit_failed",
                    __("Audit could not be saved.", "vtx-media"),
                    ["status" => 500],
                );
            }
            do_action("vtx_media/audited", $id, empty($errors));
            return $this->detail($id);
        });
    }
    private function checked($result): void
    {
        if ($result === false) {
            throw new \RuntimeException("Database write failed");
        }
    }
    public function detail(int $id): array
    {
        global $wpdb;
        $h = Schema::table("health");
        $f = Schema::table("findings");
        $health = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $h WHERE attachment_id=%d", $id),
            ARRAY_A,
        );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $f WHERE attachment_id=%d ORDER BY severity_rank DESC,id",
                $id,
            ),
            ARRAY_A,
        );
        $calc = Health::calculate($rows);
        $state = $health
            ? ($health["signature"] !== $this->signature() &&
            $health["state"] !== "pending"
                ? "stale"
                : $health["state"])
            : "not-analyzed";
        return [
            "attachment_id" => $id,
            "state" => $state,
            "score" => $state === "complete" ? (int) $health["score"] : null,
            "audited_at" => $health["audited_at"] ?? null,
            "decorative" => (bool) get_post_meta(
                $id,
                "_vtx_media_decorative",
                true,
            ),
            "errors" => $health ? json_decode($health["errors"], true) : [],
            "deductions" => $calc["deductions"],
            "findings" => array_map([$this, "present"], $rows),
        ];
    }
    public function present(array $row): array
    {
        $rule = $this->rules->get($row["rule_id"]);
        $data = json_decode($row["data"], true) ?: [];
        $text = $rule
            ? $this->rules->presentation($rule, $data)
            : [
                "title" => __("Retired rule", "vtx-media"),
                "explanation" => __(
                    "The extension providing this rule is unavailable.",
                    "vtx-media",
                ),
                "recommendation" => "",
                "remediation" => [],
            ];
        return array_merge($row, $text, [
            "id" => (int) $row["id"],
            "attachment_id" => (int) $row["attachment_id"],
            "confidence" => (float) $row["confidence"],
            "data" => $data,
            "stale" =>
                !$rule ||
                $rule->definition()["version"] !== $row["rule_version"],
        ]);
    }
    public function status(int $findingId, string $status)
    {
        if (!in_array($status, ["open", "ignored"], true)) {
            return new \WP_Error(
                "vtx_status",
                __("Invalid finding status.", "vtx-media"),
                ["status" => 400],
            );
        }
        global $wpdb;
        $f = Schema::table("findings");
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $f WHERE id=%d", $findingId),
            ARRAY_A,
        );
        if (!$row) {
            return new \WP_Error(
                "vtx_finding_missing",
                __("Finding not found.", "vtx-media"),
                ["status" => 404],
            );
        }
        return Lock::run("audit-" . $row["attachment_id"], function () use (
            $findingId,
            $status,
            $row,
            $f
        ) {
            global $wpdb;
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $f WHERE id=%d", $findingId),
                ARRAY_A,
            );
            if (!$row) {
                return new \WP_Error(
                    "vtx_finding_missing",
                    __("Finding not found.", "vtx-media"),
                    ["status" => 404],
                );
            }
            if ($row["status"] === "resolved") {
                return new \WP_Error(
                    "vtx_resolved",
                    __(
                        "This finding has already resolved. Re-scan to refresh it.",
                        "vtx-media",
                    ),
                    ["status" => 409],
                );
            }
            $wpdb->query("START TRANSACTION");
            try {
                $this->checked(
                    $wpdb->update(
                        $f,
                        [
                            "status" => $status,
                            "updated_at" => current_time("mysql", true),
                        ],
                        ["id" => $findingId],
                    ),
                );
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $f WHERE attachment_id=%d",
                        $row["attachment_id"],
                    ),
                    ARRAY_A,
                );
                $this->checked(
                    $wpdb->update(
                        Schema::table("health"),
                        ["score" => Health::calculate($rows)["score"]],
                        [
                            "attachment_id" => $row["attachment_id"],
                            "state" => "complete",
                        ],
                    ),
                );
                $this->checked($wpdb->query("COMMIT"));
            } catch (\Throwable $error) {
                $wpdb->query("ROLLBACK");
                return new \WP_Error(
                    "vtx_status_failed",
                    __("Finding status could not be saved.", "vtx-media"),
                    ["status" => 500],
                );
            }
            return ["id" => $findingId, "status" => $status];
        });
    }
}
