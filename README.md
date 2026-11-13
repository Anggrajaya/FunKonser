# FunKonser

## Kategori tiket dan pemesanan

- Kelola kategori konser melalui `admin/kategori.php`. Database project menggunakan tabel `kategori_tiket` yang terhubung ke `konser`; struktur tabel tidak perlu diubah.
- Daftar konser mengambil kategori, harga, dan stok dari MySQL. Halaman detail tersedia melalui `src/event.php`.
- Pemesanan membuat baris `pesanan` dan `detail_pesanan` sekaligus mengurangi stok dalam satu transaksi database. Kategori yang sudah direferensikan oleh detail pesanan tidak dapat dihapus.
