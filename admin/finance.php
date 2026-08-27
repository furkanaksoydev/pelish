<?php

declare(strict_types=1);

function admin_finance_date(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new RuntimeException('Geçerli bir tarih seçmelisin.');
    }
    return $value;
}

function admin_finance_handle_action(PDO $pdo, string $action): bool
{
    if (!in_array($action, ['save-finance-purchase', 'save-finance-payment', 'rename-finance-supplier'], true)) {
        return false;
    }
    try {
        $supplier = form_value('supplier_name');
        if ($supplier === '') {
            throw new RuntimeException('Firma adı zorunludur.');
        }
        if ($action === 'rename-finance-supplier') {
            $newName = form_value('new_supplier_name'); if ($newName === '') throw new RuntimeException('Yeni firma adı zorunludur.');
            $pdo->beginTransaction(); $pdo->prepare('UPDATE pelish_finance_purchases SET supplier_name=? WHERE supplier_name=?')->execute([$newName,$supplier]); $pdo->prepare('UPDATE pelish_finance_payments SET supplier_name=? WHERE supplier_name=?')->execute([$newName,$supplier]); $pdo->commit(); admin_flash('success','Firma adı güncellendi.'); $supplier=$newName;
        } elseif ($action === 'save-finance-purchase') {
            $products = (array) ($_POST['product_name'] ?? []); $quantities = (array) ($_POST['quantity'] ?? []); $unitPrices = (array) ($_POST['unit_price'] ?? []); $totals = (array) ($_POST['total_amount'] ?? []); $lines=[];
            foreach ($products as $index=>$product) { $product=trim((string)$product); $quantity=max(0,(int)($quantities[$index]??0)); $unitPrice=max(0,(float)str_replace(',','.',(string)($unitPrices[$index]??0))); $total=max(0,(float)str_replace(',','.',(string)($totals[$index]??0))); if($product===''&&$quantity===0&&$unitPrice==0&&$total==0)continue; if($product===''||$quantity<1||$unitPrice<=0||$total<=0)throw new RuntimeException('Fişteki her ürün için ad, adet, fiyat ve toplam tutar zorunludur.');$lines[]=[$product,$quantity,$unitPrice,$total]; }
            if(!$lines)throw new RuntimeException('Fişe en az bir ürün eklemelisin.'); $receipt='FIS-'.date('Ymd-His').'-'.strtoupper(substr(bin2hex(random_bytes(2)),0,4));$statement=$pdo->prepare('INSERT INTO pelish_finance_purchases (receipt_number,supplier_name,product_name,quantity,unit_price,total_amount,invoice_date,note) VALUES (?,?,?,?,?,?,?,?)');foreach($lines as [$product,$quantity,$unitPrice,$total])$statement->execute([$receipt,$supplier,$product,$quantity,$unitPrice,$total,admin_finance_date(form_value('invoice_date')),form_value('note')?:null]);
            admin_flash('success', count($lines).' ürünlü fiş kaydedildi.');
        } else {
            $amount = max(0, form_number('paid_amount'));
            if ($amount <= 0) {
                throw new RuntimeException('Ödeme tutarı sıfırdan büyük olmalıdır.');
            }
            $purchaseId = max(0, (int) ($_POST['purchase_id'] ?? 0));
            if ($purchaseId > 0) {
                $purchase = $pdo->prepare('SELECT supplier_name FROM pelish_finance_purchases WHERE id = ?');
                $purchase->execute([$purchaseId]);
                if ((string) $purchase->fetchColumn() !== $supplier) {
                    throw new RuntimeException('Ödeme seçilen firmaya ait bir faturaya bağlanmalıdır.');
                }
            }
            $statement = $pdo->prepare('INSERT INTO pelish_finance_payments (supplier_name, purchase_id, paid_amount, paid_at, note) VALUES (?, ?, ?, ?, ?)');
            $statement->execute([$supplier, $purchaseId ?: null, $amount, admin_finance_date(form_value('paid_at')), form_value('note') ?: null]);
            admin_flash('success', 'Firma ödemesi kaydedildi.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        admin_flash('danger', $exception instanceof RuntimeException ? $exception->getMessage() : 'Finans kaydı oluşturulamadı.');
    }
    admin_redirect('finance', ['supplier' => $supplier ?? '']);
}

function admin_render_finance(PDO $pdo): void
{
    $supplier = trim((string) ($_GET['supplier'] ?? ''));
    $summary = $pdo->query(
        'SELECT purchases.supplier_name, purchases.total_debt, COALESCE(payments.total_paid, 0) AS total_paid, purchases.invoice_count, purchases.first_invoice_date, payments.last_payment_date
         FROM (
           SELECT supplier_name, SUM(total_amount) AS total_debt, COUNT(*) AS invoice_count, MIN(invoice_date) AS first_invoice_date
           FROM pelish_finance_purchases GROUP BY supplier_name
         ) purchases
         LEFT JOIN (
           SELECT supplier_name, SUM(paid_amount) AS total_paid, MAX(paid_at) AS last_payment_date
           FROM pelish_finance_payments GROUP BY supplier_name
         ) payments ON payments.supplier_name = purchases.supplier_name
         ORDER BY (purchases.total_debt - COALESCE(payments.total_paid, 0)) DESC, purchases.supplier_name ASC'
    )->fetchAll();
    $totals = ['debt' => 0.0, 'paid' => 0.0];
    foreach ($summary as $row) {
        $totals['debt'] += (float) $row['total_debt'];
        $totals['paid'] += (float) $row['total_paid'];
    }
    $purchases = [];
    $payments = [];
    if ($supplier !== '') {
        $invoices = $pdo->prepare('SELECT * FROM pelish_finance_purchases WHERE supplier_name = ? ORDER BY invoice_date DESC, id DESC');
        $invoices->execute([$supplier]);
        $purchases = $invoices->fetchAll();
        $paymentRows = $pdo->prepare('SELECT payment.*, purchase.product_name FROM pelish_finance_payments payment LEFT JOIN pelish_finance_purchases purchase ON purchase.id = payment.purchase_id WHERE payment.supplier_name = ? ORDER BY payment.paid_at DESC, payment.id DESC');
        $paymentRows->execute([$supplier]);
        $payments = $paymentRows->fetchAll();
    }
    panel_header('finance', 'Finans', [
        ['label' => 'Firma Özeti', 'href' => admin_url('finance'), 'active' => $supplier === ''],
        ['label' => 'Yeni Fatura', 'href' => '#financePurchase'],
        ['label' => 'Ödeme Kaydı', 'href' => $supplier !== '' ? '#financePayment' : '#financeSummary'],
    ]);
    ?>
    <section class="finance-stats stat-grid">
      <article><span>TOPLAM BORÇ</span><strong><?= admin_money($totals['debt']) ?></strong><i class="fa-solid fa-file-invoice-dollar"></i></article>
      <article><span>TOPLAM ÖDEME</span><strong><?= admin_money($totals['paid']) ?></strong><i class="fa-solid fa-money-bill-transfer"></i></article>
      <article><span>KALAN BORÇ</span><strong><?= admin_money(max(0, $totals['debt'] - $totals['paid'])) ?></strong><i class="fa-solid fa-wallet"></i></article>
      <article><span>AKTİF FİRMA</span><strong><?= count($summary) ?></strong><i class="fa-solid fa-building"></i></article>
    </section>
    <section class="finance-layout">
      <article class="table-card finance-form-card" id="financePurchase">
        <div class="table-title"><div><span>YENİ FATURA</span><h1>Toptancı alışını ekle</h1></div></div>
        <form method="post" class="finance-form" data-finance-purchase>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save-finance-purchase">
          <label class="wide">Firma adı<input required name="supplier_name" list="financeSuppliers" value="<?= e($supplier) ?>" placeholder="Örn. X Toptan"></label>
          <div class="finance-receipt-lines wide" data-finance-receipt-lines><div class="finance-receipt-line"><label>Ürün adı<input required name="product_name[]" placeholder="Örn. Keten gömlek"></label><label>Adet<input required type="number" min="1" step="1" name="quantity[]" value="1" data-finance-quantity></label><label>Birim fiyat<input required type="number" min="0.01" step="0.01" name="unit_price[]" placeholder="0,00" data-finance-unit-price></label><label>Toplam fiyat<input required type="number" min="0.01" step="0.01" name="total_amount[]" placeholder="Adet × fiyat" data-finance-total></label><button type="button" data-remove-finance-line aria-label="Satırı kaldır">×</button></div></div><button class="button ghost small finance-add-line" type="button" data-add-finance-line><i class="fa-solid fa-plus"></i> Fişe ürün ekle</button><small class="finance-line-help">Her satır aynı fişe eklenir. Toplam tutar otomatik hesaplanır, gerektiğinde düzenlenebilir.</small>
          <label>Fatura tarihi<input required type="date" name="invoice_date" value="<?= date('Y-m-d') ?>"></label>
          <label class="wide">Not<textarea name="note" placeholder="İsteğe bağlı fatura veya anlaşma notu"></textarea></label>
          <button class="button primary" type="submit"><i class="fa-solid fa-plus"></i> Faturayı kaydet</button>
        </form>
      </article>
      <article class="table-card finance-help-card"><div class="table-title"><div><span>TAKİP MANTIĞI</span><h1>Her firma tek yerde</h1></div></div><div class="finance-help"><i class="fa-solid fa-circle-info"></i><p>Her alış faturası borca eklenir. Ödemeyi firma bazında veya istersen belirli bir faturaya bağlayarak gir. Firma detayında ilk fatura tarihi, son ödeme ve kalan toplam anında görünür.</p><a class="button ghost" href="#financeSummary">Firma özetine git</a></div></article>
    </section>
    <datalist id="financeSuppliers"><?php foreach ($summary as $row): ?><option value="<?= e($row['supplier_name']) ?>"><?php endforeach; ?></datalist>
    <section class="table-card finance-summary" id="financeSummary"><div class="table-title"><div><span>FİRMA BAKİYELERİ</span><h1>Nereye ne kadar borç var?</h1></div></div><div class="table-scroll"><table><thead><tr><th>Firma</th><th>Fatura</th><th>İlk borç</th><th>Toplam borç</th><th>Ödenen</th><th>Kalan</th><th>Son ödeme</th><th>İşlem</th></tr></thead><tbody><?php foreach ($summary as $row): $remaining = (float) $row['total_debt'] - (float) $row['total_paid']; ?><tr><td><strong><?= e($row['supplier_name']) ?></strong></td><td><?= (int) $row['invoice_count'] ?> fatura</td><td><?= e(date('d.m.Y', strtotime((string) $row['first_invoice_date']))) ?></td><td><strong><?= admin_money((float) $row['total_debt']) ?></strong></td><td><?= admin_money((float) $row['total_paid']) ?></td><td class="<?= $remaining > 0 ? 'finance-remaining' : '' ?>"><strong><?= admin_money($remaining) ?></strong></td><td><?= $row['last_payment_date'] ? e(date('d.m.Y', strtotime((string) $row['last_payment_date']))) : 'Ödeme yok' ?></td><td><a class="button ghost small" href="<?= e(admin_url('finance', ['supplier' => $row['supplier_name']])) ?>">İncele</a></td></tr><?php endforeach; if (!$summary): ?><tr><td colspan="8" class="empty-table"><i class="fa-solid fa-wallet"></i><strong>Henüz finans kaydı yok.</strong></td></tr><?php endif; ?></tbody></table></div></section>
    <?php if ($supplier !== ''): $current = null; foreach ($summary as $row) { if ($row['supplier_name'] === $supplier) { $current = $row; break; } } ?>
      <section class="finance-detail-head"><div><p>FİRMA DETAYI</p><h1><?= e($supplier) ?></h1><span>İlk kayıt: <?= $current ? e(date('d.m.Y', strtotime((string) $current['first_invoice_date']))) : '-' ?> · Son ödeme: <?= $current && $current['last_payment_date'] ? e(date('d.m.Y', strtotime((string) $current['last_payment_date']))) : 'Henüz yok' ?></span></div><div class="finance-detail-actions"><button class="button ghost" type="button" data-toggle-supplier-rename>Firma adını düzelt</button><a class="button ghost" href="<?= admin_url('finance') ?>">Tüm firmalar</a></div></section><form method="post" class="finance-rename-form" data-supplier-rename hidden><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="rename-finance-supplier"><input type="hidden" name="supplier_name" value="<?= e($supplier) ?>"><label>Firma adı<input required name="new_supplier_name" value="<?= e($supplier) ?>"></label><button class="button primary">Güncelle</button></form>
      <section class="finance-layout finance-detail-layout">
        <article class="table-card"><div class="table-title"><div><span>FATURALAR</span><h1>Alış geçmişi</h1></div></div><div class="table-scroll"><table><thead><tr><th>Tarih</th><th>Ürün</th><th>Adet</th><th>Birim fiyat</th><th>Fatura tutarı</th><th>Not</th></tr></thead><tbody><?php foreach ($purchases as $purchase): ?><tr><td><?= e(date('d.m.Y', strtotime((string) $purchase['invoice_date']))) ?></td><td><strong><?= e($purchase['product_name']) ?></strong></td><td><?= (int) $purchase['quantity'] ?></td><td><?= admin_money((float) $purchase['unit_price']) ?></td><td><strong><?= admin_money((float) $purchase['total_amount']) ?></strong></td><td><?= e($purchase['note'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div></article>
        <article class="table-card finance-payment-card" id="financePayment"><div class="table-title"><div><span>ÖDEME EKLE</span><h1>Firmaya ödeme yap</h1></div></div><form method="post" class="finance-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save-finance-payment"><input type="hidden" name="supplier_name" value="<?= e($supplier) ?>"><label>Ödeme tutarı<input required type="number" step="0.01" min="0.01" name="paid_amount" placeholder="0,00"></label><label>Ödeme tarihi<input required type="date" name="paid_at" value="<?= date('Y-m-d') ?>"></label><label>Faturaya bağla<select name="purchase_id"><option value="0">Genel firma ödemesi</option><?php foreach ($purchases as $purchase): ?><option value="<?= (int) $purchase['id'] ?>"><?= e(date('d.m.Y', strtotime((string) $purchase['invoice_date'])) . ' · ' . $purchase['product_name'] . ' · ' . admin_money((float) $purchase['total_amount'])) ?></option><?php endforeach; ?></select></label><label class="wide">Not<textarea name="note" placeholder="Havale, elden ödeme veya açıklama"></textarea></label><button class="button primary" type="submit"><i class="fa-solid fa-check"></i> Ödemeyi kaydet</button></form><div class="finance-payment-history"><strong>Ödeme geçmişi</strong><?php foreach ($payments as $payment): ?><article><span><?= e(date('d.m.Y', strtotime((string) $payment['paid_at']))) ?></span><div><b><?= e($payment['product_name'] ?: 'Genel firma ödemesi') ?></b><small><?= e($payment['note'] ?: 'Not yok') ?></small></div><em><?= admin_money((float) $payment['paid_amount']) ?></em></article><?php endforeach; if (!$payments): ?><p>Bu firmaya henüz ödeme girilmemiş.</p><?php endif; ?></div></article>
      </section>
    <?php endif; ?>
    <?php
    panel_footer();
}
