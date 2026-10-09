<?php
namespace VTX\Media\Jobs;
use VTX\Media\Entitlements\FeatureEntitlementInterface;
defined("ABSPATH") || exit();
final class JobRegistry
{
    private $jobs = [];
    private $entitlements;
    public function __construct(FeatureEntitlementInterface $entitlements)
    {
        $this->entitlements = $entitlements;
    }
    public function register(JobInterface $job): void
    {
        if (
            !preg_match('/^[a-z][a-z0-9-]{1,63}$/D', $job->id()) ||
            isset($this->jobs[$job->id()])
        ) {
            throw new \InvalidArgumentException("Invalid job");
        }
        $this->jobs[$job->id()] = $job;
    }
    public function unregister(string $id): void
    {
        unset($this->jobs[$id]);
    }
    public function get(string $id): ?JobInterface
    {
        $job = $this->jobs[$id] ?? null;
        return $job && $this->entitlements->hasFeature($job->feature())
            ? $job
            : null;
    }
    public function all(): array
    {
        return $this->jobs;
    }
}
