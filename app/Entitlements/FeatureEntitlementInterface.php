<?php
namespace VTX\Media\Entitlements;
defined("ABSPATH") || exit();
interface FeatureEntitlementInterface
{
    public function hasFeature(string $feature): bool;
}
