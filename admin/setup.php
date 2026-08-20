<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS pelish_products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'Genel',
    sku VARCHAR(100) NOT NULL UNIQUE,
    barcode VARCHAR(100) NULL,
    image_url VARCHAR(500) NULL,
    sale_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    list_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    desi DECIMAL(8,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pelish_products_name (name),
    INDEX idx_pelish_products_status (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

$imageKeyColumn = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pelish_products' AND COLUMN_NAME = 'image_key'");
$imageKeyColumn->execute();
if ((int) $imageKeyColumn->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE pelish_products ADD COLUMN image_key VARCHAR(500) NULL AFTER image_url');
}

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS pelish_customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(40) NULL,
    city VARCHAR(100) NULL,
    district VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pelish_customers_name (last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelish_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(40) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NULL,
    status ENUM('Yeni','Hazırlanıyor','Kargoda','Teslim Edildi','İptal') NOT NULL DEFAULT 'Yeni',
    payment_method VARCHAR(80) NOT NULL DEFAULT 'Kredi Kartı',
    cargo_company VARCHAR(100) NULL,
    cargo_tracking VARCHAR(100) NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    cargo_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pelish_order_customer FOREIGN KEY (customer_id) REFERENCES pelish_customers(id) ON DELETE SET NULL,
    INDEX idx_pelish_orders_status (status),
    INDEX idx_pelish_orders_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelish_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    product_name VARCHAR(190) NOT NULL,
    sku VARCHAR(100) NULL,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pelish_item_order FOREIGN KEY (order_id) REFERENCES pelish_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_pelish_item_product FOREIGN KEY (product_id) REFERENCES pelish_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelish_marketplaces (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    marketplace VARCHAR(80) NOT NULL,
    store_code VARCHAR(100) NULL,
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_sync_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelish_vouchers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    title VARCHAR(190) NOT NULL,
    amount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    min_cart_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    usage_limit INT UNSIGNED NULL,
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelish_catalog_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('categories','brands','tags') NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    description VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_catalog_slug (type, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

$count = (int) $pdo->query('SELECT COUNT(*) FROM pelish_products')->fetchColumn();
if ($count === 0) {
    $seed = $pdo->prepare(
        'INSERT INTO pelish_products (name, category, sku, barcode, image_url, sale_price, list_price, cost_price, stock, desi, is_active) VALUES (:name, :category, :sku, :barcode, :image_url, :sale_price, :list_price, :cost_price, :stock, :desi, 1)'
    );
    $products = [
        ['Luna Saten Elbise', 'Elbise', 'PELISH-001', '', 'https://images.unsplash.com/photo-1566174053879-31528523f8ae?auto=format&fit=crop&w=500&q=85', 2490, 2990, 1220, 12, 1.2],
        ['Élan Keten Takım', 'Takım', 'PELISH-002', '', 'https://images.unsplash.com/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=500&q=85', 3250, 3790, 1680, 8, 1.8],
        ['Muse Drapeli Bluz', 'Üst Giyim', 'PELISH-003', '', 'https://images.unsplash.com/photo-1485968579580-b6d095142e6e?auto=format&fit=crop&w=500&q=85', 1390, 1590, 620, 16, 0.7],
        ['Isla Midi Etek', 'Alt Giyim', 'PELISH-004', '', 'https://images.unsplash.com/photo-1572804013427-4d7ca7268217?auto=format&fit=crop&w=500&q=85', 1790, 2190, 790, 6, 0.8],
        ['Nora İnce Triko', 'Triko', 'PELISH-005', '', 'https://images.unsplash.com/photo-1551488831-00ddcb6c6bd3?auto=format&fit=crop&w=500&q=85', 1690, 1990, 730, 10, 0.6],
        ['Soleil Şehir Çantası', 'Aksesuar', 'PELISH-006', '', 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=500&q=85', 2150, 2490, 970, 9, 0.9],
    ];
    foreach ($products as $product) {
        $seed->execute([
            'name' => $product[0],
            'category' => $product[1],
            'sku' => $product[2],
            'barcode' => $product[3],
            'image_url' => $product[4],
            'sale_price' => $product[5],
            'list_price' => $product[6],
            'cost_price' => $product[7],
            'stock' => $product[8],
            'desi' => $product[9],
        ]);
    }
}

$customerCount = (int) $pdo->query('SELECT COUNT(*) FROM pelish_customers')->fetchColumn();
if ($customerCount === 0) {
    $customers = [
        ['Duru', 'Aydın', 'duru.aydin@example.com', '0532 410 23 18', 'Tekirdağ', 'Süleymanpaşa'],
        ['Ece', 'Kaya', 'ece.kaya@example.com', '0531 775 90 62', 'İstanbul', 'Kadıköy'],
        ['Selin', 'Demir', 'selin.demir@example.com', '0535 212 45 67', 'Tekirdağ', 'Kumbağ'],
    ];
    $customerSeed = $pdo->prepare('INSERT INTO pelish_customers (first_name, last_name, email, phone, city, district) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($customers as $customer) {
        $customerSeed->execute($customer);
    }
}

$marketCount = (int) $pdo->query('SELECT COUNT(*) FROM pelish_marketplaces')->fetchColumn();
if ($marketCount === 0) {
    $marketSeed = $pdo->prepare('INSERT INTO pelish_marketplaces (name, marketplace, store_code, commission_rate, is_active, last_sync_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $marketSeed->execute(['Pelish Trendyol Mağazası', 'Trendyol', 'PELISH-TY', 18.0, 1]);
    $marketSeed->execute(['Pelish Hepsiburada Mağazası', 'Hepsiburada', 'PELISH-HB', 20.0, 0]);
}

$voucherCount = (int) $pdo->query('SELECT COUNT(*) FROM pelish_vouchers')->fetchColumn();
if ($voucherCount === 0) {
    $voucherSeed = $pdo->prepare('INSERT INTO pelish_vouchers (code, title, amount_type, amount, min_cart_total, usage_limit, starts_at, ends_at, is_active) VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1)');
    $voucherSeed->execute(['PELISH10', 'Yeni üyelik indirimi', 'percent', 10, 0, 250]);
    $voucherSeed->execute(['KUMBAG250', 'Kumbağ yaz seçkisi', 'fixed', 250, 1500, 100]);
}

$catalogCount = (int) $pdo->query('SELECT COUNT(*) FROM pelish_catalog_items')->fetchColumn();
if ($catalogCount === 0) {
    $catalogSeed = $pdo->prepare('INSERT INTO pelish_catalog_items (type, name, slug, description) VALUES (?, ?, ?, ?)');
    foreach ([
        ['categories', 'Elbise', 'elbise', 'Pelish elbise koleksiyonu'], ['categories', 'Üst Giyim', 'ust-giyim', 'Üst giyim seçkisi'], ['categories', 'Aksesuar', 'aksesuar', 'Günlük aksesuarlar'],
        ['brands', 'Pelish', 'pelish', 'Pelish ana marka'], ['tags', 'Yeni Sezon', 'yeni-sezon', 'Yeni sezon ürün etiketi'], ['tags', 'İndirim', 'indirim', 'İndirimli ürün etiketi'],
    ] as $item) {
        $catalogSeed->execute($item);
    }
}

header('Location: index.php?notice=setup-complete');
exit;
