<?php
namespace VTX\Media\Tests;
defined('WP_CLI') && WP_CLI || exit;
use VTX\Media\Audit\{AuditRuleInterface,Context};
final class AuditExtension implements AuditRuleInterface {
    public function definition(): array { return ['id'=>'test-extension-rule','version'=>'1','category'=>'metadata','severity'=>'low','types'=>['*'],'feature'=>'test-extension']; }
    public function evaluate(Context $context): ?array { return ['data'=>['fixture'=>true],'confidence'=>1]; }
    public function present(array $data): array { return ['title'=>'Test-only rule','explanation'=>'Fixture','recommendation'=>'Test only','remediation'=>[]]; }
}
