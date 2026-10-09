<?php
/**
 * Plugin Name: VTX Media – Media Library Manager & Optimizer
 * Description: Organize your WordPress media with virtual folders, personal favorites and a complete media inspector.
 * Version: 1.5.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: VTX Labs
 * Text Domain: vtx-media
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 */
namespace VTX\Media;
defined("ABSPATH") || exit();
const VERSION = "1.5.0";
require_once __DIR__ . "/app/Support/Autoloader.php";
Support\Autoloader::register();
register_activation_hook(__FILE__, [Database\Schema::class, "activate"]);
register_deactivation_hook(__FILE__, [Plugin::class, "deactivate"]);
add_action("init", static function () {
    (new Plugin(__FILE__))->boot();
});
