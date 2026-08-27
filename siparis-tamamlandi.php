<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$customer = store_customer($pdo);
$number = trim((string) ($_GET['order'] ?? ''));
if ($customer) {
    $orderQuery = $pdo->prepare('SELECT * FROM pelish_orders WHERE order_number = ? AND customer_id = ? LIMIT 1');
    $orderQuery->execute([$number, $customer['id']]);
} elseif (hash_equals((string) ($_SESSION['pelish_guest_order_number'] ?? ''), $number)) {
    $orderQuery = $pdo->prepare('SELECT * FROM pelish_orders WHERE order_number = ? AND customer_id IS NULL LIMIT 1');
    $orderQuery->execute([$number]);
} else {
    $orderQuery = null;
}
$order = $orderQuery ? $orderQuery->fetch() : false;
if (!$order) { store_flash('danger', 'Sipariş bilgisi bulunamadı.'); store_redirect('index.php'); }
store_render_head('Siparişin alındı');
store_render_header($pdo);
?>
<main class="store-main order-complete-main"><section class="order-complete"><i class="fa-solid fa-clock"></i><p class="eyebrow">DEKONTUN ALINDI</p><h1>Teşekkürler.</h1><p><strong><?= e($order['order_number']) ?></strong> numaralı siparişin ve Havale / EFT dekontun incelemeye alındı. Ödemen onaylandıktan sonra siparişin hazırlanmaya başlanacak.</p><div><span>Sipariş toplamı</span><strong><?= e(store_money((float) $order['grand_total'])) ?></strong></div><div><span>Teslimat</span><strong><?= e($order['recipient_name']) ?></strong></div><a class="button button-dark" href="indirimler.php">Alışverişe devam et <span>→</span></a></section></main>
<?php store_render_footer(); ?>
