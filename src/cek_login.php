<?php
require_once __DIR__ . "/session_init.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION["user_id"])) {
    header("Location: /src/login.php");
    exit;
}

if (isset($_SESSION["last_activity"]) &&
    (time() - $_SESSION["last_activity"]) > SESSION_DURASI) {
    akhiriSesi();
    header("Location: /src/login.php?timeout=1");
    exit;
}

$_SESSION["last_activity"] = time();