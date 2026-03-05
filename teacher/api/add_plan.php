<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
requireInstructor();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $user = $auth->getCurrentUser();

    // Müəllimin instructor_id-sini tap
    $instructor = $db->fetch(
        "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
        [$user['id'], $user['email']]
    );

    if (!$instructor) {
        die("Müəllim məlumatları tapılmadı");
    }

    $course_id = $_POST['course_id'];
    $title = $_POST['title'];
    $type = $_POST['type'];

    try {
        if ($type === 'live') {
            // Live class creation
            $start_time = $_POST['start_time'];
            $duration = $_POST['duration'];
            $live_link = $_POST['live_link'];

            // Calculate end time
            $end_time = date('Y-m-d H:i:s', strtotime($start_time . " + $duration minutes"));

            $db->insert('live_classes', [
                'course_id' => $course_id,
                'title' => $title,
                'instructor_id' => $instructor['id'],
                'start_time' => $start_time,
                'end_time' => $end_time,
                'duration_minutes' => $duration,
                'zoom_link' => $live_link,
                'status' => 'live'
            ]);

            // Also add to schedule table so it shows up in dashboards
            $db->insert('schedule', [
                'user_id' => $user['id'],
                'course_id' => $course_id,
                'title' => $title,
                'start_time' => date('H:i:s', strtotime($start_time)),
                'end_time' => date('H:i:s', strtotime($end_time)),
                'schedule_date' => date('Y-m-d', strtotime($start_time)),
                'type' => 'live',
                'status' => 'in-progress'
            ]);

        } else {
            // Regular lesson creation
            $db->insert('lessons', [
                'course_id' => $course_id,
                'title' => $title,
                'content' => '', // Default empty content
                'lesson_order' => 1, // Default order
                'duration_minutes' => 60,
                'has_pdf' => ($type === 'material'),
                'has_video' => ($type === 'video')
            ]);
        }

        // Redirect back with success message (simplified for now)
        header('Location: ../plan.php?success=1');
    } catch (Exception $e) {
        header('Location: ../plan.php?error=' . urlencode($e->getMessage()));
    }
}
