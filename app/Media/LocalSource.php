<?php
namespace VTX\Media\Media;
defined("ABSPATH") || exit();
/** Resolves only local files contained in the configured uploads directory. */
final class LocalSource implements FileSourceInterface
{
    public function inspect(int $id): array
    {
        $uploads = wp_get_upload_dir();
        $base = realpath($uploads["basedir"]);
        $path = get_attached_file($id, true);
        $unknown = [
            "state" => "unknown",
            "bytes" => null,
            "dimensions" => null,
        ];
        if (
            !$base ||
            !is_string($path) ||
            !$path ||
            strpos($path, "://") !== false
        ) {
            return $unknown;
        }
        $base = trailingslashit(wp_normalize_path($base));
        $normalized = wp_normalize_path($path);
        $configured = trailingslashit(wp_normalize_path($uploads["basedir"]));
        if (strpos($normalized, $configured) === 0) {
            $normalized = $base . substr($normalized, strlen($configured));
        }
        if (
            strpos($normalized, $base) !== 0 ||
            preg_match('#(^|/)\.\.(/|$)#', $normalized)
        ) {
            return $unknown;
        }
        $ancestor = $path;
        while (!file_exists($ancestor) && dirname($ancestor) !== $ancestor) {
            $ancestor = dirname($ancestor);
        }
        $real = realpath($ancestor);
        if (
            !$real ||
            (trailingslashit(wp_normalize_path($real)) !== $base &&
                strpos(wp_normalize_path($real), $base) !== 0)
        ) {
            return $unknown;
        }
        if (!is_file($path)) {
            $host = wp_parse_url(wp_get_attachment_url($id), PHP_URL_HOST);
            if (
                $host &&
                $host !== wp_parse_url($uploads["baseurl"], PHP_URL_HOST)
            ) {
                return $unknown;
            }
            return [
                "state" => "missing",
                "bytes" => null,
                "dimensions" => null,
            ];
        }
        if (!is_readable($path)) {
            return $unknown;
        }
        $meta = wp_get_attachment_metadata($id);
        $originalMissing = false;
        if (is_array($meta) && !empty($meta["original_image"])) {
            $name = $meta["original_image"];
            if (
                !is_string($name) ||
                wp_basename($name) !== $name ||
                strpos($name, "\\") !== false
            ) {
                return $unknown;
            }
            $original = dirname($path) . "/" . $name;
            $resolved = realpath($original);
            if (
                $resolved &&
                strpos(wp_normalize_path($resolved), $base) !== 0
            ) {
                return $unknown;
            }
            $originalMissing = !is_file($original);
        }
        $dimensions = null;
        if (
            strpos((string) get_post_mime_type($id), "image/") === 0 &&
            get_post_mime_type($id) !== "image/svg+xml"
        ) {
            $size = wp_getimagesize($path);
            if ($size) {
                $dimensions = [
                    "width" => (int) $size[0],
                    "height" => (int) $size[1],
                ];
            }
        }
        return [
            "state" => $originalMissing ? "missing-original" : "available",
            "bytes" => wp_filesize($path),
            "dimensions" => $dimensions,
        ];
    }
}
