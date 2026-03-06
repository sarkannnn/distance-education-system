<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$user = $auth->getCurrentUser();
if (!$user) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}
$userId = $user['id'];
$role = $user['role'];

if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$liveClassIdInput = (int) $_GET['id'];
$db = Database::getInstance();

try {
    // Resolve real local ID (in case input is TMİS ID)
    $liveClass = $db->fetch(
        "SELECT id FROM live_classes WHERE id = ? OR tmis_session_id = ?",
        [$liveClassIdInput, $liveClassIdInput]
    );
    $liveClassId = $liveClass ? $liveClass['id'] : $liveClassIdInput;

    $activeSession = $db->fetch(
        "SELECT id FROM live_attendance WHERE live_class_id = ? AND user_id = ? AND left_at IS NULL ORDER BY id DESC LIMIT 1",
        [$liveClassId, $userId]
    );

    $peerId = $_GET['peer_id'] ?? null;
    $action = $_GET['action'] ?? 'heartbeat';

    if ($activeSession) {
        if ($action === 'leave') {
            $db->query("UPDATE live_attendance SET left_at = NOW() WHERE id = ?", [$activeSession['id']]);
        } else {
            $updateQuery = "UPDATE live_attendance SET last_heartbeat = NOW()";
            $params = [];
            if ($peerId) {
                $updateQuery .= ", peer_id = ?";
                $params[] = $peerId;
            }
            $updateQuery .= " WHERE id = ?";
            $params[] = $activeSession['id'];
            $db->query($updateQuery, $params);
        }
    } else if ($action !== 'leave') {
        $db->query(
            "INSERT INTO live_attendance (live_class_id, user_id, role, joined_at, last_heartbeat, peer_id) VALUES (?, ?, ?, NOW(), NOW(), ?)",
            [$liveClassId, $userId, $role, $peerId]
        );
    }
} catch (Exception $e) {
    // Do nothing on error for heartbeat
}

// Return 1x1 transparent GIF since it's loaded as an image
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
exit;
