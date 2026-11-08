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