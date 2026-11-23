-- =======================================================
-- HRIS PresensiKu - Database Schema & Initial Data
-- MySQL 8.0+ / MariaDB Compatible
-- =======================================================

CREATE DATABASE IF NOT EXISTS `hris_presensi` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hris_presensi`;

-- -------------------------------------------------------
-- 1. Table: users
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nip` VARCHAR(30) NOT NULL UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'karyawan') NOT NULL DEFAULT 'karyawan',
  `jabatan` VARCHAR(80) NOT NULL DEFAULT 'Staf',
  `divisi` VARCHAR(80) NOT NULL DEFAULT 'Operasional',
  `no_hp` VARCHAR(20) DEFAULT NULL,
  `alamat` TEXT DEFAULT NULL,
  `foto` VARCHAR(255) DEFAULT 'default.png',
  `gaji_pokok` DECIMAL(12,2) NOT NULL DEFAULT 4500000.00,
  `tunjangan_makan` DECIMAL(12,2) NOT NULL DEFAULT 500000.00,
  `tunjangan_transport` DECIMAL(12,2) NOT NULL DEFAULT 500000.00,
  `tarif_lembur_per_jam` DECIMAL(12,2) NOT NULL DEFAULT 35000.00,
  `potongan_terlambat_per_jam` DECIMAL(12,2) NOT NULL DEFAULT 25000.00,
  `potongan_bpjs_per_bulan` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status_kerja` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  `tanggal_masuk` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tingkatkan skema lama saat file ini diimpor ulang, tanpa membuat duplikat kolom.
SET @hris_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='tanggal_masuk')=0,
  'ALTER TABLE users ADD COLUMN tanggal_masuk DATE NULL AFTER status_kerja',
  'SELECT 1'
);
PREPARE hris_stmt FROM @hris_sql;
EXECUTE hris_stmt;
DEALLOCATE PREPARE hris_stmt;

SET @hris_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='potongan_bpjs_per_bulan')=0,
  'ALTER TABLE users ADD COLUMN potongan_bpjs_per_bulan DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER potongan_terlambat_per_jam',
  'SELECT 1'
);
PREPARE hris_stmt FROM @hris_sql;
EXECUTE hris_stmt;
DEALLOCATE PREPARE hris_stmt;

UPDATE users SET tanggal_masuk=DATE(created_at) WHERE tanggal_masuk IS NULL;

-- -------------------------------------------------------
-- 2. Table: pengaturan_kantor
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengaturan_kantor` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kantor` VARCHAR(100) NOT NULL DEFAULT 'Kantor Pusat PresensiKu',
  `alamat_kantor` VARCHAR(255) NOT NULL DEFAULT 'Jl. Pemuda No. 45, Surabaya',
  `latitude` DECIMAL(10,8) NOT NULL DEFAULT -7.25747190,
  `longitude` DECIMAL(11,8) NOT NULL DEFAULT 112.75208830,
  `radius_meter` INT NOT NULL DEFAULT 100,
  `jam_masuk` TIME NOT NULL DEFAULT '08:00:00',
  `jam_pulang` TIME NOT NULL DEFAULT '17:00:00',
  `toleransi_menit` INT NOT NULL DEFAULT 15,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- 3. Table: presensi
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `presensi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `tanggal` DATE NOT NULL,
  `jam_masuk` TIME DEFAULT NULL,
  `foto_masuk` VARCHAR(255) DEFAULT NULL,
  `lat_masuk` DECIMAL(10,8) DEFAULT NULL,
  `long_masuk` DECIMAL(11,8) DEFAULT NULL,
  `jarak_masuk_meter` INT DEFAULT NULL,
  `jam_pulang` TIME DEFAULT NULL,
  `foto_pulang` VARCHAR(255) DEFAULT NULL,
  `lat_pulang` DECIMAL(10,8) DEFAULT NULL,
  `long_pulang` DECIMAL(11,8) DEFAULT NULL,
  `jarak_pulang_meter` INT DEFAULT NULL,
  `menit_terlambat` INT DEFAULT 0,
  `jam_lembur` DECIMAL(4,2) DEFAULT 0.00,
  `status` ENUM('hadir', 'terlambat', 'izin', 'sakit', 'cuti', 'alpha') NOT NULL DEFAULT 'hadir',
  `catatan` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_presensi_per_day` (`user_id`, `tanggal`),
  CONSTRAINT `fk_presensi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- 4. Table: pengajuan
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengajuan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `jenis` ENUM('cuti', 'izin', 'sakit', 'lembur') NOT NULL,
  `tanggal_mulai` DATE NOT NULL,
  `tanggal_selesai` DATE NOT NULL,
  `jumlah_hari` INT NOT NULL DEFAULT 1,
  `jam_lembur` INT DEFAULT 0,
  `keterangan` TEXT NOT NULL,
  `berkas_lampiran` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending', 'disetujui', 'ditolak') NOT NULL DEFAULT 'pending',
  `catatan_admin` TEXT DEFAULT NULL,
  `reviewed_by` INT DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pengajuan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- 5. Table: payroll
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payroll` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `bulan` INT NOT NULL,
  `tahun` INT NOT NULL,
  `gaji_pokok` DECIMAL(12,2) NOT NULL,
  `tunjangan_makan` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tunjangan_transport` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `uang_lembur` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_hadir` INT NOT NULL DEFAULT 0,
  `total_terlambat` INT NOT NULL DEFAULT 0,
  `total_lembur_jam` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `potongan_terlambat` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `potongan_alpha` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `potongan_bpjs` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_take_home_pay` DECIMAL(12,2) NOT NULL,
  `status_bayar` ENUM('draft', 'dibayar') NOT NULL DEFAULT 'draft',
  `tanggal_bayar` DATE DEFAULT NULL,
  `catatan` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_payroll_month` (`user_id`, `bulan`, `tahun`),
  CONSTRAINT `fk_payroll_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- 6. Table: hari_libur (kalender kerja yang dapat diatur admin)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `hari_libur` (
  `tanggal` DATE PRIMARY KEY,
  `nama` VARCHAR(120) NOT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_hari_libur_admin` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- 7. Table: audit_log (jejak tindakan sensitif)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `actor_user_id` INT DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `entity_type` VARCHAR(40) NOT NULL,
  `entity_id` BIGINT DEFAULT NULL,
  `details` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_actor` (`actor_user_id`),
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =======================================================
-- SEED DATA AWAL (DEMO & TESTING)
-- =======================================================

-- 1. Pengaturan Default Kantor
INSERT INTO `pengaturan_kantor` (`id`, `nama_kantor`, `alamat_kantor`, `latitude`, `longitude`, `radius_meter`, `jam_masuk`, `jam_pulang`, `toleransi_menit`)
VALUES (1, 'Kantor Pusat PresensiKu Tech', 'Jl. Pemuda No. 45, Surabaya, Jawa Timur', -7.26250000, 112.74850000, 100, '08:00:00', '17:00:00', 15)
ON DUPLICATE KEY UPDATE `id` = 1;

-- 2. Data Pengguna (Password: admin123 untuk admin, karyawan123 untuk karyawan)
-- Hash generated via password_hash('...', PASSWORD_DEFAULT)
INSERT INTO `users` (`id`, `nip`, `nama`, `email`, `password`, `role`, `jabatan`, `divisi`, `no_hp`, `alamat`, `gaji_pokok`, `tunjangan_makan`, `tunjangan_transport`, `tarif_lembur_per_jam`, `potongan_terlambat_per_jam`, `status_kerja`)
VALUES
(1, 'ADM001', 'HRD Manager (Admin)', 'admin@hris.test', '$2y$10$EX7B4zROGzG7BDD6E6UJeeuHowqwC6z3aI2qrOhPU/nh/SDeO7wZC', 'admin', 'HR Manager', 'Human Resources', '081234567890', 'Surabaya', 7500000.00, 750000.00, 750000.00, 50000.00, 30000.00, 'aktif'),
(2, 'KRY001', 'Budi Santoso', 'budi@hris.test', '$2y$10$yn2GVUcRovzBAH4DoG3FEeFRIdMhKy5CLcPz/ipTmMpLcYEHjw0bW', 'karyawan', 'Senior Software Engineer', 'Teknologi & IT', '081298765432', 'Sidoarjo', 6000000.00, 600000.00, 600000.00, 40000.00, 25000.00, 'aktif'),
(3, 'KRY002', 'Siti Nurhaliza', 'siti@hris.test', '$2y$10$yn2GVUcRovzBAH4DoG3FEeFRIdMhKy5CLcPz/ipTmMpLcYEHjw0bW', 'karyawan', 'Product UI/UX Designer', 'Kreatif & Desain', '081345678901', 'Gresik', 5200000.00, 500000.00, 500000.00, 35000.00, 25000.00, 'aktif'),
(4, 'KRY003', 'Ahmad Fauzi', 'ahmad@hris.test', '$2y$10$yn2GVUcRovzBAH4DoG3FEeFRIdMhKy5CLcPz/ipTmMpLcYEHjw0bW', 'karyawan', 'Digital Marketing Specialist', 'Pemasaran', '081378901234', 'Surabaya Barat', 4800000.00, 500000.00, 500000.00, 35000.00, 25000.00, 'aktif')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 3. Data Presensi Sample Hari Ini & Kemarin
INSERT INTO `presensi` (`user_id`, `tanggal`, `jam_masuk`, `lat_masuk`, `long_masuk`, `jarak_masuk_meter`, `jam_pulang`, `lat_pulang`, `long_pulang`, `jarak_pulang_meter`, `menit_terlambat`, `status`)
VALUES
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '07:55:00', -7.26251000, 112.74852000, 15, '17:05:00', -7.26251000, 112.74852000, 15, 0, 'hadir'),
(3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:25:00', -7.26253000, 112.74854000, 25, '17:00:00', -7.26253000, 112.74854000, 25, 25, 'terlambat'),
(4, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '07:50:00', -7.26250500, 112.74851500, 12, '17:15:00', -7.26250500, 112.74851500, 12, 0, 'hadir')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 4. Data Pengajuan Cuti Sample
INSERT INTO `pengajuan` (`user_id`, `jenis`, `tanggal_mulai`, `tanggal_selesai`, `jumlah_hari`, `keterangan`, `status`)
SELECT 2, 'cuti', DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 6 DAY), 2, 'Keperluan keluarga di luar kota', 'pending'
WHERE NOT EXISTS (SELECT 1 FROM `pengajuan` WHERE `user_id`=2 AND `jenis`='cuti' AND `keterangan`='Keperluan keluarga di luar kota');

INSERT INTO `pengajuan` (`user_id`, `jenis`, `tanggal_mulai`, `tanggal_selesai`, `jumlah_hari`, `keterangan`, `status`)
SELECT 3, 'izin', DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 1, 'Perpanjangan SIM & keperluan administrasi', 'pending'
WHERE NOT EXISTS (SELECT 1 FROM `pengajuan` WHERE `user_id`=3 AND `jenis`='izin' AND `keterangan`='Perpanjangan SIM & keperluan administrasi');
