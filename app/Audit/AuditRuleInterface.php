<?php
namespace VTX\Media\Audit;
defined("ABSPATH") || exit();
interface AuditRuleInterface
{
    /** id, version, category, severity, types, feature; all machine-readable. */
    public function definition(): array;
    /** Null means no finding; payload contains only evidence and optional severity/confidence/kind overrides. */
    public function evaluate(Context $context): ?array;
    /** Localized title, explanation, recommendation and remediation IDs. Never parsed by the engine. */
    public function present(array $data): array;
}
