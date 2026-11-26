<?php
require __DIR__ . '/config.php';

if (user_id() !== null) {
    header('Location: index.php');
    exit;
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? '')) {
        $errors[] = 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $pass1    = (string)($_POST['password'] ?? '');
        $pass2    = (string)($_POST['password2'] ?? '');

        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $errors[] = 'Username 3-30 karakter, hanya huruf, angka, dan underscore (_).';
        }
        if (strlen($pass1) < 6) {
            $errors[] = 'Password minimal 6 karakter.';
        } elseif (strlen($pass1) > 72) {
            $errors[] = 'Password maksimal 72 karakter.';
        }
        if ($pass1 !== $pass2) {
            $errors[] = 'Konfirmasi password tidak sama.';
        }

        if (!$errors) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $st = $pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
                $st->execute([$username, password_hash($pass1, PASSWORD_DEFAULT)]);
                $id = (int)$pdo->lastInsertId();

                $st = $pdo->prepare('INSERT INTO pengaturan (user_id) VALUES (?)');
                $st->execute([$id]);
                $pdo->commit();

                session_regenerate_id(true);
                $_SESSION['uid']   = $id;
                $_SESSION['uname'] = $username;
                header('Location: index.php');
                exit;
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ((int)($ex->errorInfo[1] ?? 0) === 1062) {
                    $errors[] = 'Username sudah dipakai, coba yang lain.';
                } else {
                    error_log('Registrasi gagal: ' . $ex->getMessage());
                    $errors[] = 'Gagal membuat akun. Coba lagi nanti.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar - TugasKu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Sora:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="auth">
  <form class="login-box" method="post" action="register.php" autocomplete="off">
    <h1>Buat Akun</h1>
    <p>Daftar untuk mulai mencatat tugasmu.</p>

    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <label for="username">Username</label>
    <input id="username" name="username" type="text" value="<?= e($username) ?>" maxlength="30" required autofocus>
    <div class="bantuan">3-30 karakter: huruf, angka, atau underscore.</div>

    <label for="password">Password</label>
    <input id="password" name="password" type="password" minlength="6" maxlength="72" required>
    <div class="bantuan">Minimal 6 karakter.</div>

    <label for="password2">Ulangi password</label>
    <input id="password2" name="password2" type="password" minlength="6" maxlength="72" required>

    <div class="pesan-error">
      <?php if ($errors): ?>
        <ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
    <button class="btn-utama" type="submit">Daftar</button>

    <div class="auth-link">Sudah punya akun? <a href="login.php">Masuk</a></div>
  </form>
</main>
</body>
</html>
