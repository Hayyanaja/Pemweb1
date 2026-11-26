<?php
require __DIR__ . '/config.php';

if (user_id() !== null) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? '')) {
        $error = 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $st = db()->prepare('SELECT id, username, password FROM users WHERE username = ? LIMIT 1');
        $st->execute([$username]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);           // cegah session fixation
            $_SESSION['uid']   = (int)$user['id'];
            $_SESSION['uname'] = $user['username'];
            header('Location: index.php');
            exit;
        }
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk - TugasKu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Sora:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="auth">
  <form class="login-box" method="post" action="login.php" autocomplete="off">
    <h1>TugasKu</h1>
    <p>Masuk untuk mengelola tugas kuliahmu.</p>

    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <label for="username">Username</label>
    <input id="username" name="username" type="text" value="<?= e($username) ?>" required autofocus>

    <label for="password">Password</label>
    <input id="password" name="password" type="password" required>

    <div class="pesan-error"><?= e($error) ?></div>
    <button class="btn-utama" type="submit">Masuk</button>

    <div class="auth-link">Belum punya akun? <a href="register.php">Daftar</a></div>
  </form>
</main>
</body>
</html>
