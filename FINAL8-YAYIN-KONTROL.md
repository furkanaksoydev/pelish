# Pelish Final 8 · Yayın Kontrolü

Bu sürüm; vitrin, indirim filtreleri, ürün görsel/renk yönetimi, finans modülü ve iyzico görünürlük kriterlerini içerir.

## Yükleme öncesi

1. `admin/config.local.php` dosyasını sunucuda bırakın; bu dosya paketlenmez. Veritabanı, R2 ve e-posta ayarları burada kalır.
2. Paket içeriğini alan adının belge köküne yükleyin ve `/admin/setup.php` adresini bir kez açın. Kurulum; dört vitrin kartı ile `pelish_finance_purchases` ve `pelish_finance_payments` tablolarını ekler.
3. Kurulum tamamlandığında `admin/setup.php` kilitlenir. Yönetim panelinden **Ana Sayfa Vitrini** ekranına girip dört kartın ürününü seçin.
4. `https://www.pelish.co/odeme.php` üzerinden hem kapıda ödeme hem kartlı ödeme görünümünü kontrol edin.

## iyzico inceleme notu

Footer’da ve kartlı ödeme seçeneğinde iyzico’nun resmi logo paketinden alınan “iyzico ile Öde / Visa / Mastercard” varlıkları yer alır. Kart verileri Pelish veritabanına gönderilmez veya kaydedilmez.

Gerçek kart tahsilatını açmak için iyzico Merchant API anahtarları, callback URL’leri ve 3D Secure akışı ayrıca yapılandırılmalıdır. Bu olmadan kartlı seçenek kullanıcıya güvenli biçimde açık kabul edilmez; mevcut akış tahsilat oluşturmadan durur.

## Hukuki yayın verileri

Footer’da **Gizlilik Politikası**, **Teslimat ve İade** ve **Mesafeli Satış Sözleşmesi** görünür. Ödeme adımında Ön Bilgilendirme, Mesafeli Satış ve KVKK kutuları varsayılan olarak boş gelir; siparişe özel belge kopyaları sipariş kaydıyla saklanır.

Yayına geçmeden önce şirket unvanı, açık adres, vergi dairesi/no, MERSİS, KEP, iade operasyonu ve gerçek kargo sürelerini kendi şirket bilgilerinizle doldurun; nihai hukuki metinleri hukuk danışmanınızla onaylayın.
