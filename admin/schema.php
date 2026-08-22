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
