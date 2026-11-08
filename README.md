# FunKonser

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  no_hp VARCHAR(20),
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE venues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(150) NOT NULL,
  kota VARCHAR(100) NOT NULL,
  alamat TEXT,
  kapasitas INT
) ENGINE=InnoDB;

CREATE TABLE konser (
  id INT AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(200) NOT NULL,
  artis VARCHAR(150) NOT NULL,
  deskripsi TEXT,
  poster VARCHAR(255),
  tanggal DATETIME NOT NULL,
  venue_id INT NOT NULL,
  status ENUM('akan_datang','berlangsung','selesai','dibatalkan') DEFAULT 'akan_datang',
  FOREIGN KEY (venue_id) REFERENCES venues(id)
) ENGINE=InnoDB;

CREATE TABLE kategori_tiket (
  id INT AUTO_INCREMENT PRIMARY KEY,
  konser_id INT NOT NULL,
  nama VARCHAR(50) NOT NULL,
  harga INT NOT NULL,
  stok INT NOT NULL,
  FOREIGN KEY (konser_id) REFERENCES konser(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pesanan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_pesanan VARCHAR(20) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  total_harga INT NOT NULL,
  status ENUM('menunggu_pembayaran','dibayar','dibatalkan','kedaluwarsa') DEFAULT 'menunggu_pembayaran',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE detail_pesanan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pesanan_id INT NOT NULL,
  kategori_tiket_id INT NOT NULL,
  jumlah INT NOT NULL,
  harga_satuan INT NOT NULL,
  FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE,
  FOREIGN KEY (kategori_tiket_id) REFERENCES kategori_tiket(id)
) ENGINE=InnoDB;

CREATE TABLE pembayaran (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pesanan_id INT NOT NULL,
  metode VARCHAR(50) NOT NULL,
  jumlah INT NOT NULL,
  status ENUM('pending','berhasil','gagal') DEFAULT 'pending',
  dibayar_pada DATETIME,
  FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE
) ENGINE=InnoDB;
