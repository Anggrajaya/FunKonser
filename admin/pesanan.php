<?php
require_once __DIR__ . "/../src/cek_admin.php";
require_once __DIR__ . "/../src/koneksi.php";

const STATUS_PESANAN = ["menunggu_pembayaran", "dibayar", "dibatalkan", "kedaluwarsa"];
const PER_HALAMAN    = 10;

function e($t) { return htmlspecialchars((string) $t, ENT_QUOTES, "UTF-8"); }
function rupiah($a) { return "Rp " . number_format((int) $a, 0, ",", "."); }

$q  = trim($_GET["q"] ?? "");
$fs = $_GET["status"] ?? "";
if (!in_array($fs, STATUS_PESANAN, true)) $fs = "";

// Kondisi pencarian dan filter (dipakai oleh query hitung dan query daftar)
$where = " WHERE 1 = 1";
$tipe  = "";
$param = [];
if ($q !== "") {
    $where .= " AND (p.kode_pesanan LIKE ? OR u.nama LIKE ? OR u.email LIKE ?)";
    $like   = "%" . $q . "%";
    $tipe  .= "sss";
    array_push($param, $like, $like, $like);
}
if ($fs !== "") {
    $where .= " AND p.status = ?";
    $tipe  .= "s";
    $param[] = $fs;
}

// Total data untuk pagination
$hitung = $conn->prepare("SELECT COUNT(*) AS total FROM pesanan p JOIN users u ON u.id = p.user_id" . $where);
if ($param) $hitung->bind_param($tipe, ...$param);
$hitung->execute();
$total = (int) $hitung->get_result()->fetch_assoc()["total"];

$totalHal = max(1, (int) ceil($total / PER_HALAMAN));
$halaman  = min(max(1, (int) ($_GET["hal"] ?? 1)), $totalHal);
$offset   = ($halaman - 1) * PER_HALAMAN;

// Daftar pesanan
$sql = "SELECT p.id, p.kode_pesanan, p.total_harga, p.status, p.created_at,
               u.nama, u.email,
               GROUP_CONCAT(DISTINCT k.judul SEPARATOR ', ') AS konser,
               GROUP_CONCAT(CONCAT(kt.nama, ' x ', d.jumlah) SEPARATOR ', ') AS tiket,
               (SELECT pb.metode FROM pembayaran pb
                 WHERE pb.pesanan_id = p.id ORDER BY pb.id DESC LIMIT 1) AS metode
          FROM pesanan p
          JOIN users u ON u.id = p.user_id
          LEFT JOIN detail_pesanan d ON d.pesanan_id = p.id
          LEFT JOIN kategori_tiket kt ON kt.id = d.kategori_tiket_id
          LEFT JOIN konser k ON k.id = kt.konser_id"
     . $where .
     " GROUP BY p.id, p.kode_pesanan, p.total_harga, p.status, p.created_at, u.nama, u.email
       ORDER BY p.created_at DESC, p.id DESC
       LIMIT ? OFFSET ?";

$tipeList  = $tipe . "ii";
$paramList = array_merge($param, [PER_HALAMAN, $offset]);

$stmt = $conn->prepare($sql);
$stmt->bind_param($tipeList, ...$paramList);
$stmt->execute();
$daftar = $stmt->get_result();

$badge = [
    "menunggu_pembayaran" => "bg-yellow-100 text-yellow-800",
    "dibayar"             => "bg-green-100 text-green-800",
    "dibatalkan"          => "bg-red-100 text-red-800",
    "kedaluwarsa"         => "bg-gray-200 text-gray-700",
];

function urlHal($h, $q, $fs) {
    $p = ["hal" => $h];
    if ($q !== "")  $p["q"] = $q;
    if ($fs !== "") $p["status"] = $fs;
    return "/admin/pesanan.php?" . http_build_query($p);
}

