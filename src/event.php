<?php
require_once 'includes/data.php';
$id = (int)($_GET['id'] ?? 0);
$e = null;
foreach ($events as $x) if ($x['id'] === $id) $e = $x;
if (!$e) { http_response_code(404); $judul='Tidak ditemukan'; require 'includes/header.php';
  echo '<p class="max-w-6xl mx-auto px-4 py-20">Konser tidak ditemukan. <a class="text-violet underline" href="index.php">Kembali ke daftar konser</a></p>';
  require 'includes/footer.php'; exit; }
$judul = $e['judul'];
[$h,$b,$t] = tgl($e['tanggal']);
$kelas = [
  ['nama'=>'Festival','info'=>'Berdiri, area depan panggung','harga'=>$e['harga']],
  ['nama'=>'CAT 1','info'=>'Tribun duduk, pandangan terbaik','harga'=>$e['harga']+250000],
  ['nama'=>'VIP','info'=>'Area eksklusif + merchandise','harga'=>$e['harga']+700000],
];
require 'includes/header.php';
?>
<?= poster($e, 'h-56 md:h-72') ?>
<main class="max-w-6xl mx-auto px-4 -mt-16 relative grid lg:grid-cols-3 gap-8">
  <section class="lg:col-span-2">
    <div class="rounded-2xl bg-white p-6 md:p-8 shadow-sm">
      <a href="index.php" class="text-sm text-violet hover:underline">Kembali ke daftar konser</a>
      <h1 class="mt-3 font-display font-extrabold text-3xl md:text-4xl leading-tight"><?= htmlspecialchars($e['judul']) ?></h1>
      <dl class="mt-5 grid sm:grid-cols-2 gap-4 text-sm">
        <div><dt class="text-ink/50">Tanggal</dt><dd class="font-medium"><?= "$h $b $t, {$e['jam']} WIB" ?></dd></div>
        <div><dt class="text-ink/50">Lokasi</dt><dd class="font-medium"><?= $e['tempat'] ?>, <?= $e['kota'] ?></dd></div>
      </dl>
      <h2 class="mt-8 font-display font-bold text-xl">Tentang acara</h2>
      <p class="mt-2 text-ink/70 max-w-prose leading-relaxed">Konser <?= htmlspecialchars($e['artis']) ?> di <?= $e['kota'] ?>. Pintu dibuka dua jam sebelum acara. Bawa KTP dan e-tiket yang dikirim ke emailmu. Satu tiket berlaku untuk satu orang.</p>
    </div>
  </section>

  <aside class="lg:pt-16">
    <form action="#" method="post" id="pesan" class="rounded-2xl bg-white p-6 shadow-sm lg:sticky lg:top-24">
      <h2 class="font-display font-bold text-xl">Pilih tiket</h2>
      <div class="mt-4 space-y-3">
        <?php foreach ($kelas as $i=>$k): ?>
        <label class="flex items-center justify-between gap-3 rounded-xl border border-ink/15 p-4 cursor-pointer has-[:checked]:border-violet has-[:checked]:bg-violet/5">
          <span class="flex items-center gap-3">
            <input type="radio" name="kelas" value="<?= $k['nama'] ?>" data-harga="<?= $k['harga'] ?>" class="accent-violet" <?= $i===0?'checked':'' ?>>
            <span><span class="block font-bold"><?= $k['nama'] ?></span><span class="block text-xs text-ink/60"><?= $k['info'] ?></span></span>
          </span>
          <span class="font-bold text-sm"><?= rupiah($k['harga']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="mt-5 flex items-center justify-between">
        <span class="text-sm font-medium">Jumlah tiket</span>
        <div class="flex items-center gap-3">
          <button type="button" id="kurang" class="size-8 rounded-full border border-ink/20 hover:border-violet" aria-label="Kurangi">−</button>
          <input id="jumlah" name="jumlah" value="1" readonly class="w-8 text-center font-bold bg-transparent">
          <button type="button" id="tambah" class="size-8 rounded-full border border-ink/20 hover:border-violet" aria-label="Tambah">+</button>
        </div>
      </div>
      <div class="mt-5 flex justify-between border-t border-ink/10 pt-4">
        <span>Total</span><span id="total" class="font-display font-extrabold text-xl text-violet"></span>
      </div>
      <button class="mt-4 w-full rounded-full bg-violet py-3 font-bold text-white hover:bg-ink transition">Lanjut ke pembayaran</button>
    </form>
  </aside>
</main>
<script>
const jml = document.getElementById('jumlah'), tot = document.getElementById('total');
const rp = n => 'Rp' + n.toLocaleString('id-ID');
function hitung(){
  const h = +document.querySelector('input[name=kelas]:checked').dataset.harga;
  tot.textContent = rp(h * +jml.value);
}
document.getElementById('tambah').onclick = () => { if (+jml.value < 6) jml.value++; hitung(); };
document.getElementById('kurang').onclick = () => { if (+jml.value > 1) jml.value--; hitung(); };
document.querySelectorAll('input[name=kelas]').forEach(r => r.onchange = hitung);
hitung();
</script>
<?php require 'includes/footer.php'; ?>
