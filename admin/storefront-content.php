<?php

declare(strict_types=1);

function admin_save_home_collection_slots(PDO $pdo): never
{
    $slotKeys = ['collection-1', 'collection-2', 'collection-3', 'collection-4'];
    $ids = [];
    foreach ($slotKeys as $key) {
        $ids[$key] = max(0, (int) ($_POST[$key] ?? 0));
    }
    $valid = $pdo->prepare('SELECT COUNT(*) FROM pelish_products WHERE id = ?');
    foreach ($ids as $id) {
        if ($id < 1) { throw new RuntimeException('Her vitrin kartı için bir ürün seçmelisin.'); }
        $valid->execute([$id]);
        if ((int) $valid->fetchColumn() !== 1) { throw new RuntimeException('Seçilen ürünlerden biri bulunamadı.'); }
    }
    $pdo->beginTransaction();
    try {
        $save = $pdo->prepare('INSERT INTO pelish_home_collection_slots (slot_key, product_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE product_id = VALUES(product_id)');
        foreach ($ids as $key => $id) { $save->execute([$key, $id]); }
        $pdo->commit();
        admin_flash('success', 'Ana sayfa vitrin ürünleri güncellendi.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
    header('Location: index.php?page=storefront');
    exit;
}

function admin_render_storefront_content(PDO $pdo): void
{
    $products = $pdo->query('SELECT id, name, category, image_url, is_active FROM pelish_products ORDER BY is_active DESC, name')->fetchAll();
    $slots = ['collection-1' => 1, 'collection-2' => 1, 'collection-3' => 1, 'collection-4' => 1];
    foreach ($pdo->query('SELECT slot_key, product_id FROM pelish_home_collection_slots')->fetchAll() as $slot) {
        if (array_key_exists($slot['slot_key'], $slots)) { $slots[$slot['slot_key']] = (int) $slot['product_id']; }
    }
    panel_header('storefront', 'Ana Sayfa Vitrini', [['label' => 'Vitrin Ürünleri', 'href' => admin_url('storefront'), 'active' => true]]);
    ?>
    <section class="licence-card"><div class="licence-icon"><i class="fa-solid fa-window-maximize"></i></div><div><strong>Ana sayfa vitrin kartları.</strong><p>Collections alanındaki dört kartta hangi ürünün gösterileceğini buradan seç. Aynı ürünü birden fazla kartta kullanabilirsin; kartlar ürünün ilk üç görseli arasında otomatik geçiş yapar.</p></div><a href="../index.php#vitrin-secimleri" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> Vitrini görüntüle</a></section>
    <section class="table-card storefront-manager"><div class="table-title"><div><span>ANA SAYFA</span><h1>Vitrin ürünleri</h1></div></div><form method="post" class="product-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save-home-collection-slots"><div class="storefront-slot-grid"><?php foreach ($slots as $key => $selected): ?><label><span><?= e(mb_strtoupper(str_replace('collection-', 'KART ', $key), 'UTF-8')) ?></span><select required name="<?= e($key) ?>"><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" <?= (int) $product['id'] === $selected ? 'selected' : '' ?>><?= e($product['name']) ?><?= $product['is_active'] ? '' : ' · Pasif' ?></option><?php endforeach; ?></select></label><?php endforeach; ?></div><div class="modal-actions"><button class="button primary" type="submit">Vitrini güncelle</button></div></form></section>
    <?php
    panel_footer();
}
