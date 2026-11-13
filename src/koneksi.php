<?php

$host = "sql311.infinityfree.com";
$user = "if0_43432145";            
$pass = "fleUpCEVpQ6VH";        
$db   = "if0_43432145_FunKonser";            

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
