<?php
/**
 * Get Live Attendance API
 * Returns list of attendees for a specific live class
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, will send as JSON

try {
    require_once '../includes/auth.php';
    require_once '../config/database.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Include error: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$liveClassId = $_GET['id'] ?? null;
if (!$liveClassId) {
    echo json_encode(['success' => false, 'message' => 'Missing live class ID']);
    exit;
}

$db = Database::getInstance();

// Ensure columns exist
try {
    $db->query("SELECT is_kicked FROM live_attendance LIMIT 1");
} catch (Exception $e) {
    try {
        $db->query("ALTER TABLE live_attendance ADD COLUMN is_kicked TINYINT(1) DEFAULT 0");
    } catch (Exception $e2) {
    }
}

try {
    $db->query("SELECT peer_id FROM live_attendance LIMIT 1");
} catch (Exception $e) {
    try {
        $db->query("ALTER TABLE live_attendance ADD COLUMN peer_id VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e2) {
    }
}

// 1. Get Course ID first - Support both local ID and TMİS Session ID
$liveClass = $db->fetch(
    "SELECT id, course_id, instructor_id FROM live_classes WHERE id = ? OR tmis_session_id = ?",
    [$liveClassId, $liveClassId]
);

// Fallback: Əgər id/tmis_session_id ilə tapılmadısa, course_id ilə aktiv dərsi axtar
if (!$liveClass) {
    $subjectId = $_GET['subject_id'] ?? null;
    if ($subjectId) {
        $liveClass = $db->fetch(
            "SELECT id, course_id, instructor_id FROM live_classes WHERE course_id = ? AND status = 'live' ORDER BY id DESC LIMIT 1",
            [$subjectId]
        );
    }
}

if (!$liveClass) {
    echo json_encode(['success' => false, 'message' => 'Live class not found']);
    exit;
}

// Real internal ID to be used for further local queries
$internalId = $liveClass['id'];
$courseId = $liveClass['course_id'];
$instructorId = $liveClass['instructor_id'];

// 3. Get Full Student Roster from TMIS
$tmisToken = TmisApi::getToken();
$roster = [];
if ($tmisToken && $courseId > 0) {
    try {
        $details = TmisApi::getSubjectDetails($tmisToken, (int) $courseId);
        if ($details['success'] && isset($details['data']['students']) && is_array($details['data']['students'])) {
            $sidMap = [];
            foreach ($details['data']['students'] as $s) {
                $id = $s['id'] ?? ($s['student_id'] ?? 0);
                if (!$id)
                    continue;

                $roster[$id] = [
                    'userId' => $id,
                    'name' => ($s['last_name'] ?? ($s['surname'] ?? '')) . ' ' . ($s['first_name'] ?? ($s['name'] ?? '')) . (!empty($s['father_name']) ? ' ' . $s['father_name'] : ''),
                    'role' => 'student',
                    'is_online' => false,
                    'is_kicked' => 0,
                    'peer_id' => null,
                    'joined_at' => null
                ];

                if (!empty($s['student_id'])) {
                    $sidMap[$s['student_id']] = $id;
                }
            }
        }
    } catch (Exception $e) {
        error_log('Get Live Attendance TMIS Roster Error: ' . $e->getMessage());
    }
}

// Fallback to local enrollment ONLY IF roster is still empty
if (empty($roster)) {
    $localRoster = $db->fetchAll("
        SELECT u.id as user_id, CONCAT(u.first_name, ' ', u.last_name) as full_name, u.role as user_role
        FROM users u
        JOIN enrollments e ON u.id = e.user_id
        WHERE e.course_id = ?
    ", [$courseId]);

    foreach ($localRoster as $s) {
        $roster[$s['user_id']] = [
            'userId' => $s['user_id'],
            'name' => $s['full_name'],
            'role' => $s['user_role'],
            'is_online' => false,
            'is_kicked' => 0,
            'peer_id' => null,
            'joined_at' => null
        ];
    }
}

$totalEnrolled = count($roster);

// 4. Get active attendees (anyone who ever joined)
$attendees = $db->fetchAll(
    "SELECT la.id AS att_id, la.user_id AS att_user_id, la.joined_at AS att_joined_at, 
            la.is_kicked AS att_is_kicked, la.peer_id AS att_peer_id, la.left_at AS att_left_at, 
            la.last_heartbeat AS att_last_heartbeat,
            CONCAT(u.first_name, ' ', u.last_name) as full_name, u.role as user_role,
            (la.left_at IS NULL AND la.last_heartbeat > DATE_SUB(NOW(), INTERVAL 35 SECOND) AND la.is_kicked = 0) as is_online_db
     FROM live_attendance la
     LEFT JOIN users u ON la.user_id = u.id
     INNER JOIN (
        SELECT user_id, MAX(id) as max_id
        FROM live_attendance
        WHERE live_class_id = ?
        GROUP BY user_id
     ) latest ON la.id = latest.max_id
     WHERE la.live_class_id = ?
     ORDER BY (u.role = 'instructor') DESC, is_online_db DESC, la.joined_at ASC",
    [$internalId, $internalId]
);

$onlineCount = 0;
$finalAttendees = [];

// Track who we've already added from roster
$processedUserIds = [];

// First add instructor if attending
foreach ($attendees as $att) {
    if ($att['user_role'] === 'instructor') {
        $finalAttendees[] = [
            'id' => $att['att_id'],
            'user_id' => $att['att_user_id'],
            'userId' => $att['att_user_id'],
            'name' => $att['full_name'] ?: 'Müəllim',
            'role' => 'instructor',
            'is_online' => (bool) $att['is_online_db'],
            'peer_id' => $att['att_peer_id'],
            'is_kicked' => (int) $att['att_is_kicked'],
            'joined_at' => date('H:i', strtotime($att['att_joined_at']))
        ];
    } else {
        // Find matching ID in roster (might be id or student_id)
        $idToUpdate = $att['att_user_id'];
        if (!isset($roster[$idToUpdate]) && !empty($sidMap) && isset($sidMap[$idToUpdate])) {
            $idToUpdate = $sidMap[$idToUpdate];
        }

        // Sync student status into roster
        if (isset($roster[$idToUpdate])) {
            $roster[$idToUpdate]['is_online'] = (bool) $att['is_online_db'];
            $roster[$idToUpdate]['is_kicked'] = (int) $att['att_is_kicked'];
            $roster[$idToUpdate]['peer_id'] = $att['att_peer_id'];
            $roster[$idToUpdate]['joined_at'] = date('H:i', strtotime($att['att_joined_at']));

            if ($att['is_online_db'])
                $onlineCount++;
            $processedUserIds[] = $idToUpdate;
        } else {
            // Student attending but NOT in roster. Add directly.
            $finalAttendees[] = [
                'userId' => $att['att_user_id'],
                'name' => $att['full_name'] ?: 'Naməlum Tələbə (ID: ' . $att['att_user_id'] . ')',
                'role' => 'student',
                'is_online' => (bool) $att['is_online_db'],
                'is_kicked' => (int) $att['att_is_kicked'],
                'peer_id' => $att['att_peer_id'],
                'joined_at' => date('H:i', strtotime($att['att_joined_at']))
            ];
            if ($att['is_online_db'])
                $onlineCount++;
        }
    }
}

// Merge ALL roster students into final list (their status was updated in the previous loop)
foreach ($roster as $r) {
    $finalAttendees[] = $r;
}

echo json_encode([
    'success' => true,
    'online_count' => $onlineCount,
    'total_count' => $totalEnrolled,
    'attendees' => $finalAttendees
]);
?>