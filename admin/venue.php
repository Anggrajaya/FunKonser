<?php
require_once __DIR__ . "/../src/cek_admin.php";
require_once __DIR__ . "/../src/koneksi.php";

function e($t) { return htmlspecialchars((string) $t, ENT_QUOTES, "UTF-8"); }
function alihkan($url) { header("Location: " . $url); exit; }
function flash($tipe, $pesan) { $_SESSION["flash"] = [$tipe, $pesan]; }

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}
function cekCsrf() {
    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        http_response_code(419);
        exit("Token tidak valid. Muat ulang halaman lalu coba lagi.");
    }
}

/* ================= PROSES FORM ================= */
$errors = [];
$form   = null;
$formId = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cekCsrf();
    $aksi = $_POST["aksi"] ?? "";

    // ----- HAPUS -----
    if ($aksi === "hapus") {
        $id = (int) ($_POST["id"] ?? 0);
        try {
            $d = $conn->prepare("DELETE FROM venues WHERE id = ?");
            $d->bind_param("i", $id);
            $d->execute();
            flash($d->affected_rows > 0 ? "ok" : "err",
                  $d->affected_rows > 0 ? "Venue berhasil dihapus." : "Venue tidak ditemukan.");
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1451) {
                flash("err", "Venue masih dipakai oleh konser, jadi tidak bisa dihapus. Pindahkan atau hapus konsernya dulu.");
            } else {
                flash("err", "Gagal menghapus venue.");
            }
        }
        alihkan("/admin/venue.php");
    }

    // ----- SIMPAN (tambah / ubah) -----
    if ($aksi === "simpan") {
        $formId = (int) ($_POST["id"] ?? 0);
        $form = [
            "nama"      => trim($_POST["nama"] ?? ""),
            "kota"      => trim($_POST["kota"] ?? ""),
            "alamat"    => trim($_POST["alamat"] ?? ""),
            "kapasitas" => trim($_POST["kapasitas"] ?? ""),
        ];

        if ($form["nama"] === "" || $form["kota"] === "") $errors[] = "Nama venue dan kota wajib diisi.";
        if (mb_strlen($form["nama"]) > 150) $errors[] = "Nama venue maksimal 150 karakter.";
        if (mb_strlen($form["kota"]) > 100) $errors[] = "Kota maksimal 100 karakter.";

        $kapasitas = null;
        if ($form["kapasitas"] !== "") {
            if (!ctype_digit($form["kapasitas"]) || (int) $form["kapasitas"] > 2000000) {
                $errors[] = "Kapasitas harus berupa angka bulat positif.";
            } else {
                $kapasitas = (int) $form["kapasitas"];
            }
        }

        if (!$errors) {
            try {
                // Cegah venue ganda (nama + kota sama)
                $c = $conn->prepare("SELECT id FROM venues WHERE nama = ? AND kota = ? AND id <> ?");
                $c->bind_param("ssi", $form["nama"], $form["kota"], $formId);
                $c->execute();
                if ($c->get_result()->num_rows > 0) {
                    $errors[] = "Venue dengan nama dan kota yang sama sudah ada.";
                } elseif ($formId > 0) {
                    $u = $conn->prepare("UPDATE venues SET nama = ?, kota = ?, alamat = ?, kapasitas = ? WHERE id = ?");
                    $u->bind_param("sssii", $form["nama"], $form["kota"], $form["alamat"], $kapasitas, $formId);
                    $u->execute();
                    flash("ok", "Venue berhasil diperbarui.");
                    alihkan("/admin/venue.php");
                } else {
                    $i = $conn->prepare("INSERT INTO venues (nama, kota, alamat, kapasitas) VALUES (?, ?, ?, ?)");
                    $i->bind_param("sssi", $form["nama"], $form["kota"], $form["alamat"], $kapasitas);
                    $i->execute();
                    flash("ok", "Venue berhasil ditambahkan.");
                    alihkan("/admin/venue.php");
                }
            } catch (mysqli_sql_exception $ex) {
                $errors[] = "Terjadi kesalahan pada server. Coba lagi nanti.";
            }
        }
    }
}

