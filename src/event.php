<?php
require_once __DIR__ . "/cek_login.php";
require_once __DIR__ . "/koneksi.php";

function e($teks) { return htmlspecialchars((string) $teks, ENT_QUOTES, "UTF-8"); }
function rupiah($angka) { return "Rp " . number_format((int) $angka, 0, ",", "."); }

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

$id = filter_var($_GET["id"] ?? 0, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$konser = null;
if ($id !== false) {
    $ambilKonser = $conn->prepare("
        SELECT k.id, k.judul, k.artis, k.deskripsi, k.poster, k.tanggal, k.status,
               v.nama AS venue_nama, v.kota
          FROM konser k
          INNER JOIN venues v ON v.id = k.venue_id
         WHERE k.id = ?
    ");
    $ambilKonser->bind_param("i", $id);
    $ambilKonser->execute();
    $konser = $ambilKonser->get_result()->fetch_assoc();
}

if (!$konser) http_response_code(404);

$bookingError = "";
$jumlah = 1;
$flashBooking = $_SESSION["flash_booking"] ?? null;
unset($_SESSION["flash_booking"]);

if ($konser && $_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf"] ?? "";
    if (!is_string($token) || !hash_equals($_SESSION["csrf"], $token)) {
        http_response_code(419);
        exit("Token tidak valid. Muat ulang halaman lalu coba lagi.");
    }

    $kategoriId = filter_var(is_scalar($_POST["kategori_id"] ?? null) ? $_POST["kategori_id"] : 0, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 2147483647]]);
    $jumlah = filter_var(is_scalar($_POST["jumlah"] ?? null) ? $_POST["jumlah"] : 0, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 2147483647]]);

    if ($konser["status"] !== "akan_datang") {
        $bookingError = "Pemesanan untuk konser ini sudah tidak tersedia.";
    } elseif ($kategoriId === false || $jumlah === false) {
        $bookingError = "Pilih kategori dan jumlah tiket yang valid.";
    } else {
        $transaksiAktif = false;
        try {
            $conn->begin_transaction();
            $transaksiAktif = true;

            $ambilKategori = $conn->prepare("
                SELECT id, nama, harga, stok
                  FROM kategori_tiket
                 WHERE id = ? AND konser_id = ?
                 FOR UPDATE
            ");
            $ambilKategori->bind_param("ii", $kategoriId, $id);
            $ambilKategori->execute();
            $kategori = $ambilKategori->get_result()->fetch_assoc();

            if (!$kategori) throw new RuntimeException("Kategori tiket tidak ditemukan.");
            if ((int) $kategori["stok"] < $jumlah) throw new RuntimeException("Stok tiket tidak mencukupi. Silakan pilih jumlah yang tersedia.");

            $harga = (int) $kategori["harga"];
            if ($harga < 0) throw new RuntimeException("Harga kategori tidak valid. Hubungi admin.");
            if ($harga > 0 && $jumlah > intdiv(2147483647, $harga)) {
                throw new RuntimeException("Total harga melebihi batas transaksi.");
            }
            $total = $harga * $jumlah;

            $kurangiStok = $conn->prepare("
                UPDATE kategori_tiket
                   SET stok = stok - ?
                 WHERE id = ? AND konser_id = ? AND stok >= ?
            ");
            $kurangiStok->bind_param("iiii", $jumlah, $kategoriId, $id, $jumlah);
            $kurangiStok->execute();
            if ($kurangiStok->affected_rows !== 1) {
                throw new RuntimeException("Stok tiket baru saja berubah. Muat ulang halaman lalu coba lagi.");
            }

            $kode = "FK" . strtoupper(bin2hex(random_bytes(8)));
            $buatPesanan = $conn->prepare("INSERT INTO pesanan (kode_pesanan, user_id, total_harga) VALUES (?, ?, ?)");
            $userId = (int) $_SESSION["user_id"];
            $buatPesanan->bind_param("sii", $kode, $userId, $total);
            $buatPesanan->execute();
            $pesananId = $conn->insert_id;

            $buatDetail = $conn->prepare("
                INSERT INTO detail_pesanan (pesanan_id, kategori_tiket_id, jumlah, harga_satuan)
                VALUES (?, ?, ?, ?)
            ");
            $buatDetail->bind_param("iiii", $pesananId, $kategoriId, $jumlah, $harga);
            $buatDetail->execute();

            $conn->commit();
            $transaksiAktif = false;
            $_SESSION["flash_booking"] = ["kode" => $kode, "total" => $total];
            header("Location: /FunKonser/src/event.php?id=" . $id);
            exit;
        } catch (Throwable $ex) {
            if ($transaksiAktif) $conn->rollback();
            if ($ex instanceof RuntimeException) {
                $bookingError = $ex->getMessage();
            } else {
                error_log("Gagal memproses pemesanan tiket: " . $ex->getMessage());
                $bookingError = "Pemesanan gagal diproses. Silakan coba lagi nanti.";
            }
        }
    }
}

$kategoriStmt = $conn->prepare("
    SELECT id, nama, harga, stok
      FROM kategori_tiket
     WHERE konser_id = ?
     ORDER BY harga ASC, nama ASC
");
$kategoriStmt->bind_param("i", $id);
$kategoriStmt->execute();
$kategoriResult = $kategoriStmt->get_result();
$kategoriList = $kategoriResult->fetch_all(MYSQLI_ASSOC);
if ($jumlah < 1) $jumlah = 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $konser ? e($konser["judul"]) . " - FunKonser" : "Konser tidak ditemukan - FunKonser" ?></title>
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
    <header class="bg-ink text-white">
        <div class="max-w-6xl mx-auto px-4 py-4">
            <a href="/FunKonser/index.php" class="text-2xl font-extrabold tracking-tight">FunKonser<span class="text-lemon">.</span></a>
        </div>
    </header>

    <?php if (!$konser): ?>
        <main class="max-w-6xl mx-auto px-4 py-20">
            <h1 class="text-3xl font-extrabold">Konser tidak ditemukan</h1>
            <a class="mt-4 inline-block font-bold text-violet hover:underline" href="/FunKonser/index.php">Kembali ke daftar konser</a>
        </main>
    <?php else: ?>
        <main class="max-w-6xl mx-auto px-4 py-8 md:py-12">
            <a href="/FunKonser/index.php" class="text-sm font-bold text-violet hover:underline">← Kembali ke daftar konser</a>
            <div class="mt-5 grid lg:grid-cols-3 gap-6">
                <section class="lg:col-span-2 rounded-3xl bg-white shadow p-6 md:p-8">
                    <?php if ($konser["poster"]): ?>
                        <img src="<?= e($konser["poster"]) ?>" alt="Poster <?= e($konser["judul"]) ?>" class="w-full max-h-96 rounded-2xl object-cover">
                    <?php endif; ?>
                    <h1 class="mt-5 text-3xl md:text-4xl font-extrabold"><?= e($konser["judul"]) ?></h1>
                    <p class="mt-2 text-lg text-ink/70"><?= e($konser["artis"]) ?></p>
                    <dl class="mt-6 grid sm:grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-ink/50">Tanggal</dt><dd class="font-bold"><?= e(date("d M Y, H:i", strtotime($konser["tanggal"]))) ?> WIB</dd></div>
                        <div><dt class="text-ink/50">Lokasi</dt><dd class="font-bold"><?= e($konser["venue_nama"]) ?>, <?= e($konser["kota"]) ?></dd></div>
                    </dl>
                    <?php if ($konser["deskripsi"]): ?>
                        <h2 class="mt-8 text-xl font-extrabold">Tentang acara</h2>
                        <p class="mt-2 leading-relaxed text-ink/70"><?= nl2br(e($konser["deskripsi"])) ?></p>
                    <?php endif; ?>
                </section>

                <aside>
                    <form action="/FunKonser/src/event.php?id=<?= (int) $konser["id"] ?>" method="post" class="rounded-3xl bg-white p-6 shadow lg:sticky lg:top-6">
                        <h2 class="text-xl font-extrabold">Pilih tiket</h2>
                        <?php if ($flashBooking): ?>
                            <div class="mt-4 rounded-xl bg-green-100 px-4 py-3 text-sm text-green-900" role="status">
                                Pesanan berhasil dibuat. Kode: <strong><?= e($flashBooking["kode"]) ?></strong>.
                                Total <?= rupiah($flashBooking["total"]) ?>. Status pesanan menunggu pembayaran.
                            </div>
                        <?php endif; ?>
                        <?php if ($bookingError): ?>
                            <p class="mt-4 rounded-xl bg-red-100 px-4 py-3 text-sm font-bold text-red-800" role="alert"><?= e($bookingError) ?></p>
                        <?php endif; ?>
                        <div class="mt-4 space-y-3">
                            <?php if (!$kategoriList): ?>
                                <p class="rounded-xl bg-mist p-4 text-sm text-ink/70">Kategori tiket belum tersedia.</p>
                            <?php endif; ?>
                            <?php $kategoriTerpilih = false; ?>
                            <?php foreach ($kategoriList as $index => $kategori): ?>
                                <?php $tersedia = (int) $kategori["stok"] > 0 && $konser["status"] === "akan_datang"; ?>
                                <?php $checked = $tersedia && !$kategoriTerpilih; ?>
                                <?php if ($checked) $kategoriTerpilih = true; ?>
                                <label class="flex items-center justify-between gap-3 rounded-xl border border-ink/15 p-4 <?= $tersedia ? "cursor-pointer has-[:checked]:border-violet has-[:checked]:bg-violet/5" : "opacity-60" ?>">
                                    <span class="flex items-center gap-3">
                                        <input type="radio" name="kategori_id" value="<?= (int) $kategori["id"] ?>"
                                            data-harga="<?= (int) $kategori["harga"] ?>" data-stok="<?= (int) $kategori["stok"] ?>"
                                            class="accent-violet" <?= $checked ? "checked" : "" ?> <?= $tersedia ? "" : "disabled" ?> required>
                                        <span>
                                            <span class="block font-bold"><?= e($kategori["nama"]) ?></span>
                                            <span class="block text-xs text-ink/60"><?= (int) $kategori["stok"] ?> tiket tersedia</span>
                                        </span>
                                    </span>
                                    <span class="whitespace-nowrap text-sm font-bold"><?= rupiah($kategori["harga"]) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($kategoriList && $konser["status"] === "akan_datang"): ?>
                            <input type="hidden" name="csrf" value="<?= e($_SESSION["csrf"]) ?>">
                            <div class="mt-5 flex items-center justify-between gap-3">
                                <label for="jumlah" class="text-sm font-medium">Jumlah tiket</label>
                                <input id="jumlah" name="jumlah" type="number" min="1" value="<?= (int) $jumlah ?>"
                                    class="w-24 rounded-xl border border-ink/20 px-3 py-2 text-center font-bold" required>
                            </div>
                            <div class="mt-5 flex justify-between border-t border-ink/10 pt-4">
                                <span>Total</span><span id="total" class="text-xl font-extrabold text-violet"></span>
                            </div>
                            <button type="submit" class="mt-4 w-full rounded-xl bg-violet py-3 font-bold text-white hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50">Pesan tiket</button>
                        <?php else: ?>
                            <p class="mt-4 text-sm text-ink/60">Pemesanan untuk konser ini tidak tersedia.</p>
                        <?php endif; ?>
                    </form>
                </aside>
            </div>
        </main>
        <?php if ($kategoriList && $konser["status"] === "akan_datang"): ?>
            <script>
                const jumlahInput = document.getElementById("jumlah");
                const totalLabel = document.getElementById("total");
                const tombolPesan = document.querySelector('button[type="submit"]');
                const formatRupiah = nilai => "Rp " + nilai.toLocaleString("id-ID");
                function perbaruiTotal() {
                    const pilihan = document.querySelector('input[name="kategori_id"]:checked');
                    if (!pilihan) {
                        totalLabel.textContent = "Pilih kategori";
                        tombolPesan.disabled = true;
                        jumlahInput.removeAttribute("max");
                        return;
                    }
                    const stok = Number(pilihan.dataset.stok);
                    jumlahInput.max = stok;
                    if (Number(jumlahInput.value) < 1) jumlahInput.value = 1;
                    if (Number(jumlahInput.value) > stok) jumlahInput.value = stok;
                    totalLabel.textContent = formatRupiah(Number(pilihan.dataset.harga) * Number(jumlahInput.value || 0));
                    tombolPesan.disabled = false;
                }
                document.querySelectorAll('input[name="kategori_id"]').forEach(input => input.addEventListener("change", perbaruiTotal));
                jumlahInput.addEventListener("input", perbaruiTotal);
                perbaruiTotal();
            </script>
        <?php endif; ?>
    <?php endif; ?>

    <?php require_once __DIR__ . "/session_warning.php"; ?>
</body>
</html>
