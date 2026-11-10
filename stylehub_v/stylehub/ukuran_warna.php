<?php
require __DIR__ . '/includes/functions.php';
cek_admin();
$pdo = db();

// Dua master dalam satu halaman. Nama tabel/kolom ditulis tetap di sini (bukan dari input pengguna).
$master = [
    'ukuran' => ['tabel' => 'sizes',  'id' => 'size_id',  'nama' => 'nama_ukuran', 'maks' => 20, 'label' => 'Ukuran'],
    'warna'  => ['tabel' => 'colors', 'id' => 'color_id', 'nama' => 'nama_warna',  'maks' => 40, 'label' => 'Warna'],
];

// ===== SIMPAN / HAPUS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipe = isset($_POST['tipe']) ? $_POST['tipe'] : '';
    if (isset($master[$tipe])) {
        $m  = $master[$tipe];
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        try {
            if (isset($_POST['hapus'])) {
                $pdo->prepare("DELETE FROM {$m['tabel']} WHERE {$m['id']} = ?")->execute([$id]);
                pesan($m['label'] . ' dihapus.');
            } else {
                $nama = trim($_POST['nama']);
                if ($nama === '') {
                    pesan($m['label'] . ' wajib diisi.');
                } elseif ($id > 0) {
                    $pdo->prepare("UPDATE {$m['tabel']} SET {$m['nama']} = ? WHERE {$m['id']} = ?")->execute([$nama, $id]);
                    pesan($m['label'] . ' diubah.');
                } else {
                    $pdo->prepare("INSERT INTO {$m['tabel']} ({$m['nama']}) VALUES (?)")->execute([$nama]);
                    pesan($m['label'] . ' ditambah.');
                }
            }
        } catch (PDOException $e) {
            pesan(pesan_db($e));
        }
    }
    redirect('ukuran_warna.php');
}

// ===== AMBIL DATA =====
$edit = ['ukuran' => null, 'warna' => null];
$data = [];
foreach ($master as $tipe => $m) {
    if (isset($_GET['edit_' . $tipe])) {
        $st = $pdo->prepare("SELECT {$m['id']} AS id, {$m['nama']} AS nama FROM {$m['tabel']} WHERE {$m['id']} = ?");
        $st->execute([(int)$_GET['edit_' . $tipe]]);
        $edit[$tipe] = $st->fetch();
    }
    $data[$tipe] = $pdo->query("SELECT {$m['id']} AS id, {$m['nama']} AS nama FROM {$m['tabel']} ORDER BY {$m['id']}")->fetchAll();
}

$judul = 'Ukuran & Warna';
require __DIR__ . '/includes/header.php';
?>
<?php foreach ($master as $tipe => $m): $ed = $edit[$tipe]; ?>
    <h3><?= e($m['label']) ?></h3>
    <form method="post" class="form-baris">
        <input type="hidden" name="tipe" value="<?= $tipe ?>">
        <input type="hidden" name="id" value="<?= $ed ? $ed['id'] : 0 ?>">
        <input type="text" name="nama" maxlength="<?= $m['maks'] ?>" required value="<?= e($ed ? $ed['nama'] : '') ?>">
        <button type="submit"><?= $ed ? 'Ubah' : 'Tambah' ?></button>
        <?php if ($ed): ?><a class="btn" href="ukuran_warna.php">Batal</a><?php endif; ?>
    </form>
    <br>
    <table class="tabel">
        <tr><th>ID</th><th><?= e($m['label']) ?></th><th>Aksi</th></tr>
        <?php foreach ($data[$tipe] as $r): ?>
        <tr>
            <td><?= $r['id'] ?></td>
            <td><?= e($r['nama']) ?></td>
            <td>
                <a class="btn btn-sm" href="ukuran_warna.php?edit_<?= $tipe ?>=<?= $r['id'] ?>">Edit</a>
                <form method="post" class="inline" onsubmit="return confirm('Hapus data ini?')">
                    <input type="hidden" name="tipe" value="<?= $tipe ?>">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button type="submit" name="hapus" class="btn-danger btn-sm">Hapus</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <br>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
