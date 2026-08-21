<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$location = ($_GET['konum'] ?? '') === 'kumbag' ? 'kumbag' : 'suleymanpasa';
$copy = $location === 'kumbag'
    ? ['Kumbağ kadın giyim seçkisi', 'Tekirdağ / Kumbağ', 'Denizden şehre, hafif ve kendin gibi hissettiren parçalar.']
    : ['Süleymanpaşa kadın giyim seçkisi', 'Tekirdağ / Süleymanpaşa', 'Günün ritmine eşlik eden modern, zamansız pelish seçkisi.'];
$products = $pdo->query('SELECT * FROM pelish_products WHERE is_active=1 ORDER BY created_at DESC,id DESC')->fetchAll();
$customer = store_customer($pdo);
$favoriteIds = [];
if ($customer) { $favorite = $pdo->prepare('SELECT product_id FROM pelish_customer_favorites WHERE customer_id=?'); $favorite->execute([$customer['id']]); $favoriteIds = array_map('intval', $favorite->fetchAll(PDO::FETCH_COLUMN)); }
store_render_head($copy[0]);
store_render_header($pdo);
?>
<main class="store-main"><section class="page-intro"><p class="eyebrow"><?= e($copy[1]) ?></p><h1><?= e($copy[0]) ?></h1><p><?= e($copy[2]) ?></p></section><section class="product-grid catalog-product-grid"><?php foreach ($products as $product): store_product_card($pdo, $product, $favoriteIds); endforeach; ?><?php if (!$products): ?><div class="commerce-empty"><p class="eyebrow">PELISH</p><h1>Yeni seçki hazırlanıyor.</h1><p>Yakında burada buluşalım.</p></div><?php endif; ?></section></main>
<?php store_render_footer(); ?>
