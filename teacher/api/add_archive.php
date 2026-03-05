<?php
/**
 * Add Archive Material API
 */

require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/tmis_api.php';

$auth = new Auth();
requireInstructor();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $currentUser = $auth->getCurrentUser();

    // Get instructor ID (Mövcud deyilsə avtomatik yaradılır)
    $instructor = $db->fetch(
        "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
        [$currentUser['id'], $currentUser['email']]
    );
    if (!$instructor) {
        try {
            $insId = $db->insert('instructors', [
                'user_id' => $currentUser['id'],
                'name' => trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')),
                'email' => $currentUser['email'] ?? ''
            ]);
            $instructorId = $insId;
        } catch (Exception $e) {
            $instructorId = $currentUser['id'] ?? 1;
        }
    } else {
        $instructorId = $instructor['id'];
    }

    $course_id = intval($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? 'material';
    $duration = trim($_POST['duration'] ?? '1:30:00');

    // Fayl yoxlaması (TMİS mütləq fayl tələb edir)
    if ($course_id <= 0 || empty($title) || !isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Lazımi sahələri doldurun və faylı mütləq seçin.';
        if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $msg = 'Fayl yüklənmədi (Xəta kodu: ' . $_FILES['file']['error'] . ')';
        }
        header('Location: ../plan.php?error=' . urlencode($msg));
        exit;
    }

    try {
        $file_url = '';
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/archive/';

            // Qovluğu yarat əgər yoxdursa
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid('archive_') . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $upload_path)) {
                // Web root-dan başlayan yol
                $file_url = 'teacher/uploads/archive/' . $new_filename;
            }
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
        $insMeta = $db->fetch("SELECT name, title, faculty, specialty, course_level FROM instructors WHERE id = ?", [$instructorId]);

        // 2. Course metadata (prioritize subjects table, then courses table)
        // Search by both course_id and tmis_subject_id to be safe
        $subj = $db->fetch("SELECT subject_name FROM subjects WHERE id = ? OR id = (SELECT tmis_subject_id FROM courses WHERE id = ? LIMIT 1)", [$course_id, $course_id]);
        $courseObj = $db->fetch("SELECT title FROM courses WHERE id = ? OR tmis_subject_id = ?", [$course_id, $course_id]);

        $sName = ($subj && !empty($subj['subject_name'])) ? $subj['subject_name'] : ($courseObj['title'] ?? 'Fənn');
        $iName = ($insMeta && !empty($insMeta['name'])) ? $insMeta['name'] : (trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')));
        $iTitle = ($insMeta && !empty($insMeta['title'])) ? $insMeta['title'] : ($_SESSION['user_academic_title'] ?? 'Müəllim');

        // Insert into archived_lessons table
        $archiveId = $db->insert('archived_lessons', [
            'course_id' => $course_id,
            'tmis_subject_id' => $course_id, // TMİS subject ID-ni saxla
            'title' => $title,
            'instructor_id' => $instructorId,
            'archived_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
            'pdf_url' => ($type === 'material' || $type === 'quiz') ? $file_url : null,
            'video_url' => ($type === 'video') ? $file_url : null,
            'has_pdf' => ($type === 'material' || $type === 'quiz') ? 1 : 0,
            'has_video' => ($type === 'video') ? 1 : 0,
            'duration' => ($type === 'video') ? $duration : null,
            'views' => 0,
            // Redundant metadata
            'instructor_name' => $iName,
            'instructor_title' => $iTitle,
            'subject_name' => $_POST['course_name'] ?: $sName,
            'faculty_name' => $insMeta['faculty'] ?? ($_POST['faculty_name'] ?? ($currentUser['faculty'] ?? '')),
            'specialty_name' => $insMeta['specialty'] ?? ($_POST['specialty_name'] ?? ($currentUser['specialty'] ?? '')),
            'group_name' => $currentUser['group'] ?? '',
            'course_level' => $insMeta['course_level'] ?? ($_POST['course_level'] ?? ($currentUser['course_level'] ?? '-'))
        ]);

        // 3. Kursun adını tap
        $course = $db->fetch("SELECT title FROM courses WHERE id = ?", [$course_id]);
        $courseTitle = !empty($course['title']) ? $course['title'] : 'Fənn';

        // 4. Bu kursun tələbələrinə bildiriş göndər
        $enrolledStudents = $db->fetchAll("SELECT user_id FROM enrollments WHERE course_id = ? AND status = 'active'", [$course_id]);

        foreach ($enrolledStudents as $student) {
            $db->insert('notifications', [
                'user_id' => $student['user_id'],
                'title' => 'Arxiv Materialı Əlavə Edildi',
                'message' => "{$courseTitle} kursuna yeni material əlavə edildi: {$title}",
                'type' => 'info',
                'is_read' => 0
            ]);
        }

        // 5. Canlı bildiriş (Alert) əlavə et ki, ana ekranda da görünsün (6 saat limitli)
        $db->insert('live_alerts', [
            'instructor_id' => $instructorId,
            'course_id' => $course_id,
            'message' => "Yeni material əlavə edildi: {$title}",
            'type' => 'success',
            'expires_at' => date('Y-m-d H:i:s', strtotime("+6 hours"))
        ]);

        // ============================================================
        // TMİS API-yə arxiv materialı yüklə - DISABLED as per user request
        // ============================================================
        /*
        $tmisToken = TmisApi::getToken();
        // TMİS inteqrasiyasında course_id birbaşa TMİS subject ID-dir
        $tmisSubjectId = $course_id;

        if ($tmisToken && $tmisSubjectId && !empty($file_url)) {
            try {
                // Faylı TMİS-ə yüklə
                $uploadFilePath = realpath($upload_path);
                file_put_contents('../tmis_debug.log', date('H:i:s') . " - Uploading file: " . $uploadFilePath . " (Exists: " . (file_exists($uploadFilePath) ? 'Yes' : 'No') . ")\n", FILE_APPEND);

                if ($uploadFilePath && file_exists($uploadFilePath)) {
                    $tmisUploadResult = TmisApi::uploadArchive($tmisToken, [
                        'subject_id' => (int) $tmisSubjectId,
                        'title' => $title,
                        'file_type' => $type // 'type' yerine 'file_type' olaraq göndəririk
                    ], $uploadFilePath);

                    file_put_contents('../tmis_debug.log', date('H:i:s') . " - TMIS Response: " . json_encode($tmisUploadResult, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

                    if (!$tmisUploadResult['success']) {
                        $errorMsg = $tmisUploadResult['message'] ?? 'Naməlum xəta';
                        // Əgər validasiya xətasıdırsa, daha anlaşıqlı mesaj göstərək
                        if ($errorMsg === 'validation.uploaded') {
                            $errorMsg = 'Fayl TMİS serverinə tam yüklənə bilmədi. Fayl ölçüsünün çox böyük olmadığını yoxlayın.';
                        }
                        error_log('TMİS Archive Upload xətası: ' . $errorMsg);
                        $_SESSION['tmis_error'] = $errorMsg;
                    } else {
                        $_SESSION['tmis_success'] = "Material həm lokal bazaya, həm də TMİS-ə uğurla yükləndi.";
                    }
                } else {
                    file_put_contents('../tmis_debug.log', date('H:i:s') . " - FILE NOT FOUND: " . $upload_path . "\n", FILE_APPEND);
                }
            } catch (Exception $e) {
                error_log('TMİS Archive Upload Exception: ' . $e->getMessage());
                $_SESSION['tmis_error'] = "Sistem xətası: " . $e->getMessage();
            }
        }
        */

        header('Location: ../plan.php?success=1');
    } catch (Exception $e) {
        header('Location: ../plan.php?error=' . urlencode($e->getMessage()));
    }
} else {
    header('Location: ../plan.php');
}
