<?php
if (!defined("WP_CLI") || !WP_CLI) {
    exit();
}
use VTX\Media\Entitlements\{FeatureRegistry, EntitlementService};
$features = new FeatureRegistry();
$features->register("free-fixture", true);
$features->register("paid-fixture");
$ent = new EntitlementService($features);
if (!$ent->hasFeature("free-fixture") || $ent->hasFeature("not-registered")) {
    throw new RuntimeException("Feature registry boundary failed");
}
$expected = defined("VTX_MEDIA_DEV_MODE") && VTX_MEDIA_DEV_MODE === true;
if ($ent->hasFeature("paid-fixture") !== $expected) {
    throw new RuntimeException("Developer entitlement failed");
}
echo "PASS: developer mode " .
    ($expected ? "enabled" : "disabled") .
    "; registered paid feature " .
    ($expected ? "available" : "denied") .
    "; unknown feature denied; Core available." .
    PHP_EOL;