/* ================= DATA TAMPILAN ================= */
$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);

$tampilForm = false;
$idForm     = 0;
$dataForm   = ["nama" => "", "kota" => "", "alamat" => "", "kapasitas" => ""];

if ($form !== null) {
    $tampilForm = true;
    $idForm     = $formId;
    $dataForm   = $form;
} elseif (isset($_GET["edit"])) {
    $idForm = (int) $_GET["edit"];
    $s = $conn->prepare("SELECT nama, kota, alamat, kapasitas FROM venues WHERE id = ?");
    $s->bind_param("i", $idForm);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if ($row) {
        $tampilForm = true;
        $dataForm = [
            "nama" => $row["nama"], "kota" => $row["kota"],
            "alamat" => $row["alamat"] ?? "", "kapasitas" => $row["kapasitas"] ?? "",
        ];
    } else {
        $idForm = 0;
        $flash = ["err", "Venue tidak ditemukan."];
    }
} elseif (isset($_GET["tambah"])) {
    $tampilForm = true;
}

// Daftar venue + pencarian
$q = trim($_GET["q"] ?? "");
$sql = "SELECT v.id, v.nama, v.kota, v.alamat, v.kapasitas,
               (SELECT COUNT(*) FROM konser k WHERE k.venue_id = v.id) AS jml_konser
          FROM venues v
         WHERE 1 = 1";
if ($q !== "") $sql .= " AND (v.nama LIKE ? OR v.kota LIKE ?)";
$sql .= " ORDER BY v.kota, v.nama";

$stmt = $conn->prepare($sql);
if ($q !== "") {
    $like = "%" . $q . "%";
    $stmt->bind_param("ss", $like, $like);
}
$stmt->execute();
$daftar = $stmt->get_result();

