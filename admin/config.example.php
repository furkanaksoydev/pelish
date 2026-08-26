<?php

return [
    'host' => 'localhost',
    'database' => 'your_database_name',
    'username' => 'your_database_user',
    'password' => 'your_database_password',
    'r2' => [
        'account_id' => 'your_cloudflare_account_id',
        'access_key_id' => 'your_r2_access_key_id',
        'secret_access_key' => 'your_r2_secret_access_key',
        'bucket' => 'pelish',
        'public_base_url' => 'https://cdn.pelish.co',
        'key_prefix' => 'products',
    ],
    // Kayıt doğrulama kodunu gönderen, kimliği doğrulanmış SMTP ayarları.
    'mail' => [
        'from_email' => '',
        'from_name' => 'pelish',
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_timeout' => 15,
    ],
    // Bu bilgileri canlıdaki config.local.php dosyasında eksiksiz doldurun.
    // Sipariş sözleşmeleri ve şirket künyesi bu alanlardan üretilir.
    'company' => [
        'legal_name' => '',
        'address' => '',
        'phone' => '',
        'email' => '',
        'mersis_no' => '',
        'tax_office' => '',
        'tax_no' => '',
        'kep_address' => '',
        'etbis_qr_url' => '',
    ],
    // Yalnızca 3D Secure destekli ödeme sağlayıcınızın resmî SDK/API
    // entegrasyonu tamamlandığında doldurun. Kart verileri bu dosyada veya
    // veritabanında tutulmaz.
    'payment' => [
        'provider' => '',
        'merchant_id' => '',
    ],
];
