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

/**
 * İki fiyat girildiğinde yüksek olan eski/liste fiyatı, düşük olan ise
 * müşterinin ödeyeceği fiyat olur. Böylece yönetimde fiyatların giriş sırası
 * değişse bile vitrinde indirim her zaman doğru ve anlaşılır gösterilir.
 *
 * @return array{current: float, original: float, discount_percent: int, is_discounted: bool}
 */
function store_product_price(array $product): array
{
    $sale = max(0, (float) ($product['sale_price'] ?? 0));
    $list = max(0, (float) ($product['list_price'] ?? 0));
    $prices = array_values(array_filter([$sale, $list], static fn(float $price): bool => $price > 0));
    if (!$prices) {
        return ['current' => 0.0, 'original' => 0.0, 'discount_percent' => 0, 'is_discounted' => false];
    }
    $original = max($prices);
    $current = count($prices) > 1 ? min($prices) : $original;
    $isDiscounted = count($prices) > 1 && $original > $current;

    return [
        'current' => $current,
        'original' => $original,
        'discount_percent' => $isDiscounted ? (int) round((($original - $current) / $original) * 100) : 0,
        'is_discounted' => $isDiscounted,
    ];
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

/**
 * Formdaki dönüş alanı kaybolsa bile işlem yapan kullanıcıyı güvenli biçimde
 * geldiği mağaza sayfasına döndürür. Yalnızca aynı hosttan gelen ve bilinen
 * PHP sayfası biçimindeki yollar kabul edilir.
 */
function store_referer_return(string $fallback = 'index.php'): string
{
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $parts = parse_url($referer);
    if (!is_array($parts)) {
        return $fallback;
    }
    $host = strtolower((string) ($parts['host'] ?? ''));
    $currentHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || $currentHost === '' || $host !== preg_replace('/:\\d+$/', '', $currentHost)) {
        return $fallback;
    }
    $page = basename((string) ($parts['path'] ?? ''));
    $query = (string) ($parts['query'] ?? '');
    return store_safe_return($page . ($query !== '' ? '?' . $query : ''), $fallback);
}

