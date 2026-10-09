<?php
namespace VTX\Media\Jobs;
defined("ABSPATH") || exit();
interface JobInterface
{
    public function id(): string;
    public function feature(): string;
    public function snapshot(int $author): array;
    public function next(array $state, int $limit): array;
    public function process(int $attachment, int $job): bool;
}
