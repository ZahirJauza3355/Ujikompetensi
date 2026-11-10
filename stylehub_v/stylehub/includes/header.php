<?php
/**
 * Bagian atas semua halaman (setelah login): header, menu, judul, dan pesan.
 * Sebelum memanggil file ini, halaman boleh mengisi:
 *   $judul        -> judul halaman
 *   $tanpa_kartu  -> true jika isi halaman tidak dibungkus kartu putih (dipakai dashboard)
 */
require_once __DIR__ . '/functions.php';
cek_login();
$judul       = isset($judul) ? $judul : 'StyleHub';
$tanpa_kartu = isset($tanpa_kartu) ? $tanpa_kartu : false;
$admin       = ($_SESSION['role'] === 'admin');
$halaman     = basename($_SERVER['PHP_SELF']);

// [file, label, hanya admin?, hanya kasir?]
$menu = [
    ['dashboard.php',         'Menu',              false],
    ['pembayaran.php',        'Pembayaran',        false, true],
    ['riwayat_pembayaran.php','Riwayat Pembayaran',false],
    ['produk.php',            'Produk',            false],
    ['variasi.php',           'Variasi & Stok',    true],
    ['kategori.php',          'Kategori',          true],
    ['ukuran_warna.php',      'Ukuran & Warna',    true],
    ['laporan_stok.php',      'Laporan Stok',      false],
    ['users.php',             'Pengguna',          true],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> - StyleHub</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="header">
    <div class="container">
        <div class="topbar">
            <a href="dashboard.php" class="logo">Style<span>Hub</span></a>
            <div class="user-info">
                <span><b><?= e($_SESSION['nama']) ?></b> (<?= e($_SESSION['role']) ?>)</span>
                <a href="logout.php" class="btn btn-keluar">Keluar</a>
            </div>
        </div>
        <nav class="menu">
            <?php foreach ($menu as $m): ?>
                <?php if ($m[2] && !$admin) continue; ?>
                <?php if (!empty($m[3]) && $admin) continue; ?>
                <a href="<?= $m[0] ?>" class="<?= $halaman === $m[0] ? 'aktif' : '' ?>"><?= e($m[1]) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>

<main class="section">
<div class="container">
    <?php if (!$tanpa_kartu): ?><h1 class="judul-halaman"><?= e($judul) ?></h1><?php endif; ?>
    <?php
    if (!empty($_SESSION['pesan'])) {
        echo '<div class="pesan">' . e($_SESSION['pesan']) . '</div>';
        unset($_SESSION['pesan']);
    }
    ?>
    <?php if (!$tanpa_kartu): ?><div class="card"><?php endif; ?>