function store_current_return(string $fallback = 'index.php'): string
{
    $request = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = (string) parse_url($request, PHP_URL_PATH);
    $page = basename($path);
    if (!preg_match('/^[a-z0-9-]+\.php$/i', $page)) {
        return $fallback;
    }
    $query = (string) parse_url($request, PHP_URL_QUERY);
    return store_safe_return($page . ($query !== '' ? '?' . $query : ''), $fallback);
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

/** @return array<int, array<string, mixed>> */
function store_product_colors(PDO $pdo, int $productId, array $images): array
{
    $statement = $pdo->prepare(
        'SELECT c.*, mapped.product_image_id, mapped.image_url
         FROM pelish_product_colors c
         LEFT JOIN (
             SELECT ci.color_id, ci.product_image_id, i.image_url
             FROM pelish_product_color_images ci
             INNER JOIN pelish_product_images i ON i.id = ci.product_image_id
             INNER JOIN (
                 SELECT color_id, MIN(sort_order) AS min_sort FROM pelish_product_color_images GROUP BY color_id
             ) first_image ON first_image.color_id = ci.color_id AND first_image.min_sort = ci.sort_order
         ) mapped ON mapped.color_id = c.id
         WHERE c.product_id = ? ORDER BY c.sort_order, c.id'
    );
    $statement->execute([$productId]);
    $colors = $statement->fetchAll();
    if ($colors) {
        return $colors;
    }

    // Eski kayıtlar için geriye dönük güvenli görünüm. Yeni sürüm renkleri
    // pelish_product_colors üzerinden okur ve bir görseli birden çok renge
    // bağlayabilir.
    $fallback = [];
    foreach ($images as $image) {
        $name = trim((string) ($image['color_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $key = mb_strtolower($name, 'UTF-8');
        if (!isset($fallback[$key])) {
            $fallback[$key] = [
                'id' => 0,
                'color_name' => $name,
                'color_hex' => $image['color_hex'] ?? '#c7b6a3',
                'product_image_id' => $image['id'] ?? 0,
                'image_url' => $image['image_url'] ?? '',
            ];
        }
    }
    return array_values($fallback);
}

/** @return array<int, array{size_code: string, stock: int}> */
function store_product_size_stocks(PDO $pdo, int $productId, int $colorId = 0): array
{
    $statement = $pdo->prepare('SELECT size_code, stock FROM pelish_product_color_size_stocks WHERE product_id = ? AND color_id = ? ORDER BY FIELD(size_code, "Standart", "XS", "S", "M", "L", "XL", "XXL")');
    $statement->execute([$productId, $colorId]);
    $rows = $statement->fetchAll();
    if (!$rows && $colorId > 0) { $statement->execute([$productId, 0]); $rows = $statement->fetchAll(); }
    return array_map(static fn(array $row): array => ['size_code' => (string) $row['size_code'], 'stock' => (int) $row['stock']], $rows);
}

function store_size_stock(PDO $pdo, int $productId, string $size, int $colorId = 0): ?int
{
    $statement = $pdo->prepare('SELECT stock FROM pelish_product_color_size_stocks WHERE product_id = ? AND color_id = ? AND size_code = ? LIMIT 1');
    $statement->execute([$productId, $colorId, $size]);
    $stock = $statement->fetchColumn();
    if ($stock === false && $colorId > 0) { $statement->execute([$productId, 0, $size]); $stock = $statement->fetchColumn(); }
    return $stock === false ? null : (int) $stock;
}

/** @return list<string> */
function store_active_categories(PDO $pdo, bool $discountOnly = false): array
{
    $where = ['is_active = 1', "TRIM(category) <> ''"];
    if ($discountOnly) {
        // A discount only exists when both prices are present and different.
        $where[] = 'sale_price > 0 AND list_price > 0 AND sale_price <> list_price';
    }
    $statement = $pdo->query('SELECT DISTINCT category FROM pelish_products WHERE ' . implode(' AND ', $where) . ' ORDER BY category ASC');
    return array_values(array_filter(array_map('trim', $statement->fetchAll(PDO::FETCH_COLUMN))));
}

/** @param list<int> $productIds @return array<int, list<string>> */
function store_product_preview_images(PDO $pdo, array $productIds, int $limit = 3): array
{
    $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn (int $id): bool => $id > 0)));
    if (!$productIds) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($productIds), '?'));
    $statement = $pdo->prepare("SELECT product_id, image_url FROM pelish_product_images WHERE product_id IN ({$marks}) ORDER BY product_id, is_primary DESC, sort_order ASC, id ASC");
    $statement->execute($productIds);
    $result = [];
    foreach ($statement->fetchAll() as $row) {
        $productId = (int) $row['product_id'];
        if (!isset($result[$productId])) {
            $result[$productId] = [];
        }
        if (count($result[$productId]) < $limit && !in_array((string) $row['image_url'], $result[$productId], true)) {
            $result[$productId][] = (string) $row['image_url'];
        }
    }
    return $result;
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

