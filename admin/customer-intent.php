<?php

declare(strict_types=1);

function customer_carts_enhanced(PDO $pdo): void
{
    $items = $pdo->query(<<<'SQL'
SELECT c.id AS customer_id, c.first_name, c.last_name, c.email, c.phone, cart.updated_at,
       GROUP_CONCAT(CONCAT(p.name, IF(i.selected_size IS NULL, '', CONCAT(' · ', i.selected_size)), IF(i.color_name IS NULL, '', CONCAT(' · ', i.color_name)), ' ×', i.quantity) ORDER BY i.id SEPARATOR '||') AS products,
       SUM(i.quantity) AS item_count,
       SUM(i.quantity * CASE WHEN p.sale_price > 0 AND p.list_price > 0 THEN LEAST(p.sale_price, p.list_price) WHEN p.sale_price > 0 THEN p.sale_price ELSE p.list_price END) AS cart_total
FROM pelish_customer_carts cart
INNER JOIN pelish_customer_cart_items i ON i.cart_id = cart.id
INNER JOIN pelish_customers c ON c.id = cart.customer_id
INNER JOIN pelish_products p ON p.id = i.product_id
GROUP BY cart.id, c.id
ORDER BY cart.updated_at DESC
SQL)->fetchAll();

    $detailCustomerId = (int) ($_GET['cart_customer'] ?? 0);
    $detailCustomer = null;
    $detailItems = [];
    if ($detailCustomerId > 0) {
        $customerStatement = $pdo->prepare('SELECT c.id, c.first_name, c.last_name, c.email, c.phone FROM pelish_customer_carts cart INNER JOIN pelish_customers c ON c.id = cart.customer_id WHERE c.id = ? LIMIT 1');
        $customerStatement->execute([$detailCustomerId]);
        $detailCustomer = $customerStatement->fetch() ?: null;
        if ($detailCustomer) {
            $itemStatement = $pdo->prepare('SELECT i.*, p.name, p.category, p.sale_price, p.list_price, COALESCE(pi.image_url, p.image_url) AS image_url FROM pelish_customer_cart_items i INNER JOIN pelish_customer_carts cart ON cart.id = i.cart_id INNER JOIN pelish_products p ON p.id = i.product_id LEFT JOIN pelish_product_images pi ON pi.id = i.product_image_id WHERE cart.customer_id = ? ORDER BY i.created_at DESC');
            $itemStatement->execute([$detailCustomerId]);
            $detailItems = $itemStatement->fetchAll();
        }
    }

    panel_header('customers', 'Sepetinde Ürün Olanlar', [
        ['label' => 'Müşteri Listesi', 'href' => admin_url('customers')],
        ['label' => 'Sepetinde Ürün Olanlar', 'href' => admin_url('customers', ['view' => 'carts']), 'active' => true],
        ['label' => 'Favori Analizi', 'href' => admin_url('customers', ['view' => 'favorites'])],
    ]);
    ?>
    <section class="licence-card"><div class="licence-icon"><i class="fa-solid fa-cart-shopping"></i></div><div><strong>Satın almaya yakın müşteriler.</strong><p>Bu liste, hesabında ürün bırakan müşterilerin canlı sepetlerini gösterir.</p></div></section>
    <section class="table-card intent-carts-card"><div class="table-title"><div><span>MÜŞTERİ NİYETİ</span><h1>Sepetinde ürün olanlar</h1></div><div class="table-actions"><a class="button ghost" href="<?= admin_url('customers') ?>">Müşteriler</a><a class="button ghost" href="<?= admin_url('customers', ['view' => 'favorites']) ?>"><i class="fa-solid fa-heart"></i> Favori analizi</a></div></div><div class="table-scroll"><table><thead><tr><th>Müşteri</th><th>İletişim</th><th>Sepetteki ürünler</th><th>Adet</th><th>Sepet tutarı</th><th>Son hareket</th><th>İşlem</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><strong><?= e($item['first_name'] . ' ' . $item['last_name']) ?></strong><br><span class="cell-muted">#<?= (int) $item['customer_id'] ?></span></td><td><?= e($item['email']) ?><br><span class="cell-muted"><?= e($item['phone'] ?: '-') ?></span></td><td><?php foreach (explode('||', (string) $item['products']) as $product): ?><span class="cart-product-line"><?= e($product) ?></span><?php endforeach; ?></td><td><strong><?= (int) $item['item_count'] ?></strong></td><td><strong><?= admin_money((float) $item['cart_total']) ?></strong></td><td><?= date('d.m.Y H:i', strtotime($item['updated_at'])) ?></td><td><a class="button ghost small" href="<?= admin_url('customers', ['view' => 'carts', 'cart_customer' => $item['customer_id']]) ?>">İncele</a></td></tr><?php endforeach; if (!$items): ?><tr><td colspan="7" class="empty-table"><i class="fa-solid fa-cart-shopping"></i><strong>Henüz ürün eklenmiş sepet yok.</strong></td></tr><?php endif; ?></tbody></table></div></section>
    <?php if ($detailCustomer): ?><div class="modal is-open"><div class="modal-dialog intent-modal"><div class="modal-head"><div><span>CANLI SEPET DETAYI</span><h2><?= e($detailCustomer['first_name'] . ' ' . $detailCustomer['last_name']) ?></h2><small><?= e($detailCustomer['email']) ?><?= $detailCustomer['phone'] ? ' · ' . e($detailCustomer['phone']) : '' ?></small></div><a class="modal-close" href="<?= admin_url('customers', ['view' => 'carts']) ?>">×</a></div><div class="intent-cart-detail"><?php foreach ($detailItems as $detailItem): $itemPrice = admin_current_product_price($detailItem); ?><article><img src="<?= e($detailItem['image_url'] ?: 'https://cdn.pelish.co/logo.png') ?>" alt="<?= e($detailItem['name']) ?>"><div><strong><?= e($detailItem['name']) ?></strong><span><?= e($detailItem['category']) ?> · <?= e($detailItem['selected_size'] ?: 'Beden seçilmedi') ?><?= $detailItem['color_name'] ? ' · ' . e($detailItem['color_name']) : '' ?></span><small><?= (int) $detailItem['quantity'] ?> adet × <?= admin_money($itemPrice) ?></small></div><b><?= admin_money($itemPrice * (int) $detailItem['quantity']) ?></b></article><?php endforeach; if (!$detailItems): ?><div class="empty-state"><i class="fa-solid fa-cart-shopping"></i><strong>Bu sepet artık boş.</strong></div><?php endif; ?></div><div class="modal-actions"><a class="button ghost" href="<?= admin_url('customers', ['view' => 'carts']) ?>">Kapat</a></div></div></div><?php endif; ?>
    <?php panel_footer();
}

