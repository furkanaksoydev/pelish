<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/panel.php';

admin_require_login();
$adminId = (int) $_SESSION['pelish_admin_id'];
$find = $pdo->prepare('SELECT * FROM pelish_admin_accounts WHERE id = ?');
$find->execute([$adminId]);
$admin = $find->fetch();
if (!$admin) { admin_logout(); header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username)) { admin_flash('danger', 'Kullanıcı adı 3–80 karakter olmalı.'); }
    elseif (!password_verify($currentPassword, $admin['password_hash'])) { admin_flash('danger', 'Mevcut şifre doğru değil.'); }
    elseif ($newPassword !== '' && strlen($newPassword) < 8) { admin_flash('danger', 'Yeni şifre en az 8 karakter olmalı.'); }
    else {
        try {
            if ($newPassword !== '') { $pdo->prepare('UPDATE pelish_admin_accounts SET username=?, password_hash=? WHERE id=?')->execute([$username, password_hash($newPassword, PASSWORD_DEFAULT), $adminId]); }
            else { $pdo->prepare('UPDATE pelish_admin_accounts SET username=? WHERE id=?')->execute([$username, $adminId]); }
            admin_flash('success', 'Yönetici hesabı güncellendi.');
        } catch (PDOException $exception) { admin_flash('danger', 'Bu kullanıcı adı zaten kullanılıyor.'); }
    }
    header('Location: account.php'); exit;
}
panel_header('dashboard', 'Yönetici hesabı', [['label' => 'Hesap ayarları', 'href' => 'account.php', 'active' => true]]);
?>
<section class="table-card account-card"><div class="table-title"><div><span>GÜVENLİK</span><h1>Yönetici hesabı</h1></div></div><form method="post" class="product-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="form-grid"><label>Kullanıcı adı<input required name="username" value="<?= e($admin['username']) ?>"></label><label>Mevcut şifre<input required type="password" name="current_password" autocomplete="current-password"></label><label>Yeni şifre <small>(değiştirmeyeceksen boş bırak)</small><input type="password" minlength="8" name="new_password" autocomplete="new-password"></label></div><div class="modal-actions"><a href="index.php" class="button ghost">İptal</a><button class="button primary">Kaydet</button></div></form></section>
<?php panel_footer(); ?>