function store_add_to_cart(PDO $pdo, int $customerId, int $productId, int $imageId, string $size, int $colorId = 0): string
{
    $product = store_product($pdo, $productId);
    if (!$product || (int) $product['stock'] < 1) {
        throw new RuntimeException('Bu ürün şu anda stokta değil.');
    }
    $sizes = ['Standart', 'XS', 'S', 'M', 'L', 'XL', 'XXL'];
    if (!in_array($size, $sizes, true)) {
        throw new RuntimeException('Lütfen bir beden seçin.');
    }
    $sizeStock = store_size_stock($pdo, $productId, $size, $colorId);
    if ($sizeStock === null) {
        throw new RuntimeException('Bu beden bu ürün için sunulmuyor.');
    }
    if ($sizeStock < 1) {
        throw new RuntimeException('Seçtiğin beden şu anda stokta yok.');
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
    $inCart = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM pelish_customer_cart_items WHERE cart_id = ? AND product_id = ? AND selected_size = ? AND color_id <=> ?');
    $inCart->execute([$cartId, $productId, $size, $colorId ?: null]);
    if ((int) $inCart->fetchColumn() >= $sizeStock) {
        throw new RuntimeException('Bu beden için sepetteki adet stok sınırına ulaştı.');
    }
    $find = $pdo->prepare('SELECT id, quantity FROM pelish_customer_cart_items WHERE cart_id = ? AND product_id = ? AND product_image_id <=> ? AND color_id <=> ? AND selected_size = ? LIMIT 1');
    $find->execute([$cartId, $productId, $image ? (int) $image['id'] : null, $colorId ?: null, $size]);
    $line = $find->fetch();
    if ($line) {
        $pdo->prepare('UPDATE pelish_customer_cart_items SET quantity = quantity + 1 WHERE id = ?')->execute([$line['id']]);
    } else {
        $color = null; if ($colorId > 0) { $colorQuery=$pdo->prepare('SELECT color_name,color_hex FROM pelish_product_colors WHERE id=? AND product_id=?');$colorQuery->execute([$colorId,$productId]);$color=$colorQuery->fetch()?:null; }
        $insert = $pdo->prepare('INSERT INTO pelish_customer_cart_items (cart_id, product_id, product_image_id, color_id, selected_size, color_name, color_hex, quantity) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
        $insert->execute([$cartId, $productId, $image ? (int) $image['id'] : null, $colorId ?: null, $size, $color['color_name'] ?? $image['color_name'] ?? null, $color['color_hex'] ?? $image['color_hex'] ?? null]);
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

function store_finish_pending_action(PDO $pdo, int $customerId): string
{
    $pending = $_SESSION['pelish_pending_action'] ?? null;
    unset($_SESSION['pelish_pending_action']);
    if (!is_array($pending) || empty($pending['type'])) {
        return 'index.php';
    }
    $returnTo = store_safe_return((string) ($pending['return_to'] ?? ''), 'index.php');
    try {
        if ($pending['type'] === 'favorite') {
            $added = store_toggle_favorite($pdo, $customerId, (int) ($pending['product_id'] ?? 0));
            store_flash('success', $added ? 'Favorilerine eklendi.' : 'Favorilerinden çıkarıldı.');
        }
        if ($pending['type'] === 'cart') {
            store_flash('success', store_add_to_cart($pdo, $customerId, (int) ($pending['product_id'] ?? 0), (int) ($pending['image_id'] ?? 0), (string) ($pending['size'] ?? ''), (int) ($pending['color_id'] ?? 0)));
        }
    } catch (RuntimeException $exception) {
        store_flash('danger', $exception->getMessage());
    }
    return $returnTo;
}

function store_counts(PDO $pdo, ?array $customer = null): array
{
    $customer ??= store_customer($pdo);
    if (!$customer) {
        return ['cart' => array_sum(array_map(static fn(array $line): int => max(0, (int) ($line['quantity'] ?? 0)), (array) ($_SESSION['pelish_guest_cart'] ?? []))), 'favorites' => 0];
    }
    $cart = $pdo->prepare('SELECT COALESCE(SUM(i.quantity), 0) FROM pelish_customer_cart_items i INNER JOIN pelish_customer_carts c ON c.id = i.cart_id WHERE c.customer_id = ?');
    $cart->execute([$customer['id']]);
    $favorite = $pdo->prepare('SELECT COUNT(*) FROM pelish_customer_favorites WHERE customer_id = ?');
    $favorite->execute([$customer['id']]);
    return ['cart' => (int) $cart->fetchColumn(), 'favorites' => (int) $favorite->fetchColumn()];
}

function store_normalize_phone(string $value): string
{
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if ($digits !== '' && $digits[0] !== '0') {
        $digits = '0' . $digits;
    }
    return substr($digits, 0, 11);
}

function store_phone_is_valid(string $value): bool
{
    return (bool) preg_match('/^0\d{10}$/', store_normalize_phone($value));
}

function store_format_phone(string $value): string
{
    $digits = store_normalize_phone($value);
    if ($digits === '') {
        return '0';
    }
    $parts = [substr($digits, 0, 4), substr($digits, 4, 3), substr($digits, 7, 2), substr($digits, 9, 2)];
    return trim(implode(' ', array_filter($parts, static fn(string $part): bool => $part !== '')));
}

function store_customer_addresses(PDO $pdo, int $customerId): array
{
    $statement = $pdo->prepare('SELECT * FROM pelish_customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, updated_at DESC, id DESC');
    $statement->execute([$customerId]);
    return $statement->fetchAll();
}

function store_address_from_post(array $source, array $customer): array
{
    $data = [
        'title' => trim((string) ($source['title'] ?? '')),
        'recipient_name' => trim((string) ($source['recipient_name'] ?? '')),
        'phone' => store_normalize_phone((string) ($source['phone'] ?? '')),
        'city' => trim((string) ($source['city'] ?? '')),
        'district' => trim((string) ($source['district'] ?? '')),
        'neighborhood' => trim((string) ($source['neighborhood'] ?? '')),
        'address_line' => trim((string) ($source['address_line'] ?? '')),
        'postal_code' => preg_replace('/\D+/', '', (string) ($source['postal_code'] ?? '')) ?? '',
        'is_default' => isset($source['is_default']) ? 1 : 0,
    ];
    if ($data['recipient_name'] === '') {
        $data['recipient_name'] = trim((string) ($customer['first_name'] ?? '') . ' ' . (string) ($customer['last_name'] ?? ''));
    }
    if ($data['title'] === '' || $data['recipient_name'] === '' || !store_phone_is_valid($data['phone']) || $data['city'] === '' || $data['district'] === '' || $data['address_line'] === '') {
        throw new RuntimeException('Adres başlığı, alıcı, geçerli telefon, il, ilçe ve açık adres zorunludur.');
    }
    return $data;
}

function store_save_customer_address(PDO $pdo, int $customerId, array $data, int $addressId = 0): int
{
    $pdo->beginTransaction();
    try {
        if ($data['is_default']) {
            $pdo->prepare('UPDATE pelish_customer_addresses SET is_default = 0 WHERE customer_id = ?')->execute([$customerId]);
        } elseif ($addressId === 0) {
            $count = $pdo->prepare('SELECT COUNT(*) FROM pelish_customer_addresses WHERE customer_id = ?');
            $count->execute([$customerId]);
            if ((int) $count->fetchColumn() === 0) {
                $data['is_default'] = 1;
            }
        }
        if ($addressId > 0) {
            $owned = $pdo->prepare('SELECT id FROM pelish_customer_addresses WHERE id = ? AND customer_id = ? FOR UPDATE');
            $owned->execute([$addressId, $customerId]);
            if (!$owned->fetchColumn()) {
                throw new RuntimeException('Düzenlemek istediğin adres bulunamadı.');
            }
            $update = $pdo->prepare('UPDATE pelish_customer_addresses SET title=:title, recipient_name=:recipient_name, phone=:phone, city=:city, district=:district, neighborhood=:neighborhood, address_line=:address_line, postal_code=:postal_code, is_default=:is_default WHERE id=:id AND customer_id=:customer_id');
            $update->execute($data + ['id' => $addressId, 'customer_id' => $customerId]);
        } else {
            $insert = $pdo->prepare('INSERT INTO pelish_customer_addresses (customer_id,title,recipient_name,phone,city,district,neighborhood,address_line,postal_code,is_default) VALUES (:customer_id,:title,:recipient_name,:phone,:city,:district,:neighborhood,:address_line,:postal_code,:is_default)');
            $insert->execute($data + ['customer_id' => $customerId]);
            $addressId = (int) $pdo->lastInsertId();
        }
        $pdo->commit();
        return $addressId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}

function store_delete_customer_address(PDO $pdo, int $customerId, int $addressId): void
{
    $delete = $pdo->prepare('DELETE FROM pelish_customer_addresses WHERE id = ? AND customer_id = ?');
    $delete->execute([$addressId, $customerId]);
    if ($delete->rowCount() === 0) {
        throw new RuntimeException('Silmek istediğin adres bulunamadı.');
    }
    $remaining = $pdo->prepare('SELECT id FROM pelish_customer_addresses WHERE customer_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1');
    $remaining->execute([$customerId]);
    if ($nextId = (int) $remaining->fetchColumn()) {
        $pdo->prepare('UPDATE pelish_customer_addresses SET is_default = 1 WHERE id = ?')->execute([$nextId]);
    }
}

function store_cart_items(PDO $pdo, int $customerId): array
{
    $itemsQuery = $pdo->prepare('SELECT i.*, p.name, p.category, p.sku, p.sale_price, p.list_price, p.stock, COALESCE(pi.image_url, p.image_url) AS image_url FROM pelish_customer_cart_items i INNER JOIN pelish_customer_carts c ON c.id=i.cart_id INNER JOIN pelish_products p ON p.id=i.product_id LEFT JOIN pelish_product_images pi ON pi.id=i.product_image_id WHERE c.customer_id=? ORDER BY i.created_at DESC');
    $itemsQuery->execute([$customerId]);
    return array_map(static function (array $item): array { $item['price'] = store_product_price($item); return $item; }, $itemsQuery->fetchAll());
}

function store_guest_cart_items(PDO $pdo): array
{
    $lines = (array) ($_SESSION['pelish_guest_cart'] ?? []); $items = [];
    foreach ($lines as $key => $line) { $product = store_product($pdo, (int) ($line['product_id'] ?? 0)); if (!$product) continue; $imageId=(int)($line['image_id']??0); $imageUrl=$product['image_url']; if($imageId>0){$image=$pdo->prepare('SELECT image_url FROM pelish_product_images WHERE id=? AND product_id=?');$image->execute([$imageId,$product['id']]);$imageUrl=$image->fetchColumn()?:$imageUrl;} $items[]=['id'=>'guest-'.$key,'guest_key'=>(string)$key,'product_id'=>(int)$product['id'],'product_image_id'=>$imageId,'color_id'=>(int)($line['color_id']??0),'selected_size'=>(string)($line['size']??''),'color_name'=>$line['color_name']??null,'color_hex'=>$line['color_hex']??null,'quantity'=>max(1,(int)($line['quantity']??1)),'name'=>$product['name'],'category'=>$product['category'],'sku'=>$product['sku'],'sale_price'=>$product['sale_price'],'list_price'=>$product['list_price'],'stock'=>$product['stock'],'image_url'=>$imageUrl]; }
    return array_map(static function(array $item):array{$item['price']=store_product_price($item);return $item;},$items);
}

function store_guest_add_to_cart(PDO $pdo, int $productId, int $imageId, string $size, int $colorId): string
{
    $stock=store_size_stock($pdo,$productId,$size,$colorId); if($stock===null||$stock<1)throw new RuntimeException('Seçtiğin beden şu anda stokta yok.');
    $color=['color_name'=>null,'color_hex'=>null];if($colorId>0){$q=$pdo->prepare('SELECT color_name,color_hex FROM pelish_product_colors WHERE id=? AND product_id=?');$q->execute([$colorId,$productId]);$color=$q->fetch()?:$color;}
    $lines=(array)($_SESSION['pelish_guest_cart']??[]);$key=sha1($productId.'|'.$imageId.'|'.$colorId.'|'.$size);$quantity=(int)($lines[$key]['quantity']??0)+1;if($quantity>$stock)throw new RuntimeException('Bu beden için stok sınırına ulaştın.');$lines[$key]=['product_id'=>$productId,'image_id'=>$imageId,'color_id'=>$colorId,'size'=>$size,'quantity'=>$quantity,'color_name'=>$color['color_name'],'color_hex'=>$color['color_hex']];$_SESSION['pelish_guest_cart']=$lines;$product=store_product($pdo,$productId);return ($product['name']??'Ürün').' · '.$size.' sepetine eklendi.';
}

function store_active_cart_items(PDO $pdo, ?array $customer = null): array { $customer ??= store_customer($pdo); return $customer ? store_cart_items($pdo,(int)$customer['id']) : store_guest_cart_items($pdo); }

function store_cart_totals(array $items): array
{
    $subtotal = array_reduce($items, static fn(float $sum, array $item): float => $sum + ((float) $item['price']['current'] * (int) $item['quantity']), 0.0);
    $cargo = $subtotal > 0 && $subtotal < 3500 ? 79.0 : 0.0;
    return ['subtotal' => $subtotal, 'cargo' => $cargo, 'grand_total' => $subtotal + $cargo];
}

function store_company(array $config): array
{
    $company = (array) ($config['company'] ?? []);
    return [
        'legal_name' => trim((string) ($company['legal_name'] ?? 'PELISH')),
        'address' => trim((string) ($company['address'] ?? 'Şirket açık adresi henüz tanımlanmadı.')),
        'phone' => trim((string) ($company['phone'] ?? '')),
        'email' => trim((string) ($company['email'] ?? ($config['mail']['from_email'] ?? ''))),
        'mersis_no' => trim((string) ($company['mersis_no'] ?? '')),
        'tax_office' => trim((string) ($company['tax_office'] ?? '')),
        'tax_no' => trim((string) ($company['tax_no'] ?? '')),
        'kep_address' => trim((string) ($company['kep_address'] ?? '')),
        'etbis_qr_url' => trim((string) ($company['etbis_qr_url'] ?? '')),
    ];
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

function store_send_verification_email(array $mail, string $to, string $code, string $verificationPath = 'dogrula.php', bool $emailChange = false): bool
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
    // Güzel Hosting Exim sunucusu alan adıyla yapılan EHLO çağrısını taklit
    // olarak reddedebildiği için güvenli ve yerel bir HELO kimliği kullanılır.
    $hostname = 'localhost';
    $name = trim(str_replace(["\r", "\n"], '', (string) ($mail['from_name'] ?? 'pelish'))) ?: 'pelish';
    $subject = $emailChange ? 'pelish e-posta değişikliği doğrulama kodun' : 'pelish kayıt doğrulama kodun';
    $actionCopy = $emailChange ? 'e-posta adresini güncellemek' : 'pelish hesabını oluşturmak';
    $verificationUrl = 'https://www.pelish.co/' . ltrim($verificationPath, '/') . '?code=' . rawurlencode($code);
    $plainBody = "Merhaba,\n\n{$actionCopy} için doğrulama kodun: {$code}\n\nKodunla doğrudan doğrulama ekranını açmak için: {$verificationUrl}\n\nBu kod 3 dakika geçerlidir. Eğer bu isteği sen yapmadıysan bu e-postayı yok sayabilirsin.";
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $safeVerificationUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');
    $htmlBody = <<<HTML
<!doctype html>
<html lang="tr">
  <body style="margin:0;padding:0;background:#f5f1ec;color:#181716;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f5f1ec;">
      <tr><td align="center" style="padding:38px 16px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#ffffff;">
          <tr><td align="center" style="padding:42px 42px 26px;border-bottom:1px solid #e8e1da;">
            <img src="https://cdn.pelish.co/pelishlogo.png" width="148" alt="pelish" style="display:block;width:148px;max-width:100%;height:auto;border:0;outline:none;text-decoration:none;">
          </td></tr>
          <tr><td align="center" style="padding:42px 42px 18px;">
            <p style="margin:0 0 14px;color:#897c70;font-size:11px;line-height:16px;letter-spacing:2px;font-weight:700;">HOŞ GELDİN</p>
            <h1 style="margin:0;color:#181716;font-family:Georgia,'Times New Roman',serif;font-size:34px;line-height:42px;font-weight:500;">E-postanı<br>doğrulayalım.</h1>
            <p style="margin:22px 0 0;color:#615a54;font-size:15px;line-height:24px;">{$actionCopy} için<br>aşağıdaki doğrulama kodunu kullan.</p>
          </td></tr>
          <tr><td align="center" style="padding:18px 42px 24px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="background:#f6f0ea;border:1px solid #e6dbd0;">
              <tr><td align="center" style="padding:14px 28px 18px;">
                <p style="margin:0 0 8px;color:#897c70;font-size:10px;line-height:14px;letter-spacing:2px;font-weight:700;">DOĞRULAMA KODUN</p>
                <a href="{$safeVerificationUrl}" target="_blank" style="display:block;color:#1b1917;text-decoration:none;"><p style="margin:0;color:#1b1917;font-family:'Courier New',Courier,monospace;font-size:34px;line-height:40px;letter-spacing:8px;font-weight:700;">{$safeCode}</p><p style="margin:10px 0 0;color:#897c70;font-size:10px;line-height:14px;letter-spacing:1.6px;font-weight:700;">DOĞRULAMAK İÇİN DOKUN</p></a>
              </td></tr>
            </table>
          </td></tr>
          <tr><td align="center" style="padding:0 42px 42px;">
            <p style="margin:0;color:#615a54;font-size:13px;line-height:21px;">Bu kod <strong style="color:#1b1917;">3 dakika</strong> boyunca geçerli.<br>Eğer bu isteği sen yapmadıysan bu e-postayı yok sayabilirsin.</p>
          </td></tr>
          <tr><td align="center" style="padding:19px 24px;background:#181716;">
            <p style="margin:0;color:#f4eee8;font-size:10px;line-height:15px;letter-spacing:1.5px;">PELISH · TEKİRDAĞ / SÜLEYMANPAŞA</p>
          </td></tr>
        </table>
      </td></tr>
    </table>
  </body>
</html>
HTML;

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
        $boundary = 'pelish-' . bin2hex(random_bytes(12));
        $messageBody = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $plainBody . "\r\n\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $htmlBody . "\r\n\r\n"
            . '--' . $boundary . "--\r\n";
        $normalizedBody = str_replace(["\r\n", "\r"], "\n", $messageBody);
        $normalizedBody = preg_replace('/(?m)^\./', '..', $normalizedBody) ?? $normalizedBody;
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $name . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $sent = fwrite($connection, implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $normalizedBody) . "\r\n.\r\n") !== false
            && store_smtp_response($connection) === 250;
    }

    @store_smtp_command($connection, 'QUIT', [221]);
    fclose($connection);
    return $sent;
}

function store_send_transfer_proof_notification(array $mail, string $recipient, string $orderNumber, float $total, string $proofUrl): bool
{
    $from = trim((string) ($mail['from_email'] ?? ''));
    $host = trim((string) ($mail['smtp_host'] ?? ''));
    $username = trim((string) ($mail['smtp_username'] ?? $from));
    $password = (string) ($mail['smtp_password'] ?? '');
    $port = max(1, min(65535, (int) ($mail['smtp_port'] ?? 587)));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($recipient, FILTER_VALIDATE_EMAIL) || $host === '' || $username === '' || $password === '') return false;
    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
    $connection = @stream_socket_client('tcp://' . $host . ':' . $port, $errorNo, $errorMessage, 15, STREAM_CLIENT_CONNECT, $context);
    if (!is_resource($connection)) return false;
    stream_set_timeout($connection, 15);
    $sent = store_smtp_response($connection) === 220 && store_smtp_command($connection, 'EHLO localhost', [250]);
    if ($sent && strtolower((string) ($mail['smtp_encryption'] ?? 'tls')) === 'tls') $sent = store_smtp_command($connection, 'STARTTLS', [220]) && stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) === true && store_smtp_command($connection, 'EHLO localhost', [250]);
    if ($sent) $sent = store_smtp_command($connection, 'AUTH LOGIN', [334]) && store_smtp_command($connection, base64_encode($username), [334]) && store_smtp_command($connection, base64_encode($password), [235]) && store_smtp_command($connection, 'MAIL FROM:<' . $from . '>', [250]) && store_smtp_command($connection, 'RCPT TO:<' . $recipient . '>', [250,251]) && store_smtp_command($connection, 'DATA', [354]);
    if ($sent) {
        $subject = 'Pelish · Havale/EFT dekont incelemesi gerekiyor';
        $totalText = number_format($total, 2, ',', '.') . ' TL';
        $body = "Yeni Havale / EFT dekontu yüklendi.\n\nSipariş: {$orderNumber}\nTutar: {$totalText}\nDekont: {$proofUrl}\n\nYönetim panelinden dekontu inceleyip ödeme onayı veya reddi verin.";
        $headers = 'Date: ' . date(DATE_RFC2822) . "\r\nFrom: pelish <{$from}>\r\nTo: <{$recipient}>\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . '?=' . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
        $sent = fwrite($connection, $headers . "\r\n\r\n" . str_replace("\n", "\r\n", $body) . "\r\n.\r\n") !== false && store_smtp_response($connection) === 250;
    }
    @store_smtp_command($connection, 'QUIT', [221]); fclose($connection); return $sent;
}

function store_attach_payment_proof(PDO $pdo, int $orderId, array $proof): void
{
    $statement = $pdo->prepare('UPDATE pelish_orders SET payment_status="Bekliyor", payment_proof_url=?, payment_proof_key=?, payment_proof_mime=?, payment_proof_name=?, payment_proof_uploaded_at=NOW() WHERE id=?');
    $statement->execute([$proof['url'], $proof['key'], $proof['mime'], $proof['original_name'], $orderId]);
}
