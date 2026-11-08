<?php
session_start();
require_once __DIR__ . "/koneksi.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$email    = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

function kembali($pesan, $email) {
    $_SESSION["error_login"] = $pesan;
    $_SESSION["old_login"]   = $email;
    header("Location: login.php");
    exit;
}

if ($email === "" || $password === "") {
    kembali("Email dan password wajib diisi.", $email);
}

try {
    $stmt = $conn->prepare("SELECT id, nama, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
} catch (mysqli_sql_exception $e) {
    kembali("Terjadi kesalahan pada server. Coba lagi nanti.", $email);
}

if (!$user || !password_verify($password, $user["password"])) {
    kembali("Email atau password salah.", $email);
}

session_regenerate_id(true);
$_SESSION["user_id"] = $user["id"];
$_SESSION["nama"]    = $user["nama"];
$_SESSION["role"]    = $user["role"];

if ($user["role"] === "admin") {
    header("Location: /FunKonser/admin/index.php");
} else {
    header("Location: /FunKonser/index.php");
}
exit;