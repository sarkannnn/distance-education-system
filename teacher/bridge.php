<?php
/**
 * Distant Təhsil - Müəllim Bridge/SSO (Ssenari 1)
 * 
 * TMİS platformasından avtomatik yönləndirmə:
 * TMİS → DistantEducationBridgeController → bridge_tokens → bu fayl
 * 
 * İstifadəçi TMİS-ə daxil olduqdan sonra "Distant Təhsil" bölməsinə
 * kliklədikdə avtomatik olaraq bu URL-ə yönləndirilir.
 * Token əsaslı doğrulama aparılır.
 */
require_once 'includes/auth.php';

$token = $_GET['token'] ?? null;

if (!$token) {
    header('Location: login.php');
    exit;
}

$auth = new Auth();

// Bridge vasitəsilə giriş
$result = $auth->loginViaBridge($token);

if ($result['success']) {
    header('Location: index.php');
    exit;
} else {
    $errorType = 'token_expired';
    if (strpos($result['message'], 'tapılmadı') !== false) {
        $errorType = 'user_not_found';
    }
    header('Location: login.php?error=' . $errorType);
    exit;
}
