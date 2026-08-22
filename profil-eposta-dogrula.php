<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$customer = store_require_customer($pdo);
$findChange = $pdo->prepare('SELECT * FROM pelish_customer_email_changes WHERE customer_id = ? LIMIT 1');
$findChange->execute([$customer['id']]);
$change = $findChange->fetch();
if (!$change) { store_flash('info', 'Bekleyen bir e-posta değişikliği bulunmuyor.'); store_redirect('profil.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    store_verify_csrf();
    $action = (string) ($_POST['action'] ?? 'verify');
    if ($action === 'resend') {
        $code = (string) random_int(100000, 999999);
        if (!store_send_verification_email((array) ($config['mail'] ?? []), (string) $change['new_email'], $code, 'profil-eposta-dogrula.php', true)) {
            store_flash('danger', 'Doğrulama e-postası gönderilemedi.');
        } else {
            $pdo->prepare('UPDATE pelish_customer_email_changes SET code_hash=?, expires_at=DATE_ADD(NOW(), INTERVAL 3 MINUTE), attempts=0 WHERE id=?')->execute([password_hash($code, PASSWORD_DEFAULT), $change['id']]);
            store_flash('success', 'Yeni kod gönderildi; 3 dakika içinde kullanabilirsin.');
        }
        store_redirect('profil-eposta-dogrula.php');
    }
    $code = preg_replace('/\D+/', '', (string) ($_POST['code'] ?? ''));
    if (strtotime((string) $change['expires_at']) < time()) { store_flash('danger', 'Kodun süresi doldu. Yeni kod isteyebilirsin.'); }
    elseif ((int) $change['attempts'] >= 5) { store_flash('danger', 'Çok fazla hatalı deneme yapıldı. Yeni kod isteyebilirsin.'); }
    elseif (!password_verify($code, (string) $change['code_hash'])) { $pdo->prepare('UPDATE pelish_customer_email_changes SET attempts=attempts+1 WHERE id=?')->execute([$change['id']]); store_flash('danger', 'Kod doğru değil. Tekrar deneyebilirsin.'); }
    else {
        try {
            $pdo->beginTransaction();
            $emailTaken = $pdo->prepare('SELECT COUNT(*) FROM pelish_customers WHERE email=? AND id != ?');
            $emailTaken->execute([$change['new_email'], $customer['id']]);
            if ((int) $emailTaken->fetchColumn() > 0) { throw new RuntimeException('Bu e-posta artık başka bir hesapta kullanılıyor.'); }
            $pdo->prepare('UPDATE pelish_customers SET email=?, phone=?, password_hash=COALESCE(?, password_hash), email_verified_at=NOW() WHERE id=?')->execute([$change['new_email'], $change['new_phone'], $change['new_password_hash'], $customer['id']]);
            $pdo->prepare('DELETE FROM pelish_customer_email_changes WHERE id=?')->execute([$change['id']]);
            $pdo->commit();
            store_flash('success', 'E-posta adresin ve bekleyen değişikliklerin güncellendi.');
            store_redirect('profil.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            store_flash('danger', $exception instanceof RuntimeException ? $exception->getMessage() : 'E-posta değişikliği tamamlanamadı.');
        }
    }
}
$prefillCode = preg_replace('/\D+/', '', (string) ($_GET['code'] ?? ''));
store_render_head('Yeni e-postanı doğrula');
store_render_header($pdo);
?>
<main class="store-main auth-main"><section class="verify-card"><p class="eyebrow">GÜVENLİK ADIMI</p><h1>Yeni e-postanı doğrula.</h1><p><strong><?= e((string) $change['new_email']) ?></strong> adresine 6 haneli bir kod gönderdik.</p><p class="verify-countdown" data-verify-countdown data-countdown-until="<?= (int) strtotime((string) $change['expires_at']) * 1000 ?>" aria-live="polite">Kodun geçerlilik süresi hesaplanıyor…</p><form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><input type="hidden" name="action" value="verify"><label>Doğrulama kodu<input name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" value="<?= e($prefillCode) ?>" data-verification-code></label><button class="button button-dark" type="submit">E-postamı doğrula <span>→</span></button></form><form method="post" class="resend-form"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><input type="hidden" name="action" value="resend"><button type="submit" data-countdown-resend>Yeni kod gönder</button></form></section></main>
<?php store_render_footer(); ?>
