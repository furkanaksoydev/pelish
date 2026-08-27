<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$customer = store_customer($pdo);
$items = store_active_cart_items($pdo, $customer);
$subtotal = array_reduce($items, static fn(float $sum, array $item): float => $sum + ($item['price']['current'] * (int) $item['quantity']), 0.0);
$cargo = $subtotal > 0 && $subtotal < 3500 ? 79.0 : 0.0;
store_render_head('Sepetim');
store_render_header($pdo);
?>
<main class="store-main">
  <section class="page-intro compact"><p class="eyebrow">SEPETİM</p><h1>Seçtiklerin.</h1></section>
  <?php if (!$items): ?>
    <section class="commerce-empty full-empty"><p class="eyebrow">PELISH</p><h1>Sepetin henüz boş.</h1><p>İyi hissettiren bir parçayı seçtiğinde burada seni bekliyor olacak.</p><a class="button button-dark" href="indirimler.php">İndirimleri keşfet <span>→</span></a></section>
  <?php else: ?>
    <section class="saved-layout">
      <div class="saved-products">
        <?php foreach ($items as $item): ?>
          <article class="saved-card">
            <a href="urun.php?id=<?= (int) $item['product_id'] ?>"><img src="<?= e($item['image_url'] ?: 'https://cdn.pelish.co/logo.png') ?>" alt="<?= e($item['name']) ?>"></a>
            <div class="saved-card-copy">
              <small><?= e($item['category']) ?></small><h2><?= e($item['name']) ?></h2>
              <strong class="cart-item-price"><?php if ($item['price']['is_discounted']): ?><del><?= store_money($item['price']['original']) ?></del><?php endif; ?><?= store_money($item['price']['current']) ?></strong>
              <div class="cart-variants"><small class="cart-size">Beden: <?= e($item['selected_size']) ?></small><?php if ($item['color_name']): ?><small class="cart-color"><i style="--cart-color:<?= e($item['color_hex'] ?: '#bbb') ?>"></i>Renk: <?= e($item['color_name']) ?></small><?php endif; ?></div>
              <div class="saved-card-actions cart-line-actions">
                <form method="post" action="store/action.php"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><input type="hidden" name="action" value="cart-quantity"><input type="hidden" name="line_id" value="<?= (int) $item['id'] ?>"><?php if (!$customer): ?><input type="hidden" name="guest_key" value="<?= e((string) $item['guest_key']) ?>"><?php endif; ?><input type="hidden" name="change" value="-1"><input type="hidden" name="return_to" value="sepetim.php"><button type="submit" <?= (int) $item['quantity'] === 1 ? 'disabled' : '' ?> aria-label="Adedi azalt">−</button></form>
                <output><?= (int) $item['quantity'] ?> adet</output>
                <form method="post" action="store/action.php"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><input type="hidden" name="action" value="cart-quantity"><input type="hidden" name="line_id" value="<?= (int) $item['id'] ?>"><?php if (!$customer): ?><input type="hidden" name="guest_key" value="<?= e((string) $item['guest_key']) ?>"><?php endif; ?><input type="hidden" name="change" value="1"><input type="hidden" name="return_to" value="sepetim.php"><button type="submit" aria-label="Adedi artır">+</button></form>
                <form method="post" action="store/action.php"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><input type="hidden" name="action" value="cart-remove"><input type="hidden" name="line_id" value="<?= (int) $item['id'] ?>"><?php if (!$customer): ?><input type="hidden" name="guest_key" value="<?= e((string) $item['guest_key']) ?>"><?php endif; ?><input type="hidden" name="return_to" value="sepetim.php"><button class="remove-line" type="submit">Kaldır</button></form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <aside class="cart-summary"><h2>Sipariş özeti</h2><p><span>Ürünler (<?= array_sum(array_column($items, 'quantity')) ?>)</span><strong><?= store_money($subtotal) ?></strong></p><p><span>Kargo</span><strong><?= $cargo ? store_money($cargo) : 'Ücretsiz' ?></strong></p><p class="cart-total"><span>Toplam</span><strong><?= store_money($subtotal + $cargo) ?></strong></p><a class="button button-dark" href="odeme.php">Ödemeye geç <span>→</span></a><small><?= $customer ? 'Ürünlerin hesabında güvenle saklanır.' : 'Üyelik oluşturmadan da siparişini tamamlayabilirsin.' ?></small></aside>
    </section>
  <?php endif; ?>
</main>
<?php store_render_footer(); ?>
