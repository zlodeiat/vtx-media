<?php
namespace VTX\Media\Media;
defined("ABSPATH") || exit();
interface FileSourceInterface
{
    /** Return state, nullable bytes and nullable width/height; never public absolute paths. */
    public function inspect(int $id): array;
}
