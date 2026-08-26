<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';
require __DIR__ . '/store/checkout.php';

$customer = store_require_customer($pdo);
$addresses = store_customer_addresses($pdo, (int) $customer['id']);
$items = store_cart_items($pdo, (int) $customer['id']);
$totals = store_cart_totals($items);
$paymentProviderConfigured = !empty($config['payment']['provider']) && !empty($config['payment']['merchant_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    store_verify_csrf();
    $paymentMethod = (string) ($_POST['payment_method'] ?? 'cod');
    $addressId = (int) ($_POST['address_id'] ?? 0);
    $termsAccepted = isset($_POST['terms_accepted']) && isset($_POST['pre_information_accepted']);
    $kvkkAccepted = isset($_POST['kvkk_accepted']);
    $marketingConsent = isset($_POST['marketing_consent']);
    try {
        if ($paymentMethod === 'card') {
            if (!$paymentProviderConfigured) {
                throw new RuntimeException('Kredi kartı ile ödeme, 3D Secure sanal POS sağlayıcısı bağlandıktan sonra açılacaktır. Kart verilerin kaydedilmedi.');
            }
            throw new RuntimeException('Sanal POS yönlendirme modülü henüz etkinleştirilmedi. Kart bilgilerin işlenmedi ve kaydedilmedi.');
        }
        if ($paymentMethod !== 'cod') {
            throw new RuntimeException('Geçerli bir ödeme yöntemi seçmelisin.');
        }
        $order = store_create_cod_order($pdo, $customer, $addressId, $termsAccepted, $kvkkAccepted, $marketingConsent, $config);
        store_redirect('siparis-tamamlandi.php?order=' . rawurlencode($order['number']));
    } catch (Throwable $exception) {
        store_flash('danger', $exception instanceof RuntimeException ? $exception->getMessage() : 'Sipariş oluşturulamadı. Lütfen tekrar dene.');
        store_redirect('odeme.php');
    }
}

store_render_head('Ödeme');
store_render_header($pdo);
?>
<main class="store-main checkout-main">
  <section class="page-intro compact"><p class="eyebrow">GÜVENLİ ÖDEME</p><h1>Siparişini tamamla.</h1><p>Adresini ve ödeme yöntemini seç; sözleşme kopyaların siparişinle birlikte saklansın.</p></section>
  <?php if (!$items): ?><section class="commerce-empty full-empty"><p class="eyebrow">PELISH</p><h1>Sepetin boş.</h1><p>Ödeme adımına devam etmek için önce sepetine ürün eklemelisin.</p><a class="button button-dark" href="indirimler.php">Ürünleri keşfet <span>→</span></a></section><?php else: ?>
  <form method="post" class="checkout-layout" data-checkout-form novalidate><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>">
    <section class="checkout-steps">
      <article class="checkout-section"><div class="checkout-section-head"><span>01</span><div><p class="eyebrow">TESLİMAT ADRESİ</p><h2>Nereye gönderelim?</h2></div><a href="adresler.php?return=odeme.php">Yeni adres</a></div><?php if (!$addresses): ?><div class="checkout-empty"><p>Kayıtlı teslimat adresin yok.</p><a class="button button-dark" href="adresler.php?return=odeme.php">Adres ekle <span>→</span></a></div><?php else: ?><div class="checkout-addresses"><?php foreach ($addresses as $address): ?><label class="checkout-address"><input type="radio" required name="address_id" value="<?= (int) $address['id'] ?>" <?= $address['is_default'] ? 'checked' : '' ?>><span><strong><?= e($address['title']) ?><?= $address['is_default'] ? ' · Varsayılan' : '' ?></strong><b><?= e($address['recipient_name']) ?></b><small><?= nl2br(e($address['address_line'])) ?><br><?= e($address['district'] . ' / ' . $address['city']) ?></small><em><?= e(store_format_phone($address['phone'])) ?></em></span><a href="adresler.php?<?= e(http_build_query(['edit' => $address['id'], 'return' => 'odeme.php'])) ?>">Düzenle</a></label><?php endforeach; ?></div><?php endif; ?></article>
      <article class="checkout-section"><div class="checkout-section-head"><span>02</span><div><p class="eyebrow">ÖDEME YÖNTEMİ</p><h2>Nasıl ödemek istersin?</h2></div></div><div class="payment-methods"><label class="payment-option"><input type="radio" name="payment_method" value="card" data-payment-method="card"><span><i class="fa-regular fa-credit-card"></i><b>Kredi / banka kartı</b><small>3D Secure ile güvenli ödeme</small><img class="iyzico-checkout-logo" src="assets/payment/iyzico-ile-ode.svg" alt="iyzico ile Öde"></span></label><label class="payment-option"><input type="radio" name="payment_method" value="cod" checked data-payment-method="cod"><span><i class="fa-solid fa-truck-fast"></i><b>Kapıda ödeme</b><small>Ürün tesliminde ödeme</small></span></label></div><div class="card-fields" data-card-fields hidden><p>Bilgilerin yalnızca 3D Secure ödeme kuruluşunun güvenli ekranına aktarılır; pelish kart numaranı, son kullanım tarihini veya CVV bilgisini saklamaz.</p><div class="auth-split"><label>Kart üzerindeki ad soyad<input type="text" autocomplete="cc-name" data-card-name placeholder="AD SOYAD"></label><label>Kart numarası<input inputmode="numeric" autocomplete="cc-number" data-card-number placeholder="0000 0000 0000 0000"></label></div><div class="auth-split"><label>Son kullanma tarihi<input inputmode="numeric" autocomplete="cc-exp" data-card-expiry placeholder="AA/YY"></label><label>Güvenlik kodu<input inputmode="numeric" autocomplete="cc-csc" maxlength="4" data-card-cvv placeholder="CVV"></label></div><small class="payment-security-note">Kart alanlarında isim hariç yalnızca rakam kabul edilir. Bu alanların hiçbirine `name` niteliği verilmemiştir ve sunucuya gönderilmez.</small></div><div class="cod-note" data-cod-note><i class="fa-solid fa-circle-info"></i> Kapıda ödeme ile siparişin oluşturulduğunda ödeme durumun “Bekliyor” olarak kayda alınır.</div></article>
      <article class="checkout-section"><div class="checkout-section-head"><span>03</span><div><p class="eyebrow">ONAYLAR</p><h2>Bilgilendirmeler.</h2></div></div><div class="checkout-consents"><label class="consent-line"><input type="checkbox" required name="pre_information_accepted"><span><a href="yasal.php?belge=on-bilgilendirme" target="_blank" rel="noopener">Ön Bilgilendirme Formu</a>'nu okudum ve onaylıyorum.</span></label><label class="consent-line"><input type="checkbox" required name="terms_accepted"><span><a href="yasal.php?belge=mesafeli-satis" target="_blank" rel="noopener">Mesafeli Satış Sözleşmesi</a>'ni okudum ve onaylıyorum.</span></label><label class="consent-line"><input type="checkbox" required name="kvkk_accepted"><span><a href="yasal.php?belge=kvkk" target="_blank" rel="noopener">KVKK Aydınlatma Metni</a>'ni okudum.</span></label><label class="consent-line optional"><input type="checkbox" name="marketing_consent"><span>Kampanya, indirim ve yeni sezon duyuruları için ticari elektronik ileti almak istiyorum. Bu tercih İYS süreçleriyle yönetilir; dilediğim zaman geri alabilirim.</span></label></div></article>
    </section>
    <aside class="checkout-summary"><p class="eyebrow">SİPARİŞ ÖZETİ</p><h2>Seçtiklerin.</h2><div class="checkout-items"><?php foreach ($items as $item): ?><article><img src="<?= e($item['image_url'] ?: 'https://cdn.pelish.co/logo.png') ?>" alt="<?= e($item['name']) ?>"><span><strong><?= e($item['name']) ?></strong><small><?= e($item['selected_size'] ?: 'Beden seçilmedi') ?><?= $item['color_name'] ? ' · ' . e($item['color_name']) : '' ?> · <?= (int) $item['quantity'] ?> adet</small></span><b><?= e(store_money($item['price']['current'] * (int) $item['quantity'])) ?></b></article><?php endforeach; ?></div><p><span>Ürünler</span><strong><?= e(store_money($totals['subtotal'])) ?></strong></p><p><span>Kargo</span><strong><?= $totals['cargo'] ? e(store_money($totals['cargo'])) : 'Ücretsiz' ?></strong></p><p class="checkout-total"><span>Toplam</span><strong><?= e(store_money($totals['grand_total'])) ?></strong></p><button class="button button-dark" type="submit" <?= !$addresses ? 'disabled' : '' ?>>Siparişi onayla <span>→</span></button><small>Kapıda ödeme siparişin şimdi oluşur. Kartlı ödemeler yalnızca yetkili 3D Secure ödeme kuruluşuna yönlendirilerek tamamlanabilir.</small></aside>
  </form>
  <?php endif; ?>
</main>
<?php store_render_footer(); ?>
