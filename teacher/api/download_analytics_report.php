<?php
/**
 * API: Analitika Hesabatını Yüklə (CSV)
 */
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
requireInstructor();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// Müəllimin instructor_id-sini tap
$instructor = null;
try {
    $instructor = $db->fetch(
        "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
        [$currentUser['id'], $currentUser['email']]
    );
} catch (Exception $e) {
    die("Səlahiyyət xətası");
}

if (!$instructor) {
    die("Müəllim tapılmadı");
}

try {
    // ============================================================
    // TMİS API-dən Course Stats çək (əgər varsa)
    // ============================================================
    $tmisToken = TmisApi::getToken();
    $courseRows = [];
    $tmisLoaded = false;

    if ($tmisToken) {
        try {
            $courseStatsResult = TmisApi::getAnalyticsCourseStats($tmisToken);

            if ($courseStatsResult['success'] && isset($courseStatsResult['data']) && is_array($courseStatsResult['data'])) {
                $tmisLoaded = true;

                foreach ($courseStatsResult['data'] as $cs) {
                    $courseRows[] = [
                        'title' => trim($cs['subject_name'] ?? 'Fənn'),
                        'instructor_name' => $currentUser['first_name'] . ' ' . $currentUser['last_name'],
                        'total_students' => $cs['all_students'] ?? 0,
                        'active_students' => $cs['active_students'] ?? 0,
                        'm_total' => $cs['planned_topics'] ?? 0,
                        'm_done' => $cs['completed_topics'] ?? 0,
                        's_total' => 0,
                        's_done' => 0,
                        'attendance' => round($cs['attendance_percent'] ?? 0),
                        'completion' => round($cs['progress_percent'] ?? 0),
                        'status' => $cs['status'] ?? 'Normal'
                    ];
                }
            }
        } catch (Exception $e) {
            error_log('TMİS CSV Report xətası: ' . $e->getMessage());
        }
    }

    // Fallback: lokal bazadan
    if (!$tmisLoaded) {
        $coursesData = $db->fetchAll(
            "SELECT c.id, c.title, c.total_lessons, c.initial_students, c.lecture_count, c.seminar_count,
                    (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.lesson_type = 'lecture' AND lc.status IN ('ended', 'completed')) as lecture_done,
                    (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.lesson_type = 'seminar' AND lc.status IN ('ended', 'completed')) as seminar_done,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as enrolled_count,
                    (SELECT COUNT(DISTINCT user_id) FROM live_attendance la 
                     WHERE la.role = 'student' AND la.live_class_id IN (SELECT id FROM live_classes WHERE course_id = c.id)) as active_student_count
             FROM courses c
             WHERE c.instructor_id = ? AND c.status = 'active'
             ORDER BY c.created_at DESC",
            [$instructor['id']]
        );

        foreach ($coursesData as $course) {
            $totalStudents = max(($course['initial_students'] ?? 0), ($course['enrolled_count'] ?? 0));
            $mDone = $course['lecture_done'] ?? 0;
            $sDone = $course['seminar_done'] ?? 0;
            $mTotal = $course['lecture_count'] ?? 0;
            $sTotal = $course['seminar_count'] ?? 0;

            $done = $mDone + $sDone;
            $total = ($mTotal + $sTotal) > 0 ? ($mTotal + $sTotal) : ($done > 0 ? $done : 1);

            $completion = min(100, round(($done / $total) * 100));
            $attendance = $totalStudents > 0 ? min(100, round(($course['active_student_count'] / $totalStudents) * 100)) : 0;

            $status = 'Normal';
            if ($attendance >= 80 && $completion >= 50)
                $status = 'Əla';
            elseif ($attendance >= 60 || $completion >= 30)
                $status = 'Yaxşı';
            elseif ($attendance < 30)
                $status = 'Kritik';

            $courseRows[] = [
                'title' => $course['title'],
                'instructor_name' => $currentUser['first_name'] . ' ' . $currentUser['last_name'],
                'total_students' => $totalStudents,
                'active_students' => $course['active_student_count'],
                'm_total' => $mTotal,
                'm_done' => $mDone,
                's_total' => $sTotal,
                's_done' => $sDone,
                'attendance' => $attendance,
                'completion' => $completion,
                'status' => $status
            ];
        }
    }

    // CSV Header-ləri
    $filename = "NDU_Analitika_Hesabati_" . date('Y-m-d_H-i') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    // BOM
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Başlıq sətiri
    fputcsv($output, ['Fənn Adı', 'Müəllim', 'Ümumi Tələbə', 'Aktiv Tələbə', 'Mühazirə (Plan)', 'Mühazirə (Keçilən)', 'Seminar (Plan)', 'Seminar (Keçilən)', 'Davamiyyət (%)', 'Proqres (%)', 'Status']);

    foreach ($courseRows as $row) {
        fputcsv($output, [
            $row['title'],
            $row['instructor_name'],
            $row['total_students'],
            $row['active_students'],
            $row['m_total'],
            $row['m_done'],
            $row['s_total'],
            $row['s_done'],
            $row['attendance'] . '%',
            $row['completion'] . '%',
            $row['status']
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    die("Hesabat yaradılarkən xəta baş verdi: " . $e->getMessage());
}
