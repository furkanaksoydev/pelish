<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$customer = store_require_customer($pdo);
$query = $pdo->prepare('SELECT p.* FROM pelish_customer_favorites f INNER JOIN pelish_products p ON p.id=f.product_id WHERE f.customer_id=? AND p.is_active=1 ORDER BY f.created_at DESC');
$query->execute([$customer['id']]);
$products = $query->fetchAll();
store_render_head('Favorilerim');
store_render_header($pdo);
?>
<main class="store-main"><section class="page-intro compact"><p class="eyebrow">FAVORİLERİM</p><h1>Sevdiklerin.</h1></section><?php if (!$products): ?><section class="commerce-empty full-empty"><p class="eyebrow">PELISH</p><h1>Henüz favori ürünün yok.</h1><p>İyi hissettiren bir parçayı seçtiğinde burada seni bekliyor olacak.</p><a class="button button-dark" href="indirimler.php">İndirimleri keşfet <span>→</span></a></section><?php else: ?><section class="product-grid favorite-product-grid"><?php foreach ($products as $product): $price = store_product_price($product); ?><article class="product-card db-product-card"><a class="product-image" href="urun.php?id=<?= (int) $product['id'] ?>"><img src="<?= e($product['image_url'] ?: 'https://images.unsplash.com/photo-1485968579580-b6d095142e6e?auto=format&fit=crop&w=900&q=85') ?>" alt="<?= e($product['name']) ?>"><?php if ($price['is_discounted']): ?><mark><span>−<?= $price['discount_percent'] ?>%</span> İndirim</mark><?php endif; ?></a><div class="product-actions"><form method="post" action="store/action.php"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><input type="hidden" name="action" value="favorite-remove"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="return_to" value="favorilerim.php"><button class="heart liked" type="submit" aria-label="Favoriden çıkar"><span>♥</span></button></form></div><a class="product-info" href="urun.php?id=<?= (int) $product['id'] ?>"><div><small><?= e($product['category']) ?></small><h3><?= e($product['name']) ?></h3></div><strong><?php if ($price['is_discounted']): ?><del><?= store_money($price['original']) ?></del><?php endif; ?><span><?= store_money($price['current']) ?></span></strong></a></article><?php endforeach; ?></section><?php endif; ?></main>
<?php store_render_footer(); ?>
