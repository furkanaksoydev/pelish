<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { store_redirect('../index.php'); }
$isAsync = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');

/** @param array<string, mixed> $payload */
function store_action_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

store_verify_csrf();
$action = (string) ($_POST['action'] ?? '');
$returnTo = store_safe_return($_POST['return_to'] ?? null);
// Adet ve satır silme yalnızca sepet bağlamında çalışır. Dönüş adresi
// istemciden gelse bile bu işlemleri sepet ekranına sabitliyoruz.
if (in_array($action, ['cart-quantity', 'cart-remove'], true)) {
    $returnTo = 'sepetim.php';
}
$productId = (int) ($_POST['product_id'] ?? 0);
if ($action === 'cart' && $productId > 0) {
    $returnTo = 'urun.php?id=' . $productId;
}
if ($action === 'favorite' && $productId > 0 && str_starts_with($returnTo, 'urun.php')) {
    $returnTo = 'urun.php?id=' . $productId;
}
$pending = [
    'type' => $action,
    'product_id' => $productId,
    'image_id' => (int) ($_POST['image_id'] ?? 0),
    'size' => strtoupper(trim((string) ($_POST['size'] ?? ''))),
    'return_to' => $returnTo,
];

if (in_array($action, ['favorite', 'cart'], true) && !store_customer($pdo)) {
    $_SESSION['pelish_pending_action'] = $pending;
    store_flash('info', 'Bu işlemi tamamlamak için giriş yapmalısın.');
    if ($isAsync) {
        store_action_json([
            'ok' => false,
            'requires_auth' => true,
            'redirect' => store_url('giris.php'),
        ], 401);
    }
    store_redirect('../giris.php');
}

$customer = store_customer($pdo);
if (!$customer) {
    if ($isAsync) {
        store_action_json(['ok' => false, 'requires_auth' => true, 'redirect' => store_url('giris.php')], 401);
    }
    store_redirect('../giris.php');
}
$message = '';
$isFavorite = null;
try {
    if ($action === 'favorite') {
        $isFavorite = store_toggle_favorite($pdo, (int) $customer['id'], $productId);
        $message = $isFavorite ? 'Favorilerine eklendi.' : 'Favorilerinden çıkarıldı.';
        store_flash('success', $message);
    } elseif ($action === 'cart') {
        store_flash('success', store_add_to_cart($pdo, (int) $customer['id'], $productId, (int) $pending['image_id'], (string) $pending['size']));
    } elseif ($action === 'cart-quantity') {
        $lineId = (int) ($_POST['line_id'] ?? 0);
        $change = (int) ($_POST['change'] ?? 0);
        if (!in_array($change, [-1, 1], true)) { throw new RuntimeException('Geçersiz adet işlemi.'); }
        $line = $pdo->prepare('SELECT i.* FROM pelish_customer_cart_items i INNER JOIN pelish_customer_carts c ON c.id=i.cart_id WHERE i.id=? AND c.customer_id=?');
        $line->execute([$lineId, $customer['id']]);
        $item = $line->fetch();
        if (!$item) { throw new RuntimeException('Sepet satırı bulunamadı.'); }
        if ($change < 0 && (int) $item['quantity'] <= 1) { throw new RuntimeException('Sepette en az bir ürün kalmalı.'); }
        $pdo->prepare('UPDATE pelish_customer_cart_items SET quantity = quantity + ? WHERE id = ?')->execute([$change, $lineId]);
        store_flash('success', 'Sepet adedi güncellendi.');
    } elseif ($action === 'cart-remove') {
        $delete = $pdo->prepare('DELETE i FROM pelish_customer_cart_items i INNER JOIN pelish_customer_carts c ON c.id=i.cart_id WHERE i.id=? AND c.customer_id=?');
        $delete->execute([(int) ($_POST['line_id'] ?? 0), $customer['id']]);
        store_flash('success', 'Ürün sepetinden kaldırıldı.');
    } elseif ($action === 'favorite-remove') {
        $pdo->prepare('DELETE FROM pelish_customer_favorites WHERE customer_id=? AND product_id=?')->execute([$customer['id'], $productId]);
        $isFavorite = false;
        $message = 'Ürün favorilerinden kaldırıldı.';
        store_flash('success', $message);
    } else {
        throw new RuntimeException('Bilinmeyen işlem.');
    }
} catch (RuntimeException $exception) {
    if ($isAsync) {
        store_action_json(['ok' => false, 'message' => $exception->getMessage()], 422);
    }
    store_flash('danger', $exception->getMessage());
}
if ($isAsync && $isFavorite !== null) {
    store_action_json([
        'ok' => true,
        'is_favorite' => $isFavorite,
        'favorite_count' => store_counts($pdo, $customer)['favorites'],
        'message' => $message,
    ]);
}
store_redirect('../' . $returnTo);
