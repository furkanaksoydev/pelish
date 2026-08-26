<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$customer = store_require_customer($pdo);
$returnTo = store_safe_return((string) ($_GET['return'] ?? 'profil.php'), 'profil.php');
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    store_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $returnTo = store_safe_return((string) ($_POST['return_to'] ?? 'profil.php'), 'profil.php');
    try {
        if ($action === 'save-address') {
            $addressId = (int) ($_POST['address_id'] ?? 0);
            $data = store_address_from_post($_POST, $customer);
            store_save_customer_address($pdo, (int) $customer['id'], $data, $addressId);
            store_flash('success', $addressId ? 'Teslimat adresin güncellendi.' : 'Yeni teslimat adresin eklendi.');
        } elseif ($action === 'delete-address') {
            store_delete_customer_address($pdo, (int) $customer['id'], (int) ($_POST['address_id'] ?? 0));
            store_flash('success', 'Teslimat adresi silindi.');
        } else {
            throw new RuntimeException('Geçersiz adres işlemi.');
        }
    } catch (Throwable $exception) {
        store_flash('danger', $exception instanceof RuntimeException ? $exception->getMessage() : 'Adres işlemi tamamlanamadı.');
    }
    store_redirect($returnTo);
}

$addresses = store_customer_addresses($pdo, (int) $customer['id']);
$editing = null;
foreach ($addresses as $address) {
    if ((int) $address['id'] === $editId) { $editing = $address; break; }
}
$form = $editing ?: [
    'id' => 0,
    'title' => '',
    'recipient_name' => trim($customer['first_name'] . ' ' . $customer['last_name']),
    'phone' => $customer['phone'] ?? '',
    'city' => 'Tekirdağ',
    'district' => 'Süleymanpaşa',
    'neighborhood' => '',
    'address_line' => '',
    'postal_code' => '',
    'is_default' => !$addresses,
];
store_render_head('Adreslerim');
store_render_header($pdo);
?>
<main class="store-main account-address-main">
  <section class="page-intro compact"><p class="eyebrow">HESABIM</p><h1>Teslimat adreslerin.</h1><p>Siparişlerinde kullanacağın adresleri buradan güvenle yönetebilirsin.</p></section>
  <div class="address-layout">
    <section class="address-list" aria-label="Kayıtlı adresler">
      <div class="section-title"><span>KAYITLI ADRESLER</span><h2>Adres defterin.</h2></div>
      <?php if (!$addresses): ?><div class="commerce-empty address-empty"><p class="eyebrow">PELISH</p><h2>Henüz adres eklemedin.</h2><p>İlk teslimat adresini sağdaki formdan ekleyebilirsin.</p></div><?php endif; ?>
      <?php foreach ($addresses as $address): ?><article class="address-card <?= $address['is_default'] ? 'is-default' : '' ?>"><div><span><?= e($address['title']) ?><?= $address['is_default'] ? ' · Varsayılan' : '' ?></span><strong><?= e($address['recipient_name']) ?></strong><p><?= nl2br(e($address['address_line'])) ?><br><?= e(trim(($address['neighborhood'] ? $address['neighborhood'] . ' · ' : '') . $address['district'] . ' / ' . $address['city'])) ?><?= $address['postal_code'] ? ' · ' . e($address['postal_code']) : '' ?></p><small><?= e(store_format_phone($address['phone'])) ?></small></div><div class="address-card-actions"><a href="adresler.php?<?= e(http_build_query(['edit' => $address['id'], 'return' => $returnTo])) ?>">Düzenle</a><form method="post"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><input type="hidden" name="action" value="delete-address"><input type="hidden" name="address_id" value="<?= (int) $address['id'] ?>"><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><button type="submit" data-confirm="Bu teslimat adresi silinsin mi?">Kaldır</button></form></div></article><?php endforeach; ?>
    </section>
    <section class="address-form-card"><p class="eyebrow"><?= $editing ? 'ADRESİ DÜZENLE' : 'YENİ ADRES' ?></p><h2><?= $editing ? 'Adresini güncelle.' : 'Yeni adres ekle.' ?></h2><form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><input type="hidden" name="action" value="save-address"><input type="hidden" name="address_id" value="<?= (int) $form['id'] ?>"><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><label>Adres başlığı<input required name="title" maxlength="100" placeholder="Ev, iş yeri…" value="<?= e($form['title']) ?>"></label><label>Alıcı adı soyadı<input required name="recipient_name" maxlength="200" value="<?= e($form['recipient_name']) ?>"></label><label>Telefon numarası<input required name="phone" value="<?= e(store_format_phone($form['phone'])) ?>" data-phone-mask></label><div class="auth-split"><label>İl<input required name="city" value="<?= e($form['city']) ?>"></label><label>İlçe<input required name="district" value="<?= e($form['district']) ?>"></label></div><div class="auth-split"><label>Mahalle<input name="neighborhood" value="<?= e($form['neighborhood']) ?>"></label><label>Posta kodu<input inputmode="numeric" maxlength="10" name="postal_code" value="<?= e($form['postal_code']) ?>"></label></div><label>Açık adres<textarea required name="address_line" rows="4" placeholder="Cadde, sokak, bina ve daire numarası"><?= e($form['address_line']) ?></textarea></label><label class="consent-line"><input type="checkbox" name="is_default" <?= $form['is_default'] ? 'checked' : '' ?>><span>Bu adresi varsayılan teslimat adresim yap.</span></label><div class="address-form-actions"><a href="<?= e($returnTo) ?>" class="button button-light">Vazgeç</a><button class="button button-dark" type="submit">Adresi kaydet <span>→</span></button></div></form>
    </section>
  </div>
</main>
<?php store_render_footer(); ?>
