<?php
/**
 * RIWAYAT PEMBAYARAN: daftar struk per periode, omzet, dan rekap per metode.
 * Admin dan kasir boleh melihat. Klik nomor struk untuk membuka / mencetak ulang.
 */
require __DIR__ . '/includes/functions.php';
cek_login();
$pdo = db();

// Filter tanggal (bawaan: 30 hari terakhir). Validasi format supaya aman.
$cek   = function ($t, $bawaan) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$t);
    return ($d && $d->format('Y-m-d') === $t) ? $t : $bawaan;
};
$awal  = $cek(isset($_GET['awal'])  ? $_GET['awal']  : '', date('Y-m-d', strtotime('-30 days')));
$akhir = $cek(isset($_GET['akhir']) ? $_GET['akhir'] : '', date('Y-m-d'));
if ($awal > $akhir) { $t = $awal; $awal = $akhir; $akhir = $t; }

$metode = isset($_GET['metode']) ? $_GET['metode'] : '';
if (!in_array($metode, ['Tunai', 'Transfer', 'QRIS'], true)) $metode = '';

$where  = 'p.tgl_pembayaran >= ? AND p.tgl_pembayaran < DATE_ADD(?, INTERVAL 1 DAY)';
$param  = [$awal, $akhir];
if ($metode !== '') { $where .= ' AND p.metode = ?'; $param[] = $metode; }

// 1) Ringkasan: jumlah struk, omzet, total diskon
$st = $pdo->prepare("SELECT COUNT(*) AS jumlah, COALESCE(SUM(p.total),0) AS omzet, COALESCE(SUM(p.diskon),0) AS diskon
                       FROM payments p WHERE $where");
$st->execute($param);
$ringkas = $st->fetch();

// 2) Rekap per metode pembayaran
$st = $pdo->prepare("SELECT p.metode, COUNT(*) AS jumlah, SUM(p.total) AS omzet
                       FROM payments p WHERE $where GROUP BY p.metode ORDER BY omzet DESC");
$st->execute($param);
$per_metode = $st->fetchAll();

// 3) Daftar struk (jumlah item dihitung dengan subquery)
$st = $pdo->prepare("SELECT p.payment_id, p.no_struk, p.tgl_pembayaran, p.metode, p.diskon, p.total, p.dibayar, p.kembalian,
                            u.nama AS kasir,
                            (SELECT COALESCE(SUM(i.qty),0) FROM payment_items i WHERE i.payment_id = p.payment_id) AS item
                       FROM payments p JOIN users u ON u.user_id = p.user_id
                      WHERE $where ORDER BY p.tgl_pembayaran DESC, p.payment_id DESC LIMIT 200");
$st->execute($param);
$daftar = $st->fetchAll();

$judul = 'Riwayat Pembayaran';
require __DIR__ . '/includes/header.php';
?>
<form method="get">
    <table>
        <tr><td>Dari</td><td><input type="date" name="awal" value="<?= e($awal) ?>"></td></tr>
        <tr><td>Sampai</td><td><input type="date" name="akhir" value="<?= e($akhir) ?>"></td></tr>
        <tr><td>Metode</td><td>
            <select name="metode">
                <option value="">Semua</option>
                <?php foreach (['Tunai', 'Transfer', 'QRIS'] as $m): ?>
                    <option <?= $metode === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endforeach; ?>
            </select></td></tr>
    </table>
    <br>
    <button type="submit">Tampilkan</button>
</form>

<h3>Ringkasan</h3>
<table class="tabel">
    <tr><th>Jumlah struk</th><th>Total diskon</th><th>Omzet</th></tr>
    <tr><td><?= (int)$ringkas['jumlah'] ?></td><td><?= rupiah($ringkas['diskon']) ?></td><td><?= rupiah($ringkas['omzet']) ?></td></tr>
</table>

<h3>Per metode pembayaran</h3>
<table class="tabel">
    <tr><th>Metode</th><th>Jumlah struk</th><th>Omzet</th></tr>
    <?php foreach ($per_metode as $r): ?>
    <tr><td><?= e($r['metode']) ?></td><td><?= (int)$r['jumlah'] ?></td><td><?= rupiah($r['omzet']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$per_metode): ?><tr><td colspan="3">Tidak ada pembayaran pada periode ini.</td></tr><?php endif; ?>
</table>

<h3>Daftar struk</h3>
<table class="tabel">
    <tr><th>No. struk</th><th>Tanggal</th><th>Kasir</th><th>Item</th><th>Metode</th><th>Diskon</th><th>Total</th><th>Kembalian</th></tr>
    <?php foreach ($daftar as $r): ?>
    <tr>
        <td><a href="struk.php?id=<?= $r['payment_id'] ?>"><?= e($r['no_struk']) ?></a></td>
        <td><?= tanggal($r['tgl_pembayaran']) ?></td>
        <td><?= e($r['kasir']) ?></td>
        <td><?= (int)$r['item'] ?></td>
        <td><?= e($r['metode']) ?></td>
        <td><?= rupiah($r['diskon']) ?></td>
        <td><?= rupiah($r['total']) ?></td>
        <td><?= rupiah($r['kembalian']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$daftar): ?><tr><td colspan="8">Tidak ada struk pada periode ini.</td></tr><?php endif; ?>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
