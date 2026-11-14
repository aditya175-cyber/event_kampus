-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 07, 2026 at 10:01 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_lomba`
--

-- --------------------------------------------------------

--
-- Table structure for table `jadwal`
--

CREATE TABLE `jadwal` (
  `id_jadwal` int NOT NULL,
  `id_kegiatan` int NOT NULL,
  `nama_sesi` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `lokasi` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deskripsi` text COLLATE utf8mb4_general_ci
) ;

--
-- Dumping data for table `jadwal`
--

INSERT INTO `jadwal` (`id_jadwal`, `id_kegiatan`, `nama_sesi`, `tanggal`, `jam_mulai`, `jam_selesai`, `lokasi`, `deskripsi`) VALUES
(1, 1, 'Registrasi Peserta', '2026-11-15', '08:00:00', '09:00:00', 'Aula Kampus', 'Registrasi ulang peserta'),
(2, 1, 'Pembukaan Lomba', '2026-11-15', '09:00:00', '10:00:00', 'Aula Kampus', 'Pembukaan kegiatan lomba'),
(3, 1, 'Pelaksanaan Lomba', '2026-11-15', '10:00:00', '15:00:00', 'Lab Komputer', 'Pelaksanaan kompetisi');

-- --------------------------------------------------------

--
-- Table structure for table `kategori_lomba`
--

CREATE TABLE `kategori_lomba` (
  `id_kategori` int NOT NULL,
  `nama_kategori` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategori_lomba`
--

INSERT INTO `kategori_lomba` (`id_kategori`, `nama_kategori`, `deskripsi`) VALUES
(1, 'Pemrograman', 'Lomba di bidang pemrograman');

-- --------------------------------------------------------

--
-- Table structure for table `kegiatan`
--

CREATE TABLE `kegiatan` (
  `id_kegiatan` int NOT NULL,
  `nama_kegiatan` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_general_ci,
  `tgl_mulai` date DEFAULT NULL,
  `tgl_selesai` date DEFAULT NULL,
  `lokasi` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kuota` int DEFAULT NULL,
  `biaya_daftar` decimal(12,2) DEFAULT NULL,
  `id_ormawa` int DEFAULT NULL,
  `id_kategori` int DEFAULT NULL
) ;

--
-- Dumping data for table `kegiatan`
--

