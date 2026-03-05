<?php
header('Content-Type: application/json');
require_once '../student/config/database.php';

$db = Database::getInstance();

try {
    // Tələbə sessiyasını yoxla (əgər tələbə panelidirsə)
    session_name('STUDENT_SESSION');
    @session_start();
    $studentId = $_SESSION['user_id'] ?? null;

    // Get active alerts that haven't expired AND whose associated live class is still 'live'
    $sql = "SELECT a.*, COALESCE(i.name, CONCAT(u.first_name, ' ', u.last_name)) as instructor_name,
                   c.title as course_title
            FROM live_alerts a
            LEFT JOIN instructors i ON a.instructor_id = i.id
            LEFT JOIN users u ON i.user_id = u.id
            LEFT JOIN courses c ON a.course_id = c.id
            WHERE (a.expires_at IS NULL OR a.expires_at > NOW())
            AND (a.course_id IS NULL OR EXISTS (
                SELECT 1 FROM live_classes lc 
                WHERE lc.course_id = a.course_id 
                AND lc.instructor_id = a.instructor_id 
                AND lc.status = 'live'
            ))";

    $params = [];
    if ($studentId) {
        // Əgər tələbə giriş edibsə, yalnız qlobal və ya öz fənlərinə aid olanları görsün
        $sql .= " AND (a.course_id IS NULL OR a.course_id IN (SELECT course_id FROM enrollments WHERE user_id = ?))";
        $params[] = $studentId;
    }

    $sql .= " ORDER BY a.created_at DESC LIMIT 5";
    $alerts = $db->fetchAll($sql, $params);

    echo json_encode(['success' => true, 'alerts' => $alerts]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
