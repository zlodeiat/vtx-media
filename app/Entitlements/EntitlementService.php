<?php
namespace VTX\Media\Entitlements;
defined("ABSPATH") || exit();
final class EntitlementService implements FeatureEntitlementInterface
{
    private $registry;
    private $sources = [];
    public function __construct(FeatureRegistry $registry)
    {
        $this->registry = $registry;
    }
    public function development(): bool
    {
        return defined("VTX_MEDIA_DEV_MODE") && VTX_MEDIA_DEV_MODE === true;
    }
    public function addSource(
        string $id,
        FeatureEntitlementInterface $source
    ): void {
        $this->sources[$id] = $source;
    }
    public function removeSource(string $id): void
    {
        unset($this->sources[$id]);
    }
    public function hasFeature(string $feature): bool
    {
        $definition = $this->registry->get($feature);
        if (!$definition) {
            return false;
        }
        if ($definition["free"] || $this->development()) {
            return true;
        }
        foreach ($this->sources as $source) {
            try {
                if ($source->hasFeature($feature)) {
                    return true;
                }
            } catch (\Throwable $e) {
                /* Entitlement failures deny access. */
            }
        }
        return false;
    }
}
