<?php
/** Struk pembayaran siap cetak. Klik "Cetak struk" (atau Ctrl+P). */
require __DIR__ . '/includes/functions.php';
cek_login();
$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$st = $pdo->prepare(
    'SELECT p.*, u.nama AS kasir
       FROM payments p
       JOIN users u ON u.user_id = p.user_id
      WHERE p.payment_id = ?'
);
$st->execute([$id]);
$bayar = $st->fetch();
if (!$bayar) {
    exit('Struk tidak ditemukan. <a href="pembayaran.php">Kembali ke pembayaran</a>');
}

// Detail: produk, ukuran, warna, qty, harga, subtotal (JOIN beberapa tabel)
$st = $pdo->prepare(
    'SELECT pr.nama_produk, sz.nama_ukuran, cl.nama_warna, i.qty, i.harga_jual, i.subtotal
       FROM payment_items i
       JOIN product_variants v ON v.variant_id = i.variant_id
       JOIN products pr ON pr.product_id = v.product_id
       LEFT JOIN sizes sz ON sz.size_id = v.size_id
       LEFT JOIN colors cl ON cl.color_id = v.color_id
      WHERE i.payment_id = ? ORDER BY i.item_id'
);
$st->execute([$id]);
$items = $st->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk <?= e($bayar['no_struk']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="struk-page">
<div class="struk-aksi no-print">
    <button onclick="window.print()">Cetak struk</button>
    <?php if ($_SESSION['role'] === 'kasir'): ?>
    <a class="btn" href="pembayaran.php">Pembayaran baru</a>
    <?php endif; ?>
    <a class="btn" href="riwayat_pembayaran.php">Riwayat</a>
</div>

<div class="struk">
    <h2>Style<span>Hub</span></h2>
    <p class="muted">Toko Fashion - Pakaian &amp; Aksesori</p>
    <hr>
    <table class="info">
        <tr><td>No. Struk</td><td>: <?= e($bayar['no_struk']) ?></td></tr>
        <tr><td>Tanggal</td><td>: <?= tanggal($bayar['tgl_pembayaran']) ?></td></tr>
        <tr><td>Kasir</td><td>: <?= e($bayar['kasir']) ?></td></tr>
    </table>
    <hr>
    <table class="tabel">
        <tr><th>Produk</th><th>Ukuran</th><th>Warna</th><th>Qty</th><th>Harga</th><th>Jumlah</th></tr>
        <?php foreach ($items as $it): ?>
        <tr>
            <td><?= e($it['nama_produk']) ?></td>
            <td><?= e($it['nama_ukuran'] ?: '-') ?></td>
            <td><?= e($it['nama_warna'] ?: '-') ?></td>
            <td><?= $it['qty'] ?></td>
            <td><?= rupiah($it['harga_jual']) ?></td>
            <td><?= rupiah($it['subtotal']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <hr>
    <table class="info">
        <tr><td>Subtotal</td><td>: <?= rupiah($bayar['subtotal']) ?></td></tr>
        <tr><td>Diskon</td><td>: <?= rupiah($bayar['diskon']) ?></td></tr>
        <tr><td><b>TOTAL</b></td><td>: <b><?= rupiah($bayar['total']) ?></b></td></tr>
        <tr><td>Metode</td><td>: <?= e($bayar['metode']) ?></td></tr>
        <tr><td>Dibayar</td><td>: <?= rupiah($bayar['dibayar']) ?></td></tr>
        <tr><td>Kembalian</td><td>: <?= rupiah($bayar['kembalian']) ?></td></tr>
    </table>
    <hr>
    <p class="muted">Terima kasih sudah berbelanja!</p>
</div>
</body>
</html>