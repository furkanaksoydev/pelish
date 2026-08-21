<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth.php';

try { $pdo->query('SELECT 1 FROM pelish_admin_accounts LIMIT 1'); } catch (PDOException $exception) { header('Location: setup.php'); exit; }
if (admin_logged_in()) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $lookup = $pdo->prepare('SELECT * FROM pelish_admin_accounts WHERE username = ? LIMIT 1');
    $lookup->execute([$username]);
    $admin = $lookup->fetch();
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        $error = 'Kullanıcı adı veya şifre doğru değil.';
    } else {
        session_regenerate_id(true);
        $_SESSION['pelish_admin_id'] = (int) $admin['id'];
        $pdo->prepare('UPDATE pelish_admin_accounts SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);
        header('Location: index.php');
        exit;
    }
}
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Yönetici girişi · pelish</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Mono&family=DM+Sans:wght@400;500;600;700&display=swap"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"><link rel="stylesheet" href="assets/admin.css"></head><body class="admin-auth-body"><main class="admin-auth-card"><a class="panel-logo" href="../index.php"><img src="https://cdn.lavira360.com/pelish/logo.png" alt="pelish"></a><p>YÖNETİM PANELİ</p><h1>Giriş yap.</h1><span>Bu alan yalnızca yetkili yöneticiler içindir.</span><?php if ($error): ?><div class="admin-login-error"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><label>Kullanıcı adı<input required name="username" autocomplete="username"></label><label>Şifre<input required type="password" name="password" autocomplete="current-password"></label><button class="button primary" type="submit">Panele gir <i class="fa-solid fa-arrow-right"></i></button></form><a href="../index.php">← Mağazaya dön</a></main></body></html>
