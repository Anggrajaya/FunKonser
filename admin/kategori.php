<?php
require_once __DIR__ . "/../src/cek_admin.php";
require_once __DIR__ . "/../src/koneksi.php";

function e($teks) { return htmlspecialchars((string) $teks, ENT_QUOTES, "UTF-8"); }
function rupiah($angka) { return "Rp " . number_format((int) $angka, 0, ",", "."); }
function alihkan($url) { header("Location: " . $url); exit; }
function flash($tipe, $pesan) { $_SESSION["flash_kategori"] = [$tipe, $pesan]; }

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

$errors = [];
$form = ["konser_id" => 0, "nama" => "", "harga" => "", "stok" => ""];
$formId = 0;
$filterKonserId = 0;

if (isset($_GET["konser_id"])) {
    $filterKonserId = filter_var(is_scalar($_GET["konser_id"]) ? $_GET["konser_id"] : 0, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 2147483647]]);
    if ($filterKonserId === false) {
        flash("err", "Konser tidak valid.");
        alihkan("/FunKonser/admin/kategori.php");
    }
    $cekKonser = $conn->prepare("SELECT id FROM konser WHERE id = ?");
    $cekKonser->bind_param("i", $filterKonserId);
    $cekKonser->execute();
    if ($cekKonser->get_result()->num_rows === 0) {
        flash("err", "Konser tidak ditemukan.");
        alihkan("/FunKonser/admin/kategori.php");
    }
    $form["konser_id"] = $filterKonserId;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf"] ?? "";
    if (!is_string($token) || !hash_equals($_SESSION["csrf"], $token)) {
        http_response_code(419);
        exit("Token tidak valid. Muat ulang halaman lalu coba lagi.");
    }

    $aksi = is_string($_POST["aksi"] ?? null) ? $_POST["aksi"] : "";
    $id = filter_var(is_scalar($_POST["id"] ?? null) ? $_POST["id"] : 0, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 2147483647]]);
    $formId = $id === false ? 0 : $id;
    if (!in_array($aksi, ["hapus", "simpan"], true)) {
        flash("err", "Aksi kategori tidak valid.");
        alihkan("/FunKonser/admin/kategori.php");
    }

    if ($aksi === "hapus") {
        if ($formId <= 0) {
            flash("err", "Kategori tidak valid.");
            alihkan("/FunKonser/admin/kategori.php");
        }

        $transaksiAktif = false;
        try {
            $conn->begin_transaction();
            $transaksiAktif = true;

            $kunci = $conn->prepare("SELECT id FROM kategori_tiket WHERE id = ? FOR UPDATE");
            $kunci->bind_param("i", $formId);
            $kunci->execute();
            if ($kunci->get_result()->num_rows === 0) {
                throw new RuntimeException("Kategori tidak ditemukan.");
            }

            $cekPesanan = $conn->prepare("SELECT COUNT(*) AS jumlah FROM detail_pesanan WHERE kategori_tiket_id = ?");
            $cekPesanan->bind_param("i", $formId);
            $cekPesanan->execute();
            if ((int) $cekPesanan->get_result()->fetch_assoc()["jumlah"] > 0) {
                throw new RuntimeException("Kategori sudah digunakan dalam pesanan dan tidak dapat dihapus.");
            }

            $hapus = $conn->prepare("DELETE FROM kategori_tiket WHERE id = ?");
            $hapus->bind_param("i", $formId);
            $hapus->execute();
            $conn->commit();
            $transaksiAktif = false;
            flash("ok", "Kategori berhasil dihapus.");
        } catch (Throwable $ex) {
            if ($transaksiAktif) $conn->rollback();
            if ($ex instanceof RuntimeException) {
                flash("err", $ex->getMessage());
            } else {
                error_log("Gagal menghapus kategori tiket: " . $ex->getMessage());
                flash("err", "Terjadi kesalahan saat menghapus kategori. Coba lagi nanti.");
            }
        }
        alihkan("/FunKonser/admin/kategori.php");
    }

    if ($aksi === "simpan") {
        $form = [
            "konser_id" => is_scalar($_POST["konser_id"] ?? null) ? (string) $_POST["konser_id"] : "",
            "nama" => is_string($_POST["nama"] ?? null) ? trim($_POST["nama"]) : "",
            "harga" => is_scalar($_POST["harga"] ?? null) ? trim((string) $_POST["harga"]) : "",
            "stok" => is_scalar($_POST["stok"] ?? null) ? trim((string) $_POST["stok"]) : "",
        ];

        if ($formId < 0 || ($id === false)) $errors[] = "ID kategori tidak valid.";
        if ($form["nama"] === "") {
            $errors[] = "Nama kategori wajib diisi.";
        } elseif (mb_strlen($form["nama"]) > 50) {
            $errors[] = "Nama kategori maksimal 50 karakter.";
        }

        $harga = filter_var($form["harga"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 2147483647]]);
        if ($harga === false) $errors[] = "Harga harus berupa bilangan bulat dan tidak boleh negatif.";

        $stok = filter_var($form["stok"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 2147483647]]);
        if ($stok === false) $errors[] = "Stok harus berupa bilangan bulat dan tidak boleh negatif.";

        $konserId = filter_var($form["konser_id"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 2147483647]]);
        if ($konserId === false) {
            $errors[] = "Pilih konser.";
        } else {
            $cekKonser = $conn->prepare("SELECT id FROM konser WHERE id = ?");
            $cekKonser->bind_param("i", $konserId);
            $cekKonser->execute();
            if ($cekKonser->get_result()->num_rows === 0) $errors[] = "Konser yang dipilih tidak ditemukan.";
        }

        if ($formId > 0) {
            $cekKategori = $conn->prepare("SELECT id FROM kategori_tiket WHERE id = ?");
            $cekKategori->bind_param("i", $formId);
            $cekKategori->execute();
            if ($cekKategori->get_result()->num_rows === 0) $errors[] = "Kategori tidak ditemukan.";
        }

        if (!$errors) {
            try {
                if ($formId > 0) {
                    $simpan = $conn->prepare("UPDATE kategori_tiket SET konser_id = ?, nama = ?, harga = ?, stok = ? WHERE id = ?");
                    $simpan->bind_param("isiii", $konserId, $form["nama"], $harga, $stok, $formId);
                } else {
                    $simpan = $conn->prepare("INSERT INTO kategori_tiket (konser_id, nama, harga, stok) VALUES (?, ?, ?, ?)");
                    $simpan->bind_param("isii", $konserId, $form["nama"], $harga, $stok);
                }
                $simpan->execute();
                flash("ok", $formId > 0 ? "Kategori berhasil diperbarui." : "Kategori berhasil ditambahkan.");
                alihkan("/FunKonser/admin/kategori.php");
            } catch (Throwable $ex) {
                error_log("Gagal menyimpan kategori tiket: " . $ex->getMessage());
                $errors[] = "Terjadi kesalahan saat menyimpan kategori. Coba lagi nanti.";
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" && isset($_GET["edit"])) {
    $formId = filter_var(is_scalar($_GET["edit"]) ? $_GET["edit"] : 0, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 2147483647]]);
    if ($formId === false) {
        flash("err", "Kategori tidak valid.");
        alihkan("/FunKonser/admin/kategori.php");
    }
    $ambil = $conn->prepare("SELECT konser_id, nama, harga, stok FROM kategori_tiket WHERE id = ?");
    $ambil->bind_param("i", $formId);
    $ambil->execute();
    $data = $ambil->get_result()->fetch_assoc();
    if (!$data) {
        flash("err", "Kategori tidak ditemukan.");
        alihkan("/FunKonser/admin/kategori.php");
    }
    $form = $data;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" && !isset($_GET["edit"]) && $filterKonserId > 0) {
    $form["konser_id"] = $filterKonserId;
}

