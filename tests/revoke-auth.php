<?php
if (!defined("WP_CLI") || !WP_CLI) {
    exit();
}
$file = __DIR__ . "/artifacts/auth.json";
if (is_file($file)) {
    $session = json_decode(file_get_contents($file), true);
    WP_Session_Tokens::get_instance($session["user"])->destroy(
        $session["token"],
    );
    unlink($file);
    echo "Browser test session revoked.\n";
}
