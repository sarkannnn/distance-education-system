<?php
/**
 * Edit Course API
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
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $specialization_id = intval($_POST['specialization_id'] ?? 0);
    $course_level = intval($_POST['course_level'] ?? 1);
    $lecture_count = intval($_POST['lecture_count'] ?? 16);
    $seminar_count = intval($_POST['seminar_count'] ?? 16);
    $total_lessons = $lecture_count + $seminar_count;
    $initial_students = intval($_POST['initial_students'] ?? 0);
    $weekly_days = isset($_POST['weekly_days']) ? implode(', ', $_POST['weekly_days']) : '';
    $start_time = trim($_POST['start_time'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($courseId <= 0 || empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Dərs ID və ya ad yanlışdır']);
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

        // Kəsişmə yoxlanışı (Conflict Check)
        if (!empty($weekly_days) && !empty($start_time)) {
            $newDays = explode(', ', $weekly_days);

            // Müəllimin digər aktiv dərslərini gətir (hazırki dərs xaric)
            $existingCourses = $db->fetchAll(
                "SELECT id, title, weekly_days, start_time FROM courses 
                 WHERE instructor_id = ? AND status = 'active' AND id != ?",
                [$instructor['id'], $courseId]
            );

            foreach ($existingCourses as $existing) {
                if ($existing['start_time'] == $start_time && !empty($existing['weekly_days'])) {
                    $existingDays = explode(', ', $existing['weekly_days']);
                    $intersect = array_intersect($newDays, $existingDays);

                    if (!empty($intersect)) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Xəta: Bu vaxtda ("' . implode(', ', $intersect) . ' - ' . $start_time . '") artıq "' . $existing['title'] . '" dərsi mövcuddur.'
                        ]);
                        exit;
                    }
                }
            }
        }

        $db->update('courses', [
            'title' => $title,
            'description' => $description,
            'category_id' => $category_id,
            'specialization_id' => $specialization_id,
            'course_level' => $course_level,
            'total_lessons' => $total_lessons,
            'lecture_count' => $lecture_count,
            'seminar_count' => $seminar_count,
            'initial_students' => $initial_students,
            'weekly_days' => $weekly_days,
            'start_time' => $start_time,
            'status' => $status
        ], 'id = :id', ['id' => $courseId]);

        echo json_encode(['success' => true, 'message' => 'Dərs uğurla yeniləndi']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Xəta baş verdi: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Yalnız POST sorğusu qəbul edilir']);
}
