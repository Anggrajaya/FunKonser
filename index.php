<?php require_once __DIR__ . "/src/cek_login.php"; ?>
<?php
require_once __DIR__ . "/src/session_init.php";
require_once __DIR__ . "/src/koneksi.php";
if (!isset($_SESSION["user_id"])) {
    header("Location: /src/login.php");
    exit;
}

function e($teks) { return htmlspecialchars((string) $teks, ENT_QUOTES, "UTF-8"); }
function rupiah($angka) { return "Rp " . number_format((int) $angka, 0, ",", "."); }

$q = is_string($_GET["q"] ?? null) ? trim($_GET["q"]) : "";
$kota = is_string($_GET["kota"] ?? null) ? trim($_GET["kota"]) : "";
$urut = is_string($_GET["urut"] ?? null) ? $_GET["urut"] : "tgl";
$urutanOptions = [
    "tgl" => "k.tanggal ASC",
    "murah" => "CASE WHEN MIN(CASE WHEN kt.stok > 0 THEN kt.harga END) IS NULL THEN 1 ELSE 0 END ASC, MIN(CASE WHEN kt.stok > 0 THEN kt.harga END) ASC, k.tanggal ASC",
    "mahal" => "CASE WHEN MIN(CASE WHEN kt.stok > 0 THEN kt.harga END) IS NULL THEN 1 ELSE 0 END ASC, MIN(CASE WHEN kt.stok > 0 THEN kt.harga END) DESC, k.tanggal ASC",
];
$urut = isset($urutanOptions[$urut]) ? $urut : "tgl";
$urutan = $urutanOptions[$urut];

$kotaStmt = $conn->query("SELECT DISTINCT kota FROM venues ORDER BY kota ASC");
$sql = "
    SELECT k.id, k.judul, k.artis, k.poster, k.tanggal, v.nama AS venue_nama, v.kota,
           MIN(CASE WHEN kt.stok > 0 THEN kt.harga END) AS harga_mulai,
           COALESCE(SUM(kt.stok), 0) AS stok_tersedia
      FROM konser k
      INNER JOIN venues v ON v.id = k.venue_id
      LEFT JOIN kategori_tiket kt ON kt.konser_id = k.id
     WHERE k.status = 'akan_datang'
";
$types = "";
$params = [];
if ($q !== "") {
    $sql .= " AND (k.judul LIKE ? OR k.artis LIKE ?)";
    $like = "%" . $q . "%";
    $types .= "ss";
    $params[] = $like;
    $params[] = $like;
}
if ($kota !== "") {
    $sql .= " AND v.kota = ?";
    $types .= "s";
    $params[] = $kota;
}
$sql .= " GROUP BY k.id, k.judul, k.artis, k.poster, k.tanggal, v.nama, v.kota ORDER BY " . $urutan;
$konserStmt = $conn->prepare($sql);
if ($params) $konserStmt->bind_param($types, ...$params);
$konserStmt->execute();
$konserList = $konserStmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FunKonser</title>
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap"
    rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'] },
          colors: { ink: '#1B1340', violet: '#5B3DF5', lemon: '#FFE45C', paper: '#FAF8F3', mist: '#ECE8F8' }
        }
      }
    }
  </script>
  <style>
    /* Kartu tiket: lekukan di sisi + garis putus-putus pemisah stub */
    .stub {
      position: relative;
    }

    .stub::before,
    .stub::after {
      content: "";
      position: absolute;
      left: 5.75rem;
      width: 20px;
      height: 20px;
      border-radius: 9999px;
      background: #FAF8F3;
    }

    .stub::before {
      top: -10px;
    }

    .stub::after {
      bottom: -10px;
    }

    :focus-visible {
      outline: 3px solid #5B3DF5;
      outline-offset: 2px;
    }
  </style>
</head>

