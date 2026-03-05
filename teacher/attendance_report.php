<?php
ob_start();
/**
 * Live Class Attendance Report
 */
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/tmis_api.php';

$auth = new Auth();
requireInstructor();
$db = Database::getInstance();

$lessonId = $_GET['id'] ?? null;
if ($lessonId === null || $lessonId === '') {
    header('Location: live-lessons.php');
    exit;
}

$lesson = $db->fetch("SELECT lc.*, c.title as course_title 
                 FROM live_classes lc 
                 LEFT JOIN courses c ON lc.course_id = c.id 
                 WHERE lc.id = :id1 OR lc.tmis_session_id = :id2", ['id1' => $lessonId, 'id2' => $lessonId]);
if ($lesson) {
    // Map DB column names to expected variable names
    if (empty($lesson['specialization_name']) && !empty($lesson['specialty_name'])) {
        $lesson['specialization_name'] = $lesson['specialty_name'];
    }
    if (empty($lesson['specialization_name'])) {
        $lesson['specialization_name'] = 'Təyin edilməyib';
    }
    if (empty($lesson['course_level'])) {
        $lesson['course_level'] = 'Təyin edilməyib';
    }
} else {
    // Müəllimin instructor_id-sini tapmaq (Mock üçün vacibdir)
    $currentUser = $auth->getCurrentUser();
    $instr = $db->fetch("SELECT id FROM instructors WHERE user_id = ?", [$currentUser['id']]);
    $instructorIdForMock = $instr ? $instr['id'] : 0;

    // Mock the lesson array if DB returns null (could be a direct TMIS session ID)
    $startTime = $_SESSION['mock_lesson_start_' . $lessonId] ?? date('Y-m-d H:i:s');
    $lesson = [
        'id' => $lessonId,
        'course_id' => $lessonId,
        'tmis_session_id' => $lessonId,
        'course_title' => "Dərs #" . ($lessonId ?? '0'),
        'title' => 'Mövzu təyin edilməyib',
        'lesson_type' => 'lecture',
        'specialization_name' => 'Təyin edilməyib',
        'course_level' => 'Təyin edilməyib',
        'started_at' => $startTime,
        'start_time' => $startTime,
        'created_at' => $startTime,
        'instructor_id' => $instructorIdForMock,
        'status' => 'live'
    ];
}

// course_title boşdursa (course_id TMIS subject_id olduğu üçün local courses-da yoxdur), TMIS-dən al
if (empty($lesson['course_title'])) {
    $tmisTokenForTitle = TmisApi::getToken();
    if ($tmisTokenForTitle) {
        try {
            $subjectsList = TmisApi::getSubjectsList($tmisTokenForTitle);
            
            if ($subjectsList['success'] && !empty($subjectsList['data'])) {
                foreach ($subjectsList['data'] as $subj) {
                    $s_id = $subj['id'] ?? $subj['subject_id'] ?? 0;
                    if ($s_id == $lesson['course_id']) {
                        $lesson['course_title'] = $subj['subject_name'] ?? $subj['name'] ?? '';
                        $lesson['specialization_name'] = $subj['profession_name'] ?? 'Təyin edilməyib';
                        $lesson['course_level'] = isset($subj['course']) ? $subj['course'] . '-cü kurs' : 'Təyin edilməyib';
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            // ignore
        }
    }
    // Hələ də boşdursa default
    if (empty($lesson['course_title'])) {
        $lesson['course_title'] = $lesson['title'] ?? ("Dərs #" . $lessonId);
    }
}

// --- 1. Fetch Everyone who actually attended (Logs) ---
$attendeeLogs = $db->fetchAll(
    "SELECT 
        la.user_id as user_id,
        MAX(u.first_name) as first_name, 
        MAX(u.last_name) as last_name, 
        MAX(u.email) as email, 
        MAX(COALESCE(u.role, la.role)) as user_role,
        MAX(u.fin_number) as fin_number,
        MIN(la.joined_at) as first_join,
        MAX(la.last_heartbeat) as last_seen,
        COALESCE(SUM(GREATEST(0, TIMESTAMPDIFF(SECOND, 
            GREATEST(la.joined_at, COALESCE(lc.started_at, la.joined_at)), 
            COALESCE(la.left_at, la.last_heartbeat)
        ))), 0) as total_seconds,
        COALESCE(MAX(CASE WHEN la.left_at IS NULL AND la.last_heartbeat > DATE_SUB(NOW(), INTERVAL 35 SECOND) AND la.is_kicked = 0 THEN 1 ELSE 0 END), 0) as is_currently_online
     FROM live_attendance la
     LEFT JOIN users u ON la.user_id = u.id
     INNER JOIN live_classes lc ON la.live_class_id = lc.id
     WHERE la.live_class_id = ?
     GROUP BY la.user_id",
    [$lessonId]
);



// --- 2. Build the Full Official Roster ---
$roster = [];
$tmisToken = TmisApi::getToken();

if ($tmisToken) {
    try {
        $subjectId = $lesson['course_id'];
        
        // Robustness: If course_id seems to be a mock lesson ID (matches lessonId), 
        // try to find the real subject ID from TMIS or instructor's current teaching list.
        if ($subjectId == $lessonId || $subjectId <= 0) {
            // 1. Try to find from latest live class in local DB
            $latestReal = $db->fetch("SELECT course_id FROM live_classes WHERE course_id != ? AND instructor_id = ? ORDER BY created_at DESC LIMIT 1", [$lessonId, $lesson['instructor_id']]);
            if ($latestReal) {
                $subjectId = $latestReal['course_id'];
            } else {
                // 2. Try to get first subject from TMIS list
                $subjects = TmisApi::getSubjectsList($tmisToken);
                if ($subjects['success'] && !empty($subjects['data'])) {
                    $subjectId = $subjects['data'][0]['id'] ?? $subjects['data'][0]['subject_id'] ?? $subjectId;
                }
            }
        }

        if ($subjectId > 0) {
            $details = TmisApi::getSubjectDetails($tmisToken, (int)$subjectId);
            if ($details['success'] && isset($details['data']['students']) && is_array($details['data']['students'])) {
                if (!empty($details['data']['students'])) {
                    file_put_contents('uploads/student_details_full_debug.log', "Keys: " . print_r(array_keys($details['data']['students'][0]), true) . "\nData: " . print_r($details['data']['students'][0], true));
                }
                $sidMap = [];
                foreach ($details['data']['students'] as $s) {
                    $uid = $s['id'] ?? ($s['student_id'] ?? 0);
                    if (!$uid) continue;
                    
                    $roster[$uid] = [
                        'user_id' => $uid,
                        'first_name' => $s['first_name'] ?? ($s['name'] ?? ''),
                        'last_name' => $s['last_name'] ?? ($s['surname'] ?? ''),
                        'father_name' => $s['father_name'] ?? '',
                        'email' => $s['email'] ?? '',
                        'user_role' => 'student',
                        'first_join' => null,
                        'last_seen' => null,
                        'total_seconds' => 0,
                        'is_currently_online' => 0
                    ];

                    if (!empty($s['student_id'])) {
                        $sidMap[$s['student_id']] = $uid;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Attendance Report TMIS Error: ' . $e->getMessage());
    }
}

// Fallback to local enrollment ONLY IF roster is still empty
if (empty($roster)) {
    $localRoster = $db->fetchAll("
        SELECT u.id as user_id, u.first_name, u.last_name, u.email, u.role as user_role
        FROM users u
        JOIN enrollments e ON u.id = e.user_id
        WHERE e.course_id = ?
    ", [$lesson['course_id']]);
    
    foreach ($localRoster as $s) {
        $roster[$s['user_id']] = array_merge($s, [
            'first_join' => null,
            'last_seen' => null,
            'total_seconds' => 0,
            'is_currently_online' => 0
        ]);
    }
}

// Note: Demo students removed as per user request to show real registered students.

// --- 3. Merge Attendance Logs into Roster (Only for students) ---
foreach ($attendeeLogs as $log) {
    // Skip instructors
    if ($log['user_role'] === 'instructor') continue;

    $uid = $log['user_id'];
    $idToUpdate = $uid;
    if (!isset($roster[$idToUpdate]) && !empty($sidMap) && isset($sidMap[$idToUpdate])) {
        $idToUpdate = $sidMap[$idToUpdate];
    }

    if (isset($roster[$idToUpdate])) {
        // Person is in roster, update their stats BUT keep existing name fields if they are better
        foreach ($log as $key => $val) {
            // Only update if roster's version is empty or it's an attendance-specific field
            if (empty($roster[$idToUpdate][$key]) || in_array($key, ['first_join', 'last_seen', 'total_seconds', 'is_currently_online'])) {
                if ($val !== null && $val !== '') {
                    $roster[$idToUpdate][$key] = $val;
                }
            }
        }
    } else {
        // Person joined but not in roster (guest, tutor, or unsynced student)
        $roster[$uid] = $log;
    }
}

// --- 4. Prepare final list ($logs) & Final Fallback for missing names ---
$logs = [];
foreach ($roster as $r) {
    if (empty($r['last_name'])) {
        $r['last_name'] = '(ID: ' . $r['user_id'] . ')';
    }
    if (empty($r['first_name'])) {
        $r['first_name'] = 'Tələbə';
    }
    $logs[] = $r;
}

// Sorting
usort($logs, function($a, $b) {
    if ($a['total_seconds'] != $b['total_seconds']) {
        return $b['total_seconds'] <=> $a['total_seconds'];
    }
    return strcmp($a['last_name'], $b['last_name']);
});

// Total stats calculation
$rosterCount = count($logs);
$joinedCount = 0;
$totalSecondsAll = 0;
$maxStudentDurationSeconds = 0;

foreach($logs as $l) {
    if ($l['first_join']) {
        $joinedCount++;
        $totalSecondsAll += $l['total_seconds'];
        
        if($l['total_seconds'] > $maxStudentDurationSeconds) {
            $maxStudentDurationSeconds = $l['total_seconds'];
        }
    }
}

// --- 5. Calculate Teacher Duration separately for baseline (hidden from report) ---
$teacherData = $db->fetch(
    "SELECT 
        COALESCE(SUM(GREATEST(0, TIMESTAMPDIFF(SECOND, 
            GREATEST(la.joined_at, COALESCE(lc.started_at, la.joined_at)), 
            COALESCE(la.left_at, la.last_heartbeat)
        ))), 0) as total_seconds
     FROM live_attendance la
     LEFT JOIN live_classes lc ON la.live_class_id = lc.id
     WHERE la.live_class_id = ? AND la.user_id = (SELECT user_id FROM instructors WHERE id = ?)",
    [$lessonId, $lesson['instructor_id']]
);
$teacherDurationSeconds = $teacherData['total_seconds'] ?? 0;

// 1. Dərsin rəsmi başlama vaxtı (Əgər started_at yoxdursa, scheduled start_time)
$officialStartTime = $lesson['started_at'] ? strtotime($lesson['started_at']) : strtotime($lesson['start_time']);

// 2. Dərsin bitmə vaxtını təyin et
$lastActivity = $db->fetch("SELECT MAX(last_heartbeat) as latest FROM live_attendance WHERE live_class_id = ?", [$lessonId]);
$lastHeartbeat = ($lastActivity && $lastActivity['latest']) ? strtotime($lastActivity['latest']) : $officialStartTime;

// Əgər dərs hələ canlıdırsa, indiki vaxtı əsas götür (ki, faizlər düzgün azalsın)
// Əgər dərs bitibsə (recording varsa), sonuncu loqu əsas götür
$isLive = ($lesson['status'] === 'live');
$endTime = $isLive ? max(time(), $lastHeartbeat) : $lastHeartbeat;

// 3. Dərsin ümumi rəsmi müddəti (Saniyə ilə)
$totalLessonSeconds = max(0, $endTime - $officialStartTime);

// 4. Baseline: Müəllimin orda olduğu vaxt, dərsin total müddəti, ən uzun qalan tələbə və ya bazadakı qeydə alınmış müddət
// Yalnız artıq bitmiş dərslər üçün stored duration istifadə et
$storedDurationSeconds = 0;
if (!$isLive && ($lesson['duration_minutes'] ?? 0) > 0) {
    $storedDurationSeconds = ($lesson['duration_minutes']) * 60;
}
$baselineDuration = max($teacherDurationSeconds, $totalLessonSeconds, $maxStudentDurationSeconds, $storedDurationSeconds);

// Təhlükəsizlik: bölmədə xəta olmasın deyə minimum 1 saniyə
if ($baselineDuration < 1) $baselineDuration = 1;

$avgMinutes = $joinedCount > 0 ? round(($totalSecondsAll / $joinedCount) / 60) : 0;
$onlineCount = 0;
foreach($logs as $l) if($l['is_currently_online']) $onlineCount++;

// ============================================================
// TMİS API-dən Attendance Report datası çək (əlavə mənbə)
// ============================================================
$tmisToken = TmisApi::getToken();
$tmisAttendanceData = null;

if ($tmisToken) {
    // tmis_session_id-ni lokal bazadan oxu. Əgər yoxdursa, birbaşa lessonId-ni yoxla
    $tmisSessionInfo = $db->fetch("SELECT tmis_session_id FROM live_classes WHERE id = ?", [$lessonId]);
    $tmisSessionId = !empty($tmisSessionInfo['tmis_session_id']) ? $tmisSessionInfo['tmis_session_id'] : $lessonId;
    
    if ($tmisSessionId) {
        try {
            $attResult = TmisApi::getAttendanceReport($tmisToken, (int) $tmisSessionId);
            
            if ($attResult['success'] && isset($attResult['data'])) {
                $tmisAttendanceData = $attResult['data'];
                
                if (isset($tmisAttendanceData['dashboard'])) {
                    // DEBUG: Log first row
                    if (!empty($tmisAttendanceData['rows'])) {
                        file_put_contents('uploads/student_row_full_debug.log', print_r($tmisAttendanceData['rows'][0], true));
                    }
                    $tmisDashboard = $tmisAttendanceData['dashboard'];
                    // TMİS present_count ilə müqayisə et (logging üçün)
                    error_log('TMİS Attendance: present_count=' . ($tmisDashboard['present_count'] ?? 'N/A') . 
                               ', local_joined=' . $joinedCount);

                    // --- NEW: Update mock data with real info from TMIS dashboard ---
                    // Əgər fənn adı placeholder-dirsə, TMİS-dən gələni yaz
                    if (strpos($lesson['course_title'] ?? '', 'Dərs #') === 0 && !empty($tmisDashboard['subject_name'])) {
                        $lesson['course_title'] = $tmisDashboard['subject_name'];
                    }
                    // Mövzu yoxdursa, TMİS-dən gələni yaz
                    if (($lesson['title'] ?? 'Mövzu təyin edilməyib') === 'Mövzu təyin edilməyib' && !empty($tmisDashboard['topic'])) {
                        $lesson['title'] = $tmisDashboard['topic'];
                    }
                    // Başlama vaxtı
                    if (!empty($tmisDashboard['started_at']) && ($lesson['status'] ?? '') === 'live') {
                        $lesson['started_at'] = $tmisDashboard['started_at'];
                    }
                }
                
                // TMİS rows ilə lokal roster-i zənginləşdir (əgər rows varsa)
                if (isset($tmisAttendanceData['rows']) && is_array($tmisAttendanceData['rows'])) {
                    foreach ($tmisAttendanceData['rows'] as $tmisRow) {
                        $tmisFinNumber = $tmisRow['fin_number'] ?? null;
                        if (!$tmisFinNumber) continue;
                        
                        // FIN nömrə ilə lokal roster-dən tap
                        $found = false;
                        foreach ($logs as &$localLog) {
                            if (isset($localLog['fin_number']) && $localLog['fin_number'] === $tmisFinNumber) {
                                // TMİS-dən əlavə məlumatları birləşdir
                                $localLog['tmis_status'] = $tmisRow['status'] ?? null;
                                $found = true;
                                break;
                            }
                        }
                        unset($localLog);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('TMİS Attendance Report xətası: ' . $e->getMessage());
        }

        // --- NEW: If still placeholder, try to find in Activities or ScheduleToday ---
        if (strpos($lesson['course_title'] ?? '', 'Dərs #') === 0 || ($lesson['title'] ?? '') === 'Mövzu təyin edilməyib') {
            try {
                // 1. Check Activities
                $actResult = TmisApi::getActivities($tmisToken);
                
                if ($actResult['success'] && !empty($actResult['data']['grid']['data'])) {
                    foreach ($actResult['data']['grid']['data'] as $act) {
                        if (($act['id'] ?? 0) == $lessonId || ($act['live_session_id'] ?? 0) == $lessonId) {
                            $lesson['course_title'] = $act['subject_name'] ?? $act['name'] ?? $lesson['course_title'];
                            $lesson['title'] = $act['topic'] ?? $act['topic_name'] ?? $act['title'] ?? $lesson['title'];
                            $lesson['specialization_name'] = $act['profession_name'] ?? $lesson['specialization_name'];
                            $lesson['course_level'] = isset($act['course']) ? $act['course'] . '-cü kurs' : $lesson['course_level'];
                            break;
                        }
                    }
                }

                // 2. Check Schedule Today if still not found
                if (strpos($lesson['course_title'] ?? '', 'Dərs #') === 0) {
                    $schResult = TmisApi::getScheduleToday($tmisToken);
                    if ($schResult['success'] && !empty($schResult['data'])) {
                        foreach ($schResult['data'] as $sch) {
                            if (($sch['id'] ?? 0) == $lessonId || ($sch['tmis_session_id'] ?? 0) == $lessonId) {
                                $lesson['course_title'] = $sch['subject_name'] ?? $sch['name'] ?? $lesson['course_title'];
                                $lesson['title'] = $sch['topic'] ?? $sch['topic_name'] ?? $sch['title'] ?? $lesson['title'];
                                break;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // fallback fail
            }
        }
    }
}

// --- EXPORT TO CSV (Must be before ANY output) ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_length()) ob_end_clean();
    
    $cleanCourseTitle = preg_replace('/[^A-Za-z0-9]/', '_', $lesson['course_title']);
    $fileName = "Ishtirak_Jurnali_" . $cleanCourseTitle . "_" . date('d_m_Y', strtotime($lesson['created_at'])) . ".csv";
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    
    // Header Section (Premium Brand)
    fputcsv($output, ['NAXÇIVAN DÖVLƏT ÜNİVERSİTETİ']);
    fputcsv($output, ['Distant Təhsil Sistemi - Rəsmi İştirak Hesabatı']);
    fputcsv($output, ['========================================================================']);
    fputcsv($output, []); 

    // Lesson Details
    fputcsv($output, ['DƏRS MƏLUMATLARI']);
    fputcsv($output, ['----------------------------------------']);
    fputcsv($output, ['Kursun Adı:', $lesson['course_title']]);
    fputcsv($output, ['İxtisas:', $lesson['specialization_name']]);
    fputcsv($output, ['Kurs:', $lesson['course_level']]);
    fputcsv($output, ['Dərs mövzusu:', $lesson['title']]);
    $typeAz = ($lesson['lesson_type'] == 'seminar') ? 'Seminar' : 'Mühazirə';
    fputcsv($output, ['Dərs Növü:', $typeAz]);
    fputcsv($output, ['Tarix:', date('d.m.Y', strtotime($lesson['created_at']))]);
    fputcsv($output, ['Başlama Vaxtı:', date('H:i', strtotime($lesson['started_at'] ?? $lesson['start_time']))]);
    fputcsv($output, ['Ümumi Müddət:', round($baselineDuration / 60) . ' dəqiqə']);
    fputcsv($output, ['Cəmi Qatılan:', $joinedCount . ' / ' . $rosterCount . ' nəfər']);
    fputcsv($output, ['Generasiya Tarixi:', date('d.m.Y H:i')]);
    fputcsv($output, ['========================================================================']);
    fputcsv($output, []); 
    
    // Attendance Table
    fputcsv($output, ['İSHTİRAKÇI JURNALI']);
    fputcsv($output, ['№', 'Ad Soyad', 'E-poçt ünvanı', 'Vəzifə', 'Giriş Saatı', 'Son Görülmə', 'Müddət (dəq)', 'Faiz (%)', 'YEKUN NƏTİCƏ']);
    
    $counter = 1;
    foreach ($logs as $log) {
        $durationMin = floor($log['total_seconds'] / 60);
        $progress = $baselineDuration > 0 ? round(($log['total_seconds'] / $baselineDuration) * 100) : 0;
        $progress = min(100, $progress);
        
        $finalStatus = ($progress >= 50) ? 'İştirak etdi' : 'Qayıb';
        $role = $log['user_role'] == 'instructor' ? 'Müəllim' : 'Tələbə';
        
        fputcsv($output, [
            $counter++,
            $log['last_name'] . ' ' . $log['first_name'] . (!empty($log['father_name']) ? ' ' . $log['father_name'] : ''),
            $log['email'],
            $role,
            $log['first_join'] ? date('H:i', strtotime($log['first_join'])) : '--',
            $log['last_seen'] ? date('H:i', strtotime($log['last_seen'])) : '--',
            $durationMin,
            $progress . '%',
            $finalStatus
        ]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['Qeyd: 50% və daha çox iştirak edənlər "İştirak etdi" olaraq qeydə alınır.']);
    
    fclose($output);
    exit;
}

$isMinimal = isset($_GET['minimal']); 

// Minimal mode CSS
$extraStyle = '';
if ($isMinimal) {
    $extraStyle = '<style>.sidebar, .topnav, .main-wrapper::before { display: none !important; } .main-content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; } .app-container { display: block !important; }</style>';
}
?>
<script>
    if (window.opener && !window.location.href.includes('minimal=1')) {
        window.location.href = window.location.href + (window.location.href.includes('?') ? '&' : '?') + 'minimal=1';
    }
</script>
<?php
echo $extraStyle;




// --- NEW: LIFETIME ANALYTICS DATA ---
$instructorId = $_SESSION['user_id'];
$courseId = $lesson['course_id'];

// Get all past lessons for this course
$allLessons = $db->fetchAll(
    "SELECT lc.id, lc.created_at, lc.title as lesson_title,
        (SELECT COUNT(DISTINCT user_id) FROM live_attendance WHERE live_class_id = lc.id) as attendee_count,
        (SELECT SUM(TIMESTAMPDIFF(SECOND, joined_at, COALESCE(left_at, last_heartbeat))) FROM live_attendance WHERE live_class_id = lc.id) as total_seconds
     FROM live_classes lc
     WHERE lc.course_id = ? AND lc.instructor_id = ?
     ORDER BY lc.created_at DESC",
    [$courseId, $instructorId]
);

// Total students in this course
$courseData = $db->fetch("SELECT initial_students, tmis_subject_id FROM courses WHERE id = ?", [$courseId]);
$totalStudentsInCourse = 0;

if (!empty($courseData['tmis_subject_id'])) {
    try {
        $tmisStCount = $db->fetch(
            "SELECT COUNT(DISTINCT st.student_id) as count 
             FROM input_points ip
             JOIN students st ON ip.student_id = st.id
             WHERE ip.subject_id = ?",
            [$courseData['tmis_subject_id']]
        );
        $totalStudentsInCourse = intval($tmisStCount['count'] ?? 0);
    } catch (Exception $e) {
        $localEnroll = $db->fetch("SELECT COUNT(*) as count FROM enrollments WHERE course_id = ?", [$courseId]);
        $totalStudentsInCourse = max(intval($courseData['initial_students'] ?? 0), intval($localEnroll['count'] ?? 0));
    }
} else {
    $localEnroll = $db->fetch("SELECT COUNT(*) as count FROM enrollments WHERE course_id = ?", [$courseId]);
    $totalStudentsInCourse = max(intval($courseData['initial_students'] ?? 0), intval($localEnroll['count'] ?? 0));
}

require_once 'includes/header.php';
?>

<?php if (!$isMinimal): ?>
<div class="main-wrapper">
    <?php require_once 'includes/sidebar.php'; ?>

    <!-- Top Navigation BEFORE main-content -->
    <?php require_once 'includes/topnav.php'; ?>
<?php endif; ?>

    <main class="main-content" <?php echo $isMinimal ? 'style="margin-left:0; width:100%; padding:0; background:#f8fafc;"' : ''; ?>>
        <!-- Add a spacer div or extra top padding to avoid fixed header overlap -->
        <div class="report-container" style="padding: <?php echo $isMinimal ? '20px' : '30px'; ?>; margin-top: <?php echo $isMinimal ? '0' : '20px'; ?>;">
            
            <!-- OFFICIAL PRINT HEADER -->
            <div class="print-only" style="display: none; flex-direction: column; align-items: center; text-align: center; margin-bottom: 40px; border-bottom: 2pt solid #000; padding-bottom: 20px;">
                <div style="font-size: 20pt; font-weight: 800; color: #000; margin-bottom: 5px; text-transform: uppercase;">NAXÇIVAN DÖVLƏT ÜNİVERSİTETİ</div>
                <div style="font-size: 14pt; font-weight: 700; color: #334155; margin-bottom: 25px;">Distant Təhsil Sistemi — Rəsmi İştirak Jurnalı</div>
                
                <div style="width: 100%; display: table; border-collapse: collapse; text-align: left; font-size: 10.5pt; color: #000;">
                    <div style="display: table-row;">
                        <div style="display: table-cell; width: 50%; padding: 6px 12px; border: 1pt solid #000;"><strong>Fənn:</strong><br><?php echo e($lesson['course_title'] ?? ''); ?></div>
                        <div style="display: table-cell; width: 50%; padding: 6px 12px; border: 1pt solid #000;"><strong>Dərs mövzusu:</strong><br><?php echo e($lesson['title'] ?? ''); ?></div>
                    </div>
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 6px 12px; border: 1pt solid #000;"><strong>İxtisas:</strong><br><?php echo e($lesson['specialization_name'] ?? 'Təyin edilməyib'); ?></div>
                        <div style="display: table-cell; padding: 6px 12px; border: 1pt solid #000;"><strong>Kurs:</strong><br><?php echo e($lesson['course_level'] ?? 'Təyin edilməyib'); ?></div>
                    </div>
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 6px 12px; border: 1pt solid #000;"><strong>Dərs Növü:</strong><br><?php echo (($lesson['lesson_type'] ?? '') == 'seminar') ? 'Seminar' : 'Mühazirə'; ?></div>
                        <div style="display: table-cell; padding: 6px 12px; border: 1pt solid #000;"><strong>Tarix:</strong><br><?php echo date('d.m.Y', strtotime($lesson['created_at'])); ?></div>
                    </div>
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 6px 12px; border: 1pt solid #000;"><strong>Başlama Vaxtı:</strong><br><?php echo date('H:i', strtotime($lesson['started_at'] ?? $lesson['start_time'])); ?></div>
                        <div style="display: table-cell; padding: 6px 12px; border: 1pt solid #000;"><strong>Dərs Müddəti:</strong><br><?php echo round($baselineDuration / 60); ?> dəqiqə</div>
                    </div>
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 6px 12px; border: 1pt solid #000;"><strong>Cəmi İştirak:</strong><br><?php echo $joinedCount; ?> / <?php echo $rosterCount; ?> nəfər</div>
                        <div style="display: table-cell; padding: 6px 12px; border: 1pt solid #000;"><strong>Generasiya:</strong><br><?php echo date('d.m.Y H:i'); ?></div>
                    </div>
                </div>
            </div>

            <div class="print-only-hide" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <div>
                    <h1 style="font-size: 26px; font-weight: 850; color: #1e293b; margin: 0; letter-spacing: -0.5px;">
                        İştirakçı Jurnalı</h1>
                    <p style="color: #64748b; margin-top: 6px; font-weight: 500; font-size: 15px;">
                        <strong>Fənn:</strong> <?php echo e($lesson['course_title'] ?? ''); ?> 
                        <span style="opacity: 0.5; margin-left: 5px;">(Dərs #<?php echo $lessonId; ?>)</span>
                    </p>
                    <p style="color: #64748b; margin-top: 2px; font-weight: 500; font-size: 14px;">
                        <strong>İxtisas:</strong> <?php echo e($lesson['specialization_name'] ?? 'Təyin edilməyib'); ?> | 
                        <strong>Kurs:</strong> <?php echo e($lesson['course_level'] ?? 'Təyin edilməyib'); ?> |
                        <strong>Dərs mövzusu:</strong> <?php echo e($lesson['title'] ?? 'Mövzu təyin edilməyib'); ?>
                    </p>
                </div>
                <?php if (!$isMinimal): ?>
                <div style="display: flex; gap: 10px;">
                    <a href="?id=<?php echo $lessonId; ?>&export=csv"
                        style="background: #10b981; color: white; padding: 10px 24px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.2s; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="sheet" style="width:16px; height:16px;"></i> Excel (CSV)
                    </a>
                    <button onclick="downloadPDF()"
                        style="background: #3b82f6; border: none; color: white; padding: 10px 24px; border-radius: 12px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2); display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="download-cloud" style="width:16px; height:16px;"></i> PDF Yüklə
                    </button>
                    <a href="javascript:history.back()"
                        style="background: white; border: 1px solid #e2e8f0; color: #475569; padding: 10px 24px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 8px;">
                        ← Geri
                    </a>
                </div>
                <?php else: ?>
                <div style="display: flex; gap: 10px;">
                    <a href="?id=<?php echo $lessonId; ?>&export=csv"
                        style="background: #10b981; color: white; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 8px;" title="Excel formatında yüklə">
                        <i data-lucide="sheet" style="width:16px; height:16px;"></i> Excel
                    </a>
                    <button onclick="downloadPDF()"
                        style="background: #3b82f6; border: none; color: white; padding: 10px 20px; border-radius: 12px; font-weight: 600; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 8px;" title="PDF yüklə">
                        <i data-lucide="download-cloud" style="width:16px; height:16px;"></i> PDF Yüklə
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Stats Summary Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 35px;">
                <div style="background: white; padding: 24px; border-radius: 24px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; display: flex; align-items: center; gap: 20px;">
                    <div style="width: 50px; height: 50px; background: #eff6ff; color: #3b82f6; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px;">👥</div>
                    <div>
                        <div style="color: #94a3b8; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">Cəmi İştirak</div>
                        <div style="font-size: 24px; font-weight: 900; color: #1e293b;"><?php echo $joinedCount; ?><span style="font-size: 14px; color: #94a3b8; font-weight: 500;"> / <?php echo $rosterCount; ?></span></div>
                    </div>
                </div>

                <div class="print-only-hide" style="background: white; padding: 24px; border-radius: 24px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; display: flex; align-items: center; gap: 20px;">
                    <div style="width: 50px; height: 50px; background: #ecfdf5; color: #10b981; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px;">🟢</div>
                    <div>
                        <div style="color: #94a3b8; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">Hazırda Canlı</div>
                        <div style="font-size: 24px; font-weight: 900; color: #1e293b;"><?php echo $onlineCount; ?></div>
                    </div>
                </div>

                <div style="background: white; padding: 24px; border-radius: 24px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; display: flex; align-items: center; gap: 20px;" title="Tələbələrin dərsdə qaldığı vaxtın orta göstəricisi">
                    <div style="width: 50px; height: 50px; background: #fef2f2; color: #ef4444; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px;">⏱️</div>
                    <div>
                        <div style="color: #94a3b8; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">Ortalama İştirak</div>
                        <div style="font-size: 24px; font-weight: 900; color: #1e293b;"><?php echo $avgMinutes; ?> <span style="font-size: 14px; color: #94a3b8; font-weight: 500;">dəq.</span></div>
                    </div>
                </div>

                <div style="background: white; padding: 24px; border-radius: 24px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; display: flex; align-items: center; gap: 20px;">
                    <div style="width: 50px; height: 50px; background: #f5f3ff; color: #7c3aed; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px;">🎬</div>
                    <div>
                        <div style="color: #94a3b8; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">Dərsin Müddəti</div>
                        <div style="font-size: 24px; font-weight: 900; color: #1e293b;"><?php echo round($baselineDuration / 60); ?> <span style="font-size: 14px; color: #94a3b8; font-weight: 500;">dəq.</span></div>
                    </div>
                </div>

                <div style="background: white; padding: 24px; border-radius: 24px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; display: flex; align-items: center; gap: 20px;">
                    <div style="width: 50px; height: 50px; background: #fffbeb; color: #f59e0b; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px;">📅</div>
                    <div>
                        <div style="color: #94a3b8; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">Tarix</div>
                        <div style="font-size: 18px; font-weight: 900; color: #1e293b;"><?php echo date('d.m.Y', strtotime($lesson['created_at'] ?? 'now')); ?></div>
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div
                style="background: white; border-radius: 24px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05);">
                <div
                    style="padding: 24px; border-bottom: 1px solid #f1f5f9; background: #fafafa; display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 20px;">📋</span>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: #334155;">Qoşulma Tarixçəsi</h3>
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                        <thead>
                            <tr style="background: #f8fafc; color: #94a3b8; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 800; border-bottom: 2px solid #f1f5f9;">
                                <th style="padding: 12px 10px;">İştirakçı</th>
                                <th style="padding: 12px 10px;">Vəzifə</th>
                                <th style="padding: 12px 10px;">Giriş</th>
                                <th style="padding: 12px 10px;">Son Görülmə</th>
                                <th style="padding: 12px 10px;">Müddət</th>
                                <th style="padding: 12px 10px;">İştirak %</th>
                                <th style="padding: 12px 10px;">Nəticə</th>
                                <th class="print-only-hide" style="padding: 12px 10px;">Status</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: 14px; color: #334155;">
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" style="padding: 60px; text-align: center; color: #94a3b8;">
                                        <div style="font-size: 40px; margin-bottom: 10px; opacity: 0.3;">📭</div>
                                        <div style="font-weight: 600;">İştirakçı qeydə alınmayıb</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <?php
                                    $fullName = ($log['last_name'] ?? '') . ' ' . ($log['first_name'] ?? '') . (!empty($log['father_name']) ? ' ' . $log['father_name'] : '');
                                    $durationMin = floor($log['total_seconds'] / 60);
                                    $progress = $baselineDuration > 0 ? round(($log['total_seconds'] / $baselineDuration) * 100) : 0;
                                    $progress = min(100, $progress); 
                                    ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" onmouseover="this.style.background='#fcfcfd'" onmouseout="this.style.background='white'">
                                        <td style="padding: 12px 10px;">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 32px; height: 32px; background: #eff6ff; color: #3b82f6; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px; flex-shrink: 0;">
                                                    <?php echo strtoupper(substr($log['first_name'], 0, 1)); ?>
                                                </div>
                                                <div style="min-width: 0; page-break-inside: avoid; break-inside: avoid;">
                                                    <div style="font-weight: 700; color: #1e293b; font-size: 13px; word-break: break-word;"><?php echo e($fullName); ?></div>
                                                    <div style="font-size: 11px; color: #94a3b8; word-break: break-all;"><?php echo e($log['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 10px;">
                                            <span style="font-size: 9px; font-weight: 800; text-transform: uppercase; color: <?php echo $log['user_role'] == 'instructor' ? '#3b82f6' : '#64748b'; ?>; background: <?php echo $log['user_role'] == 'instructor' ? '#eff6ff' : '#f1f5f9'; ?>; padding: 3px 8px; border-radius: 5px;">
                                                <?php echo $log['user_role'] == 'instructor' ? 'Müəllim' : 'Tələbə'; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px 10px; font-weight: 700; color: #10b981; font-size: 12px;">
                                            <?php echo $log['first_join'] ? date('H:i', strtotime($log['first_join'])) : '--'; ?>
                                        </td>
                                        <td style="padding: 12px 10px; font-weight: 600; color: #64748b; font-size: 12px;">
                                            <?php echo $log['last_seen'] ? date('H:i', strtotime($log['last_seen'])) : '--'; ?>
                                        </td>
                                        <td style="padding: 12px 10px; font-weight: 700; color: #1e293b; font-size: 13px;">
                                            <?php echo $durationMin; ?> <span style="font-size: 11px; font-weight: 500; color: #94a3b8;">dəq.</span>
                                        </td>
                                        <td style="padding: 12px 10px;">
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <div style="width: 40px; height: 5px; background: #f1f5f9; border-radius: 10px; overflow: hidden; flex-shrink: 0;">
                                                    <div style="height: 100%; background: #3b82f6; width: <?php echo $progress; ?>%;"></div>
                                                </div>
                                                <span style="font-size: 11px; font-weight: 700; color: #3b82f6;"><?php echo $progress; ?>%</span>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 10px;">
                                            <?php if ($log['user_role'] === 'instructor'): ?>
                                                <span style="color: #94a3b8; font-size: 11px;">--</span>
                                            <?php elseif ($progress >= 50): ?>
                                                <span style="background: #10b981; color: white; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);">
                                                    ✅ İştirak
                                                </span>
                                            <?php else: ?>
                                                <span style="background: #ef4444; color: white; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);">
                                                    ❌ Qayıb
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="print-only-hide" style="padding: 12px 10px;">
                                            <?php if ($log['is_currently_online']): ?>
                                                <div style="display: flex; align-items: center; gap: 4px;">
                                                    <span style="width: 6px; height: 6px; background: #10b981; border-radius: 50%; animation: pulse 2s infinite;"></span>
                                                    <span style="font-size: 10px; font-weight: 700; color: #059669; text-transform: uppercase;">Canlı</span>
                                                </div>
                                            <?php else: ?>
                                                <span style="font-size: 10px; color: #94a3b8; font-weight: 600; text-transform: uppercase;">Ayrılıb</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- LIFETIME ANALYTICS SECTION -->
            <div class="lifetime-analytics" style="margin-top: 60px;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 25px;">
                    <div style="width: 40px; height: 2px; background: #cbd5e1;"></div>
                    <h2 style="font-size: 20px; font-weight: 850; color: #1e293b; margin: 0; text-transform: uppercase; letter-spacing: 1px;">KURS ÜZRƏ ÜMUMİ TARİXÇƏ 📈</h2>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach($allLessons as $past): ?>
                        <?php 
                            $pastAvgDur = $past['attendee_count'] > 0 ? round(($past['total_seconds'] / $past['attendee_count']) / 60) : 0;
                            
                            if ($totalStudentsInCourse > 0) {
                                $attRate = round(($past['attendee_count'] / $totalStudentsInCourse) * 100);
                                $rateLabel = "Giriş";
                            } else {
                                // Calculate avg duration performance % based on 60 min standard
                                $attRate = min(100, round(($pastAvgDur / 60) * 100));
                                $rateLabel = "Aktivlik";
                            }
                            $isCurrent = ($past['id'] == $lessonId);
                        ?>
                        <div style="background: white; border-radius: 20px; border: 1px solid <?php echo $isCurrent ? '#3b82f6' : '#e2e8f0'; ?>; padding: 20px; position: relative; overflow: hidden; <?php echo $isCurrent ? 'box-shadow: 0 10px 20px rgba(59, 130, 246, 0.1);' : ''; ?>">
                            <?php if($isCurrent): ?>
                                <div style="position: absolute; top: 0; right: 0; background: #3b82f6; color: white; font-size: 9px; padding: 4px 12px; font-weight: 900; border-bottom-left-radius: 12px;">CARİ DƏRS</div>
                            <?php endif; ?>
                            
                            <div style="font-size: 13px; color: #94a3b8; font-weight: 700; margin-bottom: 10px;"><?php echo date('d.m.Y', strtotime($past['created_at'])); ?></div>
                            <h4 style="margin: 0 0 15px 0; font-size: 16px; font-weight: 800; color: #334155; line-height: 1.3;"><?php echo e($past['lesson_title']); ?></h4>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; background: #f8fafc; padding: 12px; border-radius: 12px;">
                                <div style="text-align: center;">
                                    <div style="font-size: 10px; color: #94a3b8; font-weight: 700; text-transform: uppercase;">Tələbə</div>
                                    <div style="font-size: 16px; font-weight: 900; color: #1e293b;"><?php echo $past['attendee_count']; ?></div>
                                </div>
                                <div style="width: 1px; height: 25px; background: #e2e8f0;"></div>
                                <div style="text-align: center;">
                                    <div style="font-size: 10px; color: #94a3b8; font-weight: 700; text-transform: uppercase;">Ortalama İştirak</div>
                                    <div style="font-size: 16px; font-weight: 900; color: #1e293b;"><?php echo $pastAvgDur; ?> <span style="font-size: 11px; font-weight: 500;">dəq.</span></div>
                                </div>
                                <div style="width: 1px; height: 25px; background: #e2e8f0;"></div>
                                <div style="text-align: center;">
                                    <div style="font-size: 10px; color: #94a3b8; font-weight: 700; text-transform: uppercase;"><?php echo $rateLabel; ?></div>
                                    <div style="font-size: 16px; font-weight: 900; color: <?php echo $attRate > 70 ? '#10b981' : ($attRate > 40 ? '#f59e0b' : '#ef4444'); ?>;"><?php echo $attRate; ?>%</div>
                                </div>
                            </div>

                            <a href="attendance_report.php?id=<?php echo $past['id']; ?>" style="display: block; width: 100%; text-align: center; padding: 10px; background: #f1f5f9; color: #475569; border-radius: 10px; font-size: 12px; font-weight: 700; text-decoration: none; transition: 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">Detallara Bax</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- OFFICIAL FOOTER FOR PRINT ONLY -->
            <div class="print-only" style="display: none; margin-top: 60px; justify-content: space-between; align-items: flex-end; padding: 0 20px;">
                <div style="text-align: center; width: 300px;">
                    <p style="font-weight: 800; margin-bottom: 50px; color: #1e293b; font-size: 12pt;">Tərtib edən müəllimin imzası:</p>
                    <div style="border-bottom: 1.5pt solid #000; width: 100%; margin-bottom: 5px;"></div>
                    <p style="font-size: 11pt; font-weight: 700; color: #000;"><?php echo e($auth->getCurrentUser()['first_name'] . ' ' . $auth->getCurrentUser()['last_name']); ?></p>
                </div>

                <div style="text-align: right; width: 300px;">
                    <p style="font-weight: 800; margin-bottom: 50px; color: #1e293b; font-size: 12pt;">Tarix:</p>
                    <div style="border-bottom: 1.5pt solid #000; width: 100%; margin-bottom: 5px;"></div>
                    <p style="font-size: 11pt; font-weight: 700; color: #000;"><?php echo date('d.m.Y H:i'); ?></p>
                </div>
            </div>
        </div>
    </main>
<?php if (!$isMinimal): ?>
</div>
<?php endif; ?>

<style>
    @keyframes pulse {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.3); opacity: 0.7; }
        100% { transform: scale(1); opacity: 1; }
    }

    @media print {
        @page { size: A4; margin: 1cm; }
        body { background: white !important; font-family: 'Times New Roman', serif !important; color: #000 !important; }
        
        /* Hide UI elements absolutely */
        .sidebar, .topnav, .print-only-hide, .lifetime-analytics,
        [data-lucide], .lucide, 
        a[href*="export=csv"], button[onclick*="downloadPDF"],
        .report-container > div:nth-child(3), /* Stats cards summary hide */
        div[style*="margin-top: 60px"], /* Lifetime analytics hide (fallback) */
        script { display: none !important; }
        
        /* Layout resets */
        .main-wrapper, .app-container { display: block !important; padding: 0 !important; margin: 0 !important; }
        .main-content { margin: 0 !important; width: 100% !important; padding: 0 !important; background: white !important; float: none !important; }
        .report-container { padding: 0 !important; margin: 0 !important; box-shadow: none !important; width: 100% !important; display: block !important; }
        
        /* Show print-only header/footer */
        .print-only { display: flex !important; visibility: visible !important; }
        
        /* Table and Content Visibility */
        div[style*="background: white"] { border: none !important; box-shadow: none !important; border-radius: 0 !important; }
        .report-container > div:last-child { display: block !important; visibility: visible !important; width: 100% !important; margin-top: 20px !important; }
        
        table { width: 100% !important; border-collapse: collapse !important; border: 1pt solid #000 !important; margin-top: 10px !important; display: table !important; }
        th { background: #f0f0f0 !important; border: 1pt solid #000 !important; padding: 10px !important; font-weight: bold !important; font-size: 10pt !important; color: #000 !important; -webkit-print-color-adjust: exact; }
        td { border: 1pt solid #000 !important; padding: 8px !important; font-size: 10pt !important; color: #000 !important; background: white !important; }
        
        tr { page-break-inside: avoid !important; break-inside: avoid !important; }
        
        /* Content Refinement */
        div[style*="width: 44px; height: 44px"], span[style*="width: 7px; height: 7px"] { display: none !important; }
        div[style*="height: 6px; background: #f1f5f9"] { display: none !important; }
        span { background: transparent !important; color: #000 !important; padding: 0 !important; font-weight: bold !important; border: none !important; }
        
        /* Ensure table is visible even if inside overflow div */
        div[style*="overflow-x: auto"] { overflow: visible !important; max-width: none !important; }
        
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>


<!-- PDF Generation Script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF() {
    const element = document.querySelector('.report-container');
    const courseTitle = "<?php echo addslashes($lesson['course_title']); ?>";
    const lessonDate = "<?php echo date('d_m_Y', strtotime($lesson['created_at'])); ?>";
    const fileName = `Ishtirak_Jurnali_${courseTitle.replace(/[^a-z0-9]/gi, '_')}_${lessonDate}.pdf`;

    // Show elements that are print-only, hide non-print ones and history
    const printElements = document.querySelectorAll('.print-only');
    const hideElements = document.querySelectorAll('.print-only-hide, .lifetime-analytics');
    
    printElements.forEach(el => el.style.display = 'flex');
    hideElements.forEach(el => el.style.display = 'none');

    const opt = {
        margin: [10, 10, 10, 10],
        filename: fileName,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { 
            scale: 2, 
            useCORS: true, 
            letterRendering: true,
            scrollX: 0,
            scrollY: 0
        },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['avoid-all', 'css'] }
    };

    // New Promise based usage:
    html2pdf().set(opt).from(element).save().then(() => {
        // Revert changes
        printElements.forEach(el => el.style.display = 'none');
        hideElements.forEach(el => el.style.display = 'flex');
        
        // Ensure lifetime-analytics is restored correctly
        const analytics = document.querySelector('.lifetime-analytics');
        if (analytics) analytics.style.display = 'block';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>