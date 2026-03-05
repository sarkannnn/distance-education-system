<?php
/**
 * Courses API
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
        // Kursları al
        $userId = $currentUser['id'];

        $courses = $db->fetchAll("
            SELECT 
                c.id,
                c.title,
                c.description,
                c.total_lessons,
                c.status as course_status,
                i.title as instructor_title,
                i.name as instructor_name,
                cat.name as category,
                e.enrolled_date,
                e.completed_lessons,
                e.progress_percent,
                e.status as enrollment_status
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            JOIN instructors i ON c.instructor_id = i.id
            LEFT JOIN categories cat ON c.category_id = cat.id
            WHERE e.user_id = ?
            ORDER BY e.enrolled_date DESC
        ", [$userId]);

        jsonResponse(['success' => true, 'courses' => $courses]);
        break;

    case 'POST':
        // Kursa qeydiyyat
        $data = json_decode(file_get_contents('php://input'), true);
        $courseId = $data['course_id'] ?? null;

        if (!$courseId) {
            jsonResponse(['success' => false, 'message' => 'Kurs ID tələb olunur'], 400);
        }

        // Mövcud qeydiyyatı yoxla
        $existing = $db->fetch("
            SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?
        ", [$currentUser['id'], $courseId]);

        if ($existing) {
            jsonResponse(['success' => false, 'message' => 'Artıq bu kursa qeydiyyatdan keçmisiniz'], 400);
        }

        // Qeydiyyat yarat
        $enrollmentId = $db->insert('enrollments', [
            'user_id' => $currentUser['id'],
            'course_id' => $courseId,
            'status' => 'active'
        ]);

        jsonResponse(['success' => true, 'enrollment_id' => $enrollmentId]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}
