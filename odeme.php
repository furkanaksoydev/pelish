<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';
require __DIR__ . '/store/checkout.php';

$customer = store_customer($pdo);
$addresses = $customer ? store_customer_addresses($pdo, (int) $customer['id']) : [];
if (!$customer) { $addresses = [['guest' => true]]; }
$items = store_active_cart_items($pdo, $customer);
$totals = store_cart_totals($items);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    store_verify_csrf();
    $addressId = (int) ($_POST['address_id'] ?? 0);
    $termsAccepted = isset($_POST['terms_accepted']) && isset($_POST['pre_information_accepted']);
    $kvkkAccepted = isset($_POST['kvkk_accepted']);
    $marketingConsent = isset($_POST['marketing_consent']);
    $r2Settings = r2_settings($config);
    $proof = null;
    try {
        if (!r2_is_configured($r2Settings)) throw new RuntimeException('Dekont yükleme için R2 ayarları tamamlanmamış.');
        $proof = r2_upload_payment_proof($r2Settings, $_FILES['payment_proof'] ?? [], 'havale-eft');
        $order = $customer
            ? store_create_manual_payment_order($pdo, $customer, $addressId, $termsAccepted, $kvkkAccepted, $marketingConsent, $config, 'Havale / EFT')
            : store_create_guest_transfer_order($pdo, ['first_name'=>trim((string)($_POST['guest_first_name']??'')),'last_name'=>trim((string)($_POST['guest_last_name']??'')),'email'=>trim((string)($_POST['guest_email']??'')),'phone'=>trim((string)($_POST['guest_phone']??'')),'city'=>trim((string)($_POST['guest_city']??'')),'district'=>trim((string)($_POST['guest_district']??'')),'address_line'=>trim((string)($_POST['guest_address_line']??''))], $termsAccepted, $kvkkAccepted, $config);
        store_attach_payment_proof($pdo, (int) $order['id'], $proof);
        if (!$customer) { $_SESSION['pelish_guest_order_number'] = (string) $order['number']; }
        $notificationTo = trim((string) ($config['mail']['payment_notification_to'] ?? 'pelinnyiilmaz@gmail.com'));
        store_send_transfer_proof_notification((array) ($config['mail'] ?? []), $notificationTo, (string) $order['number'], (float) $order['totals']['grand_total'], (string) $proof['url']);
        store_redirect('siparis-tamamlandi.php?order=' . rawurlencode($order['number']));
    } catch (Throwable $exception) {
        if (is_array($proof)) { try { r2_delete_object($r2Settings, (string) $proof['key']); } catch (Throwable $ignored) {} }
        store_flash('danger', $exception instanceof RuntimeException ? $exception->getMessage() : 'Sipariş oluşturulamadı. Lütfen tekrar dene.');
        store_redirect('odeme.php');
    }
}

