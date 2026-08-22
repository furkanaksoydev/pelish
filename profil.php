<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$customer = store_require_customer($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    store_verify_csrf();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

    if (!password_verify($currentPassword, (string) ($customer['password_hash'] ?? ''))) {
        store_flash('danger', 'Değişiklikleri kaydetmek için mevcut şifreni doğru girmelisin.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
        store_flash('danger', 'Geçerli e-posta ve telefon numarası gir.');
    } elseif ($newPassword !== '' && (strlen($newPassword) < 8 || $newPassword !== $newPasswordConfirm)) {
        store_flash('danger', 'Yeni şifren en az 8 karakter olmalı ve tekrarıyla eşleşmeli.');
    } else {
        $passwordHash = $newPassword !== '' ? password_hash($newPassword, PASSWORD_DEFAULT) : null;
        if ($email === mb_strtolower((string) $customer['email'], 'UTF-8')) {
            $pdo->prepare('UPDATE pelish_customers SET phone = ?, password_hash = COALESCE(?, password_hash) WHERE id = ?')->execute([$phone, $passwordHash, $customer['id']]);
            store_flash('success', 'Bilgilerin güvenle güncellendi.');
        } else {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM pelish_customers WHERE email = ? AND id != ?');
            $exists->execute([$email, $customer['id']]);
            $pending = $pdo->prepare('SELECT COUNT(*) FROM pelish_customer_email_changes WHERE new_email = ? AND customer_id != ?');
            $pending->execute([$email, $customer['id']]);
            if ((int) $exists->fetchColumn() > 0 || (int) $pending->fetchColumn() > 0) {
                store_flash('danger', 'Bu e-posta başka bir hesapta kullanılıyor veya doğrulanmayı bekliyor.');
            } else {
                $code = (string) random_int(100000, 999999);
                $saveChange = $pdo->prepare('INSERT INTO pelish_customer_email_changes (customer_id, new_email, new_phone, new_password_hash, code_hash, expires_at, attempts) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 3 MINUTE), 0) ON DUPLICATE KEY UPDATE new_email=VALUES(new_email), new_phone=VALUES(new_phone), new_password_hash=VALUES(new_password_hash), code_hash=VALUES(code_hash), expires_at=DATE_ADD(NOW(), INTERVAL 3 MINUTE), attempts=0');
                $saveChange->execute([$customer['id'], $email, $phone, $passwordHash, password_hash($code, PASSWORD_DEFAULT)]);
                if (!store_send_verification_email((array) ($config['mail'] ?? []), $email, $code, 'profil-eposta-dogrula.php', true)) {
                    $pdo->prepare('DELETE FROM pelish_customer_email_changes WHERE customer_id = ?')->execute([$customer['id']]);
                    store_flash('danger', 'Doğrulama e-postası gönderilemedi. Değişiklik yapılmadı.');
                } else {
                    store_flash('success', 'Yeni e-posta adresine 6 haneli doğrulama kodu gönderdik.');
                    store_redirect('profil-eposta-dogrula.php');
                }
            }
        }
    }
    store_redirect('profil.php');
}
$customer = store_customer($pdo) ?: $customer;
$counts = store_counts($pdo, $customer);
store_render_head('Profilim');
store_render_header($pdo);
?>
<main class="store-main"><section class="profile-shell"><aside><p class="eyebrow">HESABIM</p><h1><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></h1><p><?= e($customer['email']) ?></p><a href="favorilerim.php"><i class="fa-regular fa-heart"></i> Favorilerim <b><?= $counts['favorites'] ?></b></a><a href="sepetim.php"><i class="fa-solid fa-bag-shopping"></i> Sepetim <b><?= $counts['cart'] ?></b></a><a href="cikis.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Çıkış yap</a></aside><section class="profile-card"><p class="eyebrow">İLETİŞİM VE GÜVENLİK</p><h2>Bilgilerini güncelle.</h2><p class="profile-note">Her değişikliği mevcut şifrenle onaylarsın. E-posta değişikliğinde yeni adresine ayrıca bir doğrulama kodu göndeririz.</p><form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><div class="auth-split"><label>Ad<input value="<?= e($customer['first_name']) ?>" disabled></label><label>Soyad<input value="<?= e($customer['last_name']) ?>" disabled></label></div><label>E-posta<input type="email" required name="email" value="<?= e($customer['email']) ?>"></label><label>Telefon numarası<input required name="phone" value="<?= e($customer['phone']) ?>"></label><div class="profile-security"><span>ŞİFRE DEĞİŞİKLİĞİ</span><label>Yeni şifre <small>(değiştirmeyeceksen boş bırak)</small><input type="password" minlength="8" name="new_password" autocomplete="new-password"></label><label>Yeni şifre tekrarı<input type="password" minlength="8" name="new_password_confirm" autocomplete="new-password"></label></div><label class="current-password">Mevcut şifren<input required type="password" name="current_password" autocomplete="current-password"></label><button class="button button-dark" type="submit">Değişiklikleri kaydet <span>→</span></button></form></section></section></main>
<?php store_render_footer(); ?>
