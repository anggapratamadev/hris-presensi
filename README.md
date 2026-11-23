# HRIS PresensiKu

HRIS demo berbasis PHP 8.1+, MySQL/MariaDB, Bootstrap 5, Leaflet, dan JavaScript browser. Fitur utamanya adalah presensi dengan pemeriksaan radius GPS di server, selfie kamera, pengajuan cuti/izin/sakit/lembur, pengaturan kantor, rekap kehadiran, payroll bulanan, dan slip gaji siap cetak.

## Instalasi di Laragon (Windows)

1. Letakkan folder proyek di `C:\laragon\www\hris-presensi`.
2. Jalankan Laragon, lalu pastikan **Apache** dan **MySQL** berstatus aktif.
3. Buka `http://localhost/phpmyadmin`, pilih **Import**, lalu impor file `database/schema.sql`. Skrip ini membuat database `hris_presensi`, tabel, dan akun demo.
4. Buka PowerShell di folder proyek dan buat konfigurasi lokal:

   ```powershell
   Copy-Item .env.example .env
   ```

   Konfigurasi contoh menggunakan MySQL Laragon standar (`127.0.0.1`, port `3306`, pengguna `root`, tanpa kata sandi). Ubah nilai `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASS` di `.env` bila pengaturan Laragon Anda berbeda.
5. Buka `http://localhost/hris-presensi/` dan masuk dengan salah satu akun demo di bawah.

Setelah login admin, buka **Pengaturan** dan sesuaikan koordinat kantor dengan lokasi yang akan dipakai. Titik awal pada database adalah contoh di Surabaya; presensi dari lokasi lain akan ditolak sampai titik dan radius kantor disesuaikan.

Jika database proyek ini sudah ada, impor ulang `database/schema.sql` sekali untuk menambahkan kolom yang belum tersedia. Tabel dan data yang sudah ada tetap dipertahankan; akun lama memakai tanggal pembuatan akun sebagai tanggal mulai kerja awal. Admin dapat mengoreksi tanggal dan memasukkan nominal BPJS bulanan di halaman karyawan.

PHP CLI Laragon bisa dijalankan langsung dari PowerShell. Contoh untuk memeriksa sintaks satu file:

```powershell
& 'C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe' -l .\index.php
```

Jika versi direktori PHP Anda berbeda, sesuaikan lokasinya di perintah tersebut. Presensi membutuhkan izin GPS dan kamera dari browser; keduanya tersedia melalui `localhost` atau situs HTTPS.

## Akun demo

| Peran | Email | Kata sandi |
|---|---|---|
| Admin HR | `admin@hris.test` | `admin123` |
| Karyawan | `budi@hris.test` | `karyawan123` |

Ganti kata sandi demo sebelum memasukkan data nyata. Sistem menyimpan kata sandi menggunakan `password_hash()`.

## Alur fitur

- **Karyawan:** melihat ringkasan dan riwayat presensi, melakukan presensi masuk/pulang dengan GPS dan selfie, mengirim pengajuan (lampiran surat dokter wajib untuk sakit), serta melihat slip gaji sendiri.
- **Admin:** melihat rekap presensi, memverifikasi pengajuan, mengatur titik dan radius kantor serta jam kerja, membuat dan mengelola akun/status karyawan, mengatur tanggal mulai kerja dan komponen gaji, mengelola kalender hari libur, menghitung payroll, menandai payroll telah dibayar, dan meninjau log aktivitas penting.
- **Validasi presensi:** server menghitung ulang jarak dengan rumus Haversine dan menolak lokasi di luar radius atau akurasi GPS di atas 100 meter. Browser harus mengirim foto selfie JPEG.
- **Payroll demo:** gaji pokok diprorata dari tanggal mulai kerja + tunjangan makan dan transport per hari hadir + lembur yang disetujui − potongan keterlambatan − potongan alpha − nominal potongan BPJS bulanan yang diatur manual. Hari libur yang ditambahkan admin ke kalender tidak dihitung sebagai hari kerja. Payroll baru dapat dihitung setelah bulan periode berakhir. Nominal BPJS tidak dihitung otomatis dari tarif hukum.
- **Slip gaji:** tombol cetak browser dapat digunakan untuk mencetak atau menyimpan slip sebagai PDF.

## Keamanan dan batasan

- Formulir menggunakan token CSRF, kueri database menggunakan PDO prepared statements, dan sesi diregenerasi setelah login.
- Foto presensi serta lampiran disimpan di `storage/` dan dilindungi dari akses langsung. Akses file diperiksa berdasarkan pemilik atau peran admin.
- Tindakan presensi, pengajuan, pengelolaan karyawan, kalender libur, dan payroll dicatat pada halaman **Aktivitas**. Log tidak menyimpan kata sandi atau isi lampiran; tentukan retensi log sesuai kebijakan perusahaan.
- Data lokasi GPS dapat dipalsukan pada perangkat yang dikompromikan. GPS dan selfie membantu pemeriksaan operasional, tetapi bukan bukti anti-kecurangan yang kuat.
- Pustaka antarmuka dan tile peta dimuat dari CDN, jadi browser memerlukan koneksi internet.
- Ini adalah fondasi demo/portofolio, bukan payroll legal siap produksi. Aturan pajak, BPJS, hari libur, lembur, dan kebijakan perusahaan perlu ditinjau sebelum digunakan untuk pembayaran nyata.

## Verifikasi

Jalankan pemeriksaan rumus payroll tanpa dependensi tambahan:

```powershell
& 'C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe' .\tests\run.php
```

GPS dan kamera tetap perlu diperiksa manual pada ponsel/peramban yang akan digunakan: izinkan akses perangkat, cek lokasi di dalam dan di luar radius, ambil selfie, lalu uji presensi masuk dan pulang. Pemeriksaan perangkat fisik tidak dapat digantikan oleh pengujian lokal otomatis.

## Struktur folder

```text
hris-presensi/
├── admin/                 # Pengaturan, karyawan, verifikasi, rekap, payroll, audit
├── api/attendance.php     # Endpoint presensi dengan validasi server
├── assets/                # CSS dan JavaScript
├── config/                # Bootstrap aplikasi dan koneksi PDO
├── database/schema.sql    # Skema dan data demo
├── includes/              # Helper dan layout
├── storage/               # Foto dan lampiran privat
├── tests/run.php          # Pemeriksaan rumus payroll
├── dashboard.php
├── login.php
├── pengajuan.php
├── presensi.php
└── slip.php
```
