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

// Müəllimin instructor_id-sini tap (multiple IDs - lokal, TMİS)
$myTeacherIds = [];
if (isset($currentUser['id'])) {
    $myTeacherIds[] = (int) $currentUser['id'];
}
if (isset($_SESSION['tmis_id']) && $_SESSION['tmis_id'] != $currentUser['id']) {
    $myTeacherIds[] = (int) $_SESSION['tmis_id'];
}
// İnstruktor cədvəlindən də ID al (əgər varsa)
try {
    $instructor = $db->fetch(
        "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
        [$currentUser['id'], $currentUser['email']]
    );
    if (!$instructor && !empty($currentUser['email'])) {
        $emailPrefix = explode('@', $currentUser['email'])[0];
        $instructor = $db->fetch(
            "SELECT id FROM instructors WHERE email LIKE ?",
            [$emailPrefix . '@%']
        );
    }
    if ($instructor && !in_array((int) $instructor['id'], $myTeacherIds)) {
        $myTeacherIds[] = (int) $instructor['id'];
    }
} catch (Exception $e) {
}
$myTeacherIds = array_unique($myTeacherIds);

$isAdmin = ($_SESSION['user_role'] === 'admin');

// TMİS Token
require_once '../includes/tmis_api.php';
$tmisToken = TmisApi::getToken();

// TMİS fənn məlumatlarını map şəklində al (metadata üçün)
$subjectMap = [];
if ($tmisToken) {
    try {
        $subList = TmisApi::getSubjectsList($tmisToken);
        if ($subList['success'] && isset($subList['data'])) {
            foreach ($subList['data'] as $s) {
                $subjectMap[$s['id']] = $s;
            }
        }
    } catch (Exception $e) {
    }
}

