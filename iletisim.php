<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$company = store_company($config);
store_render_head('İletişim');
store_render_header($pdo);
?>
<main class="store-main legal-main"><section class="legal-document company-card"><p class="eyebrow">PELISH’E YAKIN OL</p><h1>İletişim ve şirket künyesi.</h1><p>Satış öncesi ve sonrası tüm soruların için bizimle iletişime geçebilirsin.</p><dl><dt>Ticari unvan</dt><dd><?= e($company['legal_name']) ?></dd><dt>Açık adres</dt><dd><?= nl2br(e($company['address'])) ?></dd><dt>Telefon</dt><dd><?= e($company['phone'] ?: 'Henüz tanımlanmadı') ?></dd><dt>E-posta</dt><dd><?= e($company['email'] ?: 'Henüz tanımlanmadı') ?></dd><dt>MERSİS no</dt><dd><?= e($company['mersis_no'] ?: 'Henüz tanımlanmadı') ?></dd><dt>Vergi dairesi / no</dt><dd><?= e(trim($company['tax_office'] . ' ' . $company['tax_no']) ?: 'Henüz tanımlanmadı') ?></dd><dt>KEP adresi</dt><dd><?= e($company['kep_address'] ?: 'Henüz tanımlanmadı') ?></dd></dl><?php if ($company['etbis_qr_url']): ?><img class="etbis-qr" src="<?= e($company['etbis_qr_url']) ?>" alt="ETBİS kayıt karekodu"><?php endif; ?></section></main>
<?php store_render_footer(); ?>
