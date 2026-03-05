<?php
/**
 * Teacher Session Debug - Müəllim sessiya yoxlama
 */
require_once 'includes/auth.php';

$auth = new Auth();

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'isLoggedIn' => $auth->isLoggedIn(),
    'session_id' => session_id(),
    'session_name' => session_name(),
    'logged_in' => $_SESSION['logged_in'] ?? 'NOT SET',
    'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
    'user_name' => $_SESSION['user_name'] ?? 'NOT SET',
    'user_role' => $_SESSION['user_role'] ?? 'NOT SET',
    'tmis_token' => isset($_SESSION['tmis_token']) ? substr($_SESSION['tmis_token'], 0, 20) . '...' : 'NOT SET',
    'tmis_expires' => $_SESSION['tmis_expires'] ?? 'NOT SET',
    'current_time' => time(),
    'token_status' => isset($_SESSION['tmis_expires'])
        ? (time() > $_SESSION['tmis_expires']
            ? 'EXPIRED (' . round((time() - $_SESSION['tmis_expires']) / 60) . ' dəq əvvəl)'
            : 'VALID (' . round(($_SESSION['tmis_expires'] - time()) / 60) . ' dəq qalıb)')
        : 'N/A',
    'session_keys' => array_keys($_SESSION ?? []),
    'cookies' => $_COOKIE,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
