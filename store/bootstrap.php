<?php

declare(strict_types=1);

require_once __DIR__ . '/../admin/bootstrap.php';

function store_url(string $path = 'index.php', array $params = []): string
{
    return $path . ($params ? '?' . http_build_query($params) : '');
}

function store_money(float $value): string
{
    return '₺' . number_format($value, 2, ',', '.');
}

function store_csrf(): string
{
    if (empty($_SESSION['pelish_store_csrf'])) {
        $_SESSION['pelish_store_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['pelish_store_csrf'];
}

function store_verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(store_csrf(), $token)) {
        http_response_code(419);
        exit('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
    }
}

function store_flash(string $type, string $message): void
{
    $_SESSION['pelish_store_flash'] = ['type' => $type, 'message' => $message];
}

function store_take_flash(): ?array
{
    $flash = $_SESSION['pelish_store_flash'] ?? null;
    unset($_SESSION['pelish_store_flash']);
    return is_array($flash) ? $flash : null;
}

function store_safe_return(?string $value, string $fallback = 'index.php'): string
{
    $value = trim((string) $value);
    if ($value !== '' && preg_match('#^(?:[a-z0-9-]+\.php)(?:\?[a-z0-9&=._%+-]+)?(?:#[a-z0-9-]+)?$#i', $value)) {
        return $value;
    }
    return $fallback;
}

function store_redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function store_customer(PDO $pdo): ?array
{
    $id = (int) ($_SESSION['pelish_customer_id'] ?? 0);
    if ($id < 1) {
        return null;
    }
    $statement = $pdo->prepare('SELECT * FROM pelish_customers WHERE id = ? AND is_active = 1 LIMIT 1');
    $statement->execute([$id]);
    $customer = $statement->fetch();
    if (!$customer || empty($customer['password_hash']) || empty($customer['email_verified_at'])) {
        unset($_SESSION['pelish_customer_id']);
        return null;
    }
    return $customer;
}

function store_login_customer(array $customer): void
{
    session_regenerate_id(true);
    $_SESSION['pelish_customer_id'] = (int) $customer['id'];
}

function store_logout_customer(): void
{
    unset($_SESSION['pelish_customer_id'], $_SESSION['pelish_pending_action']);
}

function store_require_customer(PDO $pdo, array $pending = []): array
{
    if ($customer = store_customer($pdo)) {
        return $customer;
    }
    if ($pending) {
        $_SESSION['pelish_pending_action'] = $pending;
    }
    store_flash('info', 'Bu işlemi tamamlamak için giriş yapmalısın.');
    store_redirect(store_url('giris.php'));
}

function store_product(PDO $pdo, int $id, bool $activeOnly = true): ?array
{
    $sql = 'SELECT p.*, COALESCE((SELECT COUNT(*) FROM pelish_customer_favorites f WHERE f.product_id = p.id), 0) AS favorite_count
            FROM pelish_products p WHERE p.id = ?' . ($activeOnly ? ' AND p.is_active = 1' : '') . ' LIMIT 1';
    $statement = $pdo->prepare($sql);
    $statement->execute([$id]);
    return $statement->fetch() ?: null;
}

function store_product_images(PDO $pdo, int $productId): array
{
    $statement = $pdo->prepare('SELECT * FROM pelish_product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order, id');
    $statement->execute([$productId]);
    $images = $statement->fetchAll();
    if (!$images) {
        $product = store_product($pdo, $productId, false);
        if ($product && !empty($product['image_url'])) {
            $images[] = ['id' => 0, 'product_id' => $productId, 'image_url' => $product['image_url'], 'color_name' => null, 'color_hex' => null, 'is_primary' => 1];
        }
    }
    return $images;
}

function store_cart_id(PDO $pdo, int $customerId): int
{
    $find = $pdo->prepare('SELECT id FROM pelish_customer_carts WHERE customer_id = ?');
    $find->execute([$customerId]);
    $id = (int) $find->fetchColumn();
    if ($id > 0) {
        return $id;
    }
    $create = $pdo->prepare('INSERT INTO pelish_customer_carts (customer_id) VALUES (?)');
    $create->execute([$customerId]);
    return (int) $pdo->lastInsertId();
}

function store_add_to_cart(PDO $pdo, int $customerId, int $productId, int $imageId, string $size): string
{
    $product = store_product($pdo, $productId);
    if (!$product || (int) $product['stock'] < 1) {
        throw new RuntimeException('Bu ürün şu anda stokta değil.');
    }
    $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
    if (!in_array($size, $sizes, true)) {
        throw new RuntimeException('Lütfen bir beden seçin.');
    }
    $image = null;
    if ($imageId > 0) {
        $imageStatement = $pdo->prepare('SELECT * FROM pelish_product_images WHERE id = ? AND product_id = ?');
        $imageStatement->execute([$imageId, $productId]);
        $image = $imageStatement->fetch() ?: null;
        if (!$image) {
            throw new RuntimeException('Seçilen renk görseli bulunamadı.');
        }
    }
    $cartId = store_cart_id($pdo, $customerId);
    $find = $pdo->prepare('SELECT id, quantity FROM pelish_customer_cart_items WHERE cart_id = ? AND product_id = ? AND product_image_id <=> ? AND selected_size = ? LIMIT 1');
    $find->execute([$cartId, $productId, $image ? (int) $image['id'] : null, $size]);
    $line = $find->fetch();
    if ($line) {
        $pdo->prepare('UPDATE pelish_customer_cart_items SET quantity = quantity + 1 WHERE id = ?')->execute([$line['id']]);
    } else {
        $insert = $pdo->prepare('INSERT INTO pelish_customer_cart_items (cart_id, product_id, product_image_id, selected_size, color_name, color_hex, quantity) VALUES (?, ?, ?, ?, ?, ?, 1)');
        $insert->execute([$cartId, $productId, $image ? (int) $image['id'] : null, $size, $image['color_name'] ?? null, $image['color_hex'] ?? null]);
    }
    $pdo->prepare('UPDATE pelish_customer_carts SET updated_at = NOW() WHERE id = ?')->execute([$cartId]);
    $parts = [$product['name'], $size];
    if (!empty($image['color_name'])) {
        $parts[] = $image['color_name'];
    }
    return implode(' · ', $parts) . ' sepetine eklendi.';
}

function store_toggle_favorite(PDO $pdo, int $customerId, int $productId): bool
{
    if (!store_product($pdo, $productId)) {
        throw new RuntimeException('Ürün bulunamadı.');
    }
    $find = $pdo->prepare('SELECT id FROM pelish_customer_favorites WHERE customer_id = ? AND product_id = ?');
    $find->execute([$customerId, $productId]);
    $existing = (int) $find->fetchColumn();
    if ($existing > 0) {
        $pdo->prepare('DELETE FROM pelish_customer_favorites WHERE id = ?')->execute([$existing]);
        return false;
    }
    $pdo->prepare('INSERT INTO pelish_customer_favorites (customer_id, product_id) VALUES (?, ?)')->execute([$customerId, $productId]);
    return true;
}

function store_finish_pending_action(PDO $pdo, int $customerId): void
{
    $pending = $_SESSION['pelish_pending_action'] ?? null;
    unset($_SESSION['pelish_pending_action']);
    if (!is_array($pending) || empty($pending['type'])) {
        return;
    }
    try {
        if ($pending['type'] === 'favorite') {
            $added = store_toggle_favorite($pdo, $customerId, (int) ($pending['product_id'] ?? 0));
            store_flash('success', $added ? 'Favorilerine eklendi.' : 'Favorilerinden çıkarıldı.');
        }
        if ($pending['type'] === 'cart') {
            store_flash('success', store_add_to_cart($pdo, $customerId, (int) ($pending['product_id'] ?? 0), (int) ($pending['image_id'] ?? 0), (string) ($pending['size'] ?? '')));
        }
    } catch (RuntimeException $exception) {
        store_flash('danger', $exception->getMessage());
    }
}

function store_counts(PDO $pdo, ?array $customer = null): array
{
    $customer ??= store_customer($pdo);
    if (!$customer) {
        return ['cart' => 0, 'favorites' => 0];
    }
    $cart = $pdo->prepare('SELECT COALESCE(SUM(i.quantity), 0) FROM pelish_customer_cart_items i INNER JOIN pelish_customer_carts c ON c.id = i.cart_id WHERE c.customer_id = ?');
    $cart->execute([$customer['id']]);
    $favorite = $pdo->prepare('SELECT COUNT(*) FROM pelish_customer_favorites WHERE customer_id = ?');
    $favorite->execute([$customer['id']]);
    return ['cart' => (int) $cart->fetchColumn(), 'favorites' => (int) $favorite->fetchColumn()];
}

function store_smtp_response($connection): ?int
{
    $code = null;
    while (($line = fgets($connection, 515)) !== false) {
        if (preg_match('/^(\d{3})([ -])/', $line, $match)) {
            $code = (int) $match[1];
            if ($match[2] === ' ') {
                return $code;
            }
        } else {
            return null;
        }
    }
    return null;
}

function store_smtp_command($connection, string $command, array $expected): bool
{
    if (fwrite($connection, $command . "\r\n") === false) {
        return false;
    }
    return in_array(store_smtp_response($connection), $expected, true);
}

function store_send_verification_email(array $mail, string $to, string $code): bool
{
    $from = trim((string) ($mail['from_email'] ?? ''));
    $host = trim((string) ($mail['smtp_host'] ?? ''));
    $username = trim((string) ($mail['smtp_username'] ?? $from));
    $password = (string) ($mail['smtp_password'] ?? '');
    $port = max(1, min(65535, (int) ($mail['smtp_port'] ?? 587)));
    $encryption = strtolower(trim((string) ($mail['smtp_encryption'] ?? 'tls')));
    $timeout = max(5, min(30, (int) ($mail['smtp_timeout'] ?? 15)));

    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL) || $host === '' || $username === '' || $password === '') {
        return false;
    }
    if (!in_array($encryption, ['tls', 'none'], true) || !extension_loaded('openssl')) {
        return false;
    }

    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
    $connection = @stream_socket_client('tcp://' . $host . ':' . $port, $errorNumber, $errorMessage, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!is_resource($connection)) {
        return false;
    }

    stream_set_timeout($connection, $timeout);
    $hostname = preg_replace('/[^A-Za-z0-9.-]/', '', (string) (gethostname() ?: 'pelish.co')) ?: 'pelish.co';
    $name = trim(str_replace(["\r", "\n"], '', (string) ($mail['from_name'] ?? 'pelish'))) ?: 'pelish';
    $subject = 'pelish kayıt doğrulama kodun';
    $body = "Merhaba,\n\npelish hesabını oluşturmak için doğrulama kodun: {$code}\n\nBu kod 3 dakika geçerlidir. Eğer bu isteği sen yapmadıysan bu e-postayı yok sayabilirsin.";

    $sent = store_smtp_response($connection) === 220
        && store_smtp_command($connection, 'EHLO ' . $hostname, [250]);

    if ($sent && $encryption === 'tls') {
        $sent = store_smtp_command($connection, 'STARTTLS', [220])
            && stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) === true
            && store_smtp_command($connection, 'EHLO ' . $hostname, [250]);
    }

    if ($sent) {
        $sent = store_smtp_command($connection, 'AUTH LOGIN', [334])
            && store_smtp_command($connection, base64_encode($username), [334])
            && store_smtp_command($connection, base64_encode($password), [235])
            && store_smtp_command($connection, 'MAIL FROM:<' . $from . '>', [250])
            && store_smtp_command($connection, 'RCPT TO:<' . $to . '>', [250, 251])
            && store_smtp_command($connection, 'DATA', [354]);
    }

    if ($sent) {
        $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
        $normalizedBody = preg_replace('/(?m)^\./', '..', $normalizedBody) ?? $normalizedBody;
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $name . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $sent = fwrite($connection, implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $normalizedBody) . "\r\n.\r\n") !== false
            && store_smtp_response($connection) === 250;
    }

    @store_smtp_command($connection, 'QUIT', [221]);
    fclose($connection);
    return $sent;
}
