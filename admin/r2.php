<?php

declare(strict_types=1);

/**
 * Cloudflare R2 S3-compatible object uploader.
 * Credentials are intentionally read only from config.local.php on the server.
 */

function r2_settings(array $config): array
{
    $r2 = $config['r2'] ?? [];
    return [
        'account_id' => trim((string) ($r2['account_id'] ?? '')),
        'access_key_id' => trim((string) ($r2['access_key_id'] ?? '')),
        'secret_access_key' => trim((string) ($r2['secret_access_key'] ?? '')),
        'bucket' => trim((string) ($r2['bucket'] ?? '')),
        'public_base_url' => rtrim(trim((string) ($r2['public_base_url'] ?? '')), '/'),
        'key_prefix' => trim(trim((string) ($r2['key_prefix'] ?? 'products')), '/'),
    ];
}

function r2_is_configured(array $settings): bool
{
    foreach (['account_id', 'access_key_id', 'secret_access_key', 'bucket', 'public_base_url'] as $key) {
        if ($settings[$key] === '') {
            return false;
        }
    }
    return extension_loaded('curl');
}

function r2_encode_key(string $key): string
{
    return implode('/', array_map('rawurlencode', explode('/', $key)));
}

function r2_object_slug(string $value): string
{
    $value = strtr(mb_strtolower(trim($value), 'UTF-8'), ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u']);
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    return trim($value, '-') ?: 'pelish-urun';
}

function r2_signing_key(string $secret, string $date): string
{
    $dateKey = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
    $regionKey = hash_hmac('sha256', 'auto', $dateKey, true);
    $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
    return hash_hmac('sha256', 'aws4_request', $serviceKey, true);
}

function r2_request(array $settings, string $method, string $key, ?string $body = null, ?string $contentType = null): array
{
    if (!r2_is_configured($settings)) {
        throw new RuntimeException('R2 ayarları eksik. config.local.php içindeki r2 alanlarını tamamlayın.');
    }

    // Cloudflare'ın bucket endpoint'i: /<bucket>/<object-key>.
    // Bu, R2 kontrol panelinde verilen S3 API URL'siyle birebir aynıdır.
    $host = $settings['account_id'] . '.r2.cloudflarestorage.com';
    $canonicalUri = '/' . rawurlencode($settings['bucket']) . '/' . r2_encode_key($key);
    $payload = $body ?? '';
    $payloadHash = hash('sha256', $payload);
    $amzDate = gmdate('Ymd\THis\Z');
    $shortDate = gmdate('Ymd');
    $canonicalHeaders = 'host:' . $host . "\n" . 'x-amz-content-sha256:' . $payloadHash . "\n" . 'x-amz-date:' . $amzDate . "\n";
    $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
    if ($contentType !== null) {
        $canonicalHeaders = 'content-type:' . $contentType . "\n" . $canonicalHeaders;
        $signedHeaders = 'content-type;' . $signedHeaders;
    }
    $canonicalRequest = $method . "\n" . $canonicalUri . "\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
    $scope = $shortDate . '/auto/s3/aws4_request';
    $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
    $signature = hash_hmac('sha256', $stringToSign, r2_signing_key($settings['secret_access_key'], $shortDate));
    $authorization = 'AWS4-HMAC-SHA256 Credential=' . $settings['access_key_id'] . '/' . $scope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

    $headers = [
        'Host: ' . $host,
        'x-amz-content-sha256: ' . $payloadHash,
        'x-amz-date: ' . $amzDate,
        'Authorization: ' . $authorization,
    ];
    if ($contentType !== null) {
        $headers[] = 'Content-Type: ' . $contentType;
    }

    $curl = curl_init('https://' . $host . $canonicalUri);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($method === 'PUT') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
    }
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    if ($response === false || $status < 200 || $status >= 300) {
        $bodyText = is_string($response) ? trim(substr($response, $headerSize, 700)) : '';
        throw new RuntimeException('R2 isteği başarısız oldu (' . $status . '). ' . ($error ?: $bodyText));
    }
    return ['status' => $status, 'headers' => substr($response, 0, $headerSize), 'body' => substr($response, $headerSize)];
}

function r2_upload_image(array $settings, array $file, string $productName): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Yüklenecek görsel seçilmedi.');
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('Görsel dosyası yüklenirken hata oluştu.');
    }
    if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
        throw new RuntimeException('Görsel en fazla 8 MB olabilir.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Yalnızca geçerli JPG, PNG veya WebP görseller yüklenebilir.');
    }
    $prefix = $settings['key_prefix'] !== '' ? $settings['key_prefix'] . '/' : '';
    $key = $prefix . gmdate('Y/m') . '/' . r2_object_slug($productName) . '-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    $body = file_get_contents($file['tmp_name']);
    if ($body === false) {
        throw new RuntimeException('Yüklenen görsel okunamadı.');
    }
    r2_request($settings, 'PUT', $key, $body, $mime);
    return ['key' => $key, 'url' => $settings['public_base_url'] . '/' . r2_encode_key($key), 'mime' => $mime];
}

function r2_delete_object(array $settings, ?string $key): void
{
    if ($key === null || $key === '' || !r2_is_configured($settings)) {
        return;
    }
    r2_request($settings, 'DELETE', $key);
}
