<?php
/**
 * Send Notification API
 * Allows teachers to send individual or bulk notifications to students
 */
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Giriş tələb olunur']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$targetType = $data['target_type'] ?? 'bulk'; // 'individual' or 'bulk'
$targetId = $data['target_id'] ?? null; // user_id for individual, course_id for bulk
$title = $data['title'] ?? 'Müəllim Bildirişi';
$message = $data['message'] ?? '';
$type = $data['type'] ?? 'info'; // info, success, warning, error

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Mesaj boş ola bilməz']);
    exit;
}

try {
    if ($targetType === 'individual') {
        if (!$targetId) {
            echo json_encode(['success' => false, 'message' => 'Tələbə ID-si tapılmadı']);
            exit;
        }

        $db->query(
            "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())",
            [$targetId, $title, $message, $type]
        );
        echo json_encode(['success' => true, 'message' => 'Bildiriş göndərildi']);
    } else {
        // Bulk notification for a course
        if (!$targetId) {
            echo json_encode(['success' => false, 'message' => 'Kurs ID-si tapılmadı']);
            exit;
        }

        // Get instructor ID
        $instructor = $db->fetch("SELECT id FROM instructors WHERE user_id = ?", [$currentUser['id']]);

        // 1. Also add as a Live Alert if instructor found
        if ($instructor) {
            try {
                $db->query(
                    "INSERT INTO live_alerts (instructor_id, course_id, message, type, expires_at) 
                     VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))",
                    [$instructor['id'], $targetId, $message, $type]
                );
            } catch (Exception $e) {
                // Ignore alert errors, continue with standard notifications
            }
        }

        // 2. Get all students enrolled in this course for standard notifications
        $students = $db->fetchAll(
            "SELECT user_id FROM enrollments WHERE course_id = ?",
            [$targetId]
        );

        foreach ($students as $student) {
            $db->query(
                "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())",
                [$student['user_id'], $title, $message, $type]
            );
        }

        echo json_encode(['success' => true, 'message' => count($students) . ' tələbəyə bildiriş göndərildi']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
