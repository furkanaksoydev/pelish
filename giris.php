<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$activeTab = ($_GET['tab'] ?? '') === 'kayit' ? 'kayit' : 'giris';
if (store_customer($pdo)) { store_redirect('profil.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    store_verify_csrf();
    $mode = (string) ($_POST['mode'] ?? 'login');
    if ($mode === 'login') {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
        $password = (string) ($_POST['password'] ?? '');
        $find = $pdo->prepare('SELECT * FROM pelish_customers WHERE email = ? AND is_active = 1 LIMIT 1');
        $find->execute([$email]);
        $customer = $find->fetch();
        if (!$customer || empty($customer['password_hash']) || !password_verify($password, $customer['password_hash'])) {
            store_flash('danger', 'Giriş bilgilerini kontrol et.');
        } elseif (empty($customer['email_verified_at'])) {
            store_flash('info', 'Önce e-posta adresini doğrulamalısın.');
            store_redirect('dogrula.php');
        } else {
            store_login_customer($customer);
            $pdo->prepare('UPDATE pelish_customers SET last_login_at = NOW() WHERE id = ?')->execute([$customer['id']]);
            store_finish_pending_action($pdo, (int) $customer['id']);
            if (!isset($_SESSION['pelish_store_flash'])) { store_flash('success', 'Tekrar hoş geldin, ' . $customer['first_name'] . '.'); }
            store_redirect('index.php');
        }
        $activeTab = 'giris';
    }
    if ($mode === 'register') {
        $data = [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'email' => mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8'),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
        ];
        if ($data['first_name'] === '' || $data['last_name'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || $data['phone'] === '') {
            store_flash('danger', 'Lütfen tüm bilgilerini geçerli biçimde doldur.');
        } elseif (mb_strlen($data['password']) < 8) {
            store_flash('danger', 'Şifren en az 8 karakter olmalı.');
        } else {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM pelish_customers WHERE email = ?');
            $exists->execute([$data['email']]);
            if ((int) $exists->fetchColumn() > 0) {
                store_flash('danger', 'Bu e-posta adresi zaten kullanılıyor.');
            } else {
                $code = (string) random_int(100000, 999999);
                if (!store_send_verification_email((array) ($config['mail'] ?? []), $data['email'], $code)) {
                    store_flash('danger', 'Doğrulama e-postası gönderilemedi. Yönetici, yerel mail gönderen ayarını tamamlamalı.');
                } else {
                    $upsert = $pdo->prepare('INSERT INTO pelish_email_verifications (first_name, last_name, email, phone, password_hash, code_hash, expires_at, attempts) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 3 MINUTE), 0) ON DUPLICATE KEY UPDATE first_name=VALUES(first_name), last_name=VALUES(last_name), phone=VALUES(phone), password_hash=VALUES(password_hash), code_hash=VALUES(code_hash), expires_at=DATE_ADD(NOW(), INTERVAL 3 MINUTE), attempts=0');
                    $upsert->execute([$data['first_name'], $data['last_name'], $data['email'], $data['phone'], password_hash($data['password'], PASSWORD_DEFAULT), password_hash($code, PASSWORD_DEFAULT)]);
                    $_SESSION['pelish_verify_email'] = $data['email'];
                    store_flash('success', '6 haneli doğrulama kodunu e-posta adresine gönderdik. Kod 3 dakika geçerli.');
                    store_redirect('dogrula.php');
                }
            }
        }
        $activeTab = 'kayit';
    }
}

store_render_head('Giriş yap');
store_render_header($pdo);
?>
<main class="store-main auth-main"><section class="auth-shell"><div class="auth-intro"><p class="eyebrow">PELISH’E HOŞ GELDİN</p><h1>Her seçkin<br><em>seninle kalsın.</em></h1><p>Favorilerin ve sepetin, giriş yaptığın her yerde seni bekler.</p></div><div class="auth-card"><div class="auth-tabs"><a class="<?= $activeTab === 'giris' ? 'active' : '' ?>" href="giris.php">Giriş yap</a><a class="<?= $activeTab === 'kayit' ? 'active' : '' ?>" href="giris.php?tab=kayit">Kayıt ol</a></div><?php if ($activeTab === 'giris'): ?><form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><input type="hidden" name="mode" value="login"><label>E-posta<input required type="email" name="email" autocomplete="email"></label><label>Şifre<input required type="password" name="password" autocomplete="current-password"></label><button class="button button-dark" type="submit">Giriş yap <span>→</span></button><p>Hesabın yok mu? <a href="giris.php?tab=kayit">Kayıt ol</a></p></form><?php else: ?><form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><input type="hidden" name="mode" value="register"><div class="auth-split"><label>Ad<input required name="first_name" autocomplete="given-name"></label><label>Soyad<input required name="last_name" autocomplete="family-name"></label></div><label>E-posta<input required type="email" name="email" autocomplete="email"></label><label>Telefon numarası<input required name="phone" autocomplete="tel"></label><label>Şifre<input required type="password" minlength="8" name="password" autocomplete="new-password"></label><small>Kayıt işlemini tamamlamak için e-postana 6 haneli, 3 dakika geçerli bir kod göndeririz.</small><button class="button button-dark" type="submit">Kod gönder <span>→</span></button></form><?php endif; ?></div></section></main>
<?php store_render_footer(); ?>
