<?php
namespace VTX\Media\Support;
defined("ABSPATH") || exit();
final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(static function ($class) {
            $prefix = "VTX\\Media\\";
            if (strpos($class, $prefix) !== 0) {
                return;
            }
            $file =
                dirname(__DIR__) .
                "/" .
                str_replace("\\", "/", substr($class, strlen($prefix))) .
                ".php";
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
