<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$customer = store_require_customer($pdo);
$number = trim((string) ($_GET['order'] ?? ''));
$orderQuery = $pdo->prepare('SELECT * FROM pelish_orders WHERE order_number = ? AND customer_id = ? LIMIT 1');
$orderQuery->execute([$number, $customer['id']]);
$order = $orderQuery->fetch();
if (!$order) {
    store_flash('danger', 'Sipariş bilgisi bulunamadı.');
    store_redirect('profil.php');
}
store_render_head('Siparişin alındı');
store_render_header($pdo);
?>
<main class="store-main order-complete-main"><section class="order-complete"><i class="fa-solid fa-circle-check"></i><p class="eyebrow">SİPARİŞİN ALINDI</p><h1>Teşekkürler.</h1><p><strong><?= e($order['order_number']) ?></strong> numaralı siparişini aldık. Kapıda ödeme seçtiğin için ödeme, teslimat sırasında tamamlanacak.</p><div><span>Sipariş toplamı</span><strong><?= e(store_money((float) $order['grand_total'])) ?></strong></div><div><span>Teslimat</span><strong><?= e($order['recipient_name']) ?></strong></div><a class="button button-dark" href="indirimler.php">Alışverişe devam et <span>→</span></a></section></main>
<?php store_render_footer(); ?>
