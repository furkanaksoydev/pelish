<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '') { echo '[]'; exit; }
$find = $pdo->prepare('SELECT id, name, category, image_url, sale_price, list_price FROM pelish_products WHERE is_active=1 AND (name LIKE ? OR category LIKE ?) ORDER BY name LIMIT 6');
$find->execute(['%' . $query . '%', '%' . $query . '%']);
echo json_encode($find->fetchAll(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
