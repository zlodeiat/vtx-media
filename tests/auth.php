<?php
// CLI-only short-lived browser test session. Never changes an existing password.
if (!defined("WP_CLI") || !WP_CLI) {
    exit();
}
$oldFile = __DIR__ . "/artifacts/auth.json";
if (is_file($oldFile)) {
    $old = json_decode(file_get_contents($oldFile), true);
    if (isset($old["user"], $old["token"])) {
        WP_Session_Tokens::get_instance($old["user"])->destroy($old["token"]);
    }
}
$user = get_users(["role" => "administrator", "number" => 1])[0];
$expires = time() + 7200;
$token = WP_Session_Tokens::get_instance($user->ID)->create($expires);
$url = wp_parse_url(home_url());
$cookies = [];
foreach (
    [
        LOGGED_IN_COOKIE => "logged_in",
        SECURE_AUTH_COOKIE => "secure_auth",
        AUTH_COOKIE => "auth",
    ]
    as $name => $scheme
) {
    $cookies[] = [
        "name" => $name,
        "value" => wp_generate_auth_cookie(
            $user->ID,
            $expires,
            $scheme,
            $token,
        ),
        "domain" => $url["host"],
        "path" => "/",
        "expires" => $expires,
        "httpOnly" => true,
        "secure" => $url["scheme"] === "https",
        "sameSite" => "Lax",
    ];
}
$file = __DIR__ . "/artifacts/auth.json";
file_put_contents(
    $file,
    wp_json_encode([
        "cookies" => $cookies,
        "user" => $user->ID,
        "token" => $token,
    ]),
);
chmod($file, 0600);
