<?php
$sisaAwal = SESSION_DURASI - (time() - ($_SESSION["last_activity"] ?? time()));
?>
<div id="modal-sesi" class="hidden fixed inset-0 z-[100] items-center justify-center bg-black/60 p-4"
     role="alertdialog" aria-modal="true" aria-labelledby="judul-sesi">
    <div class="w-full max-w-sm bg-white rounded-3xl shadow-xl p-6 text-center">
        <h2 id="judul-sesi" class="text-xl font-extrabold text-[#1B1340]">Sesi hampir berakhir</h2>
        <p class="mt-2 text-[#1B1340]/70">Anda akan keluar otomatis dalam</p>
        <p id="hitung-sesi" class="mt-2 text-4xl font-extrabold text-[#5B3DF5]">0:00</p>
        <p class="mt-2 text-sm text-[#1B1340]/70">Ingin melanjutkan sesi?</p>

        <div class="mt-6 flex gap-3">
            <button type="button" id="btn-keluar"
                class="flex-1 border border-[#1B1340]/20 font-bold py-3 rounded-xl hover:bg-gray-50">
                Keluar
            </button>
            <button type="button" id="btn-lanjut"
                class="flex-1 bg-[#5B3DF5] text-white font-bold py-3 rounded-xl hover:brightness-110">
                Lanjutkan
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var PERINGATAN = <?= (int) SESSION_PERINGATAN ?>;
    var deadline = Date.now() + <?= (int) $sisaAwal ?> * 1000;

    var modal  = document.getElementById("modal-sesi");
    var hitung = document.getElementById("hitung-sesi");
    var tampil = false;

    function format(detik) {
        var m = Math.floor(detik / 60), s = detik % 60;
        return m + ":" + String(s).padStart(2, "0");
    }

    function buka() {
        tampil = true;
        modal.classList.remove("hidden");
        modal.classList.add("flex");
    }

    function tutup() {
        tampil = false;
        modal.classList.add("hidden");
        modal.classList.remove("flex");
    }

    function habis() {
        window.location.href = "/src/login.php?timeout=1";
    }

    setInterval(function () {
        var sisa = Math.ceil((deadline - Date.now()) / 1000);
        if (sisa <= 0) { habis(); return; }
        if (sisa <= PERINGATAN) {
            if (!tampil) buka();
            hitung.textContent = format(sisa);
        }
    }, 1000);

    document.getElementById("btn-lanjut").addEventListener("click", function () {
        fetch("/src/session.php", { method: "POST", credentials: "same-origin" })
            .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
            .then(function (d) {
                deadline = Date.now() + d.sisa * 1000;
                tutup();
            })
            .catch(habis);
    });

    document.getElementById("btn-keluar").addEventListener("click", function () {
        window.location.href = "/src/login.php";
    });
})();
</script>