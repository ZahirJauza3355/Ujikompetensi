<?php
require __DIR__ . '/includes/functions.php';
cek_admin();
$pdo = db();

// ===== SIMPAN / HAPUS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    try {
        if (isset($_POST['hapus'])) {
            $pdo->prepare('DELETE FROM product_variants WHERE variant_id = ?')->execute([$id]);
            pesan('Variasi dihapus.');
        } else {
            $pid   = (int)$_POST['product_id'];
            $size  = (int)$_POST['size_id'] ?: null;       // 0 -> NULL (tanpa ukuran)
            $color = (int)$_POST['color_id'] ?: null;
            $stok  = $_POST['stok'];
            $harga = trim($_POST['harga_jual']) === '' ? null : $_POST['harga_jual'];   // kosong = ikut harga produk

            // Cek kombinasi produk + ukuran + warna tidak ganda
            $dup = $pdo->prepare('SELECT COUNT(*) FROM product_variants WHERE product_id = ? AND size_id <=> ? AND color_id <=> ? AND variant_id <> ?');
            $dup->execute([$pid, $size, $color, $id]);

            if ($pid <= 0) {
                pesan('Produk wajib diisi.');
            } elseif (!ctype_digit((string)$stok)) {
                pesan('Stok harus angka 0 atau lebih.');
            } elseif ($harga !== null && (!is_numeric($harga) || $harga < 0)) {
                pesan('Harga khusus tidak boleh negatif.');
            } elseif ($dup->fetchColumn() > 0) {
                pesan('Kombinasi produk, ukuran, dan warna itu sudah ada.');
            } elseif ($id > 0) {
                $pdo->prepare('UPDATE product_variants SET product_id=?, size_id=?, color_id=?, stok=?, harga_jual=? WHERE variant_id=?')
                    ->execute([$pid, $size, $color, $stok, $harga, $id]);
                pesan('Variasi diubah.');
            } else {
                $pdo->prepare('INSERT INTO product_variants (product_id, size_id, color_id, stok, harga_jual) VALUES (?,?,?,?,?)')
                    ->execute([$pid, $size, $color, $stok, $harga]);
                pesan('Variasi ditambah.');
            }
        }
    } catch (PDOException $e) {
        // variasi sudah pernah dibayar (tidak bisa dihapus) atau aturan nilai dilanggar
        pesan(pesan_db($e));
    }
    redirect('variasi.php');
}

// ===== AMBIL DATA =====
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM product_variants WHERE variant_id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}
$produk = $pdo->query('SELECT product_id, kode_produk, nama_produk FROM products ORDER BY nama_produk')->fetchAll();
$ukuran = $pdo->query('SELECT * FROM sizes ORDER BY size_id')->fetchAll();
$warna  = $pdo->query('SELECT * FROM colors ORDER BY color_id')->fetchAll();
$data   = $pdo->query('SELECT variant_id, nama_produk, nama_ukuran, nama_warna, stok, harga, status_stok FROM v_variants ORDER BY nama_produk, variant_id')->fetchAll();

$judul = 'Variasi & Stok';
require __DIR__ . '/includes/header.php';
?>
<form method="post">
    <input type="hidden" name="id" value="<?= $edit ? $edit['variant_id'] : 0 ?>">
    <table>
        <tr><td>Produk</td><td>
            <select name="product_id" required>
                <option value="">-- pilih --</option>
                <?php foreach ($produk as $p): ?>
                    <option value="<?= $p['product_id'] ?>" <?= ($edit && $edit['product_id'] == $p['product_id']) ? 'selected' : '' ?>><?= e($p['kode_produk'] . ' - ' . $p['nama_produk']) ?></option>
                <?php endforeach; ?>
            </select></td></tr>
        <tr><td>Ukuran</td><td>
            <select name="size_id">
                <option value="0">-- tanpa ukuran --</option>
                <?php foreach ($ukuran as $u): ?>
                    <option value="<?= $u['size_id'] ?>" <?= ($edit && $edit['size_id'] == $u['size_id']) ? 'selected' : '' ?>><?= e($u['nama_ukuran']) ?></option>
                <?php endforeach; ?>
            </select></td></tr>
        <tr><td>Warna</td><td>
            <select name="color_id">
                <option value="0">-- tanpa warna --</option>
                <?php foreach ($warna as $w): ?>
                    <option value="<?= $w['color_id'] ?>" <?= ($edit && $edit['color_id'] == $w['color_id']) ? 'selected' : '' ?>><?= e($w['nama_warna']) ?></option>
                <?php endforeach; ?>
            </select></td></tr>
        <tr><td>Stok</td><td><input type="number" name="stok" min="0" step="1" required value="<?= e($edit ? $edit['stok'] : '0') ?>"></td></tr>
        <tr><td>Harga khusus (opsional)</td><td><input type="number" name="harga_jual" min="0" step="any" value="<?= e($edit && $edit['harga_jual'] !== null ? $edit['harga_jual'] : '') ?>"> kosong = ikut harga produk</td></tr>
    </table>
    <br>
    <button type="submit"><?= $edit ? 'Ubah' : 'Tambah' ?></button>
    <?php if ($edit): ?><a class="btn" href="variasi.php">Batal</a><?php endif; ?>
</form>
<br>

<table class="tabel">
    <tr><th>Produk</th><th>Ukuran</th><th>Warna</th><th>Harga</th><th>Stok</th><th>Status</th><th>Aksi</th></tr>
    <?php foreach ($data as $r): ?>
    <tr>
        <td><?= e($r['nama_produk']) ?></td>
        <td><?= e($r['nama_ukuran'] ?: '-') ?></td>
        <td><?= e($r['nama_warna'] ?: '-') ?></td>
        <td><?= rupiah($r['harga']) ?></td>
        <td><?= $r['stok'] ?></td>
        <td><span class="badge badge-<?= strtolower($r['status_stok']) ?>"><?= e($r['status_stok']) ?></span></td>
        <td>
            <a class="btn btn-sm" href="variasi.php?edit=<?= $r['variant_id'] ?>">Edit</a>
            <form method="post" class="inline" onsubmit="return confirm('Hapus variasi ini?')">
                <input type="hidden" name="id" value="<?= $r['variant_id'] ?>">
                <button type="submit" name="hapus" class="btn-danger btn-sm">Hapus</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
