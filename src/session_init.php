<?php
define("SESSION_DURASI", 60 * 30); 
define("SESSION_PERINGATAN", 30);

ini_set("session.gc_maxlifetime", SESSION_DURASI);

session_set_cookie_params([
    "lifetime" => 0,
    "path"     => "/",
    "httponly" => true,
    "samesite" => "Lax",
]);

session_start();

function akhiriSesi() {
    $params = session_get_cookie_params();
    $namaCookie = session_name();

    $_SESSION = [];
    session_unset();
    session_destroy();

    if (ini_get("session.use_cookies")) {
        setcookie($namaCookie, "", [
            "expires" => time() - 42000,
            "path" => $params["path"],
            "domain" => $params["domain"],
            "secure" => $params["secure"],
            "httponly" => $params["httponly"],
            "samesite" => $params["samesite"] ?? "Lax",
        ]);
    }
}