try {
    $courseRows = [];

    // Müəllimin bütün bitmiş dərslərini course_id üzrə qruplaşdır
    if ($isAdmin) {
        $whereLP = "lc.status IN ('ended', 'completed')";
        $paramsLP = [];
    } else {
        if (empty($myTeacherIds)) {
            die("Müəllim tapılmadı");
        }
        $idPlaceholder = implode(',', array_fill(0, count($myTeacherIds), '?'));
        $whereLP = "lc.status IN ('ended', 'completed') AND lc.instructor_id IN ($idPlaceholder)";
        $paramsLP = $myTeacherIds;
    }

    $courseStats = $db->fetchAll("
        SELECT 
            lc.course_id,
            MAX(lc.instructor_id) as instructor_id,
            COUNT(*) as total_lessons,
            COUNT(CASE WHEN lc.lesson_type = 'lecture' THEN 1 END) as lecture_count,
            COUNT(CASE WHEN lc.lesson_type = 'seminar' THEN 1 END) as seminar_count,
            COUNT(CASE WHEN lc.lesson_type = 'laboratory' THEN 1 END) as lab_count
        FROM live_classes lc
        WHERE {$whereLP}
        GROUP BY lc.course_id
        ORDER BY total_lessons DESC
    ", $paramsLP);

    foreach ($courseStats as $cs) {
        $cId = (int) $cs['course_id'];
        $sInfo = $subjectMap[$cId] ?? [];

        // Fənn adını TMIS-dən al, yoxdursa lokal bazadan axtar
        $courseName = $sInfo['subject_name'] ?? '';
        if (empty($courseName)) {
            $courseRow = $db->fetch("SELECT title FROM courses WHERE tmis_subject_id = ?", [$cId]);
            if (!$courseRow) {
                $courseRow = $db->fetch("SELECT title FROM courses WHERE id = ?", [$cId]);
            }
            $courseName = $courseRow ? $courseRow['title'] : ('Fənn #' . $cId);
        }

        // Tələbə sayını TMİS subject details API-dən al (courses.php ilə eyni mənbə)
        $totalStudents = 0;
        if ($tmisToken) {
            try {
                $subjectDetailResult = TmisApi::getSubjectDetails($tmisToken, $cId);
                if ($subjectDetailResult['success'] && isset($subjectDetailResult['data'])) {
                    $detail = $subjectDetailResult['data'];
                    $totalStudents = (int) ($detail['total_students'] ?? ($detail['student_count'] ?? ($detail['students_count'] ?? 0)));
                    // Əgər 'students' massivi gəlirsə, onu da say
                    if ($totalStudents == 0 && isset($detail['students']) && is_array($detail['students'])) {
                        $totalStudents = count($detail['students']);
                    }
                }
            } catch (Exception $e) {
                // Xəta olsa lokal fallback istifadə ediləcək
            }
        }
        // Lokal fallback
        if ($totalStudents == 0) {
            $localStudents = $db->fetch("SELECT COUNT(*) as count FROM enrollments WHERE course_id = ?", [$cId]);
            if ($localStudents) {
                $totalStudents = (int) $localStudents['count'];
            }
        }
        // Əlavə fallback: courses.initial_students
        if ($totalStudents == 0) {
            $courseInitial = $db->fetch("SELECT initial_students FROM courses WHERE id = ? OR tmis_subject_id = ?", [$cId, $cId]);
            if ($courseInitial) {
                $totalStudents = (int) ($courseInitial['initial_students'] ?? 0);
            }
        }
        $activeStudents = 0; 

        // Davamiyyət hesabla
        $attendance = 0;
        if ($totalStudents > 0) {
            $perLessonAttendance = $db->fetchAll("
                SELECT lc.id, 
                       COUNT(DISTINCT la.user_id) as joined_count
                FROM live_classes lc
                LEFT JOIN live_attendance la ON la.live_class_id = lc.id AND la.role = 'student'
                WHERE lc.course_id = ? AND lc.status IN ('ended', 'completed')
                GROUP BY lc.id
            ", [$cId]);

            if (!empty($perLessonAttendance)) {
                $totalRate = 0;
                $lessonCountForAtt = 0;

                foreach ($perLessonAttendance as $pla) {
                    $rate = min(100, round(($pla['joined_count'] / $totalStudents) * 100));
                    $totalRate += $rate;
                    $lessonCountForAtt++;
                }

                $attendance = $lessonCountForAtt > 0 ? round($totalRate / $lessonCountForAtt) : 0;

                // Unique active students
                $uniqueStudentCount = $db->fetch("
                    SELECT COUNT(DISTINCT la.user_id) as cnt
                    FROM live_attendance la
                    JOIN live_classes lc ON la.live_class_id = lc.id
                    WHERE lc.course_id = ? AND la.role = 'student'
                ", [$cId]);
                $activeStudents = (int) ($uniqueStudentCount['cnt'] ?? 0);
            }
        } else {
            $uniqueStudentCount = $db->fetch("
                SELECT COUNT(DISTINCT la.user_id) as cnt
                FROM live_attendance la
                JOIN live_classes lc ON la.live_class_id = lc.id
                WHERE lc.course_id = ? AND la.role = 'student'
            ", [$cId]);
            $activeStudents = (int) ($uniqueStudentCount['cnt'] ?? 0);
            $totalStudents = $activeStudents;
            $attendance = $activeStudents > 0 ? 100 : 0;
        }

        $doneLessons = (int) $cs['total_lessons'];
        $mDone = (int) $cs['lecture_count'];
        $sDone = (int) $cs['seminar_count'];
        $lDone = (int) $cs['lab_count'];

        // Info Display
        $specDisplay = $sInfo['profession_name'] ?? 'Təyin edilməyib';
        $courseLevel = ($sInfo['course'] ?? 1) . '-cü kurs';

        if (empty($sInfo['profession_name'])) {
            $lcSpecRow = $db->fetch("
                SELECT specialty_name, group_name FROM live_classes 
                WHERE course_id = ? AND (specialty_name IS NOT NULL OR group_name IS NOT NULL) 
                ORDER BY id DESC LIMIT 1
            ", [$cId]);
            if ($lcSpecRow) {
                $specStr = $lcSpecRow['specialty_name'] ?? 'Təyin edilməyib';
                if (!empty($lcSpecRow['group_name'])) {
                    $specStr .= ' (' . $lcSpecRow['group_name'] . ')';
                }
                $specDisplay = $specStr;
            }
        }

        if ($isAdmin) {
            $instId = $cs['instructor_id'] ?? 0;
            $instName = '';
            if ($instId > 0) {
                // Get name from users table via instructors
                $instRow = $db->fetch("SELECT u.first_name, u.last_name FROM users u JOIN instructors i ON u.id = i.user_id WHERE i.id = ?", [$instId]);
                if ($instRow) {
                    $instName = trim($instRow['first_name'] . ' ' . $instRow['last_name']);
                }
            }
            $instructorName = !empty($instName) ? $instName : 'Müəllim';
        } else {
            $instructorName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
        }

        $courseRows[] = [
            'course_id_display' => 'NDU-' . (1000 + $cId),
            'title' => $courseName,
            'specialization' => $specDisplay,
            'course_level' => $courseLevel,
            'instructor_name' => $instructorName,
            'total_students' => $totalStudents,
            'active_students' => $activeStudents,
            'm_done' => $mDone,
            's_done' => $sDone,
            'l_done' => $lDone,
            'attendance' => $attendance
        ];
    }

    // CSV Header-ləri
    $filename = "NDU_Onlayn_Sessiya_Hesabati_" . date('Y-m-d_H-i') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    // BOM
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Başlıq sətiri
    fputcsv($output, ['Fənn ID', 'Fənn Adı', 'İxtisas', 'Kurs', 'Müəllim', 'Ümumi Tələbə', 'Mühazirə', 'Seminar', 'Laboratoriya', 'Davamiyyət (%)']);

    foreach ($courseRows as $row) {
        fputcsv($output, [
            $row['course_id_display'],
            $row['title'],
            $row['specialization'],
            $row['course_level'],
            $row['instructor_name'],
            $row['total_students'],
            $row['m_done'],
            $row['s_done'],
            $row['l_done'],
            $row['attendance'] . '%'
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    die("Hesabat yaradılarkən xəta baş verdi: " . $e->getMessage());
}

