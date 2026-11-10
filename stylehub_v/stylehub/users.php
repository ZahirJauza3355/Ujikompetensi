<?php
require __DIR__ . '/includes/functions.php';
cek_admin();
$pdo = db();

// ===== SIMPAN / HAPUS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    try {
        if (isset($_POST['hapus'])) {
            if ($id === (int)$_SESSION['user_id']) {
                pesan('Tidak bisa menghapus akun yang sedang dipakai.');
            } else {
                $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$id]);
                pesan('Pengguna dihapus.');
            }
        } else {
            $username = trim($_POST['username']);
            $nama     = trim($_POST['nama']);
            $role     = $_POST['role'];
            $password = $_POST['password'];

            if ($username === '' || $nama === '' || !in_array($role, ['admin', 'kasir'], true)) {
                pesan('Username, nama, dan role wajib diisi.');
            } elseif ($id === 0 && strlen($password) < 6) {
                pesan('Password minimal 6 karakter.');
            } elseif ($id > 0 && $password !== '' && strlen($password) < 6) {
                pesan('Password baru minimal 6 karakter (kosongkan jika tidak diganti).');
            } elseif ($id > 0 && $id === (int)$_SESSION['user_id'] && $role !== 'admin') {
                pesan('Anda tidak bisa menurunkan role akun sendiri.');
            } elseif ($id === 0) {
                // password disimpan sebagai HASH, bukan teks biasa
                $pdo->prepare('INSERT INTO users (username, password, nama, role) VALUES (?,?,?,?)')
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $nama, $role]);
                pesan('Pengguna ditambah.');
            } elseif ($password !== '') {
                $pdo->prepare('UPDATE users SET username=?, nama=?, role=?, password=? WHERE user_id=?')
                    ->execute([$username, $nama, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
                pesan('Pengguna diubah (password diganti).');
            } else {
                $pdo->prepare('UPDATE users SET username=?, nama=?, role=? WHERE user_id=?')
                    ->execute([$username, $nama, $role, $id]);
                pesan('Pengguna diubah.');
            }
        }
    } catch (PDOException $e) {
        pesan(pesan_db($e));
    }
    redirect('users.php');
}

// ===== AMBIL DATA =====
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT user_id, username, nama, role FROM users WHERE user_id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}
$data = $pdo->query('SELECT user_id, username, nama, role FROM users ORDER BY user_id')->fetchAll();

$judul = 'Pengguna';
require __DIR__ . '/includes/header.php';
?>
<form method="post" autocomplete="off">
    <input type="hidden" name="id" value="<?= $edit ? $edit['user_id'] : 0 ?>">
    <table>
        <tr><td>Username</td><td><input type="text" name="username" maxlength="30" required value="<?= e($edit ? $edit['username'] : '') ?>"></td></tr>
        <tr><td>Nama</td><td><input type="text" name="nama" maxlength="100" required value="<?= e($edit ? $edit['nama'] : '') ?>"></td></tr>
        <tr><td>Role</td><td>
            <select name="role">
                <option value="kasir" <?= ($edit && $edit['role'] === 'kasir') ? 'selected' : '' ?>>kasir</option>
                <option value="admin" <?= ($edit && $edit['role'] === 'admin') ? 'selected' : '' ?>>admin</option>
            </select></td></tr>
        <tr><td>Password</td><td><input type="password" name="password" <?= $edit ? '' : 'required' ?>> <?= $edit ? '(kosongkan jika tidak diganti)' : '(minimal 6 karakter)' ?></td></tr>
    </table>
    <br>
    <button type="submit"><?= $edit ? 'Ubah' : 'Tambah' ?></button>
    <?php if ($edit): ?><a class="btn" href="users.php">Batal</a><?php endif; ?>
</form>
<br>

<table class="tabel">
    <tr><th>ID</th><th>Username</th><th>Nama</th><th>Role</th><th>Aksi</th></tr>
    <?php foreach ($data as $r): ?>
    <tr>
        <td><?= $r['user_id'] ?></td>
        <td><?= e($r['username']) ?></td>
        <td><?= e($r['nama']) ?></td>
        <td><?= e($r['role']) ?></td>
        <td>
            <a class="btn btn-sm" href="users.php?edit=<?= $r['user_id'] ?>">Edit</a>
            <?php if ((int)$r['user_id'] !== (int)$_SESSION['user_id']): ?>
            <form method="post" class="inline" onsubmit="return confirm('Hapus pengguna ini?')">
                <input type="hidden" name="id" value="<?= $r['user_id'] ?>">
                <button type="submit" name="hapus" class="btn-danger btn-sm">Hapus</button>
            </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
