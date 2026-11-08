<?php
require_once __DIR__ . "/session_init.php";
$error = $_SESSION["error_register"] ?? "";
$old   = $_SESSION["old_register"] ?? [];
unset($_SESSION["error_register"], $_SESSION["old_register"]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap"
        rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: {
                fontFamily: { sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'] },
                colors: { ink: '#1B1340', violet: '#5B3DF5', lemon: '#FFE45C', paper: '#FAF8F3', mist: '#ECE8F8' }
            } }
        }
    </script>
</head>
<body class="min-h-screen bg-violet font-sans text-ink antialiased">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">

            <a href="index.html" class="block text-center text-3xl font-extrabold tracking-tight text-white mb-6">
                FunKonser<span class="text-lemon">.</span>
            </a>

            <div class="bg-white rounded-3xl shadow-xl p-6 md:p-8">
                <h1 class="text-2xl font-extrabold">Buat akun baru</h1>
                <p class="text-ink/60 mt-1">Daftar untuk memesan dan melihat e-tiketmu.</p>

                <form action="koneksiRegister.php" method="post" id="form-register" class="mt-6 space-y-5">

                    <div>
                        <label for="nama" class="block font-bold text-sm">Username :</label>
                        <input type="text" id="nama" name="nama" autocomplete="username" required
                            value="<?= htmlspecialchars($old["nama"] ?? "") ?>"
                            placeholder="Masukkan username"
                            class="mt-1 w-full border border-ink/20 rounded-xl px-4 py-3 outline-none focus:border-violet focus:ring-2 focus:ring-violet/30">
                    </div>

                    <div>
                        <label for="email" class="block font-bold text-sm">Email :</label>
                        <input type="email" id="email" name="email" autocomplete="email" required
                            value="<?= htmlspecialchars($old["email"] ?? "") ?>"
                            placeholder="Masukkan email"
                            class="mt-1 w-full border border-ink/20 rounded-xl px-4 py-3 outline-none focus:border-violet focus:ring-2 focus:ring-violet/30">
                    </div>

                    <div>
                        <label for="no_hp" class="block font-bold text-sm">No Telepon :</label>
                        <input type="tel" id="no_hp" name="no_hp" autocomplete="tel" required
                            value="<?= htmlspecialchars($old["no_hp"] ?? "") ?>"
                            placeholder="Contoh: 081234567890"
                            class="mt-1 w-full border border-ink/20 rounded-xl px-4 py-3 outline-none focus:border-violet focus:ring-2 focus:ring-violet/30">
                    </div>

                    <div>
                        <label for="password" class="block font-bold text-sm">Password :</label>
                        <input type="password" id="password" name="password" autocomplete="new-password"
                            required minlength="8"
                            placeholder="Minimal 8 karakter"
                            class="mt-1 w-full border border-ink/20 rounded-xl px-4 py-3 outline-none focus:border-violet focus:ring-2 focus:ring-violet/30">
                    </div>

                    <?php if ($error): ?>
                        <p class="text-sm font-bold text-red-600" role="alert"><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>

                    <button type="submit"
                        class="w-full bg-violet text-white font-bold py-3 rounded-xl hover:brightness-110">
                        Daftar
                    </button>
                </form>

                <p class="text-center text-sm text-ink/70 mt-6">
                    Sudah punya akun?
                    <a href="login.php" class="font-bold text-violet hover:underline">Log In</a>
                </p>
            </div>

        </div>
    </div>
</body>
</html>