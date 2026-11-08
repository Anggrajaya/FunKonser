<?php
require_once __DIR__ . "/session_init.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: /src/login.php");
    exit;
}

if (isset($_SESSION["last_activity"]) &&
    (time() - $_SESSION["last_activity"]) > SESSION_DURASI) {
    session_unset();
    session_destroy();
    header("Location: /src/login.php?timeout=1");
    exit;
}

$_SESSION["last_activity"] = time();