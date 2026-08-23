# pelish.co canlıya alma notu

Yüklemeye hazır dosyalar proje dışındaki `C:\xampp\htdocs\pelish-canli-yukleme-final-v3` klasöründe, aynı içeriğin ZIP sürümü ise `C:\xampp\htdocs\pelish-canli-yukleme-final-v3.zip` dosyasındadır. Hostingin belge köküne yalnızca bu paketin **içeriğini** yükleyin.

1. `admin/config.example.php` dosyasını sunucuda `admin/config.local.php` adıyla kopyalayın; veritabanı, R2 ve e-posta gönderici ayarlarını sadece bu sunucu dosyasına yazın.
2. Bir kez `https://www.pelish.co/admin/setup.php` yolunu açın. Kurulum yalnızca şemayı ve başlangıç yönetici hesabını oluşturur; örnek ürün, müşteri, kupon veya katalog verisi eklemez.
3. İlk ürün görseli yüklemesini admin panelinden yaparak R2 bağlantısını doğrulayın. Görseller `https://cdn.pelish.co/products/...` yolundan sunulur.
4. Hostingde PHP 8.0+, PDO MySQL, cURL, Fileinfo ve Apache `mod_rewrite` etkin olmalıdır.

Paket; Git geçmişi, Node/Next/Vite dosyaları, testler, önbellekler, eski statik ekranlar, eski arşiv ve `config.local.php` dosyasını içermez. `.htaccess`, `config.local.php` dosyasını web erişimine ayrıca kapatır.
