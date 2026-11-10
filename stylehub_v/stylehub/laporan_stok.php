<?php
/** LAPORAN STOK: produk, ukuran, warna, stok, dan status (status dihitung FUNCTION fn_status_stok). */
require __DIR__ . '/includes/functions.php';
cek_login();
$pdo = db();

$hanya_rendah = isset($_GET['rendah']);

$sql = 'SELECT nama_produk, nama_kategori, nama_ukuran, nama_warna, stok, status_stok FROM v_variants';
if ($hanya_rendah) {
    $sql .= " WHERE status_stok IN ('RESTOK', 'HABIS')";
}
$sql .= " ORDER BY (status_stok = 'AMAN'), stok ASC, nama_produk";   // yang bermasalah tampil paling atas
$data = $pdo->query($sql)->fetchAll();

$judul = 'Laporan Stok';
require __DIR__ . '/includes/header.php';
?>
<form method="get" class="form-baris">
    <label><input type="checkbox" name="rendah" value="1" <?= $hanya_rendah ? 'checked' : '' ?>> Hanya stok rendah dan habis</label>
    <button type="submit">Tampilkan</button>
</form>
<p>Keterangan status: AMAN (stok di atas 5), RESTOK (stok 1 sampai 5, perlu restok), HABIS (stok 0).</p>

<table class="tabel">
    <tr><th>Produk</th><th>Kategori</th><th>Ukuran</th><th>Warna</th><th>Stok</th><th>Status</th></tr>
    <?php foreach ($data as $r): ?>
    <tr>
        <td><?= e($r['nama_produk']) ?></td>
        <td><?= e($r['nama_kategori']) ?></td>
        <td><?= e($r['nama_ukuran'] ?: '-') ?></td>
        <td><?= e($r['nama_warna'] ?: '-') ?></td>
        <td><?= $r['stok'] ?></td>
        <td><span class="badge badge-<?= strtolower($r['status_stok']) ?>"><?= e($r['status_stok']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$data): ?><tr><td colspan="7">Tidak ada data.</td></tr><?php endif; ?>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
