<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
requireInstructor();

if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_POST)) {
    $db = Database::getInstance();
    $currentUser = $auth->getCurrentUser();

    // Find instructor_id
    $instructor = $db->fetch(
        "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
        [$currentUser['id'], $currentUser['email']]
    );

    if (!$instructor) {
        echo json_encode(['success' => false, 'message' => 'Müəllim tapılmadı']);
        exit;
    }

    $message = trim($_POST['message'] ?? '');
    $type = $_POST['type'] ?? 'info';
    $course_id = isset($_POST['course_id']) && $_POST['course_id'] != '' ? intval($_POST['course_id']) : null;
    $duration = intval($_POST['duration'] ?? 15); // minutes

    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Mesaj boş ola bilməz']);
        exit;
    }

    try {
        // Timezone problemin qarşısını almaq üçün MySQL-in öz vaxtından istifadə edirik
        $db->query(
            "INSERT INTO live_alerts (instructor_id, course_id, message, type, expires_at) 
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))",
            [$instructor['id'], $course_id, $message, $type, $duration]
        );

        echo json_encode(['success' => true, 'message' => 'Bildiriş uğurla yayındı']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Xəta: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Yalnız POST qəbul edilir']);
}
