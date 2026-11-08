<?php
require_once __DIR__ . "/../src/cek_admin.php";
require_once __DIR__ . "/../src/koneksi.php";

function e($teks) { return htmlspecialchars((string) $teks, ENT_QUOTES, "UTF-8"); }
function rupiah($angka) { return "Rp " . number_format((int) $angka, 0, ",", "."); }

// Ringkasan statistik
$stat = $conn->query("
    SELECT
      (SELECT COUNT(*) FROM konser) AS total_konser,
      (SELECT COUNT(*) FROM konser WHERE status = 'akan_datang') AS konser_aktif,
      (SELECT COUNT(*) FROM users WHERE role = 'user') AS total_user,
      (SELECT COUNT(*) FROM pesanan) AS total_pesanan,
      (SELECT COALESCE(SUM(total_harga), 0) FROM pesanan WHERE status = 'dibayar') AS pendapatan,
      (SELECT COALESCE(SUM(d.jumlah), 0)
         FROM detail_pesanan d
         JOIN pesanan p ON p.id = d.pesanan_id
        WHERE p.status = 'dibayar') AS tiket_terjual
")->fetch_assoc();

// 5 pesanan terbaru
$pesananBaru = $conn->query("
    SELECT p.kode_pesanan, u.nama, p.total_harga, p.status, p.created_at
      FROM pesanan p
      JOIN users u ON u.id = p.user_id
     ORDER BY p.created_at DESC
     LIMIT 5
");

$badge = [
    "menunggu_pembayaran" => "bg-yellow-100 text-yellow-800",
    "dibayar"             => "bg-green-100 text-green-800",
    "dibatalkan"          => "bg-red-100 text-red-800",
    "kedaluwarsa"         => "bg-gray-200 text-gray-700",
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - FunKonser</title>
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
<body class="min-h-screen bg-paper font-sans text-ink antialiased">

    <!-- Header -->
    <header class="bg-violet text-white">
        <div class="max-w-6xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-3">
            <a href="/admin/index.php" class="text-2xl font-extrabold tracking-tight">
                FunKonser<span class="text-lemon">.</span>
                <span class="ml-2 text-xs font-bold bg-lemon text-ink rounded-full px-2 py-1 align-middle">ADMIN</span>
            </a>
            <nav class="flex flex-wrap items-center gap-4 text-sm font-bold">
                <a href="/admin/index.php" class="underline underline-offset-4">Dashboard</a>
                <a href="/admin/konser.php" class="hover:underline">Konser</a>
                <a href="/admin/pesanan.php" class="hover:underline">Pesanan</a>
                <a href="/index.php" class="hover:underline">Lihat Situs</a>
                <span class="hidden md:inline text-white/70">|</span>
                <span class="text-white/80"><?= e($_SESSION["nama"] ?? "Admin") ?></span>
                <a href="/src/logout.php" class="bg-white text-violet rounded-xl px-3 py-2 hover:brightness-95">Keluar</a>
            </nav>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8">

        <h1 class="text-2xl md:text-3xl font-extrabold">Dashboard</h1>
        <p class="text-ink/60 mt-1">Ringkasan aktivitas FunKonser.</p>

        <!-- Kartu statistik -->
        <section class="mt-6 grid grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl shadow p-5">
                <p class="text-sm text-ink/60 font-bold">Total Konser</p>
                <p class="text-3xl font-extrabold mt-1"><?= e($stat["total_konser"]) ?></p>
                <p class="text-xs text-ink/50 mt-1"><?= e($stat["konser_aktif"]) ?> akan datang</p>
            </div>
            <div class="bg-white rounded-2xl shadow p-5">
                <p class="text-sm text-ink/60 font-bold">Total Pengguna</p>
                <p class="text-3xl font-extrabold mt-1"><?= e($stat["total_user"]) ?></p>
            </div>
            <div class="bg-white rounded-2xl shadow p-5">
                <p class="text-sm text-ink/60 font-bold">Total Pesanan</p>
                <p class="text-3xl font-extrabold mt-1"><?= e($stat["total_pesanan"]) ?></p>
            </div>
            <div class="bg-white rounded-2xl shadow p-5">
                <p class="text-sm text-ink/60 font-bold">Tiket Terjual</p>
                <p class="text-3xl font-extrabold mt-1"><?= e($stat["tiket_terjual"]) ?></p>
            </div>
            <div class="bg-violet text-white rounded-2xl shadow p-5 col-span-2">
                <p class="text-sm text-white/70 font-bold">Pendapatan (pesanan dibayar)</p>
                <p class="text-3xl font-extrabold mt-1"><?= rupiah($stat["pendapatan"]) ?></p>
            </div>
        </section>

        <!-- Menu cepat -->
        <section class="mt-8">
            <h2 class="text-lg font-extrabold">Kelola</h2>
            <div class="mt-3 grid sm:grid-cols-3 gap-4">
                <a href="/admin/konser.php" class="bg-white rounded-2xl shadow p-5 hover:ring-2 hover:ring-violet">
                    <p class="font-extrabold">Konser</p>
                    <p class="text-sm text-ink/60 mt-1">Tambah, ubah, dan hapus data konser.</p>
                </a>
                <a href="/admin/kategori.php" class="bg-white rounded-2xl shadow p-5 hover:ring-2 hover:ring-violet">
                    <p class="font-extrabold">Kategori Tiket</p>
                    <p class="text-sm text-ink/60 mt-1">Atur harga dan stok tiket.</p>
                </a>
                <a href="/admin/pesanan.php" class="bg-white rounded-2xl shadow p-5 hover:ring-2 hover:ring-violet">
                    <p class="font-extrabold">Pesanan</p>
                    <p class="text-sm text-ink/60 mt-1">Pantau dan kelola pesanan masuk.</p>
                </a>
            </div>
        </section>

        <!-- Pesanan terbaru -->
        <section class="mt-8">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-extrabold">Pesanan Terbaru</h2>
                <a href="/admin/pesanan.php" class="text-sm font-bold text-violet hover:underline">Lihat semua</a>
            </div>

            <div class="mt-3 bg-white rounded-2xl shadow overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-mist text-ink/70">
                        <tr>
                            <th class="px-4 py-3 font-bold">Kode</th>
                            <th class="px-4 py-3 font-bold">Pembeli</th>
                            <th class="px-4 py-3 font-bold">Total</th>
                            <th class="px-4 py-3 font-bold">Status</th>
                            <th class="px-4 py-3 font-bold">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pesananBaru->num_rows === 0): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-ink/50">Belum ada pesanan.</td>
                            </tr>
                        <?php endif; ?>

                        <?php while ($p = $pesananBaru->fetch_assoc()): ?>
                            <tr class="border-t border-ink/10">
                                <td class="px-4 py-3 font-bold"><?= e($p["kode_pesanan"]) ?></td>
                                <td class="px-4 py-3"><?= e($p["nama"]) ?></td>
                                <td class="px-4 py-3"><?= rupiah($p["total_harga"]) ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-bold <?= $badge[$p["status"]] ?? "bg-gray-100" ?>">
                                        <?= e(str_replace("_", " ", $p["status"])) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-ink/60"><?= e(date("d M Y H:i", strtotime($p["created_at"]))) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <?php require_once __DIR__ . "/../src/sesi_warning.php"; ?>
</body>
</html>