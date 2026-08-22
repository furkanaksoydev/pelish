<?php

declare(strict_types=1);

session_start();

$configFile = __DIR__ . '/config.local.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Yönetim paneli bağlantı ayarı bulunamadı. config.local.php dosyasını config.example.php üzerinden oluşturun.');
}

$config = require $configFile;
require_once __DIR__ . '/schema.php';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['database']),
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    exit('Veri tabanı bağlantısı kurulamadı. Yerel bağlantı ayarlarınızı ve MySQL hizmetini kontrol edin.');
}

pelish_run_migrations($pdo);

if (empty($_SESSION['pelish_admin_csrf'])) {
    $_SESSION['pelish_admin_csrf'] = bin2hex(random_bytes(32));
}

function csrf_token(): string
{
    return $_SESSION['pelish_admin_csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['pelish_admin_csrf'], $token)) {
        http_response_code(419);
        exit('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
