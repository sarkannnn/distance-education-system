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

    // Müəllimin instructor_id-sini tapmaq
    $instructor = $db->fetch(
        "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
        [$user['id'], $user['email']]
    );
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

    if (!$instructorId) {
        $instructorId = $user['id'] ?? 1;
    }

    // Axın dərsi üçün çoxlu kurs ID-ləri
    $course_ids = $_POST['course_ids'] ?? [];
    $topic_name = $_POST['title'] ?? $_POST['topic_name'] ?? null;
    $lesson_type = $_POST['lesson_type'] ?? 'lecture';
    $course_name = $_POST['course_name'] ?? 'Fənn';

    if (empty($course_ids) || !is_array($course_ids) || count($course_ids) < 2) {
        echo json_encode(['success' => false, 'message' => 'Ən azı 2 ixtisas seçilməlidir']);
        exit;
    }

    if (!$topic_name) {
        echo json_encode(['success' => false, 'message' => 'Mövzu adı daxil edilməyib']);
        exit;
    }

    // Birinci kurs əsas kurs olaraq istifadə edilir
    $primary_course_id = $course_ids[0];
    $stream_course_ids_str = implode(',', $course_ids);

    $now = date('Y-m-d H:i:s');
    $end_time = date('Y-m-d H:i:s', strtotime("+90 minutes"));

    // Dərs nömrəsini hesabla
    $currentTypeCounts = $db->fetch(
        "SELECT COUNT(*) as cnt FROM live_classes WHERE course_id = ? AND lesson_type = ? AND status IN ('ended', 'completed')",
        [$primary_course_id, $lesson_type]
    );
    $current = $currentTypeCounts['cnt'] ?? 0;
    $lesson_number = $current + 1;

    // Instructor metadatasını al
    $courseInfo = $db->fetch("SELECT lecture_count, seminar_count, instructor_id FROM courses WHERE id = ?", [$primary_course_id]);
    $finalInstructorId = $instructorId;
    if ($courseInfo && !empty($courseInfo['instructor_id'])) {
        $finalInstructorId = $courseInfo['instructor_id'];
    }

    try {
        // Kurs əlaqəsini yoxla — hər bir seçilmiş kurs üçün
        foreach ($course_ids as $cid) {
            $courseCheck = $db->fetch("SELECT id FROM courses WHERE tmis_subject_id = ? OR id = ?", [$cid, $cid]);
            if (!$courseCheck) {
                $db->insert('courses', [
                    'id' => $cid,
                    'tmis_subject_id' => $cid,
                    'title' => $course_name,
                    'instructor_id' => $instructorId,
                    'course_level' => $_POST['course_level'] ?? 1,
                    'status' => 'active'
                ]);
            }
        }

        // Instructor metadata
        $insMeta = $db->fetch("SELECT name, title, faculty, specialty, course_level FROM instructors WHERE id = ?", [$finalInstructorId]);

        // Course/Subject metadata
        $subj = $db->fetch("SELECT subject_name FROM subjects WHERE id = ? OR id = (SELECT tmis_subject_id FROM courses WHERE id = ? LIMIT 1)", [$primary_course_id, $primary_course_id]);
        $courseObj = $db->fetch("SELECT title FROM courses WHERE id = ? OR tmis_subject_id = ?", [$primary_course_id, $primary_course_id]);

        $sName = ($subj && !empty($subj['subject_name'])) ? $subj['subject_name'] : ($courseObj['title'] ?? $course_name);
        $iName = ($insMeta && !empty($insMeta['name'])) ? $insMeta['name'] : ($_SESSION['user_name'] ?? 'Müəllim');
        $iTitle = ($insMeta && !empty($insMeta['title'])) ? $insMeta['title'] : ($_SESSION['user_academic_title'] ?? 'Müəllim');

        // Resolve specialty/department names for stream
        $resolvedSpecialties = [];
        $tmisToken = TmisApi::getToken();
        if ($tmisToken) {
            try {
                $subsList = TmisApi::getSubjectsList($tmisToken);
                if ($subsList['success'] && !empty($subsList['data'])) {
                    $nameMap = [];
                    foreach ($subsList['data'] as $s) {
                        $sid = $s['id'] ?? $s['subject_id'] ?? 0;
                        if ($sid) $nameMap[$sid] = $s['profession_name'] ?? ($s['subject_name'] ?? 'Naməlum');
                    }
                    foreach ($course_ids as $cid) {
                        if (isset($nameMap[$cid])) {
                            $resolvedSpecialties[] = $nameMap[$cid];
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Stream specialty resolution error: " . $e->getMessage());
            }
        }

        $finalSpecialtyName = !empty($resolvedSpecialties) ? implode(', ', array_unique($resolvedSpecialties)) : 'Axın (çoxlu ixtisas)';

        // Axın dərsi — TEK live_classes qeydi
        $live_class_id = $db->insert('live_classes', [
            'course_id' => $primary_course_id,
            'tmis_subject_id' => $primary_course_id,
            'stream_course_ids' => $stream_course_ids_str,
            'is_stream' => 1,
            'title' => $topic_name,
            'lesson_type' => $lesson_type,
            'lesson_number' => $lesson_number,
            'instructor_id' => $finalInstructorId,
            'start_time' => $now,
            'started_at' => $now,
            'end_time' => $end_time,
            'duration_minutes' => 0,
            'status' => 'live',
            'instructor_name' => $iName,
            'instructor_title' => $iTitle,
            'subject_name' => $sName,
            'faculty_name' => (!empty($_POST['faculty_name'])) ? $_POST['faculty_name'] : (($insMeta && !empty($insMeta['faculty'])) ? $insMeta['faculty'] : ($user['faculty'] ?? '')),
            'specialty_name' => $finalSpecialtyName,
            'group_name' => $user['group'] ?? '',
            'course_level' => (!empty($_POST['course_level']) && $_POST['course_level'] !== '-') ? $_POST['course_level'] : (($insMeta && !empty($insMeta['course_level'])) ? $insMeta['course_level'] : '-')
        ]);

        // Hər kurs üçün schedule qeydi
        foreach ($course_ids as $cid) {
            $db->insert('schedule', [
                'user_id' => $user['id'],
                'course_id' => $cid,
                'live_class_id' => $live_class_id,
                'title' => $topic_name,
                'start_time' => date('H:i:s'),
                'end_time' => date('H:i:s', strtotime($end_time)),
                'schedule_date' => date('Y-m-d'),
                'type' => 'live',
                'status' => 'in-progress'
            ]);
        }

        // Cavabı dərhal göndər
        $responseData = [
            'success' => true,
            'live_class_id' => $live_class_id,
            'tmis_session_id' => null,
            'subject_id' => $primary_course_id,
            'is_stream' => true,
            'stream_course_ids' => $course_ids
        ];

        if (function_exists('fastcgi_finish_request')) {
            echo json_encode($responseData);
            fastcgi_finish_request();
        } else {
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
        // ARXA FON ƏMƏLİYYATLARI
        // ============================================================

        // Hər kurs üçün TMİS journal topics əlavə et
        foreach ($course_ids as $cid) {
            $tmisSubject = $db->fetch("SELECT * FROM subjects WHERE id = ?", [$cid]);
            if ($tmisSubject && isset($tmisSubject['education_year_id'])) {
                $js_type_id = 1;
                if ($lesson_type === 'seminar') $js_type_id = 2;
                elseif ($lesson_type === 'laboratory') $js_type_id = 3;

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
                    error_log('Stream Journal Topics insert xətası: ' . $e->getMessage());
                }
            }
        }

        // Köhnə aktiv alertləri bitir (hər kurs üçün)
        foreach ($course_ids as $cid) {
            try {
                $db->update(
                    'live_alerts',
                    ['expires_at' => date('Y-m-d H:i:s')],
                    'course_id = :cid AND instructor_id = :iid AND expires_at > NOW()',
                    ['cid' => $cid, 'iid' => $instructorId]
                );
            } catch (Exception $e) {
                error_log('Alert yenilənmə xətası: ' . $e->getMessage());
            }
        }

        // Hər kurs üçün canlı bildiriş (Alert) əlavə et
        foreach ($course_ids as $cid) {
            try {
                $db->insert('live_alerts', [
                    'instructor_id' => $instructorId,
                    'course_id' => $cid,
                    'message' => "Axın dərsi başladı: {$topic_name}. İndi qoşula bilərsiniz!",
                    'type' => 'error',
                    'category' => 'live_started',
                    'expires_at' => date('Y-m-d H:i:s', strtotime("+90 minutes"))
                ]);
            } catch (Exception $e) {
                error_log('Alert əlavə etmə xətası: ' . $e->getMessage());
            }
        }

        // TMİS API — hər kurs üçün bildiriş
        $tmisToken = TmisApi::getToken();
        if ($tmisToken) {
            foreach ($course_ids as $cid) {
                try {
                    TmisApi::startLiveSession($tmisToken, [
                        'subject_id' => (int) $cid,
                        'lesson_type' => $lesson_type,
                        'topic' => $topic_name,
                        'started_at' => $now,
                        'lesson_number' => $lesson_number
                    ]);
                } catch (Exception $e) {
                    error_log('TMİS Stream Start Exception for course ' . $cid . ': ' . $e->getMessage());
                }
            }
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>