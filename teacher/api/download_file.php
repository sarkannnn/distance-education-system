<?php
/**
 * Download Proxy - Arxiv fayllarını yükləmək üçün proxy
 * 
 * TMİS serverindən gələn faylları cross-origin problem olmadan yükləyir.
 * Həm lokal fayllar, həm də remote URL-lər üçün işləyir.
 */

require_once '../includes/auth.php';

$auth = new Auth();
requireInstructor();

$url = $_GET['url'] ?? '';
$filename = $_GET['filename'] ?? 'download';

if (empty($url)) {
    http_response_code(400);
    echo 'URL tələb olunur';
    exit;
}

// Təhlükəsizlik: Yalnız icazəli domenlərə yönləndir
$allowedDomains = ['tmis.ndu.edu.az', 'localhost', '127.0.0.1'];
$parsedUrl = parse_url($url);
$host = $parsedUrl['host'] ?? '';

// Lokal fayl yoxlaması
$isLocal = false;
if (strpos($url, 'http') !== 0) {
    // Nisbi yol - lokal fayldır
    $localPath = realpath(__DIR__ . '/../' . ltrim($url, '/'));
    if (!$localPath) {
        $localPath = realpath(__DIR__ . '/../../' . ltrim($url, '/'));
    }
    if ($localPath && file_exists($localPath)) {
        $isLocal = true;
    }
}

if ($isLocal && $localPath) {
    // Lokal fayl
    $mime = mime_content_type($localPath);
    $fileSize = filesize($localPath);

    // Fayl adını URL-dən çıxar
    if ($filename === 'download') {
        $filename = basename($localPath);
    }

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');

    readfile($localPath);
    exit;
}

// Remote URL
if (!in_array($host, $allowedDomains) && !empty($host)) {
    http_response_code(403);
    echo 'Bu domendən yükləmə icazə verilmir: ' . htmlspecialchars($host);
    exit;
}

// TMİS token-i əlavə et
$tmisToken = $_SESSION['tmis_token'] ?? '';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 300);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

if (!empty($tmisToken)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $tmisToken
    ]);
}

// Əvvəlcə HEAD sorğusu ilə faylın tipini öyrən
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_exec($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode >= 400) {
    http_response_code($httpCode);
    echo 'Fayl yüklənə bilmədi (HTTP ' . $httpCode . ')';
    curl_close($ch);
    exit;
}

// Fayl adını təyin et
if ($filename === 'download') {
    $pathParts = pathinfo($parsedUrl['path'] ?? '');
    $filename = ($pathParts['filename'] ?? 'video') . '.' . ($pathParts['extension'] ?? 'mp4');
}

// İndi faylı yüklə
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
if ($contentLength > 0) {
    header('Content-Length: ' . (int) $contentLength);
}
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Birbaşa çıxışa yaz (RAM-da saxlamadan)
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
    echo $data;
    flush();
    return strlen($data);
});

curl_exec($ch);
curl_close($ch);