$input = "mt-1 w-full border border-ink/20 rounded-xl px-4 py-3 outline-none focus:border-violet focus:ring-2 focus:ring-violet/30";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Venue - FunKonser</title>
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
                <a href="/admin/index.php" class="hover:underline">Dashboard</a>
                <a href="/admin/venue.php" class="underline underline-offset-4">Venue</a>
                <a href="/admin/konser.php" class="hover:underline">Konser</a>
                <a href="/admin/kategori.php" class="hover:underline">Kategori</a>
                <a href="/admin/pesanan.php" class="hover:underline">Pesanan</a>
                <a href="/index.php" class="hover:underline">Lihat Situs</a>
                <span class="hidden md:inline text-white/70">|</span>
                <span class="text-white/80"><?= e($_SESSION["nama"] ?? "Admin") ?></span>
                <a href="/src/logout.php" class="bg-white text-violet rounded-xl px-3 py-2 hover:brightness-95">Keluar</a>
            </nav>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold">Kelola Venue</h1>
                <p class="text-ink/60 mt-1">Tempat penyelenggaraan konser.</p>
            </div>
            <?php if (!$tampilForm): ?>
                <a href="/admin/venue.php?tambah=1"
                   class="bg-violet text-white font-bold px-5 py-3 rounded-xl hover:brightness-110">+ Tambah Venue</a>
            <?php endif; ?>
        </div>

        <?php if ($flash): ?>
            <p class="mt-4 rounded-xl px-4 py-3 text-sm font-bold <?= $flash[0] === "ok" ? "bg-green-100 text-green-800" : "bg-red-100 text-red-800" ?>"
               role="status"><?= e($flash[1]) ?></p>
        <?php endif; ?>

        <?php if ($tampilForm): ?>
        <!-- ============ FORM TAMBAH / UBAH ============ -->
        <section class="mt-6 bg-white rounded-3xl shadow p-6 md:p-8">
            <h2 class="text-xl font-extrabold"><?= $idForm > 0 ? "Ubah Venue" : "Tambah Venue" ?></h2>

            <?php if ($errors): ?>
                <div class="mt-4 rounded-xl bg-red-100 text-red-800 px-4 py-3 text-sm font-bold" role="alert">
                    <?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="/admin/venue.php" method="post" class="mt-5 grid md:grid-cols-2 gap-5">
                <input type="hidden" name="csrf" value="<?= e($_SESSION["csrf"]) ?>">
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="id" value="<?= (int) $idForm ?>">

                <div>
                    <label for="nama" class="block font-bold text-sm">Nama Venue :</label>
                    <input type="text" id="nama" name="nama" required maxlength="150"
                           placeholder="Contoh: Stadion Gelora Bung Karno"
                           value="<?= e($dataForm["nama"]) ?>" class="<?= $input ?>">
                </div>

                <div>
                    <label for="kota" class="block font-bold text-sm">Kota :</label>
                    <input type="text" id="kota" name="kota" required maxlength="100"
                           placeholder="Contoh: Jakarta"
                           value="<?= e($dataForm["kota"]) ?>" class="<?= $input ?>">
                </div>

                <div>
                    <label for="kapasitas" class="block font-bold text-sm">Kapasitas (orang) :</label>
                    <input type="number" id="kapasitas" name="kapasitas" min="0" max="2000000"
                           placeholder="Boleh dikosongkan"
                           value="<?= e($dataForm["kapasitas"]) ?>" class="<?= $input ?>">
                </div>

                <div class="md:col-span-2">
                    <label for="alamat" class="block font-bold text-sm">Alamat :</label>
                    <textarea id="alamat" name="alamat" rows="3" placeholder="Boleh dikosongkan"
                              class="<?= $input ?>"><?= e($dataForm["alamat"]) ?></textarea>
                </div>

                <div class="md:col-span-2 flex gap-3">
                    <button type="submit" class="bg-violet text-white font-bold px-6 py-3 rounded-xl hover:brightness-110">
                        <?= $idForm > 0 ? "Simpan Perubahan" : "Simpan" ?>
                    </button>
                    <a href="/admin/venue.php" class="border border-ink/20 font-bold px-6 py-3 rounded-xl hover:bg-gray-50">Batal</a>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <!-- ============ DAFTAR VENUE ============ -->
        <form method="get" action="/admin/venue.php" class="mt-6 flex flex-wrap gap-3">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari nama venue atau kota..."
                   class="flex-1 min-w-[200px] border border-ink/20 rounded-xl px-4 py-3 outline-none focus:border-violet">
            <button type="submit" class="bg-ink text-white font-bold px-5 py-3 rounded-xl hover:brightness-125">Cari</button>
        </form>

        <div class="mt-4 bg-white rounded-2xl shadow overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-mist text-ink/70">
                    <tr>
                        <th class="px-4 py-3 font-bold">Venue</th>
                        <th class="px-4 py-3 font-bold">Kota</th>
                        <th class="px-4 py-3 font-bold">Alamat</th>
                        <th class="px-4 py-3 font-bold">Kapasitas</th>
                        <th class="px-4 py-3 font-bold">Konser</th>
                        <th class="px-4 py-3 font-bold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($daftar->num_rows === 0): ?>
                        <tr><td colspan="6" class="px-4 py-8 text-center text-ink/50">Belum ada venue.</td></tr>
                    <?php endif; ?>

                    <?php while ($v = $daftar->fetch_assoc()): ?>
                        <tr class="border-t border-ink/10 align-top">
                            <td class="px-4 py-3 font-bold"><?= e($v["nama"]) ?></td>
                            <td class="px-4 py-3"><?= e($v["kota"]) ?></td>
                            <td class="px-4 py-3 text-ink/70 max-w-xs"><?= e($v["alamat"] ?: "-") ?></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?= $v["kapasitas"] !== null ? e(number_format((int) $v["kapasitas"], 0, ",", ".")) : "-" ?>
                            </td>
                            <td class="px-4 py-3"><?= (int) $v["jml_konser"] ?></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="/admin/venue.php?edit=<?= (int) $v["id"] ?>" class="font-bold text-violet hover:underline">Ubah</a>
                                <form action="/admin/venue.php" method="post" class="inline"
                                      onsubmit="return confirm('Hapus venue ini?');">
                                    <input type="hidden" name="csrf" value="<?= e($_SESSION["csrf"]) ?>">
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int) $v["id"] ?>">
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