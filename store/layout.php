<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function store_render_head(string $title, bool $home = false): void
{
    ?>
<!doctype html>
<html lang="tr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="pelish — modern, zamansız butik seçkisi.">
    <title><?= e($title) ?> · pelish</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/ssssstyle.css">
    <link rel="stylesheet" href="assets/css/header-identical.css">
    <link rel="stylesheet" href="assets/css/storefront-core.css">
  </head>
  <body class="<?= $home ? 'store-home' : 'store-page' ?>">
    <?php
}

function store_render_header(PDO $pdo, bool $home = false): void
{
    $customer = store_customer($pdo);
    $counts = store_counts($pdo, $customer);
    ?>
    <div class="utility-bar" aria-label="Mağaza bilgileri"><div class="utility-inner"><span>HELLO FRIENDS -</span><span class="utility-message">3500 TL ve ÜZERİ <i>✦</i> KARGO ÜCRETSİZ!</span><a href="#footer">- YARDIM &amp; İLETİŞİM</a></div></div>
    <header class="site-header <?= $home ? '' : 'secondary-header' ?>" id="siteHeader">
      <div class="header-inner">
        <button class="menu-button" id="menuButton" type="button" aria-label="Menüyü aç" aria-expanded="false"><span></span><span></span><b>MENÜ</b></button>
        <nav class="desktop-nav" aria-label="Ana menü"><a href="indirimler.php">İNDİRİM</a></nav>
        <a class="brand" href="index.php" aria-label="pelish ana sayfa"><img src="https://cdn.pelish.co/logo.png" alt="pelish"></a>
        <div class="header-tools">
          <button class="header-action" type="button" data-inline-search-toggle aria-label="Ara"><i class="fa-solid fa-magnifying-glass"></i></button>
          <?php if ($customer): ?>
            <a class="account-link" href="profil.php" aria-label="Profilim"><i class="fa-regular fa-user"></i><span><?= e($customer['first_name']) ?></span></a>
          <?php else: ?>
            <a class="account-link" href="giris.php"><i class="fa-regular fa-user"></i><span>GİRİŞ YAP</span></a>
          <?php endif; ?>
          <a class="header-action" href="favorilerim.php" aria-label="Favorilerim"><i class="fa-regular fa-heart"></i><?php if ($counts['favorites']): ?><small><?= $counts['favorites'] ?></small><?php endif; ?></a>
          <a class="header-action" href="sepetim.php" aria-label="Sepetim"><i class="fa-solid fa-bag-shopping"></i><?php if ($counts['cart']): ?><small><?= $counts['cart'] ?></small><?php endif; ?></a>
        </div>
      </div>
      <div class="inline-search" id="inlineSearch"><form id="inlineSearchForm" role="search" action="indirimler.php" method="get"><i class="fa-solid fa-magnifying-glass"></i><input id="inlineSearchInput" name="q" type="search" autocomplete="off" placeholder="Ne arıyorsun?" aria-label="Ürün ara"><button type="submit" aria-label="Ara">→</button></form><div class="search-results" id="searchResults"></div></div>
    </header>
    <aside class="side-menu" id="sideMenu" aria-hidden="true"><button class="menu-close" type="button" data-menu-close aria-label="Menüyü kapat">×</button><p class="menu-brand"><img src="https://cdn.pelish.co/logo.png" alt="pelish"></p><nav><a href="indirimler.php"><span>01</span>İndirim</a><?php if ($customer): ?><a href="profil.php"><span>02</span>Profilim</a><a href="cikis.php"><span>03</span>Çıkış yap</a><?php else: ?><a href="giris.php"><span>02</span>Giriş yap / Kayıt ol</a><?php endif; ?></nav><div class="menu-footer"><span>Yeni sezondan haberdar ol.</span><a href="#footer">@pelish.co</a></div></aside>
    <?php if ($flash = store_take_flash()): ?><div class="store-flash flash-<?= e($flash['type']) ?>" data-flash><i class="fa-solid fa-circle-info"></i><span><?= e($flash['message']) ?></span><button type="button" aria-label="Kapat">×</button></div><?php endif; ?>
    <?php
}

function store_render_footer(): void
{
    ?>
    <footer class="store-footer" id="footer"><div><a class="footer-logo" href="index.php"><img src="https://cdn.pelish.co/logo.png" alt="pelish"></a><p>Tekirdağ / Süleymanpaşa</p></div><div><small>PELISH’E YAKIN OL</small><a href="https://instagram.com/pelish.co" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i> @pelish.co</a><a href="https://instagram.com/pelishaccessory" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i> @pelishaccessory</a><a href="#"><i class="fa-brands fa-tiktok"></i> @pelish.co</a></div><div><small>HIZLI ERİŞİM</small><a href="indirimler.php">İndirim</a><a href="favorilerim.php">Favorilerim</a><a href="sepetim.php">Sepetim</a></div></footer>
    <script src="assets/js/storefront-db.js"></script>
  </body>
</html>
    <?php
}

function store_product_card(PDO $pdo, array $product, array $favoriteIds = []): void
{
    $image = $product['image_url'] ?: 'https://images.unsplash.com/photo-1485968579580-b6d095142e6e?auto=format&fit=crop&w=900&q=85';
    $isFavorite = in_array((int) $product['id'], $favoriteIds, true);
    $price = store_product_price($product);
    ?>
    <article class="product-card db-product-card" data-category="<?= e($product['category']) ?>">
      <a class="product-image" href="urun.php?id=<?= (int) $product['id'] ?>"><img src="<?= e($image) ?>" alt="<?= e($product['name']) ?>"><?php if ($price['is_discounted']): ?><mark><span>−<?= $price['discount_percent'] ?>%</span> İndirim</mark><?php endif; ?></a>
      <div class="product-actions"><form method="post" action="store/action.php"><input type="hidden" name="csrf" value="<?= e(store_csrf()) ?>"><input type="hidden" name="action" value="favorite"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? 'index.php') ?>"><button class="heart <?= $isFavorite ? 'liked' : '' ?>" type="submit" aria-label="<?= $isFavorite ? 'Favoriden çıkar' : 'Favoriye ekle' ?>"><span><?= $isFavorite ? '♥' : '♡' ?></span></button></form><a class="quick-view" href="urun.php?id=<?= (int) $product['id'] ?>">İncele</a></div>
      <a class="product-info" href="urun.php?id=<?= (int) $product['id'] ?>"><div><small><?= e($product['category']) ?></small><h3><?= e($product['name']) ?></h3></div><strong><?php if ($price['is_discounted']): ?><del><?= store_money($price['original']) ?></del><?php endif; ?><span><?= store_money($price['current']) ?></span></strong></a>
    </article>
    <?php
}