$dari   = $total === 0 ? 0 : $offset + 1;
$sampai = min($offset + PER_HALAMAN, $total);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pesanan - FunKonser</title>
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

    <header class="bg-violet text-white">
        <div class="max-w-6xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-3">
            <a href="/admin/index.php" class="text-2xl font-extrabold tracking-tight">
                FunKonser<span class="text-lemon">.</span>
                <span class="ml-2 text-xs font-bold bg-lemon text-ink rounded-full px-2 py-1 align-middle">ADMIN</span>
            </a>
            <nav class="flex flex-wrap items-center gap-4 text-sm font-bold">
                <a href="/FunKonser/admin/index.php" class="hover:underline">Dashboard</a>
                <a href="/FunKonser/admin/venue.php" class="hover:underline">Venue</a>
                <a href="/FunKonser/admin/konser.php" class="hover:underline">Konser</a>
                <a href="/FunKonser/admin/pesanan.php" class="underline underline-offset-4">Pesanan</a>
                <a href="/FunKonser/index.php" class="hover:underline">Lihat Situs</a>
                <span class="hidden md:inline text-white/70">|</span>
                <span class="text-white/80"><?= e($_SESSION["nama"] ?? "Admin") ?></span>
                <a href="/src/logout.php" class="bg-white text-violet rounded-xl px-3 py-2 hover:brightness-95">Keluar</a>
            </nav>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8">

        <h1 class="text-2xl md:text-3xl font-extrabold">Daftar Pesanan</h1>
        <p class="text-ink/60 mt-1">Semua pesanan tiket yang masuk.</p>

        <!-- Pencarian dan filter -->
        <form method="get" action="/admin/pesanan.php" class="mt-6 flex flex-wrap gap-3">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari kode, nama, atau email pembeli..."
                   class="flex-1 min-w-[220px] border border-ink/20 rounded-xl px-4 py-3 outline-none focus:border-violet">
            <select name="status" class="border border-ink/20 rounded-xl px-4 py-3 bg-white">
                <option value="">Semua status</option>
                <?php foreach (STATUS_PESANAN as $st): ?>
                    <option value="<?= e($st) ?>" <?= $fs === $st ? "selected" : "" ?>>
                        <?= e(ucfirst(str_replace("_", " ", $st))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-ink text-white font-bold px-5 py-3 rounded-xl hover:brightness-125">Cari</button>
            <?php if ($q !== "" || $fs !== ""): ?>
                <a href="/admin/pesanan.php" class="border border-ink/20 font-bold px-5 py-3 rounded-xl hover:bg-gray-50">Reset</a>
            <?php endif; ?>
        </form>

        <p class="mt-4 text-sm text-ink/60">
            Menampilkan <?= $dari ?>-<?= $sampai ?> dari <?= $total ?> pesanan
        </p>

        <!-- Tabel -->
        <div class="mt-2 bg-white rounded-2xl shadow overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-mist text-ink/70">
                    <tr>
                        <th class="px-4 py-3 font-bold">Kode</th>
                        <th class="px-4 py-3 font-bold">Pembeli</th>
                        <th class="px-4 py-3 font-bold">Konser</th>
                        <th class="px-4 py-3 font-bold">Tiket</th>
                        <th class="px-4 py-3 font-bold">Total</th>
                        <th class="px-4 py-3 font-bold">Metode</th>
                        <th class="px-4 py-3 font-bold">Status</th>
                        <th class="px-4 py-3 font-bold">Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($daftar->num_rows === 0): ?>
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-ink/50">
                                <?= ($q !== "" || $fs !== "") ? "Tidak ada pesanan yang cocok dengan pencarian." : "Belum ada pesanan." ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php while ($p = $daftar->fetch_assoc()): ?>
                        <tr class="border-t border-ink/10 align-top">
                            <td class="px-4 py-3 font-bold whitespace-nowrap"><?= e($p["kode_pesanan"]) ?></td>
                            <td class="px-4 py-3">
                                <p class="font-bold"><?= e($p["nama"]) ?></p>
                                <p class="text-ink/60"><?= e($p["email"]) ?></p>
                            </td>
                            <td class="px-4 py-3 max-w-[14rem]"><?= e($p["konser"] ?: "-") ?></td>
                            <td class="px-4 py-3 max-w-[14rem]"><?= e($p["tiket"] ?: "-") ?></td>
                            <td class="px-4 py-3 font-bold whitespace-nowrap"><?= rupiah($p["total_harga"]) ?></td>
                            <td class="px-4 py-3"><?= e($p["metode"] ?: "-") ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-bold whitespace-nowrap <?= $badge[$p["status"]] ?? "bg-gray-100" ?>">
                                    <?= e(str_replace("_", " ", $p["status"])) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-ink/60 whitespace-nowrap"><?= e(date("d M Y H:i", strtotime($p["created_at"]))) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalHal > 1): ?>
            <nav class="mt-6 flex flex-wrap items-center justify-center gap-2 text-sm font-bold" aria-label="Halaman">
                <?php if ($halaman > 1): ?>
                    <a href="<?= e(urlHal($halaman - 1, $q, $fs)) ?>" class="px-4 py-2 rounded-xl bg-white shadow hover:ring-2 hover:ring-violet">Sebelumnya</a>
                <?php endif; ?>

                <?php
                $awal  = max(1, $halaman - 2);
                $akhir = min($totalHal, $halaman + 2);
                for ($h = $awal; $h <= $akhir; $h++):
                ?>
                    <a href="<?= e(urlHal($h, $q, $fs)) ?>"
                       class="px-4 py-2 rounded-xl <?= $h === $halaman ? "bg-violet text-white" : "bg-white shadow hover:ring-2 hover:ring-violet" ?>"
                       <?= $h === $halaman ? 'aria-current="page"' : "" ?>><?= $h ?></a>
                <?php endfor; ?>

                <?php if ($halaman < $totalHal): ?>
                    <a href="<?= e(urlHal($halaman + 1, $q, $fs)) ?>" class="px-4 py-2 rounded-xl bg-white shadow hover:ring-2 hover:ring-violet">Berikutnya</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    </main>

    <?php require_once __DIR__ . "/../src/sesi_warning.php"; ?>
</body>
</html>