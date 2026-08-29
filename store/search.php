<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '') { echo '[]'; exit; }
$find = $pdo->prepare('SELECT DISTINCT p.id, p.name, p.category, p.image_url, p.sale_price, p.list_price FROM pelish_products p LEFT JOIN pelish_product_categories pc ON pc.product_id=p.id WHERE p.is_active=1 AND (p.name LIKE ? OR p.category LIKE ? OR pc.category_name LIKE ?) ORDER BY p.name LIMIT 6');
$find->execute(['%' . $query . '%', '%' . $query . '%', '%' . $query . '%']);
echo json_encode($find->fetchAll(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