INSERT INTO `kegiatan` (`id_kegiatan`, `nama_kegiatan`, `deskripsi`, `tgl_mulai`, `tgl_selesai`, `lokasi`, `kuota`, `biaya_daftar`, `id_ormawa`, `id_kategori`) VALUES
(1, 'Lomba Coding', 'Kompetisi pemrograman tingkat kampus', '2026-11-10', '2026-11-12', 'Lab Komputer', 30, 25000.00, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `ormawa`
--

CREATE TABLE `ormawa` (
  `id_ormawa` int NOT NULL,
  `nama_ormawa` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `jenis_ormawa` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kontak` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ormawa`
--

INSERT INTO `ormawa` (`id_ormawa`, `nama_ormawa`, `jenis_ormawa`, `kontak`) VALUES
(1, 'BEM', 'Organisasi Mahasiswa', '081234567890');

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id_pembayaran` int NOT NULL,
  `id_pendaftaran` int NOT NULL,
  `bukti_pembayaran` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jumlah_bayar` decimal(12,2) NOT NULL,
  `tgl_pembayaran` datetime DEFAULT NULL,
  `status_pembayaran` enum('Menunggu','Diverifikasi','Ditolak') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Menunggu'
) ;

--
-- Dumping data for table `pembayaran`
--

INSERT INTO `pembayaran` (`id_pembayaran`, `id_pendaftaran`, `bukti_pembayaran`, `jumlah_bayar`, `tgl_pembayaran`, `status_pembayaran`) VALUES
(3, 1, 'bukti_andi.jpg', 50000.00, '2026-11-07 16:01:44', 'Menunggu');

-- --------------------------------------------------------

--
-- Table structure for table `pendaftaran`
--

CREATE TABLE `pendaftaran` (
  `id_pendaftaran` int NOT NULL,
  `tgl_daftar` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('Menunggu','Terverifikasi','Ditolak','Dibatalkan') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Menunggu',
  `id_peserta` int DEFAULT NULL,
  `id_kegiatan` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pendaftaran`
--

INSERT INTO `pendaftaran` (`id_pendaftaran`, `tgl_daftar`, `status`, `id_peserta`, `id_kegiatan`) VALUES
(1, '2026-11-01 00:00:00', 'Menunggu', 1, 1);

--
-- Triggers `pendaftaran`
--
DELIMITER $$
CREATE TRIGGER `cek_kuota_pendaftaran` BEFORE INSERT ON `pendaftaran` FOR EACH ROW BEGIN
    DECLARE jumlah_peserta INT;
    DECLARE batas_kuota INT;

    IF NEW.status = 'Terverifikasi' THEN

        SELECT kuota INTO batas_kuota
        FROM kegiatan
        WHERE id_kegiatan = NEW.id_kegiatan;

        SELECT COUNT(*) INTO jumlah_peserta
        FROM pendaftaran
        WHERE id_kegiatan = NEW.id_kegiatan
        AND status = 'Terverifikasi';

        IF jumlah_peserta >= batas_kuota THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Kuota kegiatan sudah penuh';
        END IF;

    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `cek_kuota_update` BEFORE UPDATE ON `pendaftaran` FOR EACH ROW BEGIN
    DECLARE jumlah_peserta INT;
    DECLARE batas_kuota INT;

    IF NEW.status = 'Terverifikasi'
       AND OLD.status <> 'Terverifikasi' THEN

        SELECT kuota INTO batas_kuota
        FROM kegiatan
        WHERE id_kegiatan = NEW.id_kegiatan;

        SELECT COUNT(*) INTO jumlah_peserta
        FROM pendaftaran
        WHERE id_kegiatan = NEW.id_kegiatan
        AND status = 'Terverifikasi';

        IF jumlah_peserta >= batas_kuota THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Kuota kegiatan sudah penuh';
        END IF;

    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `cek_tanggal_pendaftaran` BEFORE INSERT ON `pendaftaran` FOR EACH ROW BEGIN
    DECLARE tanggal_mulai DATE;

    SELECT tgl_mulai INTO tanggal_mulai
    FROM kegiatan
    WHERE id_kegiatan = NEW.id_kegiatan;

    IF NEW.tgl_daftar > tanggal_mulai THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Tanggal pendaftaran tidak boleh melewati tanggal mulai kegiatan';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `cek_tanggal_pendaftaran_update` BEFORE UPDATE ON `pendaftaran` FOR EACH ROW BEGIN
    DECLARE tanggal_mulai DATE;

    SELECT tgl_mulai INTO tanggal_mulai
    FROM kegiatan
    WHERE id_kegiatan = NEW.id_kegiatan;

    IF NEW.tgl_daftar > tanggal_mulai THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Tanggal pendaftaran tidak boleh melewati tanggal mulai kegiatan';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `peserta`
--

CREATE TABLE `peserta` (
  `id_peserta` int NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `nim` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `prodi` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_hp` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `peserta`
--

INSERT INTO `peserta` (`id_peserta`, `nama`, `nim`, `prodi`, `no_hp`) VALUES
(1, 'Andi Saputra', '2024001', 'Informatika', '081111111111');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_user` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('Peserta','Ormawa','Admin') COLLATE utf8mb4_general_ci NOT NULL,
  `id_peserta` int DEFAULT NULL,
  `id_ormawa` int DEFAULT NULL
) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `jadwal`
--
ALTER TABLE `jadwal`
  ADD PRIMARY KEY (`id_jadwal`),
  ADD KEY `fk_jadwal_kegiatan` (`id_kegiatan`);

--
-- Indexes for table `kategori_lomba`
--
ALTER TABLE `kategori_lomba`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indexes for table `kegiatan`
--
ALTER TABLE `kegiatan`
  ADD PRIMARY KEY (`id_kegiatan`),
  ADD KEY `id_ormawa` (`id_ormawa`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indexes for table `ormawa`
--
ALTER TABLE `ormawa`
  ADD PRIMARY KEY (`id_ormawa`);

--
-- Indexes for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id_pembayaran`),
  ADD UNIQUE KEY `unik_pembayaran` (`id_pendaftaran`);

--
-- Indexes for table `pendaftaran`
--
ALTER TABLE `pendaftaran`
  ADD PRIMARY KEY (`id_pendaftaran`),
  ADD UNIQUE KEY `unik_pendaftaran` (`id_peserta`,`id_kegiatan`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_tanggal_daftar` (`tgl_daftar`),
  ADD KEY `idx_kegiatan_status` (`id_kegiatan`,`status`);

--
-- Indexes for table `peserta`
--
ALTER TABLE `peserta`
  ADD PRIMARY KEY (`id_peserta`),
  ADD UNIQUE KEY `unik_nim` (`nim`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `id_peserta` (`id_peserta`),
  ADD KEY `id_ormawa` (`id_ormawa`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `jadwal`
--
ALTER TABLE `jadwal`
  MODIFY `id_jadwal` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kategori_lomba`
--
ALTER TABLE `kategori_lomba`
  MODIFY `id_kategori` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `kegiatan`
--
ALTER TABLE `kegiatan`
  MODIFY `id_kegiatan` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ormawa`
--
ALTER TABLE `ormawa`
  MODIFY `id_ormawa` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id_pembayaran` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pendaftaran`
--
ALTER TABLE `pendaftaran`
  MODIFY `id_pendaftaran` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `peserta`
--
ALTER TABLE `peserta`
  MODIFY `id_peserta` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `jadwal`
--
ALTER TABLE `jadwal`
  ADD CONSTRAINT `fk_jadwal_kegiatan` FOREIGN KEY (`id_kegiatan`) REFERENCES `kegiatan` (`id_kegiatan`);

--
-- Constraints for table `kegiatan`
--
ALTER TABLE `kegiatan`
  ADD CONSTRAINT `kegiatan_ibfk_1` FOREIGN KEY (`id_ormawa`) REFERENCES `ormawa` (`id_ormawa`),
  ADD CONSTRAINT `kegiatan_ibfk_2` FOREIGN KEY (`id_kategori`) REFERENCES `kategori_lomba` (`id_kategori`);

--
-- Constraints for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `fk_pembayaran_pendaftaran` FOREIGN KEY (`id_pendaftaran`) REFERENCES `pendaftaran` (`id_pendaftaran`);

--
-- Constraints for table `pendaftaran`
--
ALTER TABLE `pendaftaran`
  ADD CONSTRAINT `pendaftaran_ibfk_1` FOREIGN KEY (`id_peserta`) REFERENCES `peserta` (`id_peserta`),
  ADD CONSTRAINT `pendaftaran_ibfk_2` FOREIGN KEY (`id_kegiatan`) REFERENCES `kegiatan` (`id_kegiatan`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`id_peserta`) REFERENCES `peserta` (`id_peserta`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`id_ormawa`) REFERENCES `ormawa` (`id_ormawa`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
