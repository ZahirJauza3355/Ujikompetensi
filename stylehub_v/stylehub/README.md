# StyleHub — Aplikasi Kasir Toko Fashion

Aplikasi pengelolaan stok pakaian & aksesori (tampilan seperti portofolio) dengan variasi ukuran/warna, stok per variasi, pembayaran kasir (struk cetak), dan laporan stok.
Dibuat dengan HTML, CSS, PHP, dan MySQL/MariaDB.

## Cara menjalankan (XAMPP)

1. Salin folder `stylehub` ke `C:\xampp\htdocs\`.
2. Buka **XAMPP Control Panel** → *Start* **Apache** dan **MySQL**.
3. Buka `http://localhost/phpmyadmin` → tab **Import** → pilih `stylehub/database.sql` → **Go**.
   (Database `stylehub` dibuat otomatis, lengkap dengan tabel, trigger, dan data uji.)
4. Cek `config/database.php`: bawaan XAMPP (user `root`, password kosong) sudah cocok.
5. Buka `http://localhost/stylehub/` di browser.

Jika memakai **Laragon**, letakkan folder di `C:\laragon\www\` lalu buka `http://stylehub.test/` atau `http://localhost/stylehub/`.

## Akun uji

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Kasir | `kasir` | `kasir123` |

## Persyaratan
PHP 7.4+ (disarankan 8.x), MySQL 8.0.16+ atau MariaDB 10.5+ (butuh dukungan CHECK constraint, trigger, function).

## Dokumentasi
Lihat `docs/DOKUMENTASI.md` (flowchart, ERD, struktur program, tabel pengujian) dan `tests/test_database.sql` (skrip uji).
