<?php
/**
 * Debug: TMİS API-yə login olub Activities və Archive datanı yoxla
 */
require_once 'config/database.php';
require_once 'includes/tmis_api.php';

// TMİS-ə login ol
$loginResult = TmisApi::loginTeacher('serkan.m@ndu.edu.az', '20011511');

if (!$loginResult['success']) {
    file_put_contents(__DIR__ . '/debug_output.txt', "Login failed: " . ($loginResult['message'] ?? 'Unknown'));
    exit;
}

$token = $loginResult['data']['token'] ?? ($loginResult['data']['access_token'] ?? ($loginResult['token'] ?? null));
if (!$token) {
    file_put_contents(__DIR__ . '/debug_output.txt', "No token found");
    exit;
}

$output = "=== TOKEN ===\n" . substr($token, 0, 30) . "...\n\n";

// Activities çək
$activitiesResult = TmisApi::getActivities($token);
$output .= "=== TMİS ACTIVITIES ===\n";
$output .= json_encode($activitiesResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Archive çək
$archiveResult = TmisApi::getArchive($token);
$output .= "=== TMİS ARCHIVE ===\n";
$output .= json_encode($archiveResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

file_put_contents(__DIR__ . '/debug_output.txt', $output);
echo "Done! Check debug_output.txt\n";
