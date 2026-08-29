<?php

declare(strict_types=1);

/**
 * Pelish'in yerel kurulumunda tekrar tekrar güvenle çalıştırılabilen şema
 * güncellemeleri. setup.php bu dosyayı çağırır; yeni alanlar mevcut veri
 * kaybedilmeden eklenir.
 */
function pelish_column_exists(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $statement->execute([$table, $column]);
    return (int) $statement->fetchColumn() > 0;
}

function pelish_table_exists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute([$table]);
    return (int) $statement->fetchColumn() > 0;
}

function pelish_run_migrations(PDO $pdo): void
{
    // Boş kurulumlarda ana tablolar setup.php tarafından oluşturulur. Canlıda
    // ise bu küçük kayıt tablosu yeni özellikleri bir kez, veri kaybetmeden ekler.
    if (!pelish_table_exists($pdo, 'pelish_customers')) {
        return;
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS pelish_schema_migrations (version VARCHAR(80) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $version = 'customer-email-security-v1';
    if (!pelish_migration_applied($pdo, $version)) {
        pelish_install_customer_features($pdo);
        pelish_mark_migration($pdo, $version);
    }

    // Müşteri hesabında kimlik, e-posta adresidir. Eski kullanıcı adı alanı
    // yalnızca geçmiş sürümlerde kullanılıyordu; bu geçiş veri kaybetmeden
    // hesabın diğer tüm alanlarını koruyarak alanı kaldırır.
    $version = 'customer-email-only-identity-v1';
    if (!pelish_migration_applied($pdo, $version)) {
        pelish_drop_column($pdo, 'pelish_email_verifications', 'username');
        pelish_drop_column($pdo, 'pelish_customers', 'username');
        pelish_mark_migration($pdo, $version);
    }

    $version = 'storefront-address-checkout-and-vitrin-v1';
    if (!pelish_migration_applied($pdo, $version)) {
        pelish_install_storefront_checkout_features($pdo);
        pelish_mark_migration($pdo, $version);
    }

    $version = 'collection-four-finance-and-image-ordering-v1';
    if (!pelish_migration_applied($pdo, $version)) {
        pelish_install_collection_finance_features($pdo);
        pelish_mark_migration($pdo, $version);
    }

    // Renk bir görsele bağlı tekil alan değildir: aynı görsel birden fazla
    // satış rengini temsil edebilir. Beden stokları da ürün toplamından ayrı
    // tutulur; müşteri yalnızca tanımlanmış bedenleri seçebilir.
    $version = 'product-colors-and-size-stock-v1';
    if (!pelish_migration_applied($pdo, $version)) {
        pelish_install_product_color_and_size_features($pdo);
        pelish_mark_migration($pdo, $version);
    }

    $version = 'product-color-size-stock-and-finance-receipts-v1';
    if (!pelish_migration_applied($pdo, $version)) {
        pelish_install_color_size_stock_and_finance_receipts($pdo);
        pelish_mark_migration($pdo, $version);
    }

    $version = 'cart-color-variant-stock-v1';
    if (!pelish_migration_applied($pdo, $version)) {
        pelish_add_column($pdo, 'pelish_customer_cart_items', 'color_id', 'INT UNSIGNED NULL AFTER product_image_id');
        try { $pdo->exec('ALTER TABLE pelish_customer_cart_items DROP INDEX unique_cart_line'); } catch (Throwable $ignored) {}
        try { $pdo->exec('ALTER TABLE pelish_customer_cart_items ADD UNIQUE KEY unique_cart_variant_line (cart_id, product_id, product_image_id, color_id, selected_size)'); } catch (Throwable $ignored) {}
        pelish_mark_migration($pdo, $version);
    }

    $version = 'transfer-payment-proofs-v1';
    if (!pelish_migration_applied($pdo, $version)) {
        pelish_add_column($pdo, 'pelish_orders', 'payment_proof_url', 'VARCHAR(500) NULL AFTER payment_reference');
        pelish_add_column($pdo, 'pelish_orders', 'payment_proof_key', 'VARCHAR(500) NULL AFTER payment_proof_url');
        pelish_add_column($pdo, 'pelish_orders', 'payment_proof_mime', 'VARCHAR(100) NULL AFTER payment_proof_key');
        pelish_add_column($pdo, 'pelish_orders', 'payment_proof_name', 'VARCHAR(190) NULL AFTER payment_proof_mime');
        pelish_add_column($pdo, 'pelish_orders', 'payment_proof_uploaded_at', 'DATETIME NULL AFTER payment_proof_name');
        pelish_add_column($pdo, 'pelish_orders', 'payment_reviewed_at', 'DATETIME NULL AFTER payment_proof_uploaded_at');
        pelish_add_column($pdo, 'pelish_orders', 'payment_reviewed_by', 'INT UNSIGNED NULL AFTER payment_reviewed_at');
        pelish_add_column($pdo, 'pelish_orders', 'payment_review_note', 'TEXT NULL AFTER payment_reviewed_by');
        pelish_mark_migration($pdo, $version);
    }

    $version = 'product-multiple-categories-v1';
    if (!pelish_migration_applied($pdo, $version)) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS pelish_product_categories (product_id INT UNSIGNED NOT NULL, category_name VARCHAR(100) NOT NULL, sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (product_id, category_name), INDEX idx_pelish_product_categories_name (category_name), CONSTRAINT fk_pelish_product_categories_product FOREIGN KEY (product_id) REFERENCES pelish_products(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('INSERT IGNORE INTO pelish_product_categories (product_id, category_name, sort_order) SELECT id, category, 0 FROM pelish_products WHERE TRIM(category) <> ""');
        pelish_mark_migration($pdo, $version);
    }
}

function pelish_migration_applied(PDO $pdo, string $version): bool
{
    $exists = $pdo->prepare('SELECT COUNT(*) FROM pelish_schema_migrations WHERE version = ?');
    $exists->execute([$version]);
    return (int) $exists->fetchColumn() > 0;
}

function pelish_mark_migration(PDO $pdo, string $version): void
{
    $pdo->prepare('INSERT INTO pelish_schema_migrations (version) VALUES (?)')->execute([$version]);
}

function pelish_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!pelish_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function pelish_drop_column(PDO $pdo, string $table, string $column): void
{
    if (pelish_table_exists($pdo, $table) && pelish_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
    }
}

function pelish_install_customer_features(PDO $pdo): void
{
    pelish_add_column($pdo, 'pelish_customers', 'password_hash', 'VARCHAR(255) NULL AFTER phone');
    pelish_add_column($pdo, 'pelish_customers', 'email_verified_at', 'DATETIME NULL AFTER password_hash');
    pelish_add_column($pdo, 'pelish_customers', 'last_login_at', 'DATETIME NULL AFTER email_verified_at');

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS pelish_email_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_verification_email (email),
    INDEX idx_verification_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelish_customer_email_changes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    new_email VARCHAR(190) NOT NULL,
    new_phone VARCHAR(40) NOT NULL,
    new_password_hash VARCHAR(255) NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_email_change (customer_id),
    UNIQUE KEY unique_pending_customer_email (new_email),
    INDEX idx_customer_email_change_expiry (expires_at),
    CONSTRAINT fk_pelish_customer_email_change_customer FOREIGN KEY (customer_id) REFERENCES pelish_customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelish_customer_carts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_cart (customer_id),
    CONSTRAINT fk_pelish_cart_customer FOREIGN KEY (customer_id) REFERENCES pelish_customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelish_customer_cart_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_image_id INT UNSIGNED NULL,
    selected_size VARCHAR(20) NULL,
    color_name VARCHAR(100) NULL,
    color_hex CHAR(7) NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cart_line (cart_id, product_id, product_image_id, selected_size),
    INDEX idx_cart_items_product (product_id),
    CONSTRAINT fk_pelish_cart_item_cart FOREIGN KEY (cart_id) REFERENCES pelish_customer_carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_pelish_cart_item_product FOREIGN KEY (product_id) REFERENCES pelish_products(id) ON DELETE CASCADE,
    CONSTRAINT fk_pelish_cart_item_image FOREIGN KEY (product_image_id) REFERENCES pelish_product_images(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelish_customer_favorites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_favorite (customer_id, product_id),
    INDEX idx_favorite_product (product_id),
    CONSTRAINT fk_pelish_favorite_customer FOREIGN KEY (customer_id) REFERENCES pelish_customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_pelish_favorite_product FOREIGN KEY (product_id) REFERENCES pelish_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelish_admin_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM pelish_admin_accounts')->fetchColumn();
    if ($adminCount === 0) {
        $seedAdmin = $pdo->prepare('INSERT INTO pelish_admin_accounts (username, password_hash) VALUES (?, ?)');
        $seedAdmin->execute(['pelish', '$2y$10$4OHUa1yhVY3nlwHRIa5BP.NJMmwkH3AXjf.x8n/KDOefPFiJCax6W']);
    }
}

function pelish_install_storefront_checkout_features(PDO $pdo): void
{
    pelish_add_column($pdo, 'pelish_email_verifications', 'kvkk_accepted', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash');
    pelish_add_column($pdo, 'pelish_email_verifications', 'marketing_consent', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER kvkk_accepted');
    pelish_add_column($pdo, 'pelish_orders', 'address_id', 'BIGINT UNSIGNED NULL AFTER customer_id');
    pelish_add_column($pdo, 'pelish_orders', 'recipient_name', 'VARCHAR(200) NULL AFTER address_id');
    pelish_add_column($pdo, 'pelish_orders', 'recipient_phone', 'VARCHAR(40) NULL AFTER recipient_name');
    pelish_add_column($pdo, 'pelish_orders', 'shipping_address', 'TEXT NULL AFTER recipient_phone');
    pelish_add_column($pdo, 'pelish_orders', 'payment_status', "ENUM('Bekliyor','Ödendi','Başarısız','İade') NOT NULL DEFAULT 'Bekliyor' AFTER payment_method");
    pelish_add_column($pdo, 'pelish_orders', 'payment_reference', 'VARCHAR(190) NULL AFTER payment_status');
    pelish_add_column($pdo, 'pelish_orders', 'terms_accepted_at', 'DATETIME NULL AFTER note');
    pelish_add_column($pdo, 'pelish_orders', 'kvkk_accepted_at', 'DATETIME NULL AFTER terms_accepted_at');
    pelish_add_column($pdo, 'pelish_orders', 'marketing_consent', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER kvkk_accepted_at');

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS pelish_customer_addresses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    title VARCHAR(100) NOT NULL,
    recipient_name VARCHAR(200) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    city VARCHAR(100) NOT NULL,
    district VARCHAR(100) NOT NULL,
    neighborhood VARCHAR(150) NULL,
    address_line TEXT NOT NULL,
    postal_code VARCHAR(20) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_addresses_customer (customer_id, is_default),
    CONSTRAINT fk_pelish_customer_address_customer FOREIGN KEY (customer_id) REFERENCES pelish_customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS pelish_customer_consents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    consent_type VARCHAR(60) NOT NULL,
    is_granted TINYINT(1) NOT NULL DEFAULT 0,
    source VARCHAR(100) NOT NULL DEFAULT 'web',
    consent_text_version VARCHAR(40) NOT NULL DEFAULT '2026-08',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    granted_at DATETIME NULL,
    withdrawn_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_consent (customer_id, consent_type),
    CONSTRAINT fk_pelish_customer_consent_customer FOREIGN KEY (customer_id) REFERENCES pelish_customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS pelish_order_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    document_type VARCHAR(60) NOT NULL,
    document_title VARCHAR(190) NOT NULL,
    document_version VARCHAR(40) NOT NULL,
    document_html MEDIUMTEXT NOT NULL,
    document_hash CHAR(64) NOT NULL,
    approved_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_order_document (order_id, document_type),
    CONSTRAINT fk_pelish_order_document_order FOREIGN KEY (order_id) REFERENCES pelish_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS pelish_home_collection_slots (
    slot_key VARCHAR(40) NOT NULL PRIMARY KEY,
    product_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pelish_home_slot_product FOREIGN KEY (product_id) REFERENCES pelish_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

    $firstProductId = (int) $pdo->query('SELECT id FROM pelish_products ORDER BY id LIMIT 1')->fetchColumn();
    if ($firstProductId > 0) {
        $seed = $pdo->prepare('INSERT IGNORE INTO pelish_home_collection_slots (slot_key, product_id) VALUES (?, ?)');
        foreach (['collection-1', 'collection-2', 'collection-3'] as $slotKey) {
            $seed->execute([$slotKey, $firstProductId]);
        }
    }
}

function pelish_install_collection_finance_features(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS pelish_finance_purchases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(190) NOT NULL,
    product_name VARCHAR(190) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    invoice_date DATE NOT NULL,
    note VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_finance_purchase_supplier_date (supplier_name, invoice_date),
    INDEX idx_finance_purchase_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelish_finance_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(190) NOT NULL,
    purchase_id BIGINT UNSIGNED NULL,
    paid_amount DECIMAL(12,2) NOT NULL,
    paid_at DATE NOT NULL,
    note VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_finance_payment_supplier_date (supplier_name, paid_at),
    CONSTRAINT fk_pelish_finance_payment_purchase FOREIGN KEY (purchase_id) REFERENCES pelish_finance_purchases(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

    $firstProductId = (int) $pdo->query('SELECT id FROM pelish_products ORDER BY id LIMIT 1')->fetchColumn();
    if ($firstProductId > 0) {
        $seed = $pdo->prepare('INSERT IGNORE INTO pelish_home_collection_slots (slot_key, product_id) VALUES (?, ?)');
        $seed->execute(['collection-4', $firstProductId]);
    }
}

function pelish_install_product_color_and_size_features(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS pelish_product_colors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    color_name VARCHAR(100) NOT NULL,
    color_hex CHAR(7) NOT NULL DEFAULT '#c7b6a3',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_product_color_name (product_id, color_name),
    INDEX idx_product_colors_order (product_id, sort_order),
    CONSTRAINT fk_pelish_product_color_product FOREIGN KEY (product_id) REFERENCES pelish_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelish_product_color_images (
    color_id INT UNSIGNED NOT NULL,
    product_image_id INT UNSIGNED NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (color_id, product_image_id),
    INDEX idx_product_color_images_image (product_image_id),
    CONSTRAINT fk_pelish_product_color_image_color FOREIGN KEY (color_id) REFERENCES pelish_product_colors(id) ON DELETE CASCADE,
    CONSTRAINT fk_pelish_product_color_image_image FOREIGN KEY (product_image_id) REFERENCES pelish_product_images(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelish_product_size_stocks (
    product_id INT UNSIGNED NOT NULL,
    size_code VARCHAR(10) NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, size_code),
    CONSTRAINT fk_pelish_product_size_stock_product FOREIGN KEY (product_id) REFERENCES pelish_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

    // Önceki sürümlerde görselde tanımlı renk varsa onu yeni renk havuzuna
    // aktarır ve aynı görseli bu renge bağlar. Böylece canlıdaki ürünler
    // geçişten sonra renk seçimini kaybetmez.
    $pdo->exec(<<<'SQL'
INSERT IGNORE INTO pelish_product_colors (product_id, color_name, color_hex, sort_order)
SELECT product_id, color_name, COALESCE(NULLIF(color_hex, ''), '#c7b6a3'), MIN(sort_order)
FROM pelish_product_images
WHERE color_name IS NOT NULL AND TRIM(color_name) <> ''
GROUP BY product_id, color_name;

INSERT IGNORE INTO pelish_product_color_images (color_id, product_image_id, sort_order)
SELECT c.id, i.id, i.sort_order
FROM pelish_product_images i
INNER JOIN pelish_product_colors c ON c.product_id = i.product_id AND c.color_name = i.color_name
WHERE i.color_name IS NOT NULL AND TRIM(i.color_name) <> '';

INSERT IGNORE INTO pelish_product_size_stocks (product_id, size_code, stock)
SELECT id, 'M', stock FROM pelish_products WHERE stock > 0;
SQL);
}

function pelish_install_color_size_stock_and_finance_receipts(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS pelish_product_color_size_stocks (
    product_id INT UNSIGNED NOT NULL,
    color_id INT UNSIGNED NOT NULL DEFAULT 0,
    size_code VARCHAR(10) NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, color_id, size_code),
    INDEX idx_product_color_size_stock_color (color_id),
    CONSTRAINT fk_pelish_product_color_size_stock_product FOREIGN KEY (product_id) REFERENCES pelish_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    // color_id=0 eski, renkten bağımsız stok kaydını temsil eder.
    $pdo->exec('INSERT IGNORE INTO pelish_product_color_size_stocks (product_id, color_id, size_code, stock) SELECT product_id, 0, size_code, stock FROM pelish_product_size_stocks');
    pelish_add_column($pdo, 'pelish_finance_purchases', 'receipt_number', 'VARCHAR(50) NULL AFTER id');
    pelish_add_column($pdo, 'pelish_finance_purchases', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
}
