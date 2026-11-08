<?php require_once __DIR__ . "/src/cek_login.php"; ?>
<?php
require_once __DIR__ . "/src/session_init.php";
if (!isset($_SESSION["user_id"])) {
    header("Location: /src/login.php");
    exit;
}
?>

<!DOCTYPE html>
<?php require_once __DIR__ . "/src/session_warning.php";?>
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
      </nav>
      <div class="flex items-center gap-2">
        <button class="text-sm font-medium px-3 py-2 rounded-lg hover:bg-white/10">Masuk</button>
        <button class="text-sm font-bold px-4 py-2 rounded-lg bg-lemon text-ink hover:brightness-95">Daftar</button>
      </div>
    </div>
  </header>

  <!-- Hero + pencarian -->
  <section class="bg-violet text-white">
    <div class="max-w-6xl mx-auto px-4 py-14 md:py-20">
      <h1 class="text-4xl md:text-6xl font-extrabold leading-[1.05] max-w-3xl">Tiket konser, langsung di tanganmu.</h1>
      <p class="mt-4 text-lg text-white/85 max-w-xl">Cari konser di kotamu, pilih kursi, dan dapatkan e-tiket dalam
        hitungan menit.</p>
      <div class="mt-8 bg-white rounded-2xl p-2 flex flex-col md:flex-row gap-2 text-ink max-w-3xl shadow-xl">
        <label class="flex-1 flex items-center gap-2 px-3">
          <span aria-hidden="true">🔎</span>
          <input id="q" type="search" placeholder="Cari artis atau nama konser"
            class="w-full py-3 outline-none bg-transparent placeholder:text-ink/50">
        </label>
        <select id="kota" aria-label="Kota" class="px-3 py-3 rounded-xl bg-mist font-medium outline-none">
          <option value="">Semua kota</option>
        </select>
        <button id="cari" class="bg-ink text-white font-bold px-6 py-3 rounded-xl hover:bg-ink/90">Cari tiket</button>
      </div>
    </div>
  </section>

  <!-- Daftar acara -->
  <main id="acara" class="max-w-6xl mx-auto px-4 py-12">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
      <div>
        <h2 class="text-2xl md:text-3xl font-extrabold">Konser mendatang</h2>
        <p id="hitung" class="text-ink/60 mt-1"></p>
      </div>
      <div class="flex items-center gap-2">
        <label for="urut" class="text-sm text-ink/60">Urutkan</label>
        <select id="urut" class="px-3 py-2 rounded-lg bg-white border border-ink/15 text-sm font-medium">
          <option value="tgl">Tanggal terdekat</option>
          <option value="murah">Harga termurah</option>
          <option value="mahal">Harga termahal</option>
        </select>
      </div>
    </div>

    <div id="chips" class="flex flex-wrap gap-2 mt-6" role="group" aria-label="Genre"></div>
    <div id="grid" class="grid gap-5 mt-8 md:grid-cols-2"></div>
    <p id="kosong" class="hidden text-center py-16 text-ink/60">Belum ada konser yang cocok. Coba ubah kata kunci, kota,
      atau genre.</p>
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

  <!-- Modal pemesanan -->
  <div id="modal" class="fixed inset-0 z-40 hidden items-end md:items-center justify-center bg-ink/70 p-0 md:p-4"
    role="dialog" aria-modal="true" aria-labelledby="m-judul">
    <div class="bg-white w-full md:max-w-lg rounded-t-3xl md:rounded-3xl max-h-[92vh] overflow-y-auto">
      <div class="flex items-start justify-between p-5 border-b border-ink/10">
        <div>
          <h3 id="m-judul" class="text-xl font-extrabold"></h3>
          <p id="m-sub" class="text-sm text-ink/60 mt-0.5"></p>
        </div>
        <button id="tutup" class="p-2 -m-2 text-2xl leading-none" aria-label="Tutup">×</button>
      </div>
      <div id="m-isi" class="p-5"></div>
    </div>
  </div>
</body>

</html>