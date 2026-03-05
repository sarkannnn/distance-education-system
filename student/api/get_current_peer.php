<?php
/**
 * API to get teacher's current dynamic Peer ID - Robust Version
 */
require_once '../config/database.php';
header('Content-Type: application/json');

$lessonId = $_GET['id'] ?? null;

if ($lessonId) {
    $db = Database::getInstance();
    // Yalnız ID-ə görə yoxla
    $lesson = $db->fetch("SELECT zoom_link, peer_server FROM live_classes WHERE id = ?", [$lessonId]);

    if ($lesson && !empty($lesson['zoom_link'])) {
        echo json_encode(['success' => true, 'peer_id' => $lesson['zoom_link'], 'server' => $lesson['peer_server'] ?? 'local']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Teacher not started yet']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID missing']);
}
