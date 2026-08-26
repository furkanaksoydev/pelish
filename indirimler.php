<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$query = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$where = ['p.is_active = 1', 'p.sale_price > 0', 'p.list_price > 0', 'p.sale_price <> p.list_price'];
$params = [];
if ($query !== '') {
    $where[] = '(p.name LIKE ? OR p.category LIKE ?)';
    $params[] = '%' . $query . '%';
    $params[] = '%' . $query . '%';
}
if ($category !== '') {
    $where[] = 'p.category = ?';
    $params[] = $category;
}
$statement = $pdo->prepare('SELECT p.* FROM pelish_products p WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC, p.id DESC');
$statement->execute($params);
$products = $statement->fetchAll();
$categories = store_active_categories($pdo, true);
$customer = store_customer($pdo);
$favoriteIds = [];
if ($customer) { $favorites = $pdo->prepare('SELECT product_id FROM pelish_customer_favorites WHERE customer_id = ?'); $favorites->execute([$customer['id']]); $favoriteIds = array_map('intval', $favorites->fetchAll(PDO::FETCH_COLUMN)); }
store_render_head('İndirim');
store_render_header($pdo);
?>
<main class="store-main"><section class="page-intro"><p class="eyebrow">PELISH SEÇKİSİ</p><h1>İndirim</h1><p>Yalnızca avantajlı fiyatla sunulan pelish parçaları burada.</p></section><section class="catalog-toolbar"><form method="get" class="catalog-search"><i class="fa-solid fa-magnifying-glass"></i><input name="q" value="<?= e($query) ?>" placeholder="İndirimli ürün ara"><button type="submit">Ara</button></form><div class="catalog-filters"><a class="<?= $category === '' ? 'active' : '' ?>" href="indirimler.php<?= $query !== '' ? '?q=' . rawurlencode($query) : '' ?>">Tümü</a><?php foreach ($categories as $item): ?><a class="<?= $category === $item ? 'active' : '' ?>" href="indirimler.php?<?= e(http_build_query(['category' => $item, 'q' => $query ?: null])) ?>"><?= e($item) ?></a><?php endforeach; ?></div></section><section class="product-grid catalog-product-grid"><?php foreach ($products as $product): store_product_card($pdo, $product, $favoriteIds); endforeach; ?><?php if (!$products): ?><div class="commerce-empty"><p class="eyebrow">İNDİRİM</p><h1>Bu filtrede ürün yok.</h1><p>Başka bir kategori veya ürün adı deneyebilirsin.</p><a class="button button-dark" href="indirimler.php">Tüm indirimleri gör</a></div><?php endif; ?></section></main>
<?php store_render_footer(); ?>
