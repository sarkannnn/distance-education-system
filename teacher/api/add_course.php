<?php
/**
 * Add New Course API
 * Yeni dərs əlavə etmək üçün API
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

    // Əgər user_id ilə tapılmadısa, email ilə axtar
    if (!$instructor) {
        $instructor = $db->fetch(
            "SELECT id FROM instructors WHERE email = ?",
            [$currentUser['email']]
        );
    }

    if (!$instructor) {
        echo json_encode([
            'success' => false,
            'message' => 'Müəllim məlumatları tapılmadı'
        ]);
        exit;
    }

    // Form məlumatlarını al
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $specialization_id = intval($_POST['specialization_id'] ?? 0);
    $course_level = intval($_POST['course_level'] ?? 1);
    $lecture_count = intval($_POST['lecture_count'] ?? 16);
    $seminar_count = intval($_POST['seminar_count'] ?? 16);
    $total_lessons = $lecture_count + $seminar_count; // Cəmi dərs sayı
    $status = $_POST['status'] ?? 'active';

    // Validasiya
    if (empty($title)) {
        echo json_encode([
            'success' => false,
            'message' => 'Dərs adı mütləqdir'
        ]);
        exit;
    }

    if ($category_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Kateqoriya seçilməlidir'
        ]);
        exit;
    }

    if ($specialization_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'İxtisas seçilməlidir'
        ]);
        exit;
    }

    if ($course_level < 1 || $course_level > 5) {
        echo json_encode([
            'success' => false,
            'message' => 'Kurs 1-5 arası olmalıdır'
        ]);
        exit;
    }

    // Statusu yoxla
    $validStatuses = ['active', 'inactive', 'completed', 'draft'];
    if (!in_array($status, $validStatuses)) {
        $status = 'active';
    }

    try {
        // Get additional course info
        $initial_students = intval($_POST['initial_students'] ?? 0);
        $weekly_days = isset($_POST['weekly_days']) ? implode(', ', $_POST['weekly_days']) : '';
        $start_time = trim($_POST['start_time'] ?? '');

        // Kəsişmə yoxlanışı (Conflict Check)
        if (!empty($weekly_days) && !empty($start_time)) {
            $newDays = explode(', ', $weekly_days);

            // Müəllimin digər aktiv dərslərini gətir
            $existingCourses = $db->fetchAll(
                "SELECT title, weekly_days, start_time FROM courses 
                 WHERE instructor_id = ? AND status = 'active'",
                [$instructor['id']]
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

        // Insert new course
        $insertData = [
            'title' => $title,
            'description' => $description,
            'instructor_id' => $instructor['id'],
            'category_id' => $category_id,
            'specialization_id' => $specialization_id,
            'course_level' => $course_level,
            'total_lessons' => $total_lessons,
            'lecture_count' => $lecture_count,
            'seminar_count' => $seminar_count,
            'status' => $status,
            'initial_students' => $initial_students,
            'weekly_days' => $weekly_days,
            'start_time' => $start_time
        ];
        $courseId = $db->insert('courses', $insertData);

        // --- AVTOMATİK QEYDİYYAT MƏNTİQİ ---
        // Bu ixtisasda və bu kurs səviyyəsində (year) olan tələbələri tap
        $matchingStudents = $db->fetchAll(
            "SELECT id FROM users WHERE role = 'student' AND (specialization_id = ? AND course_level = ?)",
            [$specialization_id, $course_level]
        );

        $autoEnrolledCount = 0;
        foreach ($matchingStudents as $student) {
            // Əgər artıq qeydiyyatda deyilsə, əlavə et
            $exists = $db->fetch(
                "SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?",
                [$student['id'], $courseId]
            );

            if (!$exists) {
                $db->insert('enrollments', [
                    'user_id' => $student['id'],
                    'course_id' => $courseId,
                    'status' => 'active',
                    'enrolled_date' => date('Y-m-d H:i:s')
                ]);
                $autoEnrolledCount++;
            }
        }
        // -----------------------------------

        echo json_encode([
            'success' => true,
            'message' => "Dərs uğurla əlavə edildi. {$autoEnrolledCount} tələbə avtomatik qeydiyyata alındı.",
            'course_id' => $courseId,
            'auto_enrolled' => $autoEnrolledCount
        ]);

        // 2. Canlı bildiriş (Alert) yayınla ki, hər kəs yeni dərsi görsün
        $instructorName = $currentUser['first_name'] . ' ' . $currentUser['last_name'];
        $db->insert('live_alerts', [
            'instructor_id' => $instructor['id'],
            'course_id' => null, // Qlobal olsun
            'message' => "Yeni kurs əlavə edildi: {$title} (Müəllim: {$instructorName})",
            'type' => 'success',
            'expires_at' => date('Y-m-d H:i:s', strtotime("+12 hours"))
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Xəta baş verdi: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Yalnız POST sorğusu qəbul edilir'
    ]);
}