$flash = $_SESSION["flash_kategori"] ?? null;
unset($_SESSION["flash_kategori"]);

$konserList = $conn->query("SELECT id, judul FROM konser ORDER BY tanggal DESC");
$sqlDaftar = "
    SELECT kt.id, kt.konser_id, kt.nama, kt.harga, kt.stok, k.judul AS nama_konser
      FROM kategori_tiket kt
      INNER JOIN konser k ON k.id = kt.konser_id
";
if ($filterKonserId > 0) {
    $sqlDaftar .= " WHERE kt.konser_id = ?";
    $daftarStmt = $conn->prepare($sqlDaftar . " ORDER BY k.tanggal DESC, kt.nama ASC");
    $daftarStmt->bind_param("i", $filterKonserId);
    $daftarStmt->execute();
    $daftar = $daftarStmt->get_result();
} else {
    $daftar = $conn->query($sqlDaftar . " ORDER BY k.tanggal DESC, kt.nama ASC");
}
$input = "mt-1 w-full border border-ink/20 rounded-xl px-4 py-3 outline-none focus:border-violet focus:ring-2 focus:ring-violet/30";
$urlTambah = "/FunKonser/admin/kategori.php?tambah=1" . ($filterKonserId > 0 ? "&konser_id=" . $filterKonserId : "");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori Tiket - FunKonser</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: { sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'] },
            colors: { ink: '#1B1340', violet: '#5B3DF5', lemon: '#FFE45C', paper: '#FAF8F3', mist: '#ECE8F8' }
        } } }
    </script>