store_render_head('Havale / EFT ile ödeme');
store_render_header($pdo);
?>
<main class="store-main checkout-main">
  <section class="page-intro compact"><p class="eyebrow">GÜVENLİ ÖDEME</p><h1>Havale / EFT ile tamamla.</h1><p>Dekontunu yükle; ödeme onayından sonra siparişin hazırlanmaya başlanır.</p></section>
  <?php if (!$items): ?>
    <section class="commerce-empty full-empty"><p class="eyebrow">PELISH</p><h1>Sepetin boş.</h1><p>Ödeme adımına devam etmek için önce sepetine ürün eklemelisin.</p><a class="button button-dark" href="indirimler.php">Ürünleri keşfet <span>→</span></a></section>
  <?php else: ?>
  <form method="post" enctype="multipart/form-data" class="checkout-layout" data-checkout-form novalidate><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>">
    <section class="checkout-steps">
      <article class="checkout-section"><div class="checkout-section-head"><span>01</span><div><p class="eyebrow">TESLİMAT ADRESİ</p><h2>Nereye gönderelim?</h2></div><?php if($customer):?><a href="adresler.php?return=odeme.php">Yeni adres</a><?php endif;?></div><?php if(!$customer):?><div class="guest-checkout-fields"><p>Üyeliksiz devam ediyorsun. Bilgilerin yalnızca teslimat ve sipariş iletişimi için kullanılır.</p><div class="auth-split"><label>Ad<input required name="guest_first_name"></label><label>Soyad<input required name="guest_last_name"></label></div><div class="auth-split"><label>E-posta<input required type="email" name="guest_email"></label><label>Telefon<input required name="guest_phone" data-phone-mask></label></div><div class="auth-split"><label>İl<input required name="guest_city"></label><label>İlçe<input required name="guest_district"></label></div><label>Açık adres<textarea required name="guest_address_line"></textarea></label><small>Zaten hesabın var mı? <a href="giris.php">Giriş yap</a></small></div><?php elseif (!$addresses): ?><div class="checkout-empty"><p>Kayıtlı teslimat adresin yok.</p><a class="button button-dark" href="adresler.php?return=odeme.php">Adres ekle <span>→</span></a></div><?php else: ?><div class="checkout-addresses"><?php foreach ($addresses as $address): ?><label class="checkout-address"><input type="radio" required name="address_id" value="<?= (int) $address['id'] ?>" <?= $address['is_default'] ? 'checked' : '' ?>><span><strong><?= e($address['title']) ?><?= $address['is_default'] ? ' · Varsayılan' : '' ?></strong><b><?= e($address['recipient_name']) ?></b><small><?= nl2br(e($address['address_line'])) ?><br><?= e($address['district'] . ' / ' . $address['city']) ?></small><em><?= e(store_format_phone($address['phone'])) ?></em></span><a href="adresler.php?<?= e(http_build_query(['edit' => $address['id'], 'return' => 'odeme.php'])) ?>">Düzenle</a></label><?php endforeach; ?></div><?php endif; ?></article>
      <article class="checkout-section"><div class="checkout-section-head"><span>02</span><div><p class="eyebrow">ÖDEME & DEKONT</p><h2>Havale / EFT bilgileri.</h2></div></div><div class="transfer-note"><i class="fa-solid fa-building-columns"></i><div><strong>Havale / EFT</strong><span>IBAN: <b>TR85 0003 2000 0000 0003 7110 688</b><br>Hesap sahibi: <b>Pelin Yılmaz</b><br>Açıklama alanına sipariş numaranı yaz.</span></div></div><label class="payment-proof-upload">Ödeme dekontu<input required type="file" name="payment_proof" accept="application/pdf,image/jpeg,image/png,image/webp"><span>PDF, JPG, PNG veya WebP · en fazla 10 MB</span></label><p class="payment-proof-copy">Dekontun R2 üzerinde güvenle saklanır. Yönetici onayından sonra siparişin hazırlanır.</p></article>
      <article class="checkout-section"><div class="checkout-section-head"><span>03</span><div><p class="eyebrow">ONAYLAR</p><h2>Bilgilendirmeler.</h2></div></div><div class="checkout-consents"><label class="consent-line"><input type="checkbox" required name="pre_information_accepted"><span><a href="yasal.php?belge=on-bilgilendirme" target="_blank" rel="noopener">Ön Bilgilendirme Formu</a>'nu okudum ve onaylıyorum.</span></label><label class="consent-line"><input type="checkbox" required name="terms_accepted"><span><a href="yasal.php?belge=mesafeli-satis" target="_blank" rel="noopener">Mesafeli Satış Sözleşmesi</a>'ni okudum ve onaylıyorum.</span></label><label class="consent-line"><input type="checkbox" required name="kvkk_accepted"><span><a href="yasal.php?belge=kvkk" target="_blank" rel="noopener">KVKK Aydınlatma Metni</a>'ni okudum.</span></label><label class="consent-line optional"><input type="checkbox" name="marketing_consent"><span>Kampanya, indirim ve yeni sezon duyuruları için ticari elektronik ileti almak istiyorum.</span></label></div></article>
    </section>
    <aside class="checkout-summary"><p class="eyebrow">SİPARİŞ ÖZETİ</p><h2>Seçtiklerin.</h2><div class="checkout-items"><?php foreach ($items as $item): ?><article><img src="<?= e($item['image_url'] ?: 'https://cdn.pelish.co/logo.png') ?>" alt="<?= e($item['name']) ?>"><span><strong><?= e($item['name']) ?></strong><small><?= e($item['selected_size'] ?: 'Beden seçilmedi') ?><?= $item['color_name'] ? ' · ' . e($item['color_name']) : '' ?> · <?= (int) $item['quantity'] ?> adet</small></span><b><?= e(store_money($item['price']['current'] * (int) $item['quantity'])) ?></b></article><?php endforeach; ?></div><p><span>Ürünler</span><strong><?= e(store_money($totals['subtotal'])) ?></strong></p><p><span>Kargo</span><strong><?= $totals['cargo'] ? e(store_money($totals['cargo'])) : 'Ücretsiz' ?></strong></p><p class="checkout-total"><span>Toplam</span><strong><?= e(store_money($totals['grand_total'])) ?></strong></p><button class="button button-dark" type="submit" <?= !$addresses ? 'disabled' : '' ?>>Dekontu gönder <span>→</span></button><small>Dekont onay bekler; ödeme doğrulandıktan sonra siparişin işleme alınır.</small></aside>
  </form>
  <?php endif; ?>
</main>
<?php store_render_footer(); ?>
