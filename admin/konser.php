<?php
require_once __DIR__ . "/../src/cek_admin.php";
require_once __DIR__ . "/../src/koneksi.php";

const STATUS_KONSER = ["akan_datang", "berlangsung", "selesai", "dibatalkan"];
const POSTER_DIR    = __DIR__ . "/../uploads/poster/";
const POSTER_URL    = "/uploads/poster/";
const POSTER_MAX    = 2 * 1024 * 1024; // 2 MB

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

// Simpan file poster. Mengembalikan path web, atau null (cek $error untuk tahu gagal/tidak).
function simpanPoster($file, &$error) {
    $error = "";
    if (!is_array($file) || ($file["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file["error"] !== UPLOAD_ERR_OK) { $error = "Upload poster gagal (kode " . $file["error"] . ")."; return null; }
    if ($file["size"] > POSTER_MAX)       { $error = "Ukuran poster maksimal 2 MB."; return null; }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
    $ext  = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"][$mime] ?? null;
    if (!$ext) { $error = "Poster harus berformat JPG, PNG, atau WEBP."; return null; }

    if (!is_dir(POSTER_DIR) && !mkdir(POSTER_DIR, 0755, true)) { $error = "Folder upload tidak bisa dibuat."; return null; }

    $nama = bin2hex(random_bytes(16)) . "." . $ext;
    if (!move_uploaded_file($file["tmp_name"], POSTER_DIR . $nama)) { $error = "Gagal menyimpan poster."; return null; }
    return POSTER_URL . $nama;
}

// Hapus file poster hanya jika berasal dari folder upload kita (bukan URL luar)
function hapusFilePoster($path) {
    if ($path && strpos($path, POSTER_URL) === 0) {
        $f = POSTER_DIR . basename($path);
        if (is_file($f)) @unlink($f);
    }
}

/* ================= PROSES FORM ================= */
$errors = [];
$form   = null;   // diisi saat validasi gagal, agar isian tidak hilang
$formId = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cekCsrf();
    $aksi = $_POST["aksi"] ?? "";

    // ----- HAPUS -----
    if ($aksi === "hapus") {
        $id = (int) ($_POST["id"] ?? 0);
        try {
            $s = $conn->prepare("SELECT poster FROM konser WHERE id = ?");
            $s->bind_param("i", $id);
            $s->execute();
            $lama = $s->get_result()->fetch_assoc();

            $d = $conn->prepare("DELETE FROM konser WHERE id = ?");
            $d->bind_param("i", $id);
            $d->execute();

            if ($d->affected_rows > 0) {
                hapusFilePoster($lama["poster"] ?? null);
                flash("ok", "Konser berhasil dihapus.");
            } else {
                flash("err", "Konser tidak ditemukan.");
            }
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1451) {
                flash("err", "Konser sudah memiliki pesanan sehingga tidak bisa dihapus. Ubah statusnya menjadi 'dibatalkan' saja.");
            } else {
                flash("err", "Gagal menghapus konser.");
            }
        }
        alihkan("/admin/konser.php");
    }

    // ----- SIMPAN (tambah / ubah) -----
    if ($aksi === "simpan") {
        $formId = (int) ($_POST["id"] ?? 0);
        $form = [
            "judul"      => trim($_POST["judul"] ?? ""),
            "artis"      => trim($_POST["artis"] ?? ""),
            "deskripsi"  => trim($_POST["deskripsi"] ?? ""),
            "tanggal"    => trim($_POST["tanggal"] ?? ""),
            "venue_id"   => (int) ($_POST["venue_id"] ?? 0),
            "venue_baru" => trim($_POST["venue_baru"] ?? ""),
            "kota_baru"  => trim($_POST["kota_baru"] ?? ""),
            "status"     => $_POST["status"] ?? "akan_datang",
        ];

        if ($form["judul"] === "" || $form["artis"] === "") $errors[] = "Judul dan artis wajib diisi.";
        if (mb_strlen($form["judul"]) > 200 || mb_strlen($form["artis"]) > 150) $errors[] = "Judul maksimal 200 karakter dan artis maksimal 150 karakter.";
        $dt = DateTime::createFromFormat("Y-m-d\TH:i", $form["tanggal"]);
        if (!$dt) $errors[] = "Tanggal dan waktu tidak valid.";
        if (!in_array($form["status"], STATUS_KONSER, true)) $errors[] = "Status tidak valid.";

        $venueBaru = $form["venue_baru"] !== "";
        if ($venueBaru) {
            if ($form["kota_baru"] === "") $errors[] = "Isi kota untuk venue baru.";
        } elseif ($form["venue_id"] <= 0) {
            $errors[] = "Pilih venue, atau isi nama venue baru.";
        }

        $posterBaru = null;
        if (!$errors) {
            $posterBaru = simpanPoster($_FILES["poster"] ?? null, $errUpload);
            if ($errUpload !== "") $errors[] = $errUpload;
        }

        if (!$errors) {
            try {
                $conn->begin_transaction();

                if ($venueBaru) {
                    $v = $conn->prepare("INSERT INTO venues (nama, kota) VALUES (?, ?)");
                    $v->bind_param("ss", $form["venue_baru"], $form["kota_baru"]);
                    $v->execute();
                    $venueId = $conn->insert_id;
                } else {
                    $c = $conn->prepare("SELECT id FROM venues WHERE id = ?");
                    $c->bind_param("i", $form["venue_id"]);
                    $c->execute();
                    if ($c->get_result()->num_rows === 0) throw new RuntimeException("Venue tidak ditemukan.");
                    $venueId = $form["venue_id"];
                }

                $tgl = $dt->format("Y-m-d H:i:s");
                $posterLama = null;

                if ($formId > 0) {
                    $s = $conn->prepare("SELECT poster FROM konser WHERE id = ?");
                    $s->bind_param("i", $formId);
                    $s->execute();
                    $row = $s->get_result()->fetch_assoc();
                    if (!$row) throw new RuntimeException("Konser tidak ditemukan.");
                    $posterLama = $row["poster"];

                    if ($posterBaru) {
                        $u = $conn->prepare("UPDATE konser SET judul=?, artis=?, deskripsi=?, poster=?, tanggal=?, venue_id=?, status=? WHERE id=?");
                        $u->bind_param("sssssisi", $form["judul"], $form["artis"], $form["deskripsi"], $posterBaru, $tgl, $venueId, $form["status"], $formId);
                    } else {
                        $u = $conn->prepare("UPDATE konser SET judul=?, artis=?, deskripsi=?, tanggal=?, venue_id=?, status=? WHERE id=?");
                        $u->bind_param("ssssisi", $form["judul"], $form["artis"], $form["deskripsi"], $tgl, $venueId, $form["status"], $formId);
                    }
                    $u->execute();
                } else {
                    $i = $conn->prepare("INSERT INTO konser (judul, artis, deskripsi, poster, tanggal, venue_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $i->bind_param("sssssis", $form["judul"], $form["artis"], $form["deskripsi"], $posterBaru, $tgl, $venueId, $form["status"]);
                    $i->execute();
                }

                $conn->commit();
                if ($posterBaru && $posterLama) hapusFilePoster($posterLama);

                flash("ok", $formId > 0 ? "Konser berhasil diperbarui." : "Konser berhasil ditambahkan.");
                alihkan("/admin/konser.php");

            } catch (Throwable $ex) {
                $conn->rollback();
                hapusFilePoster($posterBaru);
                $errors[] = ($ex instanceof RuntimeException) ? $ex->getMessage() : "Terjadi kesalahan pada server. Coba lagi nanti.";
            }
        } else {
            hapusFilePoster($posterBaru);
        }
    }
}

/* ================= DATA TAMPILAN ================= */
$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);

$venues = $conn->query("SELECT id, nama, kota FROM venues ORDER BY nama")->fetch_all(MYSQLI_ASSOC);

// Tentukan apakah form ditampilkan, dan isinya
$tampilForm = false;
$idForm     = 0;
$dataForm   = ["judul" => "", "artis" => "", "deskripsi" => "", "tanggal" => "", "venue_id" => 0,
               "venue_baru" => "", "kota_baru" => "", "status" => "akan_datang"];

if ($form !== null) {
    $tampilForm = true;
    $idForm     = $formId;
    $dataForm   = $form;
} elseif (isset($_GET["edit"])) {
    $idForm = (int) $_GET["edit"];
    $s = $conn->prepare("SELECT * FROM konser WHERE id = ?");
    $s->bind_param("i", $idForm);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if ($row) {
        $tampilForm = true;
        $dataForm = [
            "judul" => $row["judul"], "artis" => $row["artis"], "deskripsi" => $row["deskripsi"] ?? "",
            "tanggal" => date("Y-m-d\TH:i", strtotime($row["tanggal"])),
            "venue_id" => (int) $row["venue_id"], "venue_baru" => "", "kota_baru" => "", "status" => $row["status"],
        ];
    } else {
        $idForm = 0;
        $flash = ["err", "Konser tidak ditemukan."];
    }
} elseif (isset($_GET["tambah"])) {
    $tampilForm = true;
}

// Poster saat ini (untuk pratinjau di form edit)
$posterSekarang = null;
if ($tampilForm && $idForm > 0) {
    $s = $conn->prepare("SELECT poster FROM konser WHERE id = ?");
    $s->bind_param("i", $idForm);
    $s->execute();
    $posterSekarang = $s->get_result()->fetch_assoc()["poster"] ?? null;
}

// Daftar konser + pencarian + filter
$q  = trim($_GET["q"] ?? "");
$fs = $_GET["status"] ?? "";

$sql = "SELECT k.id, k.judul, k.artis, k.poster, k.tanggal, k.status,
               v.nama AS venue_nama, v.kota,
               (SELECT COUNT(*) FROM kategori_tiket kt WHERE kt.konser_id = k.id) AS jml_kategori
          FROM konser k
          JOIN venues v ON v.id = k.venue_id
         WHERE 1 = 1";
$tipe = "";
$param = [];
if ($q !== "") {
    $sql .= " AND (k.judul LIKE ? OR k.artis LIKE ?)";
    $like = "%" . $q . "%";
    $tipe .= "ss";
    array_push($param, $like, $like);
}
if (in_array($fs, STATUS_KONSER, true)) {
    $sql .= " AND k.status = ?";
    $tipe .= "s";
    $param[] = $fs;
}
$sql .= " ORDER BY k.tanggal DESC";

$stmt = $conn->prepare($sql);
if ($param) $stmt->bind_param($tipe, ...$param);
$stmt->execute();
$daftar = $stmt->get_result();

$badge = [
    "akan_datang" => "bg-blue-100 text-blue-800",
    "berlangsung" => "bg-green-100 text-green-800",
    "selesai"     => "bg-gray-200 text-gray-700",
    "dibatalkan"  => "bg-red-100 text-red-800",
];
$input = "mt-1 w-full border border-ink/20 rounded-xl px-4 py-3 outline-none focus:border-violet focus:ring-2 focus:ring-violet/30";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Konser - FunKonser</title>
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
                <a href="/admin/venue.php" class="hover:underline">Venue</a>
                <a href="/admin/konser.php" class="underline underline-offset-4">Konser</a>
                <a href="/admin/kategori.php" class="hover:underline">Kategori</a>
                <a href="/admin/pesanan.php" class="hover:underline">Pesanan</a>
                <a href="/index.php" class="hover:underline">Lihat Situs</a>
                <span class="text-white/80"><?= e($_SESSION["nama"] ?? "Admin") ?></span>
                <a href="/src/logout.php" class="bg-white text-violet rounded-xl px-3 py-2 hover:brightness-95">Keluar</a>
            </nav>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold">Kelola Konser</h1>
                <p class="text-ink/60 mt-1">Tambah, ubah, dan hapus data konser.</p>
            </div>
            <?php if (!$tampilForm): ?>
                <a href="/admin/konser.php?tambah=1"
                   class="bg-violet text-white font-bold px-5 py-3 rounded-xl hover:brightness-110">+ Tambah Konser</a>
            <?php endif; ?>
        </div>

        <?php if ($flash): ?>
            <p class="mt-4 rounded-xl px-4 py-3 text-sm font-bold <?= $flash[0] === "ok" ? "bg-green-100 text-green-800" : "bg-red-100 text-red-800" ?>"
               role="status"><?= e($flash[1]) ?></p>
        <?php endif; ?>

        <?php if ($tampilForm): ?>
        <!-- ============ FORM TAMBAH / UBAH ============ -->
        <section class="mt-6 bg-white rounded-3xl shadow p-6 md:p-8">
            <h2 class="text-xl font-extrabold"><?= $idForm > 0 ? "Ubah Konser" : "Tambah Konser" ?></h2>

            <?php if ($errors): ?>
                <div class="mt-4 rounded-xl bg-red-100 text-red-800 px-4 py-3 text-sm font-bold" role="alert">
                    <?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="/admin/konser.php" method="post" enctype="multipart/form-data" class="mt-5 grid md:grid-cols-2 gap-5">
                <input type="hidden" name="csrf" value="<?= e($_SESSION["csrf"]) ?>">
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="id" value="<?= (int) $idForm ?>">

                <div>
                    <label for="judul" class="block font-bold text-sm">Judul Konser :</label>
                    <input type="text" id="judul" name="judul" required maxlength="200"
                           value="<?= e($dataForm["judul"]) ?>" class="<?= $input ?>">
                </div>

                <div>
                    <label for="artis" class="block font-bold text-sm">Artis :</label>
                    <input type="text" id="artis" name="artis" required maxlength="150"
                           value="<?= e($dataForm["artis"]) ?>" class="<?= $input ?>">
                </div>

                <div>
                    <label for="tanggal" class="block font-bold text-sm">Tanggal &amp; Waktu :</label>
                    <input type="datetime-local" id="tanggal" name="tanggal" required
                           value="<?= e($dataForm["tanggal"]) ?>" class="<?= $input ?>">
                </div>

                <div>
                    <label for="status" class="block font-bold text-sm">Status :</label>
                    <select id="status" name="status" class="<?= $input ?>">
                        <?php foreach (STATUS_KONSER as $st): ?>
                            <option value="<?= e($st) ?>" <?= $dataForm["status"] === $st ? "selected" : "" ?>>
                                <?= e(ucfirst(str_replace("_", " ", $st))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="venue_id" class="block font-bold text-sm">Venue :</label>
                    <select id="venue_id" name="venue_id" class="<?= $input ?>">
                        <option value="0">-- Pilih venue --</option>
                        <?php foreach ($venues as $v): ?>
                            <option value="<?= (int) $v["id"] ?>" <?= (int) $dataForm["venue_id"] === (int) $v["id"] ? "selected" : "" ?>>
                                <?= e($v["nama"] . " - " . $v["kota"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-ink/50 mt-2">Venue belum ada di daftar? Isi dua kolom di bawah (pilihan di atas akan diabaikan).</p>
                    <div class="grid sm:grid-cols-2 gap-3 mt-2">
                        <input type="text" name="venue_baru" placeholder="Nama venue baru" maxlength="150"
                               value="<?= e($dataForm["venue_baru"]) ?>" class="<?= $input ?>">
                        <input type="text" name="kota_baru" placeholder="Kota venue baru" maxlength="100"
                               value="<?= e($dataForm["kota_baru"]) ?>" class="<?= $input ?>">
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label for="deskripsi" class="block font-bold text-sm">Deskripsi :</label>
                    <textarea id="deskripsi" name="deskripsi" rows="4" class="<?= $input ?>"><?= e($dataForm["deskripsi"]) ?></textarea>
                </div>

                <div class="md:col-span-2">
                    <label for="poster" class="block font-bold text-sm">Poster (JPG/PNG/WEBP, maks. 2 MB) :</label>
                    <?php if ($posterSekarang): ?>
                        <img src="/<?= e($posterSekarang) ?>" alt="Poster saat ini" class="mt-2 h-32 rounded-xl object-cover">
                        <p class="text-xs text-ink/50 mt-1">Pilih file baru hanya jika ingin mengganti poster.</p>
                    <?php endif; ?>
                    <input type="file" id="poster" name="poster" accept="image/jpeg,image/png,image/webp"
                           class="mt-2 block w-full text-sm">
                </div>

                <div class="md:col-span-2 flex gap-3">
                    <button type="submit" class="bg-violet text-white font-bold px-6 py-3 rounded-xl hover:brightness-110">
                        <?= $idForm > 0 ? "Simpan Perubahan" : "Simpan" ?>
                    </button>
                    <a href="/admin/konser.php" class="border border-ink/20 font-bold px-6 py-3 rounded-xl hover:bg-gray-50">Batal</a>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <!-- ============ DAFTAR KONSER ============ -->
        <form method="get" action="/admin/konser.php" class="mt-6 flex flex-wrap gap-3">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari judul atau artis..."
                   class="flex-1 min-w-[200px] border border-ink/20 rounded-xl px-4 py-3 outline-none focus:border-violet">
            <select name="status" class="border border-ink/20 rounded-xl px-4 py-3 bg-white">
                <option value="">Semua status</option>
                <?php foreach (STATUS_KONSER as $st): ?>
                    <option value="<?= e($st) ?>" <?= $fs === $st ? "selected" : "" ?>><?= e(ucfirst(str_replace("_", " ", $st))) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-ink text-white font-bold px-5 py-3 rounded-xl hover:brightness-125">Cari</button>
        </form>

        <div class="mt-4 bg-white rounded-2xl shadow overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-mist text-ink/70">
                    <tr>
                        <th class="px-4 py-3 font-bold">Poster</th>
                        <th class="px-4 py-3 font-bold">Konser</th>
                        <th class="px-4 py-3 font-bold">Venue</th>
                        <th class="px-4 py-3 font-bold">Tanggal</th>
                        <th class="px-4 py-3 font-bold">Status</th>
                        <th class="px-4 py-3 font-bold">Tiket</th>
                        <th class="px-4 py-3 font-bold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($daftar->num_rows === 0): ?>
                        <tr><td colspan="7" class="px-4 py-8 text-center text-ink/50">Belum ada konser.</td></tr>
                    <?php endif; ?>

                    <?php while ($k = $daftar->fetch_assoc()): ?>
                        <tr class="border-t border-ink/10 align-middle">
                            <td class="px-4 py-3">
                                <?php if ($k["poster"]): ?>
                                    <img src="<?= e($k["poster"]) ?>" alt="" class="w-12 h-16 object-cover rounded-lg">
                                <?php else: ?>
                                    <div class="w-12 h-16 rounded-lg bg-mist"></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-bold"><?= e($k["judul"]) ?></p>
                                <p class="text-ink/60"><?= e($k["artis"]) ?></p>
                            </td>
                            <td class="px-4 py-3"><?= e($k["venue_nama"]) ?><br><span class="text-ink/60"><?= e($k["kota"]) ?></span></td>
                            <td class="px-4 py-3 whitespace-nowrap"><?= e(date("d M Y H:i", strtotime($k["tanggal"]))) ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-bold <?= $badge[$k["status"]] ?? "bg-gray-100" ?>">
                                    <?= e(str_replace("_", " ", $k["status"])) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="/admin/kategori.php?konser_id=<?= (int) $k["id"] ?>" class="font-bold text-violet hover:underline">
                                    <?= (int) $k["jml_kategori"] ?> kategori
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="/admin/konser.php?edit=<?= (int) $k["id"] ?>" class="font-bold text-violet hover:underline">Ubah</a>
                                <form action="/admin/konser.php" method="post" class="inline"
                                      onsubmit="return confirm('Hapus konser ini? Tindakan ini tidak bisa dibatalkan.');">
                                    <input type="hidden" name="csrf" value="<?= e($_SESSION["csrf"]) ?>">
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int) $k["id"] ?>">
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