</head>
<body class="min-h-screen bg-paper font-sans text-ink antialiased">
    <header class="bg-violet text-white">
        <div class="max-w-6xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-3">
            <a href="/FunKonser/admin/index.php" class="text-2xl font-extrabold tracking-tight">
                FunKonser<span class="text-lemon">.</span>
                <span class="ml-2 text-xs font-bold bg-lemon text-ink rounded-full px-2 py-1 align-middle">ADMIN</span>
            </a>
            <nav class="flex flex-wrap items-center gap-4 text-sm font-bold">
                <a href="/FunKonser/admin/index.php" class="hover:underline">Dashboard</a>
                <a href="/FunKonser/admin/venue.php" class="hover:underline">Venue</a>
                <a href="/FunKonser/admin/konser.php" class="hover:underline">Konser</a>
                <a href="/FunKonser/admin/kategori.php" class="underline underline-offset-4">Kategori</a>
                <a href="/FunKonser/admin/pesanan.php" class="hover:underline">Pesanan</a>
                <a href="/FunKonser/index.php" class="hover:underline">Lihat Situs</a>
                <span class="text-white/80"><?= e($_SESSION["nama"] ?? "Admin") ?></span>
            </nav>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold">Kelola Kategori Tiket</h1>
                <p class="text-ink/60 mt-1">Atur konser, harga, dan stok untuk setiap kategori tiket.</p>
            </div>
            <a href="<?= e($urlTambah) ?>" class="bg-violet text-white font-bold px-5 py-3 rounded-xl hover:brightness-110">+ Tambah Kategori</a>
        </div>
        <?php if ($filterKonserId > 0): ?>
            <p class="mt-4 text-sm text-ink/60">Menampilkan kategori untuk konser yang dipilih. <a href="/FunKonser/admin/kategori.php" class="font-bold text-violet hover:underline">Tampilkan semua</a></p>
        <?php endif; ?>

        <?php if ($flash): ?>
            <p class="mt-4 rounded-xl px-4 py-3 text-sm font-bold <?= $flash[0] === "ok" ? "bg-green-100 text-green-800" : "bg-red-100 text-red-800" ?>" role="status"><?= e($flash[1]) ?></p>
        <?php endif; ?>

        <?php if (isset($_GET["tambah"]) || $formId > 0 || ($_SERVER["REQUEST_METHOD"] === "POST" && $errors)): ?>
        <section class="mt-6 bg-white rounded-3xl shadow p-6 md:p-8">
            <h2 class="text-xl font-extrabold"><?= $formId > 0 ? "Ubah Kategori" : "Tambah Kategori" ?></h2>
            <?php if ($errors): ?>
                <div class="mt-4 rounded-xl bg-red-100 text-red-800 px-4 py-3 text-sm font-bold" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form action="/FunKonser/admin/kategori.php" method="post" class="mt-5 grid md:grid-cols-2 gap-5">
                <input type="hidden" name="csrf" value="<?= e($_SESSION["csrf"]) ?>">
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="id" value="<?= (int) $formId ?>">
                <div>
                    <label for="konser_id" class="block font-bold text-sm">Konser:</label>
                    <select id="konser_id" name="konser_id" required class="<?= $input ?>">
                        <option value="">-- Pilih konser --</option>
                        <?php while ($konser = $konserList->fetch_assoc()): ?>
                            <option value="<?= (int) $konser["id"] ?>" <?= (int) $form["konser_id"] === (int) $konser["id"] ? "selected" : "" ?>><?= e($konser["judul"]) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label for="nama" class="block font-bold text-sm">Nama kategori:</label>
                    <input type="text" id="nama" name="nama" required maxlength="50" value="<?= e($form["nama"]) ?>" class="<?= $input ?>">
                </div>
                <div>
                    <label for="harga" class="block font-bold text-sm">Harga (Rp):</label>
                    <input type="number" id="harga" name="harga" required min="0" max="2147483647" step="1" value="<?= e($form["harga"]) ?>" class="<?= $input ?>">
                </div>
                <div>
                    <label for="stok" class="block font-bold text-sm">Stok:</label>
                    <input type="number" id="stok" name="stok" required min="0" max="2147483647" step="1" value="<?= e($form["stok"]) ?>" class="<?= $input ?>">
                </div>
                <div class="md:col-span-2 flex gap-3">
                    <button type="submit" class="bg-violet text-white font-bold px-6 py-3 rounded-xl hover:brightness-110"><?= $formId > 0 ? "Simpan Perubahan" : "Simpan" ?></button>
                    <a href="/FunKonser/admin/kategori.php" class="border border-ink/20 font-bold px-6 py-3 rounded-xl hover:bg-gray-50">Batal</a>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <div class="mt-6 bg-white rounded-2xl shadow overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-mist text-ink/70">
                    <tr>
                        <th class="px-4 py-3 font-bold">Nama Konser</th>
                        <th class="px-4 py-3 font-bold">Nama Kategori</th>
                        <th class="px-4 py-3 font-bold">Harga</th>
                        <th class="px-4 py-3 font-bold">Stok</th>
                        <th class="px-4 py-3 font-bold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($daftar->num_rows === 0): ?>
                        <tr><td colspan="5" class="px-4 py-8 text-center text-ink/50">Belum ada kategori tiket.</td></tr>
                    <?php endif; ?>
                    <?php while ($kategori = $daftar->fetch_assoc()): ?>
                        <tr class="border-t border-ink/10">
                            <td class="px-4 py-3 font-bold"><?= e($kategori["nama_konser"]) ?></td>
                            <td class="px-4 py-3"><?= e($kategori["nama"]) ?></td>
                            <td class="px-4 py-3 whitespace-nowrap"><?= rupiah($kategori["harga"]) ?></td>
                            <td class="px-4 py-3"><?= (int) $kategori["stok"] ?></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="/FunKonser/admin/kategori.php?edit=<?= (int) $kategori["id"] ?>" class="font-bold text-violet hover:underline">Ubah</a>
                                <form action="/FunKonser/admin/kategori.php" method="post" class="inline" onsubmit="return confirm('Hapus kategori ini? Kategori yang sudah digunakan dalam pesanan tidak dapat dihapus.');">
                                    <input type="hidden" name="csrf" value="<?= e($_SESSION["csrf"]) ?>">
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int) $kategori["id"] ?>">
                                    <button type="submit" class="ml-3 font-bold text-red-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
    <?php require_once __DIR__ . "/../src/session_warning.php"; ?>
</body>
</html>
