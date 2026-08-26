<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function store_checkout_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function store_order_number(PDO $pdo): string
{
    do {
        $number = 'PLS-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $check = $pdo->prepare('SELECT COUNT(*) FROM pelish_orders WHERE order_number = ?');
        $check->execute([$number]);
    } while ((int) $check->fetchColumn() > 0);
    return $number;
}

function store_checkout_document_html(string $type, array $company, array $customer, array $address, array $items, array $totals, string $orderNumber): array
{
    $itemRows = '';
    foreach ($items as $item) {
        $itemRows .= '<tr><td>' . store_checkout_escape((string) $item['name']) . '</td><td>' . (int) $item['quantity'] . '</td><td>' . store_checkout_escape(store_money((float) $item['price']['current'])) . '</td></tr>';
    }
    $companyName = store_checkout_escape($company['legal_name']);
    $companyAddress = nl2br(store_checkout_escape($company['address']));
    $customerName = store_checkout_escape(trim($customer['first_name'] . ' ' . $customer['last_name']));
    $addressText = nl2br(store_checkout_escape(trim($address['address_line'] . "\n" . ($address['neighborhood'] ? $address['neighborhood'] . "\n" : '') . $address['district'] . ' / ' . $address['city'])));
    $orderNo = store_checkout_escape($orderNumber);
    $summary = '<table border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse"><thead><tr><th>Ürün</th><th>Adet</th><th>Birim fiyat</th></tr></thead><tbody>' . $itemRows . '</tbody></table><p>Ürün toplamı: <strong>' . store_checkout_escape(store_money((float) $totals['subtotal'])) . '</strong><br>Kargo: <strong>' . store_checkout_escape(store_money((float) $totals['cargo'])) . '</strong><br>Genel toplam: <strong>' . store_checkout_escape(store_money((float) $totals['grand_total'])) . '</strong></p>';
    $party = '<p><strong>Satıcı:</strong> ' . $companyName . '<br><strong>Adres:</strong> ' . $companyAddress . '<br><strong>Alıcı:</strong> ' . $customerName . '<br><strong>Teslimat adresi:</strong> ' . $addressText . '</p>';

    $sellerContact = '<p><strong>Satıcı iletişim bilgileri:</strong><br>Telefon: ' . store_checkout_escape((string) ($company['phone'] ?? '')) . '<br>E-posta: ' . store_checkout_escape((string) ($company['email'] ?? '')) . '<br>Vergi dairesi / no: ' . store_checkout_escape(trim((string) ($company['tax_office'] ?? '') . ' / ' . (string) ($company['tax_no'] ?? ''), ' /')) . '<br>MERSİS: ' . store_checkout_escape((string) ($company['mersis_no'] ?? '')) . '<br>KEP: ' . store_checkout_escape((string) ($company['kep_address'] ?? '')) . '</p>';
    $delivery = '<h2>6. Teslimat</h2><p>Ürünler, alıcının yukarıda belirtilen teslimat adresine gönderilir. Teslimat süresi, sipariş öncesi bilgilendirmede gösterilen süre olup ilgili mevzuattaki azami süreyi aşamaz. Teslimde paket hasarı tespit edilirse kargo görevlisi ile tutanak tutulması önerilir.</p>';
    $withdrawal = '<h2>7. Cayma hakkı, iade ve istisnalar</h2><p>Alıcı, mevzuattaki istisnalar saklı kalmak üzere ürünün kendisine veya gösterdiği üçüncü kişiye tesliminden itibaren 14 gün içinde, satıcıya açık bir bildirim yaparak gerekçe göstermeksizin ve cezai şart ödemeden cayma hakkını kullanabilir. Cayma bildirimi satıcının yukarıdaki iletişim kanalları üzerinden kalıcı veri saklayıcısı yoluyla iletilir. Kullanılmış, yıkanmış, etiketi koparılmış veya kozmetik/makyaj lekesi bulunan ürünler yeniden satışa uygunluk bakımından incelenir. Kişiye özel hazırlanan, sağlık veya hijyen bakımından iadeye elverişli olmayan ve koruyucu unsurları açılmış ürünler ile mevzuatta sayılan diğer hâllerde cayma hakkı kullanılamayabilir.</p>';
    $finalTerms = '<h2>8. Temerrüt, mücbir sebep ve uyuşmazlık</h2><p>Alıcının kredi kartı ödemesinde temerrüde düşmesi hâlinde kart sahibi banka ile arasındaki sözleşme hükümleri uygulanır. Mücbir sebep nedeniyle teslimatın imkânsızlaşması veya gecikmesi hâlinde satıcı, durumu alıcıya bildirir ve mevzuata uygun çözüm seçeneklerini sunar. Tüketici uyuşmazlıklarında, yürürlükteki mevzuat uyarınca yetkili tüketici hakem heyetleri ve tüketici mahkemeleri yetkilidir.</p><h2>9. Yürürlük</h2><p>Alıcı, elektronik ortamda siparişi onaylayarak ön bilgilendirme formunu ve işbu mesafeli satış sözleşmesini okuyup kabul ettiğini beyan eder. İşbu siparişe özel nüsha, kabul anında oluşturulur ve sipariş kaydıyla birlikte saklanır.</p>';

    if ($type === 'pre_information') {
        return [
            'title' => 'Ön Bilgilendirme Formu',
            'html' => '<h1>Ön Bilgilendirme Formu</h1><p>Sipariş no: ' . $orderNo . '</p><h2>Taraflar</h2>' . $party . $sellerContact . '<h2>Ürün, fiyat ve ödeme bilgileri</h2>' . $summary . '<p>Ürünün temel nitelikleri, vergiler dâhil toplam bedel, varsa kargo bedeli, teslimat adresi, ödeme yöntemi ve cayma hakkına ilişkin bilgiler, sipariş onayından önce alıcıya sunulmuştur.</p>' . $delivery . $withdrawal,
        ];
    }

    return [
        'title' => 'Mesafeli Satış Sözleşmesi',
        'html' => '<h1>Mesafeli Satış Sözleşmesi</h1><p>Sipariş no: ' . $orderNo . '</p><h2>1. Taraflar</h2>' . $party . $sellerContact . '<p>Bu sözleşmede satıcı; ticari veya mesleki faaliyeti kapsamında ürün sunan tarafı, alıcı ise ticari veya mesleki olmayan amaçlarla ürün edinen kişiyi ifade eder.</p><h2>2. Sözleşmenin konusu</h2><p>İşbu sözleşme, alıcının Pelish internet sitesi üzerinden elektronik ortamda sipariş verdiği ürünlerin satışı ve teslimi ile tarafların hak ve yükümlülüklerini 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği kapsamında düzenler.</p><h2>3. Sözleşme konusu ürün/ürünler</h2>' . $summary . '<h2>4. Ödeme</h2><p>Ödeme yöntemi sipariş özetinde belirtilmiştir. Kartlı ödeme, yalnızca yetkili 3D Secure ödeme kuruluşunun güvenli akışında tamamlanır; kart numarası, son kullanma tarihi ve CVV satıcı veritabanında saklanmaz.</p>' . $delivery . $withdrawal . $finalTerms,
    ];
}

