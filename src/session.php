<?php
require_once __DIR__ . "/session_init.php";
header("Content-Type: application/json");

function tidakValid() {
    http_response_code(401);
    echo json_encode(["ok" => false]);
    exit;
}

if (!isset($_SESSION["user_id"])) {
    tidakValid();
}

$sisa = SESSION_DURASI - (time() - ($_SESSION["last_activity"] ?? time()));
if ($sisa <= 0) {
    session_unset();
    session_destroy();
    tidakValid();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $_SESSION["last_activity"] = time();
    $sisa = SESSION_DURASI;
}

echo json_encode(["ok" => true, "sisa" => $sisa]);