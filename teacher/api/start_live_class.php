<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/tmis_api.php';

$auth = new Auth();
requireInstructor();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $user = $auth->getCurrentUser();

    // Müəllimin instructor_id-sini tapmaq (Lokal mövcud deyilsə istifadəçi ID-si)
    $instructor = $db->fetch(
        "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
        [$user['id'], $user['email']]
    );
    // Əgər instructor tapılmadısa, onu instructors cədvəlinə əlavə et
    if (!$instructor) {
        try {
            $insId = $db->insert('instructors', [
                'user_id' => $user['id'],
                'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                'email' => $user['email'] ?? ''
            ]);
            $instructorId = $insId;
        } catch (Exception $e) {
            $instructorId = $user['id'] ?? 1;
        }
    } else {
        $instructorId = $instructor['id'];
    }

    // fallback to user ID (TMIS ID) if no local instructor found
    if (!$instructorId) {
        $instructorId = $user['id'] ?? 1;
    }

    $course_id = $_POST['course_id'] ?? null;
    $topic_name = $_POST['title'] ?? $_POST['topic_name'] ?? null;
    $lesson_type = $_POST['lesson_type'] ?? 'lecture'; // lecture, seminar, or laboratory

    if (!$course_id || !$topic_name) {
        echo json_encode(['success' => false, 'message' => 'Məlumatlar tam deyil']);
        exit;
    }

    $tmis_subject_id = $course_id;

    // 5. TMİS subject məlumatlarını gətir
    $tmisSubject = null;
    if ($tmis_subject_id) {
        $tmisSubject = $db->fetch(
            "SELECT * FROM subjects WHERE id = ?",
            [$tmis_subject_id]
        ); // Əgər lokalda tapılsa, tapılır, yoxsa eybi yox.
    }

    // Default Zoom linki
    $zoom_link = "https://zoom.us/j/000000000";

    $now = date('Y-m-d H:i:s');
    $end_time = date('Y-m-d H:i:s', strtotime("+90 minutes")); // Standart 90 dəqiqə

    // Limitləri yoxla (Əvvəlcə lokalda, sonra TMİS API-dən)
    $courseInfo = $db->fetch("SELECT lecture_count, seminar_count, instructor_id FROM courses WHERE id = ?", [$course_id]);

    $limit = 999; // Default limitsiz
    if ($courseInfo) {
        if ($lesson_type === 'lecture') {
            $limit = $courseInfo['lecture_count'] ?? 15;
        } elseif ($lesson_type === 'seminar') {
            $limit = $courseInfo['seminar_count'] ?? 15;
        } else {
            $limit = $courseInfo['laboratory_count'] ?? 15;
        }
    } else {
        // Lokal courses cədvəlində yoxdursa TMİS API-dən subject detallarını al
        $tmisTokenCheck = TmisApi::getToken();
        if ($tmisTokenCheck) {
            try {
                $subjectDetail = TmisApi::getSubjectDetails($tmisTokenCheck, (int) $course_id);
                if ($subjectDetail['success'] && isset($subjectDetail['data'])) {
                    $sd = $subjectDetail['data'];
                    if ($lesson_type === 'lecture') {
                        $limit = $sd['subject_lecture_time'] ?? 999;
                    } elseif ($lesson_type === 'seminar') {
                        $limit = $sd['subject_seminar_time'] ?? 999;
                    } else {
                        $limit = $sd['laboratory_count'] ?? ($sd['subject_lab_time'] ?? 999);
                    }
                }
            } catch (Exception $e) {
                // Xəta olsa limit yoxlanmır
            }
        }
    }

    $currentTypeCounts = $db->fetch(
        "SELECT COUNT(*) as cnt FROM live_classes WHERE course_id = ? AND lesson_type = ? AND status IN ('ended', 'completed')",
        [$course_id, $lesson_type]
    );

    $current = $currentTypeCounts['cnt'] ?? 0;

    if ($limit > 0 && $limit < 999 && $current >= $limit) {
        $typeName = 'Dərs';
        if ($lesson_type === 'lecture')
            $typeName = 'Mühazirə';
        elseif ($lesson_type === 'seminar')
            $typeName = 'Seminar';
        elseif ($lesson_type === 'laboratory')
            $typeName = 'Laboratoriya';

        echo json_encode(['success' => false, 'message' => "{$typeName} limiti ({$limit}) artıq dolub. Daha çox {$typeName} başlada bilməzsiniz."]);
        exit;
    }

    $lesson_number = $current + 1;

    try {
        // Instructor ID-ni yoxla
        $finalInstructorId = $instructorId;
        if ($courseInfo && !empty($courseInfo['instructor_id'])) {
            $finalInstructorId = $courseInfo['instructor_id'];
        }

        // Ensure the course exists in local database for joins
        $courseCheck = $db->fetch("SELECT id FROM courses WHERE tmis_subject_id = ? OR id = ?", [$course_id, $course_id]);
        if (!$courseCheck) {
            $db->insert('courses', [
                'id' => $course_id,
                'tmis_subject_id' => $course_id,
                'title' => $_POST['course_name'] ?? 'Fənn',
                'instructor_id' => $instructorId,
                'course_level' => $_POST['course_level'] ?? 1,
                'status' => 'active'
            ]);
        }

        // Fetch metadata for redundant storage
        // 1. Instructor metadata
        $insMeta = $db->fetch("SELECT name, title, faculty, specialty, course_level FROM instructors WHERE id = ?", [$finalInstructorId]);

        // 2. Course metadata (prioritize subjects table, then courses table)
        // Search by both course_id and tmis_subject_id to be safe
        $subj = $db->fetch("SELECT subject_name FROM subjects WHERE id = ? OR id = (SELECT tmis_subject_id FROM courses WHERE id = ? LIMIT 1)", [$course_id, $course_id]);
        $courseObj = $db->fetch("SELECT title FROM courses WHERE id = ? OR tmis_subject_id = ?", [$course_id, $course_id]);

        $sName = ($subj && !empty($subj['subject_name'])) ? $subj['subject_name'] : ($courseObj['title'] ?? ($_POST['course_name'] ?? 'Fənn'));
        $iName = ($insMeta && !empty($insMeta['name'])) ? $insMeta['name'] : ($_SESSION['user_name'] ?? 'Müəllim');
        $iTitle = ($insMeta && !empty($insMeta['title'])) ? $insMeta['title'] : ($_SESSION['user_academic_title'] ?? 'Müəllim');

        // 1. Canlı dərslər cədvəlinə əlavə et (Status: LIVE)
        $live_class_id = $db->insert('live_classes', [
            'course_id' => $course_id,
            'tmis_subject_id' => $course_id, // TMİS subject ID-ni saxla
            'title' => $topic_name,
            'lesson_type' => $lesson_type,
            'lesson_number' => $lesson_number,
            'instructor_id' => $finalInstructorId,
            'start_time' => $now,
            'started_at' => $now,
            'end_time' => $end_time,
            'duration_minutes' => 0, // Başlanğıcda 0, dərs bitəndə real müddət yazılacaq
            'status' => 'live',
            // Redundant metadata storage prioritization
            'instructor_name' => $iName,
            'instructor_title' => $iTitle,
            'subject_name' => $sName,
            'faculty_name' => (!empty($_POST['faculty_name'])) ? $_POST['faculty_name'] : (($insMeta && !empty($insMeta['faculty'])) ? $insMeta['faculty'] : ($user['faculty'] ?? '')),
            'specialty_name' => (!empty($_POST['specialty_name'])) ? $_POST['specialty_name'] : (($insMeta && !empty($insMeta['specialty'])) ? $insMeta['specialty'] : ($user['specialty'] ?? '')),
            'group_name' => $user['group'] ?? '',
            'course_level' => (!empty($_POST['course_level']) && $_POST['course_level'] !== '-') ? $_POST['course_level'] : (($insMeta && !empty($insMeta['course_level'])) ? $insMeta['course_level'] : '-')
        ]);

        // 2. Cədvələ əlavə et (Status: in-progress)
        $db->insert('schedule', [
            'user_id' => $user['id'],
            'course_id' => $course_id,
            'live_class_id' => $live_class_id,
            'title' => $topic_name,
            'start_time' => date('H:i:s'),
            'end_time' => date('H:i:s', strtotime($end_time)),
            'schedule_date' => date('Y-m-d'),
            'type' => 'live',
            'status' => 'in-progress'
        ]);

        // ============================================================
        // DƏRHAl CAVAB GÖNDƏR — Gecikmə olmadan
        // ============================================================
        $responseData = ['success' => true, 'live_class_id' => $live_class_id, 'tmis_session_id' => null, 'subject_id' => $course_id];

        if (function_exists('fastcgi_finish_request')) {
            echo json_encode($responseData);
            fastcgi_finish_request();
        } else {
            // Fallback: output buffering ilə cavabı dərhal göndər
            ignore_user_abort(true);
            ob_start();
            echo json_encode($responseData);
            $size = ob_get_length();
            header("Content-Length: $size");
            header("Connection: close");
            ob_end_flush();
            flush();
        }

        // ============================================================
        // ARXA FON ƏMƏLİYYATLARI (İstifadəçi artıq cavab aldı)
        // ============================================================

        // 3. TMİS Journal Topics-ə əlavə et
        if ($tmisSubject && isset($tmisSubject['education_year_id'])) {
            $js_type_id = 1; // Default: Mühazirə
            if ($lesson_type === 'seminar')
                $js_type_id = 2;
            elseif ($lesson_type === 'laboratory')
                $js_type_id = 3;

            try {
                $db->insert('journal_topics', [
                    'education_year_id' => $tmisSubject['education_year_id'],
                    'faculty_id' => $tmisSubject['faculty_name_id'] ?? 1,
                    'profession_id' => $tmisSubject['profession_id'] ?? 1,
                    'course' => $tmisSubject['course'] ?? 1,
                    'subject_id' => $tmisSubject['id'],
                    'journal_subject_type_id' => $js_type_id,
                    'topic_name' => $topic_name,
                    'topic_date' => date('Y-m-d'),
                    'receive_assignment' => 0,
                    'delivery_method' => 'online',
                    'slug' => strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $topic_name))) . '-' . uniqid(),
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
            } catch (Exception $e) {
                error_log('Journal Topics insert xətası: ' . $e->getMessage());
            }
        }

        // 4. Kursun adını tapmaq (Əgər lokalda varsa)
        $course = $db->fetch("SELECT title FROM courses WHERE id = ?", [$course_id]);
        $courseTitle = $course ? $course['title'] : 'Kurs';

        // 5. Bu kursun tələbələrinə bildiriş göndər
        try {
            $enrolledStudents = $db->fetchAll("SELECT user_id FROM enrollments WHERE course_id = ?", [$course_id]);

            foreach ($enrolledStudents as $student) {
                $db->insert('notifications', [
                    'user_id' => $student['user_id'],
                    'title' => 'Canlı Dərs Başladı',
                    'message' => "{$courseTitle} kursu üzrə canlı dərs başladı: {$topic_name}. İndi qoşula bilərsiniz!",
                    'type' => 'error', // Təcili olduğunu bildirmək üçün qırmızı
                    'is_read' => 0
                ]);
            }
        } catch (Exception $e) {
            error_log('Bildiriş göndərmə xətası: ' . $e->getMessage());
        }

        // 6. Köhnə aktiv alertləri bitir
        try {
            $db->update(
                'live_alerts',
                ['expires_at' => date('Y-m-d H:i:s')],
                'course_id = :cid AND instructor_id = :iid AND expires_at > NOW()',
                ['cid' => $course_id, 'iid' => $instructorId]
            );
        } catch (Exception $e) {
            error_log('Alert yenilənmə xətası: ' . $e->getMessage());
        }

        // 7. Canlı bildiriş (Alert) əlavə et
        try {
            $db->insert('live_alerts', [
                'instructor_id' => $instructorId,
                'course_id' => $course_id,
                'message' => "Canlı dərs başladı: {$topic_name}. İndi qoşula bilərsiniz!",
                'type' => 'error',
                'expires_at' => date('Y-m-d H:i:s', strtotime("+90 minutes"))
            ]);
        } catch (Exception $e) {
            error_log('Alert əlavə etmə xətası: ' . $e->getMessage());
        }

        // ============================================================
        // 8. TMİS API-yə canlı dərs başlaması haqqında bildiriş göndər
        // ============================================================
        $tmisToken = TmisApi::getToken();
        $tmis_subject_id = $course_id;

        if ($tmisToken && $tmis_subject_id) {
            try {
                $tmisStartResult = TmisApi::startLiveSession($tmisToken, [
                    'subject_id' => (int) $tmis_subject_id,
                    'lesson_type' => $lesson_type,
                    'topic' => $topic_name,
                    'started_at' => $now,
                    'lesson_number' => $lesson_number
                ]);

                if ($tmisStartResult['success'] && isset($tmisStartResult['data'])) {
                    $tmisSessionId = $tmisStartResult['data']['live_session_id'] ?? $tmisStartResult['data']['id'] ?? null;

                    // TMİS session ID-sini lokal bazada yadda saxla
                    if ($tmisSessionId && $live_class_id) {
                        $db->update('live_classes', ['tmis_session_id' => $tmisSessionId], 'id = ?', [$live_class_id]);
                        $db->update('schedule', ['live_class_id' => $tmisSessionId], 'live_class_id = ?', [$live_class_id]);
                    }
                } else {
                    error_log('TMİS Start Session xətası: ' . ($tmisStartResult['message'] ?? 'Naməlum xəta'));
                }
            } catch (Exception $e) {
                error_log('TMİS Start Session Exception: ' . $e->getMessage());
            }
        }

    } catch (Exception $e) {
        // Əgər əsas insert xətası baş verdisə (hələ cavab göndərilməyib)
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>