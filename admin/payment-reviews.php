<?php

declare(strict_types=1);

function admin_render_payment_reviews(PDO $pdo): void
{
    $items = $pdo->query('SELECT o.*, CONCAT(c.first_name, " ", c.last_name) AS customer, c.email FROM pelish_orders o LEFT JOIN pelish_customers c ON c.id=o.customer_id WHERE o.payment_method="Havale / EFT" AND o.payment_proof_uploaded_at IS NOT NULL ORDER BY (o.payment_reviewed_at IS NULL) DESC, o.payment_proof_uploaded_at DESC')->fetchAll();
    panel_header('payment-reviews', 'Havale / EFT Dekontları', [['label' => 'Onay bekleyenler', 'href' => admin_url('payment-reviews'), 'active' => true], ['label' => 'Siparişler', 'href' => admin_url('orders')]]);
    ?>
    <section class="table-card payment-review-card"><div class="table-title"><div><span>ÖDEME İNCELEMESİ</span><h1>Yüklenen dekontlar</h1></div></div><div class="payment-review-list"><?php foreach ($items as $order): $waiting = !$order['payment_reviewed_at']; ?><article class="payment-review-item <?= $waiting ? 'is-waiting' : '' ?>"><div class="payment-review-meta"><span><?= $waiting ? 'ONAY BEKLİYOR' : e($order['payment_status']) ?></span><h2><?= e($order['order_number']) ?></h2><p><?= e($order['customer'] ?: 'Misafir müşteri') ?><?= $order['email'] ? ' · ' . e($order['email']) : '' ?></p><b><?= admin_money((float) $order['grand_total']) ?></b><small>Dekont: <?= e(date('d.m.Y H:i', strtotime((string) $order['payment_proof_uploaded_at']))) ?></small></div><div class="payment-proof-actions"><a class="button ghost" href="<?= e($order['payment_proof_url']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-arrow-up"></i> Dekontu incele</a><?php if ($waiting): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review-payment-proof"><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><label>İnceleme notu<textarea name="review_note" placeholder="İsteğe bağlı not"></textarea></label><div><button class="button primary" name="decision" value="approve"><i class="fa-solid fa-check"></i> Onayla</button><button class="button danger" name="decision" value="reject"><i class="fa-solid fa-xmark"></i> Reddet</button></div></form><?php else: ?><p class="payment-review-result">İncelendi: <?= e($order['payment_review_note'] ?: 'Not girilmedi.') ?></p><?php endif; ?></div></article><?php endforeach; if (!$items): ?><div class="empty-state"><i class="fa-solid fa-circle-check"></i><strong>İncelenecek Havale / EFT dekontu yok.</strong></div><?php endif; ?></div></section>
    <?php
    panel_footer();
}
