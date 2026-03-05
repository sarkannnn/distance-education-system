<?php
/**
 * Delete Course API
 */

header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
requireInstructor();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $currentUser = $auth->getCurrentUser();

    // Müəllimin instructor_id-sini tap
    $instructor = $db->fetch(
        "SELECT id FROM instructors WHERE user_id = ?",
        [$currentUser['id']]
    );

    if (!$instructor) {
        $instructor = $db->fetch(
            "SELECT id FROM instructors WHERE email = ?",
            [$currentUser['email']]
        );
    }

    if (!$instructor) {
        echo json_encode(['success' => false, 'message' => 'Müəllim məlumatları tapılmadı']);
        exit;
    }

    $courseId = intval($_POST['course_id'] ?? 0);

    if ($courseId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dərs ID yanlışdır']);
        exit;
    }

    try {
        // Kursun həqiqətən bu müəllimə aid olduğunu yoxla
        $course = $db->fetch(
            "SELECT id FROM courses WHERE id = ? AND instructor_id = ?",
            [$courseId, $instructor['id']]
        );

        if (!$course) {
            echo json_encode(['success' => false, 'message' => 'Dərs tapılmadı və ya sizə aid deyil']);
            exit;
        }

        // Kursu sil (Xarici açar məhdudiyyətləri ON DELETE CASCADE olmalıdır, yoxsa digər cədvəllərdən də silməliyik)
        $db->delete('courses', 'id = ?', [$courseId]);

        echo json_encode(['success' => true, 'message' => 'Dərs uğurla silindi']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Xəta baş verdi: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Yalnız POST sorğusu qəbul edilir']);
}
