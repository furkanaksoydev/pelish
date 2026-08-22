# pelish.co canlıya alma notu

1. Bu klasörün içeriğini hostingde `www.pelish.co` alan adının belge köküne yükleyin.
2. `admin/config.example.php` dosyasını `admin/config.local.php` adıyla kopyalayın ve yalnızca sunucuda veritabanı, R2 ve e-posta gönderici ayarlarını girin. Bu dosya sürüm kontrolüne dahil edilmez.
3. Tarayıcıda bir kez `/admin/setup.php` yolunu açın. Kurulum yalnızca şemayı ve başlangıç yönetici hesabını oluşturur; ürün, müşteri, kupon veya katalog örneği eklemez.
4. Cloudflare R2 custom domaininin DNS yayılımı tamamlandığında ürün görselleri `https://cdn.pelish.co/products/...` adresinde yayınlanır.
5. Hostingde PHP 8.0+, PDO MySQL, cURL, Fileinfo ve Apache `mod_rewrite` etkin olmalıdır.

`config.local.php` web erişimine kapatılmıştır. Bu dosyayı yalnızca güvenli dosya aktarımıyla yükleyin.
