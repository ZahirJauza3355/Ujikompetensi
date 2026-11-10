<?php
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Prepared statement: input pengguna tidak digabung langsung ke SQL
    $st = db()->prepare('SELECT user_id, username, password, nama, role FROM users WHERE username = ?');
    $st->execute([$username]);
    $user = $st->fetch();

    // password_verify membandingkan password dengan hash di database
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['nama']    = $user['nama'];
        $_SESSION['role']    = $user['role'];
        redirect('dashboard.php');
    }
    $error = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - StyleHub</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <span class="logo">Style<span>Hub</span></span>
        <p class="muted">Aplikasi kasir toko fashion</p>

        <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

        <form method="post" autocomplete="off">
            <label>Username
                <input type="text" name="username" maxlength="30" required autofocus>
            </label>
            <label>Password
                <input type="password" name="password" required>
            </label>
            <button type="submit">Masuk</button>
        </form>
    </div>
</body>
</html>
