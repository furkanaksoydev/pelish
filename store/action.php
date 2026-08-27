<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { store_redirect('../index.php'); }
$isAsync = (string) ($_POST['_response'] ?? '') === 'json'
    || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
$pelishAsyncOutputLevel = ob_get_level();
if ($isAsync) {
    // PHP uyarıları ekrana basılsa bile AJAX sözleşmesi JSON kalır. Uyarılar
    // sunucunun hata günlüğünde tutulur; istemci HTML yanıtı yüzünden başka
    // bir sayfaya yönlendirilmez.
    ini_set('display_errors', '0');
    ob_start();
}

/** @param array<string, mixed> $payload */
function store_action_json(array $payload, int $status = 200): never
{
    global $pelishAsyncOutputLevel;
    while (ob_get_level() > (int) $pelishAsyncOutputLevel) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// AJAX istekleri için hata dâhil her koşulda JSON döndürürüz. Böylece
// tarayıcı bir yönlendirme ya da hata sayfasını JSON olarak yorumlamaya çalışmaz.
$csrf = $_POST['csrf'] ?? '';
if (!is_string($csrf) || !hash_equals(store_csrf(), $csrf)) {
    if ($isAsync) {
        store_action_json(['ok' => false, 'message' => 'Oturumun yenilendi. Sayfayı yenileyip tekrar dene.'], 419);
    }
    store_verify_csrf();
}
$action = (string) ($_POST['action'] ?? '');
$returnTo = store_safe_return($_POST['return_to'] ?? null, store_referer_return());
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
$submittedSize = trim((string) ($_POST['size'] ?? ''));
if (mb_strtolower($submittedSize, 'UTF-8') === 'standart') { $submittedSize = 'Standart'; } else { $submittedSize = strtoupper($submittedSize); }
$pending = [
    'type' => $action,
    'product_id' => $productId,
    'image_id' => (int) ($_POST['image_id'] ?? 0),
    'color_id' => (int) ($_POST['color_id'] ?? 0),
    'size' => $submittedSize,
    'return_to' => $returnTo,
];

if ($action === 'favorite' && !store_customer($pdo)) {
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
if ($action === 'cart' && !$customer) {
    try { store_flash('success', store_guest_add_to_cart($pdo, $productId, (int) $pending['image_id'], (string) $pending['size'], (int) $pending['color_id'])); }
    catch (Throwable $exception) { store_flash('danger', $exception instanceof RuntimeException ? $exception->getMessage() : 'Ürün sepete eklenemedi.'); }
    store_redirect('../' . $returnTo);
}
if (!$customer && in_array($action, ['cart-quantity', 'cart-remove'], true)) {
    $key = (string) ($_POST['guest_key'] ?? '');
    $lines = (array) ($_SESSION['pelish_guest_cart'] ?? []);
    if ($key === '' || !isset($lines[$key])) {
        store_flash('danger', 'Sepet satırı bulunamadı.');
        store_redirect('../sepetim.php');
    }
    if ($action === 'cart-remove') {
        unset($lines[$key]);
        $_SESSION['pelish_guest_cart'] = $lines;
        store_flash('success', 'Ürün sepetinden kaldırıldı.');
        store_redirect('../sepetim.php');
    }
    $change = (int) ($_POST['change'] ?? 0);
    $quantity = (int) ($lines[$key]['quantity'] ?? 0);
    if (!in_array($change, [-1, 1], true) || ($change < 0 && $quantity <= 1)) {
        store_flash('danger', 'Sepette en az bir ürün kalmalı.');
        store_redirect('../sepetim.php');
    }
    if ($change > 0) {
        $stock = store_size_stock($pdo, (int) $lines[$key]['product_id'], (string) $lines[$key]['size'], (int) ($lines[$key]['color_id'] ?? 0));
        if ($stock === null || $quantity >= $stock) {
            store_flash('danger', 'Bu beden için stok sınırına ulaştın.');
            store_redirect('../sepetim.php');
        }
    }
    $lines[$key]['quantity'] = $quantity + $change;
    $_SESSION['pelish_guest_cart'] = $lines;
    store_flash('success', 'Sepet adedi güncellendi.');
    store_redirect('../sepetim.php');
}
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
        if (!$isAsync) { store_flash('success', $message); }
    } elseif ($action === 'cart') {
        store_flash('success', store_add_to_cart($pdo, (int) $customer['id'], $productId, (int) $pending['image_id'], (string) $pending['size'], (int) $pending['color_id']));
    } elseif ($action === 'cart-quantity') {
        $lineId = (int) ($_POST['line_id'] ?? 0);
        $change = (int) ($_POST['change'] ?? 0);
        if (!in_array($change, [-1, 1], true)) { throw new RuntimeException('Geçersiz adet işlemi.'); }
        $line = $pdo->prepare('SELECT i.* FROM pelish_customer_cart_items i INNER JOIN pelish_customer_carts c ON c.id=i.cart_id WHERE i.id=? AND c.customer_id=?');
        $line->execute([$lineId, $customer['id']]);
        $item = $line->fetch();
        if (!$item) { throw new RuntimeException('Sepet satırı bulunamadı.'); }
        if ($change < 0 && (int) $item['quantity'] <= 1) { throw new RuntimeException('Sepette en az bir ürün kalmalı.'); }
        if ($change > 0) {
            $sizeStock = store_size_stock($pdo, (int) $item['product_id'], (string) $item['selected_size'], (int) ($item['color_id'] ?? 0));
            if ($sizeStock === null || $sizeStock < 1) {
                throw new RuntimeException('Bu beden artık stokta yok.');
            }
            $lineTotal = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM pelish_customer_cart_items WHERE cart_id = ? AND product_id = ? AND selected_size = ? AND color_id <=> ?');
            $lineTotal->execute([(int) $item['cart_id'], (int) $item['product_id'], (string) $item['selected_size'], $item['color_id'] ?: null]);
            if ((int) $lineTotal->fetchColumn() >= $sizeStock) {
                throw new RuntimeException('Bu beden için stok sınırına ulaştın.');
            }
        }
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
        if (!$isAsync) { store_flash('success', $message); }
    } else {
        throw new RuntimeException('Bilinmeyen işlem.');
    }
} catch (Throwable $exception) {
    $message = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'Favori işlemi şu anda tamamlanamadı. Lütfen tekrar dene.';
    if ($isAsync) {
        store_action_json(['ok' => false, 'message' => $message], 422);
    }
    store_flash('danger', $message);
}
if ($isAsync && $isFavorite !== null) {
    // Bildirim sayacı yardımcı bir veridir; sayacı okurken oluşabilecek bir
    // hata, başarılı favori işleminin JSON yanıtını hiçbir zaman HTML hata
    // sayfasına dönüştürmemeli.
    $favoriteTotal = 0;
    try {
        $favoriteCount = $pdo->prepare('SELECT COUNT(*) FROM pelish_customer_favorites WHERE customer_id = ?');
        $favoriteCount->execute([$customer['id']]);
        $favoriteTotal = (int) $favoriteCount->fetchColumn();
    } catch (Throwable $ignored) {
        $favoriteTotal = 0;
    }
    store_action_json([
        'ok' => true,
        'is_favorite' => $isFavorite,
        'favorite_count' => $favoriteTotal,
        'message' => $message,
    ]);
}
store_redirect('../' . $returnTo);
