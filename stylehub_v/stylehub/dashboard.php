<?php
$judul       = 'Menu';
$tanpa_kartu = true;          // dashboard memakai hero + kartu menu, bukan satu kartu besar
require __DIR__ . '/includes/header.php';
$admin = ($_SESSION['role'] === 'admin');
?>
<section class="hero">
    <p class="eyebrow">Halo,</p>
    <h1><?= e($_SESSION['nama']) ?></h1>
    <p>Masuk sebagai <b><?= e($_SESSION['role']) ?></b>. Pilih menu di bawah untuk mulai bekerja.</p>
</section>

<div class="cards">
    <?php if (!$admin): ?>
    <a class="menu-card" href="pembayaran.php">
        <span class="tag">Kasir</span>
        <h3>Pembayaran</h3>
        <p>Pilih barang, terima bayar, dan cetak struk.</p>
    </a>
    <?php endif; ?>
    <a class="menu-card" href="riwayat_pembayaran.php">
        <span class="tag">Kasir</span>
        <h3>Riwayat Pembayaran</h3>
        <p>Daftar struk, omzet, dan rekap per metode.</p>
    </a>
    <a class="menu-card" href="produk.php">
        <span class="tag">Data</span>
        <h3>Produk</h3>
        <p>Daftar produk, harga, dan total stok.</p>
    </a>
    <a class="menu-card" href="laporan_stok.php">
        <span class="tag">Laporan</span>
        <h3>Laporan Stok</h3>
        <p>Stok per variasi dan peringatan restok.</p>
    </a>
    <?php if ($admin): ?>
    <a class="menu-card" href="variasi.php">
        <span class="tag">Admin</span>
        <h3>Variasi &amp; Stok</h3>
        <p>Atur ukuran, warna, dan stok.</p>
    </a>
    <a class="menu-card" href="kategori.php">
        <span class="tag">Admin</span>
        <h3>Kategori</h3>
        <p>Jenis pakaian dan aksesori.</p>
    </a>
    <a class="menu-card" href="ukuran_warna.php">
        <span class="tag">Admin</span>
        <h3>Ukuran &amp; Warna</h3>
        <p>Daftar ukuran dan warna.</p>
    </a>
    <a class="menu-card" href="users.php">
        <span class="tag">Admin</span>
        <h3>Pengguna</h3>
        <p>Akun admin dan kasir.</p>
    </a>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>