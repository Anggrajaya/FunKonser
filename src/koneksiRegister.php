<?php

session_start();
require_once "koneksi.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$nama     = trim($_POST["nama"] ?? "");
$email    = trim($_POST["email"] ?? "");
$no_hp    = trim($_POST["no_hp"] ?? "");
$password = $_POST["password"] ?? "";

function kembali($pesan, $nama, $email, $no_hp) {
    $_SESSION["error_register"] = $pesan;
    $_SESSION["old_register"]   = ["nama" => $nama, "email" => $email, "no_hp" => $no_hp];
    header("Location: register.php");
    exit;
}

// Validasi
if ($nama === "" || $email === "" || $no_hp === "" || $password === "") {
    kembali("Semua kolom wajib diisi.", $nama, $email, $no_hp);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    kembali("Format email tidak valid.", $nama, $email, $no_hp);
}
if (!preg_match('/^\+?[0-9]{9,15}$/', $no_hp)) {
    kembali("Nomor telepon harus berupa angka (9-15 digit).", $nama, $email, $no_hp);
}
if (strlen($password) < 8) {
    kembali("Password minimal 8 karakter.", $nama, $email, $no_hp);
}

try {
    // Cek email sudah terdaftar
    $cek = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $cek->bind_param("s", $email);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        kembali("Email sudah terdaftar. Silakan masuk.", $nama, $email, $no_hp);
    }

    // Simpan user baru (role otomatis 'user')
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (nama, email, password, no_hp) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nama, $email, $hash, $no_hp);
    $stmt->execute();

    $_SESSION["sukses_register"] = "Akun berhasil dibuat. Silakan masuk.";
    header("Location: login.php");
    exit;

} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) { // email duplikat
        kembali("Email sudah terdaftar. Silakan masuk.", $nama, $email, $no_hp);
    }
    kembali("Terjadi kesalahan pada server. Coba lagi nanti.", $nama, $email, $no_hp);
}

?>