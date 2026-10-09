<?php
namespace VTX\Media\Jobs;
defined("ABSPATH") || exit();
/** Additive contract for indexed source work. IDs are handler-owned positive cursors. */
interface WorkJobInterface extends JobInterface
{
    public function authorize(): bool;
    public function prepare(array $ids): void;
}
