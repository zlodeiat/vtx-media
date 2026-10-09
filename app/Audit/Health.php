<?php
namespace VTX\Media\Audit;
defined("ABSPATH") || exit();
final class Health
{
    public const WEIGHTS = [
        "critical" => 60,
        "high" => 20,
        "medium" => 10,
        "low" => 3,
        "info" => 1,
    ];
    public const CAPS = [
        "accessibility" => 30,
        "metadata" => 8,
        "files" => 80,
        "performance" => 25,
    ];
    public static function calculate(array $findings): array
    {
        $categories = [];
        $deductions = [];
        usort($findings, static function ($a, $b) {
            return self::WEIGHTS[$b["severity"]] <=>
                self::WEIGHTS[$a["severity"]] ?:
                strcmp($a["rule_id"], $b["rule_id"]);
        });
        foreach ($findings as $f) {
            if ($f["status"] !== "open") {
                continue;
            }
            $category = $f["category"];
            $used = $categories[$category] ?? 0;
            $points = min(
                self::WEIGHTS[$f["severity"]],
                max(0, (self::CAPS[$category] ?? 20) - $used),
            );
            $categories[$category] = $used + $points;
            $deductions[$f["rule_id"]] = $points;
        }
        return [
            "score" => max(0, 100 - array_sum($categories)),
            "deductions" => $deductions,
            "categories" => $categories,
        ];
    }
}
