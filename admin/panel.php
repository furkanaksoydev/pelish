<?php

declare(strict_types=1);

function admin_url(string $page = 'dashboard', array $params = []): string
{
    $query = array_filter(['page' => $page] + $params, static fn ($value): bool => $value !== '' && $value !== null);
    return 'index.php?' . http_build_query($query);
}
function admin_money(float $amount): string
{
    return number_format($amount, 2, ',', '.') . ' TL';
}

function admin_flash(string $type, string $text): void
{
    $_SESSION['panel_flash'] = ['type' => $type, 'text' => $text];
}

function admin_take_flash(): ?array
{
    $flash = $_SESSION['panel_flash'] ?? null;
    unset($_SESSION['panel_flash']);
    return is_array($flash) ? $flash : null;
}

function admin_redirect(string $page, array $params = []): never
{
    header('Location: ' . admin_url($page, $params));
    exit;
}

function panel_page_meta(string $page): array
{
    return [
        'dashboard' => ['Ana Sayfa', 'panel-dashboard'],
        'products' => ['Ürün Yönetimi', 'panel-products'],
        'orders' => ['Siparişler', 'panel-orders'],
        'marketplaces' => ['Pazaryeri Mağazaları', 'panel-marketplaces'],
        'vouchers' => ['Hediye Çekleri', 'panel-vouchers'],
        'customers' => ['Müşteriler', 'panel-customers'],
        'reports' => ['Raporlar', 'panel-reports'],
        'catalog' => ['Ürün Yönetimi', 'panel-products'],
    ][$page] ?? ['Yönetim Paneli', 'panel-dashboard'];
}

function panel_header(string $page, string $subTitle = '', array $subLinks = []): void
{
    [$pageTitle, $activeClass] = panel_page_meta($page);
    $nav = [
        'dashboard' => ['Ana Sayfa', 'fa-house'],
        'orders' => ['Siparişler', 'fa-bag-shopping'],
        'products' => ['Ürünler', 'fa-tags'],
        'marketplaces' => ['Pazaryeri', 'fa-store'],
        'vouchers' => ['Kampanyalar', 'fa-bullhorn'],
        'customers' => ['Müşteriler', 'fa-users'],
        'reports' => ['Raporlar', 'fa-chart-line'],
    ];
    ?>
<!doctype html>
<html lang="tr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="https://cdn.pelish.co/logo.png" type="image/png">
    <link rel="apple-touch-icon" href="https://cdn.pelish.co/logo.png">
    <title><?= e($pageTitle) ?> · pelish Yönetim Paneli</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Mono&family=DM+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/admin-interface.css?v=20260822">
  </head>
  <body>
    <div class="page-loader" id="pageLoader"><span></span><small>Yükleniyor…</small></div>
    <header class="topline"><div class="shell"><span>PELISH CONTROL · V 1.1.0</span><div class="top-actions"><a href="../index.php" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> Siteyi Görüntüle</a><a href="account.php"><i class="fa-solid fa-gear"></i> Hesap</a><a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Çıkış</a></div></div></header>
    <header class="main-header">
      <div class="shell header-row">
        <a class="panel-logo" href="<?= admin_url('dashboard') ?>" aria-label="pelish panel ana sayfa"><img src="https://cdn.pelish.co/logo.png" alt="pelish"></a>
        <button class="mobile-menu" type="button" aria-label="Menüyü aç" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
        <nav class="mega-nav" aria-label="Yönetim menüsü">
          <?php foreach ($nav as $key => [$label, $icon]): ?>
            <a class="<?= $key === $page || ($key === 'products' && $page === 'catalog') ? 'is-active' : '' ?>" href="<?= admin_url($key) ?>"><i class="fa-solid <?= e($icon) ?>"></i><span><?= e(mb_strtoupper($label, 'UTF-8')) ?></span><?= in_array($key, ['orders', 'products', 'marketplaces', 'vouchers', 'customers'], true) ? '<b>⌄</b>' : '' ?></a>
          <?php endforeach; ?>
        </nav>
        <div class="header-tools"><form class="header-search" method="get" action="index.php"><input type="hidden" name="page" value="products"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" placeholder="Panelde ara…"></form></div>
      </div>
      <div class="mega-menu"><div class="shell menu-grid">
        <section><h3>Ürün Yönetimi</h3><a href="<?= admin_url('products') ?>">Ürünler</a><a href="<?= admin_url('catalog', ['type' => 'categories']) ?>">Kategoriler</a><a href="<?= admin_url('catalog', ['type' => 'brands']) ?>">Markalar</a><a href="<?= admin_url('catalog', ['type' => 'tags']) ?>">Etiketler</a></section>
        <section><h3>Sipariş ve müşteri</h3><a href="<?= admin_url('orders') ?>">Siparişler</a><a href="<?= admin_url('customers') ?>">Müşteriler</a><a href="<?= admin_url('vouchers') ?>">Hediye Çekleri</a><a href="<?= admin_url('reports') ?>">Satış Raporları</a></section>
        <section><h3>Satış kanalları</h3><a href="<?= admin_url('marketplaces') ?>">Pazaryeri Mağazaları</a></section>
      </div></div>
    </header>
    <section class="subnav"><div class="shell"><div><strong><?= e($subTitle ?: $pageTitle) ?></strong></div><nav>
      <?php foreach ($subLinks as $link): ?><a class="<?= !empty($link['active']) ? 'active' : '' ?>" href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a><?php endforeach; ?>
    </nav></div></section>
    <main class="shell panel-content <?= e($activeClass) ?>">
      <?php if ($flash = admin_take_flash()): ?><div class="flash flash-<?= e($flash['type']) ?>"><i class="fa-solid fa-circle-check"></i><?= e($flash['text']) ?><button type="button" aria-label="Kapat">×</button></div><?php endif; ?>
    <?php
}

function panel_footer(): void
{
    ?>
    </main>
    <footer class="panel-footer"><div class="shell">Version: 1.0.0 <span>·</span> pelish Yönetim Paneli</div></footer>
    <div class="toast" id="toast" role="status"></div>
    <script src="assets/admin-interactions.js"></script>
  </body>
</html>
    <?php
}