<body class="bg-paper text-ink font-sans antialiased">

  <!-- Navbar -->
  <header class="bg-ink text-white sticky top-0 z-30">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
      <a href="#" class="text-2xl font-extrabold tracking-tight">FunKonser<span class="text-lemon">.</span></a>
      <nav class="hidden md:flex gap-6 text-sm font-medium text-white/80">
        <a href="#acara" class="hover:text-white">Konser</a>
        <a href="#cara" class="hover:text-white">Cara pesan</a>
        <a href="#" class="hover:text-white">Jual tiket</a>
        <?php if (($_SESSION["role"] ?? "") === "admin"): ?>
          <a href="/FunKonser/admin/index.php" class="hover:text-white">Dashboard</a>
        <?php endif; ?>
      </nav>
      <div class="flex items-center gap-2">
        <span class="text-sm text-white/80"><?= e($_SESSION["nama"] ?? "Pengunjung") ?></span>
        <a href="/FunKonser/src/logout.php" class="bg-white text-violet rounded-xl px-3 py-2 hover:brightness-95">Keluar</a>
      </div>
    </div>
  </header>

  <!-- Hero + pencarian -->
  <section class="bg-violet text-white">
    <div class="max-w-6xl mx-auto px-4 py-14 md:py-20">
      <h1 class="text-4xl md:text-6xl font-extrabold leading-[1.05] max-w-3xl">Tiket konser, langsung di tanganmu.</h1>
      <p class="mt-4 text-lg text-white/85 max-w-xl">Cari konser di kotamu, pilih kursi, dan dapatkan e-tiket dalam
        hitungan menit.</p>
      <form action="/FunKonser/index.php" method="get" class="mt-8 bg-white rounded-2xl p-2 flex flex-col md:flex-row gap-2 text-ink max-w-3xl shadow-xl">
        <label class="flex-1 flex items-center gap-2 px-3">
          <span aria-hidden="true">🔎</span>
          <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Cari artis atau nama konser"
            class="w-full py-3 outline-none bg-transparent placeholder:text-ink/50">
        </label>
        <select id="kota" name="kota" aria-label="Kota" class="px-3 py-3 rounded-xl bg-mist font-medium outline-none">
          <option value="">Semua kota</option>
          <?php while ($lokasi = $kotaStmt->fetch_assoc()): ?>
            <option value="<?= e($lokasi["kota"]) ?>" <?= $kota === $lokasi["kota"] ? "selected" : "" ?>><?= e($lokasi["kota"]) ?></option>
          <?php endwhile; ?>
        </select>
        <input type="hidden" name="urut" value="<?= e($urut) ?>">
        <button id="cari" type="submit" class="bg-ink text-white font-bold px-6 py-3 rounded-xl hover:bg-ink/90">Cari tiket</button>
      </form>
    </div>
  </section>

  <!-- Daftar acara -->
  <main id="acara" class="max-w-6xl mx-auto px-4 py-12">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
      <div>
        <h2 class="text-2xl md:text-3xl font-extrabold">Konser mendatang</h2>
        <p id="hitung" class="text-ink/60 mt-1"><?= $konserList->num_rows ?> konser ditemukan</p>
      </div>
      <div class="flex items-center gap-2">
        <label for="urut" class="text-sm text-ink/60">Urutkan</label>
        <select id="urut" name="urut" form="form-urut" class="px-3 py-2 rounded-lg bg-white border border-ink/15 text-sm font-medium">
          <option value="tgl">Tanggal terdekat</option>
          <option value="murah">Harga termurah</option>
          <option value="mahal">Harga termahal</option>
        </select>
      </div>
    </div>

    <form id="form-urut" action="/FunKonser/index.php" method="get">
      <input type="hidden" name="q" value="<?= e($q) ?>">
      <input type="hidden" name="kota" value="<?= e($kota) ?>">
    </form>
    <div id="grid" class="grid gap-5 mt-8 md:grid-cols-2">
      <?php if ($konserList->num_rows === 0): ?>
        <p id="kosong" class="md:col-span-2 text-center py-16 text-ink/60">Belum ada konser yang cocok. Coba ubah pencarian atau kota.</p>
      <?php endif; ?>
      <?php while ($konser = $konserList->fetch_assoc()): ?>
        <article class="overflow-hidden rounded-2xl bg-white shadow-sm">
          <?php if ($konser["poster"]): ?>
            <img src="/FunKonser<?= e($konser["poster"]) ?>" alt="Poster <?= e($konser["judul"]) ?>" class="h-52 w-full object-cover">
          <?php else: ?>
            <div class="h-52 bg-gradient-to-br from-violet to-ink"></div>
          <?php endif; ?>
          <div class="p-5">
            <p class="text-sm font-bold text-violet"><?= e(date("d M Y, H:i", strtotime($konser["tanggal"]))) ?> WIB</p>
            <h3 class="mt-2 text-xl font-extrabold"><?= e($konser["judul"]) ?></h3>
            <p class="mt-1 text-ink/60"><?= e($konser["artis"]) ?> · <?= e($konser["venue_nama"]) ?>, <?= e($konser["kota"]) ?></p>
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
              <div>
                <?php if ($konser["harga_mulai"] !== null): ?>
                  <p class="text-xs text-ink/50">Harga mulai</p>
                  <p class="font-extrabold text-violet"><?= rupiah($konser["harga_mulai"]) ?></p>
                  <p class="text-xs text-ink/50"><?= (int) $konser["stok_tersedia"] ?> tiket tersedia</p>
                <?php else: ?>
                  <p class="font-bold text-ink/50">Tiket belum tersedia</p>
                <?php endif; ?>
              </div>
              <a href="/FunKonser/src/event.php?id=<?= (int) $konser["id"] ?>" class="rounded-xl bg-ink px-4 py-3 text-sm font-bold text-white hover:bg-violet">Lihat tiket</a>
            </div>
          </div>
        </article>
      <?php endwhile; ?>
    </div>
  </main>

  <!-- Cara pesan -->
  <section id="cara" class="bg-mist">
    <div class="max-w-6xl mx-auto px-4 py-12">
      <h2 class="text-2xl md:text-3xl font-extrabold">Cara memesan tiket</h2>
      <ol class="grid md:grid-cols-3 gap-5 mt-8">
        <li class="bg-white rounded-xl p-5">
          <p class="font-bold">1. Pilih konser</p>
          <p class="text-ink/70 mt-1">Cari berdasarkan artis, kota, atau genre.</p>
        </li>
        <li class="bg-white rounded-xl p-5">
          <p class="font-bold">2. Tentukan kategori dan jumlah</p>
          <p class="text-ink/70 mt-1">Bandingkan harga tiap kategori sebelum membayar.</p>
        </li>
        <li class="bg-white rounded-xl p-5">
          <p class="font-bold">3. Terima e-tiket</p>
          <p class="text-ink/70 mt-1">Kode pemesanan dikirim ke email kamu.</p>
        </li>
      </ol>
    </div>
  </section>

  <footer class="bg-ink text-white/70 text-sm">
    <div class="max-w-6xl mx-auto px-4 py-8 flex flex-col md:flex-row justify-between gap-2">
      <p>© 2026 Panggung. Proyek contoh, semua acara dan artis fiktif.</p>
      <p>Bantuan · Syarat &amp; ketentuan · Kebijakan privasi</p>
    </div>
  </footer>

  <script>
    document.getElementById("urut").value = <?= json_encode($urut) ?>;
    document.getElementById("urut").addEventListener("change", function () {
      document.getElementById("form-urut").submit();
    });
  </script>
  <?php require_once __DIR__ . "/src/session_warning.php"; ?>
</body>

</html>