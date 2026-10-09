<?php
namespace VTX\Media\Audit;
defined("ABSPATH") || exit();
final class Settings
{
    public const OPTION = "vtx_media_audit_settings";
    public const DEFAULTS = [
        "large_bytes" => 3145728,
        "very_large_bytes" => 10485760,
        "max_width" => 6000,
        "max_height" => 4000,
        "alt_length" => 250,
    ];
    public function get(): array
    {
        return array_merge(
            self::DEFAULTS,
            array_intersect_key(
                (array) get_option(self::OPTION, []),
                self::DEFAULTS,
            ),
        );
    }
    public function save(array $data)
    {
        if (array_diff(array_keys($data), array_keys(self::DEFAULTS))) {
            return new \WP_Error(
                "vtx_settings",
                __("Unknown audit setting.", "vtx-media"),
                ["status" => 400],
            );
        }
        $new = array_merge($this->get(), $data);
        $ranges = [
            "large_bytes" => [262144, 104857600],
            "very_large_bytes" => [524288, 524288000],
            "max_width" => [1000, 50000],
            "max_height" => [1000, 50000],
            "alt_length" => [100, 2000],
        ];
        foreach ($ranges as $key => $range) {
            if (
                !is_int($new[$key]) ||
                $new[$key] < $range[0] ||
                $new[$key] > $range[1]
            ) {
                return new \WP_Error(
                    "vtx_settings",
                    __(
                        "Audit thresholds are outside the supported range.",
                        "vtx-media",
                    ),
                    ["status" => 400],
                );
            }
        }
        if ($new["very_large_bytes"] <= $new["large_bytes"]) {
            return new \WP_Error(
                "vtx_settings",
                __(
                    "Very large must exceed the large file threshold.",
                    "vtx-media",
                ),
                ["status" => 400],
            );
        }
        update_option(self::OPTION, $new, false);
        if ($this->get() !== $new) {
            return new \WP_Error(
                "vtx_settings_save",
                __("Settings could not be saved.", "vtx-media"),
                ["status" => 500],
            );
        }
        return $new;
    }
}
