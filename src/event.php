<?php
require_once __DIR__ . "/cek_login.php";
require_once __DIR__ . "/koneksi.php";

const METODE_BAYAR = [
    "transfer_bank" => "Transfer Bank (Virtual Account)",
    "gopay"         => "GoPay",
    "ovo"           => "OVO",
    "qris"          => "QRIS",
    "kartu"         => "Kartu Kredit/Debit",
];

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
$flashBayar = $_SESSION["flash_bayar"] ?? null;
unset($_SESSION["flash_bayar"]);

if ($konser && $_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf"] ?? "";
    if (!is_string($token) || !hash_equals($_SESSION["csrf"], $token)) {
        http_response_code(419);
        exit("Token tidak valid. Muat ulang halaman lalu coba lagi.");
    }

    $aksi = is_string($_POST["aksi"] ?? null) ? $_POST["aksi"] : "pesan";

    /* ========== BAYAR (mockup) / BATALKAN PESANAN ========== */
    if ($aksi === "bayar" || $aksi === "batal") {
        $kode   = is_string($_POST["kode"] ?? null) ? $_POST["kode"] : "";
        $metode = is_string($_POST["metode"] ?? null) ? $_POST["metode"] : "";
        $userId = (int) $_SESSION["user_id"];

        if ($aksi === "bayar" && !isset(METODE_BAYAR[$metode])) {
            $bookingError = "Pilih metode pembayaran.";
        } else {
            $transaksiAktif = false;
            try {
                $conn->begin_transaction();
                $transaksiAktif = true;

                // Hanya pesanan milik user yang sedang login
                $ambil = $conn->prepare("
                    SELECT id, total_harga, status
                      FROM pesanan
                     WHERE kode_pesanan = ? AND user_id = ?
                     FOR UPDATE
                ");
                $ambil->bind_param("si", $kode, $userId);
                $ambil->execute();
                $pesanan = $ambil->get_result()->fetch_assoc();

                if (!$pesanan) throw new RuntimeException("Pesanan tidak ditemukan.");
                if ($pesanan["status"] !== "menunggu_pembayaran") {
                    throw new RuntimeException("Pesanan ini sudah diproses sebelumnya.");
                }

                $pesananId = (int) $pesanan["id"];
                $total     = (int) $pesanan["total_harga"];

                if ($aksi === "bayar") {
                    $label = METODE_BAYAR[$metode];

                    $ubah = $conn->prepare("UPDATE pesanan SET status = 'dibayar' WHERE id = ? AND status = 'menunggu_pembayaran'");
                    $ubah->bind_param("i", $pesananId);
                    $ubah->execute();

                    $catat = $conn->prepare("
                        INSERT INTO pembayaran (pesanan_id, metode, jumlah, status, dibayar_pada)
                        VALUES (?, ?, ?, 'berhasil', NOW())
                    ");
                    $catat->bind_param("isi", $pesananId, $label, $total);
                    $catat->execute();

                    $flash = ["jenis" => "bayar", "kode" => $kode, "total" => $total, "metode" => $label];
                } else {
                    // Kembalikan stok tiket
                    $detail = $conn->prepare("SELECT kategori_tiket_id, jumlah FROM detail_pesanan WHERE pesanan_id = ?");
                    $detail->bind_param("i", $pesananId);
                    $detail->execute();
                    $baris = $detail->get_result()->fetch_all(MYSQLI_ASSOC);

                    $kembali = $conn->prepare("UPDATE kategori_tiket SET stok = stok + ? WHERE id = ?");
                    foreach ($baris as $b) {
                        $jml = (int) $b["jumlah"];
                        $kid = (int) $b["kategori_tiket_id"];
                        $kembali->bind_param("ii", $jml, $kid);
                        $kembali->execute();
                    }

                    $ubah = $conn->prepare("UPDATE pesanan SET status = 'dibatalkan' WHERE id = ? AND status = 'menunggu_pembayaran'");
                    $ubah->bind_param("i", $pesananId);
                    $ubah->execute();

                    $flash = ["jenis" => "batal", "kode" => $kode];
                }

                $conn->commit();
                $transaksiAktif = false;
                $_SESSION["flash_bayar"] = $flash;
                header("Location: /FunKonser/src/event.php?id=" . $id);
                exit;
            } catch (Throwable $ex) {
                if ($transaksiAktif) $conn->rollback();
                if ($ex instanceof RuntimeException) {
                    $bookingError = $ex->getMessage();
                } else {
                    error_log("Gagal memproses pembayaran: " . $ex->getMessage());
                    $bookingError = "Pembayaran gagal diproses. Silakan coba lagi nanti.";
                }
            }
        }

    /* ========== PESAN TIKET ========== */
    } else {
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

                // Arahkan ke halaman yang sama dengan popup pembayaran terbuka
                header("Location: /FunKonser/src/event.php?id=" . $id . "&bayar=" . rawurlencode($kode));
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
}

// Data untuk popup pembayaran (hanya pesanan milik user yang masih menunggu pembayaran)
$pesananBayar = null;
$kodeBayar = $_GET["bayar"] ?? "";
if ($konser && is_string($kodeBayar) && $kodeBayar !== "") {
    $uid = (int) $_SESSION["user_id"];
    $cari = $conn->prepare("
        SELECT p.kode_pesanan, p.total_harga, kt.nama AS kategori, dp.jumlah
          FROM pesanan p
          INNER JOIN detail_pesanan dp ON dp.pesanan_id = p.id
          INNER JOIN kategori_tiket kt ON kt.id = dp.kategori_tiket_id
         WHERE p.kode_pesanan = ? AND p.user_id = ? AND p.status = 'menunggu_pembayaran'
         LIMIT 1
    ");
    $cari->bind_param("si", $kodeBayar, $uid);
    $cari->execute();
    $pesananBayar = $cari->get_result()->fetch_assoc() ?: null;
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
                        <img src="/FunKonser<?= e($konser["poster"]) ?>" alt="Poster <?= e($konser["judul"]) ?>" class="w-full max-h-96 rounded-2xl object-cover">
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
                        <?php if ($flashBayar && $flashBayar["jenis"] === "batal"): ?>
                            <div class="mt-4 rounded-xl bg-yellow-100 px-4 py-3 text-sm text-yellow-900" role="status">
                                Pesanan <strong><?= e($flashBayar["kode"]) ?></strong> dibatalkan dan stok tiket dikembalikan.
                            </div>
                        <?php endif; ?>
                        <?php if ($bookingError && !$pesananBayar): ?>
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
                            <input type="hidden" name="aksi" value="pesan">
                            <div class="mt-5 flex items-center justify-between gap-3">
                                <label for="jumlah" class="text-sm font-medium">Jumlah tiket</label>
                                <input id="jumlah" name="jumlah" type="number" min="1" value="<?= (int) $jumlah ?>"
                                    class="w-24 rounded-xl border border-ink/20 px-3 py-2 text-center font-bold" required>
                            </div>
                            <div class="mt-5 flex justify-between border-t border-ink/10 pt-4">
                                <span>Total</span><span id="total" class="text-xl font-extrabold text-violet"></span>
                            </div>
                            <button type="submit" id="tombol-pesan" class="mt-4 w-full rounded-xl bg-violet py-3 font-bold text-white hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50">Pesan tiket</button>
                        <?php else: ?>
                            <p class="mt-4 text-sm text-ink/60">Pemesanan untuk konser ini tidak tersedia.</p>
                        <?php endif; ?>
                    </form>
                </aside>
            </div>
        </main>

        <?php if ($pesananBayar): ?>
        <!-- ============ POPUP PEMBAYARAN (MOCKUP) ============ -->
        <div id="modal-bayar" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             role="dialog" aria-modal="true" aria-labelledby="judul-bayar">
            <div class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-3xl bg-white p-6 shadow-xl">
                <form id="form-bayar" method="post"
                      action="/FunKonser/src/event.php?id=<?= (int) $konser["id"] ?>&amp;bayar=<?= e(rawurlencode($pesananBayar["kode_pesanan"])) ?>">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION["csrf"]) ?>">
                    <input type="hidden" name="aksi" value="bayar">
                    <input type="hidden" name="kode" value="<?= e($pesananBayar["kode_pesanan"]) ?>">

                    <div class="flex items-start justify-between gap-3">
                        <h2 id="judul-bayar" class="text-xl font-extrabold">Pembayaran</h2>
                    </div>

                    <div class="mt-4 rounded-2xl bg-mist p-4 text-sm">
                        <div class="flex justify-between"><span class="text-ink/60">Kode pesanan</span><strong><?= e($pesananBayar["kode_pesanan"]) ?></strong></div>
                        <div class="mt-1 flex justify-between"><span class="text-ink/60">Tiket</span><strong><?= e($pesananBayar["kategori"]) ?> × <?= (int) $pesananBayar["jumlah"] ?></strong></div>
                        <div class="mt-3 flex items-center justify-between border-t border-ink/10 pt-3">
                            <span class="font-bold">Total bayar</span>
                            <span class="text-xl font-extrabold text-violet"><?= rupiah($pesananBayar["total_harga"]) ?></span>
                        </div>
                    </div>

                    <fieldset class="mt-5">
                        <legend class="text-sm font-bold">Metode pembayaran</legend>
                        <div class="mt-2 space-y-2">
                            <?php $pertama = true; foreach (METODE_BAYAR as $kunci => $label): ?>
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink/15 p-3 has-[:checked]:border-violet has-[:checked]:bg-violet/5">
                                    <input type="radio" name="metode" value="<?= e($kunci) ?>" class="accent-violet" <?= $pertama ? "checked" : "" ?> required>
                                    <span class="font-medium"><?= e($label) ?></span>
                                </label>
                            <?php $pertama = false; endforeach; ?>
                        </div>
                    </fieldset>

                    <?php if ($bookingError): ?>
                        <p class="mt-4 rounded-xl bg-red-100 px-4 py-3 text-sm font-bold text-red-800" role="alert"><?= e($bookingError) ?></p>
                    <?php endif; ?>

                    <p class="mt-4 text-xs text-ink/50">Ini hanya simulasi. Tidak ada uang sungguhan yang diproses. Klik tombol di bawah untuk menandai pesanan sebagai dibayar.</p>

                    <button type="submit" id="tombol-bayar"
                        class="mt-4 w-full rounded-xl bg-violet py-3 font-bold text-white hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-70">
                        Bayar Sekarang
                    </button>

                    <div class="mt-3 flex items-center justify-between text-sm">
                        <a href="/FunKonser/src/event.php?id=<?= (int) $konser["id"] ?>" class="font-bold text-ink/60 hover:underline">Bayar nanti</a>
                        <button type="submit" form="form-batal" id="tombol-batal"
                                class="font-bold text-red-600 hover:underline">Batalkan pesanan</button>
                    </div>
                </form>

                <!-- Form terpisah untuk pembatalan (dihubungkan lewat atribut form="form-batal") -->
                <form id="form-batal" method="post"
                      action="/FunKonser/src/event.php?id=<?= (int) $konser["id"] ?>&amp;bayar=<?= e(rawurlencode($pesananBayar["kode_pesanan"])) ?>"
                      onsubmit="return confirm('Batalkan pesanan ini? Stok tiket akan dikembalikan.');">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION["csrf"]) ?>">
                    <input type="hidden" name="aksi" value="batal">
                    <input type="hidden" name="kode" value="<?= e($pesananBayar["kode_pesanan"]) ?>">
                </form>
            </div>
        </div>
        <script>
            (function () {
                var form   = document.getElementById("form-bayar");
                var tombol = document.getElementById("tombol-bayar");
                var batal  = document.getElementById("tombol-batal");
                var sedang = false;

                form.addEventListener("submit", function (ev) {
                    ev.preventDefault();
                    if (sedang) return;
                    sedang = true;
                    tombol.disabled = true;
                    batal.disabled = true;
                    tombol.innerHTML = '<span class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent align-middle"></span>Memproses pembayaran...';
                    setTimeout(function () { form.submit(); }, 1500);
                });
            })();
        </script>
        <?php endif; ?>

        <?php if ($flashBayar && $flashBayar["jenis"] === "bayar"): ?>
        <!-- ============ POPUP BERHASIL ============ -->
        <div id="modal-sukses" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             role="dialog" aria-modal="true" aria-labelledby="judul-sukses">
            <div class="w-full max-w-sm rounded-3xl bg-white p-6 text-center shadow-xl">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl text-green-700">✓</div>
                <h2 id="judul-sukses" class="mt-4 text-xl font-extrabold">Pembayaran berhasil</h2>
                <div class="mt-4 rounded-2xl bg-mist p-4 text-left text-sm">
                    <div class="flex justify-between"><span class="text-ink/60">Kode pesanan</span><strong><?= e($flashBayar["kode"]) ?></strong></div>
                    <div class="mt-1 flex justify-between"><span class="text-ink/60">Metode</span><strong><?= e($flashBayar["metode"]) ?></strong></div>
                    <div class="mt-1 flex justify-between"><span class="text-ink/60">Total</span><strong><?= rupiah($flashBayar["total"]) ?></strong></div>
                    <div class="mt-1 flex justify-between"><span class="text-ink/60">Status</span><strong class="text-green-700">Dibayar</strong></div>
                </div>
                <button type="button" class="mt-5 w-full rounded-xl bg-violet py-3 font-bold text-white hover:brightness-110"
                        onclick="document.getElementById('modal-sukses').remove()">Selesai</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($kategoriList && $konser["status"] === "akan_datang"): ?>
            <script>
                const jumlahInput = document.getElementById("jumlah");
                const totalLabel = document.getElementById("total");
                const tombolPesan = document.getElementById("tombol-pesan");
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