<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$product = store_product($pdo, (int) ($_GET['id'] ?? 0));
if (!$product) {
    http_response_code(404);
    store_render_head('Ürün bulunamadı');
    store_render_header($pdo);
    ?>
    <main class="store-main"><section class="commerce-empty"><p class="eyebrow">404</p><h1>Bu ürüne ulaşamadık.</h1><a class="button button-dark" href="indirimler.php">Seçkiye dön</a></section></main>
    <?php
    store_render_footer();
    exit;
}

$images = store_product_images($pdo, (int) $product['id']);
if (!$images && !empty($product['image_url'])) {
    $images = [['id' => 0, 'image_url' => (string) $product['image_url'], 'color_name' => null, 'color_hex' => null, 'is_primary' => 1]];
}
$colorOptions = [];
foreach ($images as $index => $image) {
    // Aynı isimli tonlar (ör. altı farklı lacivert çekimi) müşteriye tek renk
    // seçeneği olarak görünür; yalnızca isim yoksa renk kodu ayırt edicidir.
    $identity = mb_strtolower(trim((string) ($image['color_name'] ?: $image['color_hex'] ?: 'varsayılan')), 'UTF-8');
    $images[$index]['color_key'] = 'color-' . substr(hash('sha256', $identity), 0, 12);
    if (!isset($colorOptions[$identity])) {
        $colorOptions[$identity] = $images[$index];
    }
}
$primary = $images[0] ?? ['id' => 0, 'image_url' => 'https://cdn.pelish.co/pelishlogo.png', 'color_name' => null, 'color_hex' => null, 'color_key' => 'color-default'];
$customer = store_customer($pdo);
$isFavorite = false;
if ($customer) {
    $favorite = $pdo->prepare('SELECT 1 FROM pelish_customer_favorites WHERE customer_id = ? AND product_id = ?');
    $favorite->execute([$customer['id'], $product['id']]);
    $isFavorite = (bool) $favorite->fetchColumn();
}
$price = store_product_price($product);
store_render_head($product['name']);
store_render_header($pdo);
?>
<main class="store-main">
  <section class="product-detail">
    <div class="product-gallery-detail">
      <div class="detail-main-image"><button type="button" class="image-expand" data-gallery-expand aria-label="Görseli büyüt"><img id="detailMainImage" src="<?= e($primary['image_url']) ?>" alt="<?= e($product['name']) ?>"></button></div>
      <div class="detail-thumbs" role="list">
        <?php foreach ($images as $index => $image): ?>
          <button type="button" class="<?= $index === 0 ? 'active' : '' ?>" data-gallery-image data-image-id="<?= (int) $image['id'] ?>" data-color-key="<?= e($image['color_key']) ?>" data-image-url="<?= e($image['image_url']) ?>" data-image-alt="<?= e($product['name'] . ($image['color_name'] ? ' · ' . $image['color_name'] : '')) ?>" data-color-name="<?= e($image['color_name'] ?: 'Varsayılan renk') ?>"><img src="<?= e($image['image_url']) ?>" alt="<?= e($image['color_name'] ?: $product['name']) ?>"></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="product-detail-copy">
      <p class="eyebrow"><?= e($product['category']) ?></p>
      <h1><?= e($product['name']) ?></h1>
      <div class="detail-price"><?php if ($price['is_discounted']): ?><del><?= store_money($price['original']) ?></del><span class="detail-discount">−<?= $price['discount_percent'] ?>%</span><?php endif; ?><strong><?= store_money($price['current']) ?></strong></div>
      <form method="post" action="store/action.php" class="purchase-form">
        <input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>">
        <input type="hidden" name="action" value="cart">
        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
        <input type="hidden" name="image_id" id="selectedImageId" value="<?= (int) $primary['id'] ?>">
        <input type="hidden" name="return_to" value="<?= e('urun.php?id=' . (int) $product['id']) ?>">
        <div class="choice-heading"><span>RENK</span><b id="selectedColorName"><?= e($primary['color_name'] ?: 'Varsayılan renk') ?></b></div>
        <div class="detail-colors">
          <?php foreach ($colorOptions as $image): ?>
            <button class="<?= $image['color_key'] === $primary['color_key'] ? 'active' : '' ?>" type="button" data-color-select data-color-key="<?= e($image['color_key']) ?>" data-image-id="<?= (int) $image['id'] ?>" data-image-url="<?= e($image['image_url']) ?>" data-color-name="<?= e($image['color_name'] ?: 'Varsayılan renk') ?>" style="--swatch:<?= e($image['color_hex'] ?: '#c7b6a3') ?>" aria-label="<?= e($image['color_name'] ?: 'Renk seç') ?>"></button>
          <?php endforeach; ?>
        </div>
        <div class="choice-heading"><span>BEDEN</span><b id="selectedSizeLabel">Beden seçin</b></div>
        <div class="detail-sizes"><?php foreach (['S', 'M', 'L', 'XL', 'XXL'] as $size): ?><label><input type="radio" name="size" value="<?= $size ?>"><span><?= $size ?></span></label><?php endforeach; ?></div>
        <div class="detail-actions"><button class="button button-dark" type="submit">Sepete ekle <span>→</span></button></div>
      </form>
      <form method="post" action="store/action.php" data-favorite-form>
        <input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>">
        <input type="hidden" name="action" value="favorite">
        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
        <input type="hidden" name="return_to" value="<?= e('urun.php?id=' . (int) $product['id']) ?>">
        <button class="favorite-outline <?= $isFavorite ? 'liked' : '' ?>" type="submit"><i class="<?= $isFavorite ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i> <?= $isFavorite ? 'Favoriden çıkar' : 'Favorile' ?></button>
      </form>
    </div>
  </section>
</main>
<div class="gallery-lightbox" id="galleryLightbox" aria-hidden="true"><button type="button" data-lightbox-close aria-label="Kapat">×</button><button type="button" data-lightbox-prev aria-label="Önceki">←</button><img src="" alt=""><button type="button" data-lightbox-next aria-label="Sonraki">→</button></div>
<?php store_render_footer(); ?>
