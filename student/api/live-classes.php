<?php
/**
 * Live Classes API
 */
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Giriş tələb olunur'], 401);
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Canlı dərsləri al
        $liveClasses = $db->fetchAll("
            SELECT 
                lc.*,
                c.title as course_title,
                CONCAT(i.title, ' ', i.name) as instructor_name,
                (SELECT COUNT(*) FROM live_class_participants WHERE live_class_id = lc.id) as participants
            FROM live_classes lc
            JOIN courses c ON lc.course_id = c.id
            JOIN instructors i ON lc.instructor_id = i.id
            WHERE lc.start_time >= CURDATE()
            ORDER BY lc.start_time ASC
        ");

        jsonResponse(['success' => true, 'live_classes' => $liveClasses]);
        break;

    case 'POST':
        // Canlı dərsə qoşul
        $data = json_decode(file_get_contents('php://input'), true);
        $liveClassId = $data['live_class_id'] ?? null;

        if (!$liveClassId) {
            jsonResponse(['success' => false, 'message' => 'Live class ID tələb olunur'], 400);
        }

        // Mövcud iştirakı yoxla
        $existing = $db->fetch("
            SELECT id FROM live_class_participants WHERE live_class_id = ? AND user_id = ?
        ", [$liveClassId, $currentUser['id']]);

        if (!$existing) {
            $db->insert('live_class_participants', [
                'live_class_id' => $liveClassId,
                'user_id' => $currentUser['id']
            ]);
        }

        jsonResponse(['success' => true, 'message' => 'Dərsə qoşuldunuz']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}
