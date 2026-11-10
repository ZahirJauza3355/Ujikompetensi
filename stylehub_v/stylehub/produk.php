<?php
require __DIR__ . '/includes/functions.php';
cek_login();                       // admin & kasir boleh MELIHAT; hanya admin boleh mengubah
$pdo   = db();
$admin = ($_SESSION['role'] === 'admin');

// ===== SIMPAN / HAPUS (khusus admin) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_admin();
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    try {
        if (isset($_POST['hapus'])) {
            $pdo->prepare('DELETE FROM products WHERE product_id = ?')->execute([$id]);
            pesan('Produk dan variasinya dihapus.');
        } else {
            $cat    = (int)$_POST['category_id'];
            $kode   = strtoupper(trim($_POST['kode_produk']));
            $nama   = trim($_POST['nama_produk']);
            $harga  = $_POST['harga_jual'];
            $status = ((int)$_POST['status'] === 1) ? 1 : 0;

            if ($cat <= 0 || $kode === '' || $nama === '') {
                pesan('Kategori, kode, dan nama produk wajib diisi.');
            } elseif (!is_numeric($harga) || $harga < 0) {      // aturan bisnis: harga tidak boleh negatif
                pesan('Harga jual harus angka dan tidak boleh negatif.');
            } elseif ($id > 0) {
                $pdo->prepare('UPDATE products SET category_id=?, kode_produk=?, nama_produk=?, harga_jual=?, status=? WHERE product_id=?')
                    ->execute([$cat, $kode, $nama, $harga, $status, $id]);
                pesan('Produk diubah.');
            } else {
                $pdo->prepare('INSERT INTO products (category_id, kode_produk, nama_produk, harga_jual, status) VALUES (?,?,?,?,?)')
                    ->execute([$cat, $kode, $nama, $harga, $status]);
                pesan('Produk ditambah. Lanjutkan dengan menambah variasinya di menu Variasi & Stok.');
            }
        }
    } catch (PDOException $e) {
        pesan(pesan_db($e));
    }
    redirect('produk.php');
}

// ===== AMBIL DATA =====
$edit = null;
if ($admin && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}
$kategori = $pdo->query('SELECT * FROM categories ORDER BY nama_kategori')->fetchAll();

// JOIN: tampilkan nama kategori + jumlah variasi + total stok
$data = $pdo->query(
    'SELECT p.*, c.nama_kategori, COUNT(v.variant_id) AS jml_variasi, COALESCE(SUM(v.stok), 0) AS total_stok
       FROM products p
       JOIN categories c ON c.category_id = p.category_id
       LEFT JOIN product_variants v ON v.product_id = p.product_id
      GROUP BY p.product_id, p.category_id, p.kode_produk, p.nama_produk, p.harga_jual, p.status, c.nama_kategori
      ORDER BY p.product_id'
)->fetchAll();

$judul = 'Produk';
require __DIR__ . '/includes/header.php';
?>
<?php if ($admin): ?>
<form method="post">
    <input type="hidden" name="id" value="<?= $edit ? $edit['product_id'] : 0 ?>">
    <table>
        <tr><td>Kategori</td><td>
            <select name="category_id" required>
                <option value="">-- pilih --</option>
                <?php foreach ($kategori as $k): ?>
                    <option value="<?= $k['category_id'] ?>" <?= ($edit && $edit['category_id'] == $k['category_id']) ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
                <?php endforeach; ?>
            </select></td></tr>
        <tr><td>Kode produk</td><td><input type="text" name="kode_produk" maxlength="25" required value="<?= e($edit ? $edit['kode_produk'] : '') ?>"></td></tr>
        <tr><td>Nama produk</td><td><input type="text" name="nama_produk" maxlength="100" required value="<?= e($edit ? $edit['nama_produk'] : '') ?>"></td></tr>
        <tr><td>Harga jual (Rp)</td><td><input type="number" name="harga_jual" min="0" step="any" required value="<?= e($edit ? $edit['harga_jual'] : '') ?>"></td></tr>
        <tr><td>Status</td><td>
            <select name="status">
                <option value="1" <?= (!$edit || $edit['status'] == 1) ? 'selected' : '' ?>>Aktif</option>
                <option value="0" <?= ($edit && $edit['status'] == 0) ? 'selected' : '' ?>>Nonaktif</option>
            </select></td></tr>
    </table>
    <br>
    <button type="submit"><?= $edit ? 'Ubah' : 'Tambah' ?></button>
    <?php if ($edit): ?><a class="btn" href="produk.php">Batal</a><?php endif; ?>
</form>
<br>
<?php endif; ?>

<table class="tabel">
    <tr>
        <th>Kode</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Jumlah Variasi</th><th>Total Stok</th><th>Status</th>
        <?php if ($admin): ?><th>Aksi</th><?php endif; ?>
    </tr>
    <?php foreach ($data as $r): ?>
    <tr>
        <td><?= e($r['kode_produk']) ?></td>
        <td><?= e($r['nama_produk']) ?></td>
        <td><?= e($r['nama_kategori']) ?></td>
        <td><?= rupiah($r['harga_jual']) ?></td>
        <td><?= $r['jml_variasi'] ?></td>
        <td><?= $r['total_stok'] ?></td>
        <td><?= $r['status'] == 1 ? 'Aktif' : 'Nonaktif' ?></td>
        <?php if ($admin): ?>
        <td>
            <a class="btn btn-sm" href="produk.php?edit=<?= $r['product_id'] ?>">Edit</a>
            <form method="post" class="inline" onsubmit="return confirm('Hapus produk beserta semua variasinya?')">
                <input type="hidden" name="id" value="<?= $r['product_id'] ?>">
                <button type="submit" name="hapus" class="btn-danger btn-sm">Hapus</button>
            </form>
        </td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