function favorite_products(PDO $pdo): void
{
    $items = $pdo->query('SELECT p.id, p.name, p.category, p.image_url, p.sku, COUNT(f.id) AS favorite_count, COUNT(DISTINCT f.customer_id) AS customer_count FROM pelish_products p LEFT JOIN pelish_customer_favorites f ON f.product_id = p.id GROUP BY p.id ORDER BY favorite_count DESC, p.name')->fetchAll();
    panel_header('customers', 'Favori Analizi', [
        ['label' => 'Müşteri Listesi', 'href' => admin_url('customers')],
        ['label' => 'Sepetinde Ürün Olanlar', 'href' => admin_url('customers', ['view' => 'carts'])],
        ['label' => 'Favori Analizi', 'href' => admin_url('customers', ['view' => 'favorites']), 'active' => true],
    ]);
    ?>
    <section class="licence-card"><div class="licence-icon"><i class="fa-solid fa-heart"></i></div><div><strong>Ürün ilgi sinyalleri.</strong><p>Her ürünün kaç kez favorilere eklendiğini ve kaç farklı müşterinin ilgi gösterdiğini buradan takip et.</p></div></section>
    <section class="table-card"><div class="table-title"><div><span>MÜŞTERİ NİYETİ</span><h1>Ürün favori analizi</h1></div><a class="button ghost" href="<?= admin_url('customers', ['view' => 'carts']) ?>"><i class="fa-solid fa-cart-shopping"></i> Sepetler</a></div><div class="table-scroll"><table><thead><tr><th>Resim</th><th>Ürün</th><th>Kategori</th><th>Toplam favori</th><th>İlgilenen müşteri</th><th>İşlem</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><a class="product-image" href="<?= admin_url('products', ['edit' => $item['id']]) ?>"><img src="<?= e($item['image_url'] ?: 'https://cdn.pelish.co/logo.png') ?>" alt="<?= e($item['name']) ?>"></a></td><td><strong><?= e($item['name']) ?></strong><br><span class="cell-muted"><?= e($item['sku']) ?></span></td><td><?= e($item['category']) ?></td><td><strong><i class="fa-solid fa-heart"></i> <?= (int) $item['favorite_count'] ?></strong></td><td><?= (int) $item['customer_count'] ?></td><td><a class="button ghost small" href="<?= admin_url('products', ['edit' => $item['id']]) ?>">Ürünü incele</a></td></tr><?php endforeach; if (!$items): ?><tr><td colspan="6" class="empty-table"><i class="fa-solid fa-heart"></i><strong>Henüz ürün bulunmuyor.</strong></td></tr><?php endif; ?></tbody></table></div></section>
    <?php panel_footer();
}
