<?php
declare(strict_types=1);
require __DIR__ . '/store/bootstrap.php';
store_logout_customer();
store_flash('success', 'Güvenle çıkış yaptın.');
store_redirect('index.php');
