<?php
require_once __DIR__ . "/cek_login.php";

if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Akses ditolak. Halaman ini khusus admin.");
}