-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql311.infinityfree.com
-- Waktu pembuatan: 13 Nov 2026 pada 09.20
-- Versi server: 11.4.13-MariaDB
-- Versi PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_43432145_FunKonser`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_pesanan`
--

CREATE TABLE `detail_pesanan` (
  `id` int(11) NOT NULL,
  `pesanan_id` int(11) NOT NULL,
  `kategori_tiket_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_satuan` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `detail_pesanan`
--

INSERT INTO `detail_pesanan` (`id`, `pesanan_id`, `kategori_tiket_id`, `jumlah`, `harga_satuan`) VALUES
(1, 1, 1, 1, 5000000);

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_tiket`
--

CREATE TABLE `kategori_tiket` (
  `id` int(11) NOT NULL,
  `konser_id` int(11) NOT NULL,
  `nama` varchar(50) NOT NULL,
  `harga` int(11) NOT NULL,
  `stok` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `kategori_tiket`
--

INSERT INTO `kategori_tiket` (`id`, `konser_id`, `nama`, `harga`, `stok`) VALUES
(1, 1, 'Gold', 5000000, 999),
(2, 1, 'Silver', 4000000, 1500),
(3, 1, 'Bronze', 3000000, 2500);

-- --------------------------------------------------------

--
-- Struktur dari tabel `konser`
--

CREATE TABLE `konser` (
  `id` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `artis` varchar(150) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `poster` varchar(255) DEFAULT NULL,
  `tanggal` datetime NOT NULL,
  `venue_id` int(11) NOT NULL,
  `status` enum('akan_datang','berlangsung','selesai','dibatalkan') DEFAULT 'akan_datang'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `konser`
--

INSERT INTO `konser` (`id`, `judul`, `artis`, `deskripsi`, `poster`, `tanggal`, `venue_id`, `status`) VALUES
(1, 'The Weekend Asia Tours', 'The Weekend', 'The weekend hadir menghibur GBK', '/uploads/poster/6181ea8dbd98f7ff7fac59f5f63835fe.jpg', '2027-01-13 20:32:00', 1, 'akan_datang');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL,
  `pesanan_id` int(11) NOT NULL,
  `metode` varchar(50) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `status` enum('pending','berhasil','gagal') DEFAULT 'pending',
  `dibayar_pada` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `pembayaran`
--

INSERT INTO `pembayaran` (`id`, `pesanan_id`, `metode`, `jumlah`, `status`, `dibayar_pada`) VALUES
(1, 1, 'GoPay', 5000000, 'berhasil', '2026-11-13 05:39:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int(11) NOT NULL,
  `kode_pesanan` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_harga` int(11) NOT NULL,
  `status` enum('menunggu_pembayaran','dibayar','dibatalkan','kedaluwarsa') DEFAULT 'menunggu_pembayaran',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `pesanan`
--

INSERT INTO `pesanan` (`id`, `kode_pesanan`, `user_id`, `total_harga`, `status`, `created_at`) VALUES
(1, 'FK0101D2F9A52721B7', 3, 5000000, 'dibayar', '2026-11-13 13:39:03');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password`, `no_hp`, `role`, `created_at`) VALUES
(1, 'Anggraena Wijaya', 'anggraenawijaya1234@gmail.com', '$2y$12$EJ3elFMIsUmATxqXnOYcj.nLnei.TK7VmWcYQyNfTSaNufNhL1Gsa', '081228936443', 'user', '2026-11-13 13:28:17'),
(2, 'admin', 'admin@gmail.com', '$2y$12$pOSRxMjLO1z84MIp2o88m.7MWWQKO5v9MIkWhX5gGXiZJ4r5/vPoO', '081228936443', 'admin', '2026-11-13 13:29:52'),
(3, 'gendut', 'gendutgembrot@gmail.com', '$2y$12$n7Hn.HsvqKP1zRePz9/t5OYNNqOUnPAHdiAioWbLM3TniEGToBgz.', '9118555656565', 'user', '2026-11-13 13:38:27');

-- --------------------------------------------------------

--
-- Struktur dari tabel `venues`
--

CREATE TABLE `venues` (
  `id` int(11) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `kota` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `kapasitas` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `venues`
--

INSERT INTO `venues` (`id`, `nama`, `kota`, `alamat`, `kapasitas`) VALUES
(1, 'Stadion Gelora Bung Karno', 'Jakarta', '', 5000);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pesanan_id` (`pesanan_id`),
  ADD KEY `kategori_tiket_id` (`kategori_tiket_id`);

--
-- Indeks untuk tabel `kategori_tiket`
--
ALTER TABLE `kategori_tiket`
  ADD PRIMARY KEY (`id`),
  ADD KEY `konser_id` (`konser_id`);

--
-- Indeks untuk tabel `konser`
--
ALTER TABLE `konser`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venue_id` (`venue_id`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pesanan_id` (`pesanan_id`);

--
-- Indeks untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_pesanan` (`kode_pesanan`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `kategori_tiket`
--
ALTER TABLE `kategori_tiket`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `konser`
--
ALTER TABLE `konser`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `venues`
--
ALTER TABLE `venues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD CONSTRAINT `detail_pesanan_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detail_pesanan_ibfk_2` FOREIGN KEY (`kategori_tiket_id`) REFERENCES `kategori_tiket` (`id`);

--
-- Ketidakleluasaan untuk tabel `kategori_tiket`
--
ALTER TABLE `kategori_tiket`
  ADD CONSTRAINT `kategori_tiket_ibfk_1` FOREIGN KEY (`konser_id`) REFERENCES `konser` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `konser`
--
ALTER TABLE `konser`
  ADD CONSTRAINT `konser_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`);

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
