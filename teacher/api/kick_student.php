<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['user_role'] !== 'instructor') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized (Role: ' . ($_SESSION['user_role'] ?? 'none') . ')']);
    exit;
}

$db = Database::getInstance();
$liveClassId = trim($_POST['live_class_id'] ?? $_GET['live_class_id'] ?? '');
$targetUserId = trim($_POST['user_id'] ?? $_GET['user_id'] ?? '');

// Robust check against empty or 'undefined' values
if (
    !$liveClassId || !$targetUserId ||
    strtolower($liveClassId) === 'undefined' || strtolower($targetUserId) === 'undefined' ||
    !is_numeric($liveClassId) || !is_numeric($targetUserId)
) {

    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => "Invalid parameters. Received Class: '$liveClassId', User: '$targetUserId'",
        'debug_post' => $_POST,
        'debug_get' => $_GET
    ]);
    exit;
}

$liveClassId = (int) $liveClassId;
$targetUserId = (int) $targetUserId;

try {
    // Set is_kicked = 1 and left_at = NOW() to stop attendance duration immediately
    // Generic Kick: Update LATEST record regardless of whether they are currently online or not
    $latest = $db->fetch(
        "SELECT id, left_at FROM live_attendance WHERE live_class_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1",
        [$liveClassId, $targetUserId]
    );

    if ($latest) {
        // Update the existing record. Preserve left_at if it's already set, otherwise set it to NOW()
        $db->query(
            "UPDATE live_attendance SET is_kicked = 1, left_at = IF(left_at IS NULL, NOW(), left_at) WHERE id = ?",
            [$latest['id']]
        );
    } else {
        // No prior record? Insert a "pre-banned" record
        $db->query(
            "INSERT INTO live_attendance (live_class_id, user_id, role, joined_at, left_at, is_kicked) VALUES (?, ?, 'student', NOW(), NOW(), 1)",
            [$liveClassId, $targetUserId]
        );
    }

    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $err = ob_get_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debug' => $err]);
}