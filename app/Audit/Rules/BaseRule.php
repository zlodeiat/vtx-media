<?php
namespace VTX\Media\Audit\Rules;
use VTX\Media\Audit\AuditRuleInterface;
use VTX\Media\Entitlements\FeatureRegistry;
defined("ABSPATH") || exit();
abstract class BaseRule implements AuditRuleInterface
{
    protected $id;
    protected $category = "metadata";
    protected $severity = "info";
    protected $types = ["*"];
    public function definition(): array
    {
        return [
            "id" => $this->id,
            "version" => "1",
            "category" => $this->category,
            "severity" => $this->severity,
            "types" => $this->types,
            "feature" => FeatureRegistry::AUDIT,
        ];
    }
    protected function finding(
        array $data,
        string $severity = "",
        float $confidence = 1,
        string $kind = "objective"
    ): array {
        return [
            "data" => $data,
            "severity" => $severity ?: $this->severity,
            "confidence" => $confidence,
            "kind" => $kind,
        ];
    }
    protected function text(
        string $title,
        string $explanation,
        string $recommendation,
        array $remediation = []
    ): array {
        return compact("title", "explanation", "recommendation", "remediation");
    }
}
