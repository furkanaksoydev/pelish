# pelish.co canlıya alma notu · finalv7

Yüklemeye hazır dosyalar proje dışındaki `C:\xampp\htdocs\pelish-canli-yukleme-final-v7` klasöründe, aynı içeriğin ZIP sürümü ise `C:\xampp\htdocs\pelish-canli-yukleme-final-v7.zip` dosyasındadır. Hostingin belge köküne yalnızca bu paketin **içeriğini** yükleyin.

1. `admin/config.example.php` dosyasını sunucuda `admin/config.local.php` adıyla kopyalayın. Veritabanı, R2, SMTP, şirket künyesi ve gerektiğinde ödeme sağlayıcısı ayarlarını yalnızca bu dosyaya girin.
2. Bir kez `https://www.pelish.co/admin/setup.php` yolunu açın. Kurulum; boş şemayı, müşteri/adres/izin/sipariş sözleşmesi/vitrin tablolarını ve başlangıç yönetici hesabını oluşturur. Örnek ürün veya müşteri eklemez. Başlangıç yönetici hesabı oluşunca bu uç nokta 403 ile kilitlenir.
3. `https://www.pelish.co/admin/login.php` üzerinden panele girin; ilk ürün görseli yüklemesiyle R2 erişimini doğrulayın. Görseller `https://cdn.pelish.co/products/...` yolundan sunulmalıdır.
4. `company` alanındaki ticari unvan, adres, MERSİS, vergi, KEP ve varsa `etbis_qr_url` değerlerini gerçek bilgilerinizle doldurun. Yasal metinlerde "Henüz tanımlanmadı" kalmamalıdır.
5. Kartlı ödemeyi açmadan önce yalnızca 3D Secure destekleyen bir ödeme kuruluşunun resmî SDK/API entegrasyonunu, hesabınıza ait canlı anahtarlarla tamamlayın. Bu paket kart numarası, son kullanım tarihi ve CVV bilgisini form gönderiminde ya da veritabanında tutmaz; kapıda ödeme doğrudan kullanılabilir.
6. Hostingde PHP 8.0+, PDO MySQL, cURL, Fileinfo ve Apache `mod_rewrite` etkin olmalıdır. `admin/config.local.php` paket içinde yoktur ve hem kök hem admin `.htaccess` dosyası tarafından web erişimine kapatılır.

Paket; Git geçmişi, Node/Next/Vite dosyaları, testler, önbellekler, eski statik ekranlar, eski arşiv ve tüm gizli yerel yapılandırmaları içermez.