function store_create_cod_order(PDO $pdo, array $customer, int $addressId, bool $termsAccepted, bool $kvkkAccepted, bool $marketingConsent, array $config): array
{
    if (!$termsAccepted || !$kvkkAccepted) {
        throw new RuntimeException('Ön bilgilendirme formu, mesafeli satış sözleşmesi ve KVKK aydınlatma metni onaylanmalıdır.');
    }
    $addressQuery = $pdo->prepare('SELECT * FROM pelish_customer_addresses WHERE id = ? AND customer_id = ? LIMIT 1');
    $addressQuery->execute([$addressId, $customer['id']]);
    $address = $addressQuery->fetch();
    if (!$address) {
        throw new RuntimeException('Sipariş için kayıtlı bir teslimat adresi seçmelisin.');
    }

    $pdo->beginTransaction();
    try {
        $itemsQuery = $pdo->prepare('SELECT i.*, p.name, p.category, p.sku, p.sale_price, p.list_price, p.stock, COALESCE(pi.image_url, p.image_url) AS image_url FROM pelish_customer_cart_items i INNER JOIN pelish_customer_carts c ON c.id=i.cart_id INNER JOIN pelish_products p ON p.id=i.product_id LEFT JOIN pelish_product_images pi ON pi.id=i.product_image_id WHERE c.customer_id=? FOR UPDATE');
        $itemsQuery->execute([$customer['id']]);
        $items = array_map(static function (array $item): array { $item['price'] = store_product_price($item); return $item; }, $itemsQuery->fetchAll());
        if (!$items) {
            throw new RuntimeException('Sepetin boş olduğu için sipariş oluşturulamadı.');
        }
        foreach ($items as $item) {
            if ((int) $item['stock'] < (int) $item['quantity']) {
                throw new RuntimeException($item['name'] . ' için yeterli stok kalmadı.');
            }
        }
        $totals = store_cart_totals($items);
        $number = store_order_number($pdo);
        $shippingAddress = trim($address['address_line'] . "\n" . ($address['neighborhood'] ? $address['neighborhood'] . "\n" : '') . $address['district'] . ' / ' . $address['city'] . ($address['postal_code'] ? "\n" . $address['postal_code'] : ''));
        $order = $pdo->prepare('INSERT INTO pelish_orders (order_number,customer_id,address_id,recipient_name,recipient_phone,shipping_address,status,payment_method,payment_status,subtotal,cargo_total,grand_total,terms_accepted_at,kvkk_accepted_at,marketing_consent) VALUES (?, ?, ?, ?, ?, ?, "Yeni", "Kapıda Ödeme", "Bekliyor", ?, ?, ?, NOW(), NOW(), ?)');
        $order->execute([$number, $customer['id'], $address['id'], $address['recipient_name'], $address['phone'], $shippingAddress, $totals['subtotal'], $totals['cargo'], $totals['grand_total'], $marketingConsent ? 1 : 0]);
        $orderId = (int) $pdo->lastInsertId();
        $itemInsert = $pdo->prepare('INSERT INTO pelish_order_items (order_id,product_id,product_name,sku,unit_price,quantity) VALUES (?, ?, ?, ?, ?, ?)');
        $stockUpdate = $pdo->prepare('UPDATE pelish_products SET stock = stock - ? WHERE id = ? AND stock >= ?');
        foreach ($items as $item) {
            $stockUpdate->execute([(int) $item['quantity'], $item['product_id'], (int) $item['quantity']]);
            if ($stockUpdate->rowCount() !== 1) {
                throw new RuntimeException($item['name'] . ' stokta kalmadı.');
            }
            $itemInsert->execute([$orderId, $item['product_id'], $item['name'], $item['sku'], $item['price']['current'], $item['quantity']]);
        }
        $company = store_company($config);
        $documentInsert = $pdo->prepare('INSERT INTO pelish_order_documents (order_id,document_type,document_title,document_version,document_html,document_hash,approved_at) VALUES (?, ?, ?, "2026-08", ?, ?, NOW())');
        foreach (['pre_information', 'distance_sales'] as $type) {
            $document = store_checkout_document_html($type, $company, $customer, $address, $items, $totals, $number);
            $documentInsert->execute([$orderId, $type, $document['title'], $document['html'], hash('sha256', $document['html'])]);
        }
        $consent = $pdo->prepare('INSERT INTO pelish_customer_consents (customer_id,consent_type,is_granted,source,consent_text_version,ip_address,user_agent,granted_at,withdrawn_at) VALUES (?, "marketing_email_sms", ?, "checkout", "2026-08", ?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_granted=VALUES(is_granted),source=VALUES(source),consent_text_version=VALUES(consent_text_version),ip_address=VALUES(ip_address),user_agent=VALUES(user_agent),granted_at=VALUES(granted_at),withdrawn_at=VALUES(withdrawn_at)');
        $now = date('Y-m-d H:i:s');
        $consent->execute([$customer['id'], $marketingConsent ? 1 : 0, substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500), $marketingConsent ? $now : null, $marketingConsent ? null : $now]);
        $clearCart = $pdo->prepare('DELETE i FROM pelish_customer_cart_items i INNER JOIN pelish_customer_carts c ON c.id = i.cart_id WHERE c.customer_id = ?');
        $clearCart->execute([$customer['id']]);
        $pdo->commit();
        return ['id' => $orderId, 'number' => $number, 'totals' => $totals];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}
