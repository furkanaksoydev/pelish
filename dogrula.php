<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$email = (string) ($_SESSION['pelish_verify_email'] ?? '');
if ($email === '') { store_flash('info', 'Önce kayıt formunu doldurmalısın.'); store_redirect('giris.php?tab=kayit'); }
$lookup = $pdo->prepare('SELECT * FROM pelish_email_verifications WHERE email = ? LIMIT 1');
$lookup->execute([$email]);
$verification = $lookup->fetch();
if (!$verification) { store_flash('danger', 'Kayıt doğrulama isteği bulunamadı. Lütfen yeniden kayıt ol.'); store_redirect('giris.php?tab=kayit'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    store_verify_csrf();
    $action = (string) ($_POST['action'] ?? 'verify');
    if ($action === 'resend') {
        $code = (string) random_int(100000, 999999);
        if (!store_send_verification_email((array) ($config['mail'] ?? []), $email, $code)) { store_flash('danger', 'Doğrulama e-postası gönderilemedi.'); }
        else { $pdo->prepare('UPDATE pelish_email_verifications SET code_hash=?, expires_at=DATE_ADD(NOW(), INTERVAL 3 MINUTE), attempts=0 WHERE id=?')->execute([password_hash($code, PASSWORD_DEFAULT), $verification['id']]); store_flash('success', 'Yeni kod gönderildi; 3 dakika içinde kullanabilirsin.'); }
        store_redirect('dogrula.php');
    }
    $code = preg_replace('/\D+/', '', (string) ($_POST['code'] ?? ''));
    if (strtotime((string) $verification['expires_at']) < time()) { store_flash('danger', 'Kodun süresi doldu. Yeni kod isteyebilirsin.'); }
    elseif ((int) $verification['attempts'] >= 5) { store_flash('danger', 'Çok fazla hatalı deneme yapıldı. Yeni kod isteyebilirsin.'); }
    elseif (!password_verify($code, (string) $verification['code_hash'])) { $pdo->prepare('UPDATE pelish_email_verifications SET attempts=attempts+1 WHERE id=?')->execute([$verification['id']]); store_flash('danger', 'Kod doğru değil. Tekrar deneyebilirsin.'); }
    else {
        try {
            $pdo->beginTransaction();
            $create = $pdo->prepare('INSERT INTO pelish_customers (first_name, last_name, email, phone, password_hash, email_verified_at, is_active) VALUES (?, ?, ?, ?, ?, NOW(), 1)');
            $create->execute([$verification['first_name'], $verification['last_name'], $verification['email'], $verification['phone'], $verification['password_hash']]);
            $customerId = (int) $pdo->lastInsertId();
            $pdo->prepare('DELETE FROM pelish_email_verifications WHERE id=?')->execute([$verification['id']]);
            $pdo->commit();
            store_login_customer(['id' => $customerId]);
            unset($_SESSION['pelish_verify_email']);
            $returnTo = store_finish_pending_action($pdo, $customerId);
            if (!isset($_SESSION['pelish_store_flash'])) { store_flash('success', 'E-posta adresin doğrulandı. Pelish’e hoş geldin.'); }
            store_redirect($returnTo);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            store_flash('danger', 'Hesap oluşturulamadı. E-posta adresi artık kullanılıyor olabilir.');
        }
    }
}
$prefillCode = preg_replace('/\D+/', '', (string) ($_GET['code'] ?? ''));
store_render_head('E-postanı doğrula');
store_render_header($pdo);
?>
<main class="store-main auth-main"><section class="verify-card"><p class="eyebrow">SON ADIM</p><h1>E-postanı doğrula.</h1><p><strong><?= e($email) ?></strong> adresine 6 haneli bir kod gönderdik.</p><p class="verify-countdown" data-verify-countdown data-countdown-until="<?= (int) strtotime((string) $verification['expires_at']) * 1000 ?>" aria-live="polite">Kodun geçerlilik süresi hesaplanıyor…</p><form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><input type="hidden" name="action" value="verify"><label>Doğrulama kodu<input name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" value="<?= e($prefillCode) ?>" data-verification-code></label><button class="button button-dark" type="submit">Hesabımı doğrula <span>→</span></button></form><form method="post" class="resend-form"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><input type="hidden" name="action" value="resend"><button type="submit" data-countdown-resend>Yeni kod gönder</button></form></section></main>
<?php store_render_footer(); ?>
