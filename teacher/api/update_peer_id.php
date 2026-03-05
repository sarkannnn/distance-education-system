<?php
/**
 * API to update Peer ID - AGNOSTIC VERSION (GET/POST)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once '../config/database.php';

// Həm POST, həm də GET-dən məlumatları qəbul et
$liveClassId = $_REQUEST['live_class_id'] ?? null;
$peerId = $_REQUEST['peer_id'] ?? null;

if ($liveClassId && $peerId) {
    try {
        $db = Database::getInstance();

        // Ensure 'started_at' column exists for lesson duration tracking
        try {
            $db->query("SELECT started_at FROM live_classes LIMIT 1");
        } catch (Exception $e) {
            $db->query("ALTER TABLE live_classes ADD COLUMN started_at DATETIME DEFAULT NULL");
        }

        // Ensure 'peer_server' column exists
        try {
            $db->query("SELECT peer_server FROM live_classes LIMIT 1");
        } catch (Exception $e) {
            $db->query("ALTER TABLE live_classes ADD COLUMN peer_server VARCHAR(50) DEFAULT 'local'");
        }

        $peerServer = $_REQUEST['server'] ?? 'local';

        // Update Peer ID, server and set started_at if not already set
        // Support both local ID and TMİS Session ID
        $db->query(
            "UPDATE live_classes 
             SET zoom_link = ?, 
                 peer_server = ?,
                 status = 'live',
                 started_at = IF(started_at IS NULL, NOW(), started_at) 
             WHERE id = ? OR tmis_session_id = ?",
            [$peerId, $peerServer, $liveClassId, $liveClassId]
        );

        echo json_encode(['success' => true, 'method' => $_SERVER['REQUEST_METHOD']]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing data', 'received' => $_REQUEST]);
}
