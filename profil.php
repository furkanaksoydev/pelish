<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$customer = store_require_customer($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    store_verify_csrf();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
    $phone = trim((string) ($_POST['phone'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') { store_flash('danger', 'Geçerli e-posta ve telefon numarası gir.'); }
    else {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM pelish_customers WHERE email = ? AND id != ?'); $exists->execute([$email, $customer['id']]);
        if ((int) $exists->fetchColumn() > 0) { store_flash('danger', 'Bu e-posta başka bir hesapta kullanılıyor.'); }
        else { $pdo->prepare('UPDATE pelish_customers SET email=?, phone=? WHERE id=?')->execute([$email, $phone, $customer['id']]); store_flash('success', 'İletişim bilgilerin güncellendi.'); }
    }
    store_redirect('profil.php');
}
$customer = store_customer($pdo) ?: $customer;
$counts = store_counts($pdo, $customer);
store_render_head('Profilim');
store_render_header($pdo);
?>
<main class="store-main"><section class="profile-shell"><aside><p class="eyebrow">HESABIM</p><h1><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></h1><p>@<?= e($customer['username']) ?></p><a href="favorilerim.php"><i class="fa-regular fa-heart"></i> Favorilerim <b><?= $counts['favorites'] ?></b></a><a href="sepetim.php"><i class="fa-solid fa-bag-shopping"></i> Sepetim <b><?= $counts['cart'] ?></b></a><a href="cikis.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Çıkış yap</a></aside><section class="profile-card"><p class="eyebrow">İLETİŞİM BİLGİLERİ</p><h2>Bilgilerini güncelle.</h2><form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><div class="auth-split"><label>Ad<input value="<?= e($customer['first_name']) ?>" disabled></label><label>Soyad<input value="<?= e($customer['last_name']) ?>" disabled></label></div><label>Kullanıcı adı<input value="<?= e($customer['username']) ?>" disabled></label><label>E-posta<input type="email" required name="email" value="<?= e($customer['email']) ?>"></label><label>Telefon numarası<input required name="phone" value="<?= e($customer['phone']) ?>"></label><button class="button button-dark" type="submit">Bilgileri kaydet <span>→</span></button></form></section></section></main>
<?php store_render_footer(); ?>
