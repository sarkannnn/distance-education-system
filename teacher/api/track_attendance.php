// USE TEACHER AUTH
require_once 'includes/auth.php';
// Database is already included in auth.php

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
http_response_code(403);
echo json_encode(['success' => false, 'message' => 'Unauthorized']);
exit;
}

$currentUser = $auth->getCurrentUser();
$userId = $currentUser['id'];
$role = $currentUser['role']; // 'student' or 'instructor'

// Get Input (Handle JSON and POST)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
$input = $_POST;
}

if (!$input || !isset($input['type']) || !isset($input['live_class_id'])) {
// Log for debugging
file_put_contents('../../uploads/attendance_debug.log', date('Y-m-d H:i:s') . " - Invalid Input: " . print_r($_POST,
true) . " / " . file_get_contents('php://input') . "\n", FILE_APPEND);

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid input']);
exit;
}

$type = $input['type'];
$liveClassIdInput = (int) $input['live_class_id'];
$db = Database::getInstance();

// Resolve real local ID (in case input is TMİS ID)
$liveClass = $db->fetch(
"SELECT id FROM live_classes WHERE id = ? OR tmis_session_id = ?",
[$liveClassIdInput, $liveClassIdInput]
);
$liveClassId = $liveClass ? $liveClass['id'] : $liveClassIdInput;

try {
// Find active session for this user/class
$activeSession = $db->fetch(
"SELECT id FROM live_attendance
WHERE live_class_id = ? AND user_id = ? AND left_at IS NULL
ORDER BY id DESC LIMIT 1",
[$liveClassId, $userId]
);

if ($type === 'join') {
if (!$activeSession) {
// New session
$db->query(
"INSERT INTO live_attendance (live_class_id, user_id, role, joined_at, last_heartbeat)
VALUES (?, ?, ?, NOW(), NOW())",
[$liveClassId, $userId, $role]
);
} else {
// Already active, just update heartbeat
$db->query("UPDATE live_attendance SET last_heartbeat = NOW() WHERE id = ?", [$activeSession['id']]);
}
} elseif ($type === 'heartbeat') {
if ($activeSession) {
$db->query("UPDATE live_attendance SET last_heartbeat = NOW() WHERE id = ?", [$activeSession['id']]);
} else {
// If heartbeat but no session found (weird edge case, maybe they rejoined silently), create one
$db->query(
"INSERT INTO live_attendance (live_class_id, user_id, role, joined_at, last_heartbeat)
VALUES (?, ?, ?, NOW(), NOW())",
[$liveClassId, $userId, $role]
);
}
} elseif ($type === 'leave') {
if ($activeSession) {
$db->query("UPDATE live_attendance SET left_at = NOW() WHERE id = ?", [$activeSession['id']]);
}
}

echo json_encode(['success' => true]);

} catch (Exception $e) {
http_response_code(500);
echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>