<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$products = $pdo->query('SELECT * FROM pelish_products WHERE is_active = 1 ORDER BY created_at DESC, id DESC')->fetchAll();
$customer = store_customer($pdo);
$favoriteIds = [];
if ($customer) {
    $favoriteQuery = $pdo->prepare('SELECT product_id FROM pelish_customer_favorites WHERE customer_id = ?');
    $favoriteQuery->execute([$customer['id']]);
    $favoriteIds = array_map('intval', $favoriteQuery->fetchAll(PDO::FETCH_COLUMN));
}

store_render_head('Duru Bir Etki', true);
store_render_header($pdo, true);
?>
<main id="top">
  <section class="hero" id="hero">
    <div class="hero-slides" aria-live="polite">
      <?php for ($slide = 1; $slide <= 3; $slide++): ?><div class="hero-image <?= $slide === 1 ? 'active' : '' ?>"><video class="hero-video desktop-video" muted loop playsinline preload="metadata"><source src="https://cdn.lavira360.com/pelish/hero/masaustu<?= $slide ?>.mp4" type="video/mp4"></video><video class="hero-video mobile-video" muted loop playsinline preload="metadata"><source src="https://cdn.lavira360.com/pelish/hero/<?= $slide ?>.mp4" type="video/mp4"></video></div><?php endfor; ?>
    </div>
    <div class="hero-shade"></div><div class="hero-content"><a class="button button-light" href="#sale">Koleksiyonu keşfet <span>→</span></a></div>
    <div class="hero-slider-controls" aria-label="Hero video navigasyonu"><button type="button" class="hero-arrow" data-hero-prev aria-label="Önceki video">←</button><div class="hero-dots"><button class="active" data-hero-dot="0" aria-label="1. video"></button><button data-hero-dot="1" aria-label="2. video"></button><button data-hero-dot="2" aria-label="3. video"></button></div><button type="button" class="hero-arrow" data-hero-next aria-label="Sonraki video">→</button></div><span class="hero-index" id="heroIndex">01 <i>/ 03</i></span>
  </section>
  <section class="collections section"><div class="collection-grid"><a class="collection-card large" href="indirimler.php"><img src="https://images.unsplash.com/photo-1496747611176-843222e1e57c?auto=format&fit=crop&w=1200&q=85" alt="Şehrin Ritmi"><span><i>01</i><strong>Şehrin Ritmi</strong><small>Keşfet →</small></span></a><a class="collection-card" href="indirimler.php"><img src="https://images.unsplash.com/photo-1539008835657-9e8e9680c956?auto=format&fit=crop&w=900&q=85" alt="Geceye Doğru"><span><i>02</i><strong>Geceye Doğru</strong><small>Keşfet →</small></span></a><a class="collection-card" href="indirimler.php"><img src="https://images.unsplash.com/photo-1496217590455-aa63a8350eea?auto=format&fit=crop&w=900&q=85" alt="Zamansız"><span><i>03</i><strong>Zamansız</strong><small>Keşfet →</small></span></a></div></section>
  <section class="products section" id="sale"><div class="sale-filters"><div class="filters" role="tablist"><button class="active" data-filter="all">Tümü</button><button data-filter="Yeni Sezon">Yeni Sezon</button><button data-filter="Aksesuar">Aksesuar</button></div></div><div class="product-grid" id="productGrid"><?php foreach ($products as $product): store_product_card($pdo, $product, $favoriteIds); endforeach; ?><?php if (!$products): ?><div class="commerce-empty"><p class="eyebrow">PELISH</p><h1>Yeni seçki hazırlanıyor.</h1><p>Admin panelinden eklediğin aktif ürünler burada görünecek.</p></div><?php endif; ?></div><div class="center"><a class="button button-dark" href="indirimler.php">Tüm ürünleri gör <span>→</span></a></div></section>
  <section class="newsletter section"><p class="eyebrow">PELISH’E YAKIN OL</p><h2>Güzel haberler<br>posta kutunda.</h2><form action="#" data-newsletter-form><input type="email" required aria-label="E-posta adresiniz" placeholder="E-posta adresin"><button aria-label="Abone ol">→</button></form><small>Abone olarak gizlilik politikamızı kabul etmiş olursun.</small></section>
</main>
<?php store_render_footer(); ?>
