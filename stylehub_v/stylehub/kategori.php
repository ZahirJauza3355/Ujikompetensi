<?php
require __DIR__ . '/includes/functions.php';
cek_admin();
$pdo = db();

// ===== SIMPAN / HAPUS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    try {
        if (isset($_POST['hapus'])) {
            $pdo->prepare('DELETE FROM categories WHERE category_id = ?')->execute([$id]);
            pesan('Kategori dihapus.');
        } else {
            $nama = trim($_POST['nama_kategori']);
            if ($nama === '') {
                pesan('Nama kategori wajib diisi.');
            } elseif ($id > 0) {
                $pdo->prepare('UPDATE categories SET nama_kategori = ? WHERE category_id = ?')->execute([$nama, $id]);
                pesan('Kategori diubah.');
            } else {
                $pdo->prepare('INSERT INTO categories (nama_kategori) VALUES (?)')->execute([$nama]);
                pesan('Kategori ditambah.');
            }
        }
    } catch (PDOException $e) {
        pesan(pesan_db($e));
    }
    redirect('kategori.php');
}

// ===== AMBIL DATA =====
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM categories WHERE category_id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}
$data = $pdo->query('SELECT * FROM categories ORDER BY category_id')->fetchAll();

$judul = 'Kategori';
require __DIR__ . '/includes/header.php';
?>
<form method="post" class="form-baris">
    <input type="hidden" name="id" value="<?= $edit ? $edit['category_id'] : 0 ?>">
    Nama kategori:
    <input type="text" name="nama_kategori" maxlength="60" required value="<?= e($edit ? $edit['nama_kategori'] : '') ?>">
    <button type="submit"><?= $edit ? 'Ubah' : 'Tambah' ?></button>
    <?php if ($edit): ?><a class="btn" href="kategori.php">Batal</a><?php endif; ?>
</form>
<br>

<table class="tabel">
    <tr><th>ID</th><th>Nama Kategori</th><th>Aksi</th></tr>
    <?php foreach ($data as $r): ?>
    <tr>
        <td><?= $r['category_id'] ?></td>
        <td><?= e($r['nama_kategori']) ?></td>
        <td>
            <a class="btn btn-sm" href="kategori.php?edit=<?= $r['category_id'] ?>">Edit</a>
            <form method="post" class="inline" onsubmit="return confirm('Hapus kategori ini?')">
                <input type="hidden" name="id" value="<?= $r['category_id'] ?>">
                <button type="submit" name="hapus" class="btn-danger btn-sm">Hapus</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
