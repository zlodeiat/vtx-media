<?php
namespace VTX\Media\Audit;
use VTX\Media\Entitlements\FeatureEntitlementInterface;
defined("ABSPATH") || exit();
final class AuditRuleRegistry
{
    private $rules = [];
    private $entitlements;
    public function __construct(FeatureEntitlementInterface $entitlements)
    {
        $this->entitlements = $entitlements;
    }
    public function register(AuditRuleInterface $rule): void
    {
        $d = $rule->definition();
        foreach (
            ["id", "version", "category", "severity", "feature", "types"]
            as $key
        ) {
            if (!isset($d[$key])) {
                throw new \InvalidArgumentException(
                    "Incomplete rule definition",
                );
            }
        }
        if (
            !preg_match('/^[a-z][a-z0-9-]{1,63}$/D', $d["id"]) ||
            isset($this->rules[$d["id"]]) ||
            !preg_match('/^[a-z][a-z0-9-]{1,39}$/D', $d["category"]) ||
            !in_array($d["severity"], array_keys(Health::WEIGHTS), true) ||
            !is_array($d["types"]) ||
            !preg_match('/^[a-zA-Z0-9._-]{1,32}$/D', (string) $d["version"])
        ) {
            throw new \InvalidArgumentException("Invalid or duplicate rule");
        }
        if (!is_string($d["feature"]) || !$d["types"]) {
            throw new \InvalidArgumentException(
                "Invalid rule feature or MIME types",
            );
        }
        foreach ($d["types"] as $type) {
            if (
                !is_string($type) ||
                ($type !== "*" &&
                    !preg_match('#^[a-z0-9.+-]+(?:/[a-z0-9.+-]+)?$#D', $type))
            ) {
                throw new \InvalidArgumentException("Invalid rule MIME type");
            }
        }
        $this->rules[$d["id"]] = $rule;
    }
    public function unregister(string $id): void
    {
        unset($this->rules[$id]);
    }
    public function get(string $id): ?AuditRuleInterface
    {
        return $this->rules[$id] ?? null;
    }
    public function all(): array
    {
        return $this->rules;
    }
    public function active(): array
    {
        return array_filter($this->rules, function ($r) {
            return $this->entitlements->hasFeature($r->definition()["feature"]);
        });
    }
    public function signature(array $settings): string
    {
        $versions = [];
        foreach ($this->active() as $id => $r) {
            $versions[$id] = $r->definition();
        }
        ksort($versions);
        return hash("sha256", wp_json_encode([$versions, $settings]));
    }
    public function manifest(): array
    {
        $out = [];
        foreach ($this->active() as $r) {
            $out[] = array_merge($r->definition(), $this->presentation($r, []));
        }
        return $out;
    }
    public function presentation(AuditRuleInterface $rule, array $data): array
    {
        try {
            $text = $rule->present($data);
            foreach (["title", "explanation", "recommendation"] as $key) {
                if (!isset($text[$key]) || !is_string($text[$key])) {
                    throw new \UnexpectedValueException("Invalid presentation");
                }
            }
            if (
                !isset($text["remediation"]) ||
                !is_array($text["remediation"])
            ) {
                throw new \UnexpectedValueException("Invalid remediation");
            }
            foreach ($text["remediation"] as $action) {
                if (
                    !is_string($action) ||
                    !preg_match('/^[a-z][a-z0-9-]{1,63}$/D', $action)
                ) {
                    throw new \UnexpectedValueException(
                        "Invalid remediation ID",
                    );
                }
            }
            return array_intersect_key(
                $text,
                array_flip([
                    "title",
                    "explanation",
                    "recommendation",
                    "remediation",
                ]),
            );
        } catch (\Throwable $error) {
            return [
                "title" => __("Rule presentation unavailable", "vtx-media"),
                "explanation" => __(
                    "An extension could not present this finding. Its evidence remains available.",
                    "vtx-media",
                ),
                "recommendation" => __(
                    "Review the extension providing this rule.",
                    "vtx-media",
                ),
                "remediation" => [],
            ];
        }
    }
}
