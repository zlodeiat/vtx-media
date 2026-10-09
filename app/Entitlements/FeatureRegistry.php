<?php
namespace VTX\Media\Entitlements;
defined("ABSPATH") || exit();
final class FeatureRegistry
{
    public const LIBRARY = "media-library";
    public const AUDIT = "basic-audit";
    private $features = [];
    public function register(string $id, bool $free = false): void
    {
        if (
            !preg_match('/^[a-z][a-z0-9-]{1,63}$/D', $id) ||
            isset($this->features[$id])
        ) {
            throw new \InvalidArgumentException(
                "Invalid or duplicate feature ID",
            );
        }
        $this->features[$id] = ["id" => $id, "free" => $free];
    }
    public function unregister(string $id): void
    {
        unset($this->features[$id]);
    }
    public function get(string $id): ?array
    {
        return $this->features[$id] ?? null;
    }
    public function all(): array
    {
        return array_values($this->features);
    }
}
