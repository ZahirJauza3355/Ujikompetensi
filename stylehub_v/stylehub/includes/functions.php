<?php
/**
 * Fungsi bantu yang dipakai semua halaman.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// Menampilkan teks dengan aman di HTML (mencegah XSS). Selalu dipakai saat menampilkan data.
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function rupiah($n)
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function tanggal($dt, $dengan_jam = true)
{
    if (!$dt) return '-';
    return date($dengan_jam ? 'd/m/Y H:i' : 'd/m/Y', strtotime($dt));
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

// Menyimpan pesan yang akan tampil sekali di halaman berikutnya
function pesan($teks)
{
    $_SESSION['pesan'] = $teks;
}

// Halaman wajib login
function cek_login()
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

// Halaman khusus admin
function cek_admin()
{
    cek_login();
    if ($_SESSION['role'] !== 'admin') {
        exit('Akses ditolak: halaman ini hanya untuk admin. <a href="dashboard.php">Kembali</a>');
    }
}

// Halaman khusus kasir (admin tidak boleh memakai fitur pembayaran)
function cek_kasir()
{
    cek_login();
    if ($_SESSION['role'] !== 'kasir') {
        exit('Akses ditolak: fitur pembayaran hanya untuk kasir. <a href="dashboard.php">Kembali</a>');
    }
}

// Mengubah error database menjadi pesan yang mudah dipahami
function pesan_db(PDOException $e)
{
    $kode = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
    if ($kode === 1062) return 'Gagal: data kembar (nilai yang harus unik sudah dipakai).';
    if ($kode === 1451) return 'Gagal: data masih dipakai data lain, jadi tidak bisa dihapus.';
    if ($kode === 3819) return 'Gagal: nilai ditolak aturan database (contoh: harga atau stok negatif).';
    return 'Terjadi kesalahan database.';
}