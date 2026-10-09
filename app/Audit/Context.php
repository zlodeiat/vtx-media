<?php
namespace VTX\Media\Audit;
use VTX\Media\Media\LocalSource;
use VTX\Media\Media\FileSourceInterface;
defined("ABSPATH") || exit();
final class Context
{
    public $id;
    public $post;
    public $mime;
    public $filename;
    public $alt;
    public $decorative;
    public $metadata;
    public $file;
    public $settings;
    public function __construct(
        int $id,
        array $settings,
        ?FileSourceInterface $source = null
    ) {
        $this->post = get_post($id);
        if (
            !$this->post ||
            $this->post->post_type !== "attachment" ||
            $this->post->post_status !== "inherit"
        ) {
            throw new \InvalidArgumentException("Invalid attachment");
        }
        $this->id = $id;
        $this->settings = $settings;
        $this->mime = $this->post->post_mime_type;
        $this->filename = wp_basename(
            (string) get_post_meta($id, "_wp_attached_file", true),
        );
        $this->alt = trim(
            (string) get_post_meta($id, "_wp_attachment_image_alt", true),
        );
        $this->decorative =
            (bool) get_post_meta($id, "_vtx_media_decorative", true) &&
            $this->alt === "";
        $this->metadata = wp_get_attachment_metadata($id);
        $this->file = ($source ?: new LocalSource())->inspect($id);
    }
    public function supports(array $types): bool
    {
        foreach ($types as $type) {
            if (
                $type === "*" ||
                $type === $this->mime ||
                $type === strtok($this->mime, "/")
            ) {
                return true;
            }
        }
        return false;
    }
    public static function normalize(string $value): string
    {
        $value = preg_replace(
            '/\.(jpe?g|png|gif|webp|avif|svg|bmp|tiff?|pdf)$/i',
            "",
            trim($value),
        );
        return strtolower(preg_replace("/[\s_-]+/u", " ", $value));
    }
    public static function poorName(string $value): bool
    {
        $stem = pathinfo($value, PATHINFO_FILENAME);
        return (bool) preg_match(
            '/^(?:(?:img|dsc|dscn|pxl)[_-]?\d[\w-]*|screenshot(?:[ _-].*)?|(?:download|image|untitled)(?:[-_ ]\d+)?|[a-f0-9]{24,}|[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$/i',
            $stem,
        );
    }
}
