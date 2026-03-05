<?php
/**
 * Teacher Analytics - Analitika və Statistikalar
 */
$currentPage = 'analytics';
$pageTitle = 'Analitika və Statistikalar';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

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
require_once 'includes/tmis_api.php';
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

// ============================================================
// 1. Course Performance — live_classes-dən course_id üzrə qruplaşdır
// ============================================================
$coursePerformance = [];

try {
    // Müəllimin bütün bitmiş dərslərini course_id üzrə qruplaşdır
    if ($isAdmin) {
        $whereLP = "lc.status IN ('ended', 'completed')";
        $paramsLP = [];
    } else {
        $idPlaceholder = implode(',', array_fill(0, count($myTeacherIds), '?'));
        $whereLP = "lc.status IN ('ended', 'completed') AND lc.instructor_id IN ($idPlaceholder)";
        $paramsLP = $myTeacherIds;
    }

    $courseStats = $db->fetchAll("
        SELECT 
            lc.course_id,
            COUNT(*) as total_lessons,
            COUNT(CASE WHEN lc.lesson_type = 'lecture' THEN 1 END) as lecture_count,
            COUNT(CASE WHEN lc.lesson_type = 'seminar' THEN 1 END) as seminar_count,
            COUNT(CASE WHEN lc.lesson_type = 'lab' THEN 1 END) as lab_count
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
        $activeStudents = 0; // will be recalculated below

        // Davamiyyət: Hər dərs üçün (qoşulan tələbə / ümumi tələbə) hesabla, ortalamasını al
        $attendance = 0;
        if ($totalStudents > 0) {
            // Hər bitmiş dərs üçün qoşulma sayını al
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
                $uniqueStudents = [];

                foreach ($perLessonAttendance as $pla) {
                    $rate = min(100, round(($pla['joined_count'] / $totalStudents) * 100));
                    $totalRate += $rate;
                    $lessonCountForAtt++;
                }

                $attendance = $lessonCountForAtt > 0 ? round($totalRate / $lessonCountForAtt) : 0;

                // Unique active students across all lessons
                $uniqueStudentCount = $db->fetch("
                    SELECT COUNT(DISTINCT la.user_id) as cnt
                    FROM live_attendance la
                    JOIN live_classes lc ON la.live_class_id = lc.id
                    WHERE lc.course_id = ? AND la.role = 'student'
                ", [$cId]);
                $activeStudents = (int) ($uniqueStudentCount['cnt'] ?? 0);
            }
        } else {
            // Əgər totalStudents 0-dırsa, aktiv tələbə sayını tap və ona görə hesabla
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

        // Planlı dərs saylarını TMIS-dən al
        $mTotal = (int) ($sInfo['subject_lecture_time'] ?? $mDone);
        $sTotal = (int) ($sInfo['subject_seminar_time'] ?? $sDone);
        $lTotal = (int) ($sInfo['subject_lab_time'] ?? $lDone);
        $totalPlanned = $mTotal + $sTotal + $lTotal;
        if ($totalPlanned == 0)
            $totalPlanned = $doneLessons;

        $completion = $totalPlanned > 0 ? round(($doneLessons / $totalPlanned) * 100) : 0;
        if ($completion > 100)
            $completion = 100;

        $status = 'Normal';
        if ($attendance >= 80 && $completion >= 50)
            $status = 'Əla';
        elseif ($attendance >= 60 || $completion >= 30)
            $status = 'Yaxşı';
        elseif ($attendance < 30)
            $status = 'Kritik';

        $coursePerformance[] = [
            'id' => $cId,
            'course' => $courseName,
            'profession_name' => $sInfo['profession_name'] ?? '',
            'course_level' => $sInfo['course'] ?? '',
            'instructor_name' => '',
            'total_students' => $totalStudents,
            'active_students' => $activeStudents,
            'm_done' => $mDone,
            'm_total' => $mTotal,
            's_done' => $sDone,
            's_total' => $sTotal,
            'l_done' => $lDone,
            'l_total' => $lTotal,
            'done_lessons' => $doneLessons,
            'total_lessons' => $totalPlanned,
            'attendance' => $attendance,
            'completion' => $completion,
            'status' => $status
        ];
    }
} catch (Exception $e) {
    error_log('Analytics Course Performance xətası: ' . $e->getMessage());
}

// ============================================================
// 2. Metrics (stats cards)
// ============================================================
$metrics = [];

try {
    // Aktiv Dərslər — bitmiş onlayn dərs sayı
    if ($isAdmin) {
        $activeClassesCount = $db->fetch("SELECT COUNT(*) as cnt FROM live_classes WHERE status IN ('ended', 'completed')");
    } else {
        $idPh = implode(',', array_fill(0, count($myTeacherIds), '?'));
        $activeClassesCount = $db->fetch("SELECT COUNT(*) as cnt FROM live_classes WHERE status IN ('ended', 'completed') AND instructor_id IN ($idPh)", $myTeacherIds);
    }

    // Arxiv Materialları — qeydə alınmış videolar + arxivlər
    $archiveCount = 0;
    if ($isAdmin) {
        $liveRec = $db->fetch("SELECT COUNT(*) as cnt FROM live_classes WHERE recording_path IS NOT NULL AND recording_path != ''");
        $archRec = $db->fetch("SELECT COUNT(*) as cnt FROM archived_lessons");
    } else {
        $liveRec = $db->fetch("SELECT COUNT(*) as cnt FROM live_classes WHERE recording_path IS NOT NULL AND recording_path != '' AND instructor_id IN ($idPh)", $myTeacherIds);
        $archRec = $db->fetch("SELECT COUNT(*) as cnt FROM archived_lessons WHERE instructor_id IN ($idPh)", $myTeacherIds);
    }
    $archiveCount = ($liveRec['cnt'] ?? 0) + ($archRec['cnt'] ?? 0);

    // Ortalama Davamiyyət
    $attendanceRate = 0;
    if (count($coursePerformance) > 0) {
        $totalAttRate = 0;
        foreach ($coursePerformance as $cp) {
            $totalAttRate += $cp['attendance'];
        }
        $attendanceRate = round($totalAttRate / count($coursePerformance));
    }

    // Ümumi baxışlar (yalnız archived_lessons-da views var, live_classes-da yoxdur)
    if ($isAdmin) {
        $archViews = $db->fetch("SELECT COALESCE(SUM(views), 0) as total FROM archived_lessons");
    } else {
        $archViews = $db->fetch("SELECT COALESCE(SUM(views), 0) as total FROM archived_lessons WHERE instructor_id IN ($idPh)", $myTeacherIds);
    }
    $totalViewsAll = (int) ($archViews['total'] ?? 0);

    $metrics = [
        ['label' => 'Aktiv Dərslər', 'value' => $activeClassesCount['cnt'] ?? 0, 'icon' => 'book-open', 'color' => '#0E5995', 'trend' => 'Ümumi', 'trend_color' => 'var(--text-muted)'],
        ['label' => 'Arxiv Materialları', 'value' => $archiveCount, 'icon' => 'archive', 'color' => '#4545F6', 'trend' => 'PDF və Video', 'trend_color' => 'var(--text-muted)'],
        ['label' => 'Ortalama Davamiyyət', 'value' => $attendanceRate . '%', 'icon' => 'users', 'color' => '#10b981', 'trend' => 'Tələbə iştirakı', 'trend_color' => '#10b981'],
        ['label' => 'Ümumi Baxışlar', 'value' => $totalViewsAll, 'icon' => 'eye', 'color' => '#f59e0b', 'trend' => 'Arxivlərə', 'trend_color' => 'var(--text-muted)'],
    ];
} catch (Exception $e) {
    error_log('Analytics Metrics xətası: ' . $e->getMessage());
    $metrics = [
        ['label' => 'Aktiv Dərslər', 'value' => 0, 'icon' => 'book-open', 'color' => '#0E5995', 'trend' => 'Ümumi', 'trend_color' => 'var(--text-muted)'],
        ['label' => 'Arxiv Materialları', 'value' => 0, 'icon' => 'archive', 'color' => '#4545F6', 'trend' => 'PDF və Video', 'trend_color' => 'var(--text-muted)'],
        ['label' => 'Ortalama Davamiyyət', 'value' => '0%', 'icon' => 'users', 'color' => '#10b981', 'trend' => 'Tələbə iştirakı', 'trend_color' => '#10b981'],
        ['label' => 'Ümumi Baxışlar', 'value' => 0, 'icon' => 'eye', 'color' => '#f59e0b', 'trend' => 'Arxivlərə', 'trend_color' => 'var(--text-muted)'],
    ];
}

// ============================================================
// 3. Weekly Stats (Chart)
// ============================================================
$weeklyStats = [
    1 => ['id' => 'B.e', 'attendance' => 0, 'performance' => 0],
    2 => ['id' => 'Ç.a', 'attendance' => 0, 'performance' => 0],
    3 => ['id' => 'Çər', 'attendance' => 0, 'performance' => 0],
    4 => ['id' => 'C.a', 'attendance' => 0, 'performance' => 0],
    5 => ['id' => 'Cüm', 'attendance' => 0, 'performance' => 0],
    6 => ['id' => 'Şən', 'attendance' => 0, 'performance' => 0],
    7 => ['id' => 'Baz', 'attendance' => 0, 'performance' => 0]
];

try {
    $startOfWeek = date('Y-m-d', strtotime('monday this week'));
    $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

    if ($isAdmin) {
        $whereW = "1=1";
        $paramsW = [];
    } else {
        $idPh = implode(',', array_fill(0, count($myTeacherIds), '?'));
        $whereW = "lc.instructor_id IN ($idPh)";
        $paramsW = $myTeacherIds;
    }

    $dailyAtt = $db->fetchAll("
        SELECT DAYOFWEEK(lc.start_time) as day_idx, COUNT(DISTINCT la.user_id) as student_count
        FROM live_attendance la
        JOIN live_classes lc ON la.live_class_id = lc.id
        WHERE ({$whereW}) AND lc.start_time >= ? AND lc.start_time <= ? AND la.role = 'student'
        GROUP BY DAYOFWEEK(lc.start_time)
    ", array_merge($paramsW, [$startOfWeek . ' 00:00:00', $endOfWeek . ' 23:59:59']));

    $dailyPerf = $db->fetchAll("
        SELECT DAYOFWEEK(lc.start_time) as day_idx, COUNT(*) as lesson_count
        FROM live_classes lc
        WHERE ({$whereW}) AND lc.start_time >= ? AND lc.start_time <= ? AND lc.status IN ('ended', 'completed')
        GROUP BY DAYOFWEEK(lc.start_time)
    ", array_merge($paramsW, [$startOfWeek . ' 00:00:00', $endOfWeek . ' 23:59:59']));

    $dayMap = [2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 1 => 7];
    foreach ($dailyAtt as $da) {
        $idx = $dayMap[$da['day_idx']] ?? null;
        if ($idx)
            $weeklyStats[$idx]['attendance'] = $da['student_count'];
    }
    foreach ($dailyPerf as $dp) {
        $idx = $dayMap[$dp['day_idx']] ?? null;
        if ($idx)
            $weeklyStats[$idx]['performance'] = $dp['lesson_count'];
    }
} catch (Exception $e) {
    error_log('Analytics Weekly xətası: ' . $e->getMessage());
}

// Convert to percentage for bar heights (0-100)
$maxS = 1;
$maxL = 1;
foreach ($weeklyStats as $ws) {
    if ($ws['attendance'] > $maxS)
        $maxS = $ws['attendance'];
    if ($ws['performance'] > $maxL)
        $maxL = $ws['performance'];
}
foreach ($weeklyStats as &$ws) {
    $ws['att_pct'] = ($ws['attendance'] / $maxS) * 90;
    $ws['perf_pct'] = ($ws['performance'] / $maxL) * 90;
}

require_once 'includes/header.php';
?>

<!-- Sidebar -->
<?php require_once 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-wrapper">
    <!-- Top Navigation -->
    <?php require_once 'includes/topnav.php'; ?>

    <!-- Main Content Area -->
    <main class="main-content">
        <div class="content-container space-y-6">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Analitika və Statistikalar</h1>
                <p>Cari həftə üzrə real dərs analizi və iştirak məlumatları</p>
            </div>

            <!-- Metrics Grid -->
            <div class="stats-grid-mockup">
                <?php
                $mockupColors = ['pink', 'blue', 'green', 'purple'];
                foreach ($metrics as $index => $m):
                    $colorClass = $mockupColors[$index % count($mockupColors)];
                    ?>
                    <div class="stat-card-mockup <?php echo $colorClass; ?>">
                        <div class="stat-icon-mockup <?php echo $colorClass; ?>">
                            <i data-lucide="<?php echo $m['icon']; ?>"></i>
                        </div>
                        <div class="stat-value-mockup">
                            <?php echo $m['value']; ?>
                        </div>
                        <div class="stat-label-mockup <?php echo $colorClass; ?>">
                            <?php echo $m['label']; ?>
                        </div>
                        <p
                            style="color: <?php echo $m['trend_color']; ?>; font-size: 14px; font-weight: 600; margin-top: 8px;">
                            <?php echo $m['trend']; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 24px; color: var(--text-primary);">
                        Həftəlik İştirak və Performans
                    </h2>
                    <div
                        style="height: 240px; background: var(--gray-50); border-radius: 12px; display: flex; align-items: flex-end; justify-content: space-around; padding: 20px;">
                        <?php
                        // Show first 5 days (Mon-Fri)
                        for ($i = 1; $i <= 5; $i++):
                            $ws = $weeklyStats[$i];
                            $h1 = $ws['att_pct'] ?? 0;
                            $h2 = $ws['perf_pct'] ?? 0;
                            ?>
                            <div style="text-align: center; width: 40px;">
                                <div
                                    style="display: flex; align-items: flex-end; gap: 4px; height: 160px; margin-bottom: 8px;">
                                    <div style="width: 14px; background: var(--accent); height: <?php echo max(5, $h1); ?>%; border-radius: 4px 4px 0 0; <?php if ($h1 == 0)
                                            echo 'opacity: 0.2;'; ?>" title="<?php echo $ws['attendance']; ?> tələbə">
                                    </div>
                                    <div style="width: 14px; background: var(--primary); height: <?php echo max(5, $h2); ?>%; border-radius: 4px 4px 0 0; <?php if ($h2 == 0)
                                            echo 'opacity: 0.2;'; ?>" title="<?php echo $ws['performance']; ?> dərs">
                                    </div>
                                </div>
                                <span style="font-size: 11px; color: var(--text-muted);">
                                    <?php echo $ws['id']; ?>
                                </span>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <div class="flex gap-4 mt-4 justify-center">
                        <div class="flex items-center gap-2">
                            <div style="width: 10px; height: 10px; border-radius: 2px; background: var(--accent);">
                            </div>
                            <span style="font-size: 12px; color: var(--text-muted);">İştirak</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div style="width: 10px; height: 10px; border-radius: 2px; background: var(--primary);">
                            </div>
                            <span style="font-size: 12px; color: var(--text-muted);">Performans</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="flex flex-column mb-6">
                        <h2 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
                            Tələbə
                            İştirakı (Davamiyyət)</h2>
                        <p style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Tələbələrin dərslərə
                            orta qoşulma
                            səviyyəsi</p>
                    </div>

                    <div
                        style="height: 240px; background: var(--gray-50); border-radius: 16px; padding: 20px; display: flex; flex-direction: column; gap: 18px; border: 1px solid var(--border-color);">
                        <?php if (empty($coursePerformance)): ?>
                            <p style="text-align: center; color: #94A3B8; margin-top: 40px;">Məlumat yoxdur</p>
                        <?php else: ?>
                            <?php foreach (array_slice($coursePerformance, 0, 4) as $cp): ?>
                                <div>
                                    <div class="flex justify-between mb-2">
                                        <span
                                            style="font-size: 13px; font-weight: 600; color: var(--text-primary);"><?php echo $cp['course']; ?></span>
                                        <span
                                            style="font-size: 13px; font-weight: 800; color: #3B82F6;"><?php echo $cp['attendance']; ?>%
                                            <small
                                                style="font-weight: 500; font-size: 10px; opacity: 0.7;">iştirak</small></span>
                                    </div>
                                    <div
                                        style="height: 6px; background: var(--gray-200); border-radius: 10px; overflow: hidden;">
                                        <div
                                            style="width: <?php echo $cp['attendance']; ?>%; height: 100%; background: linear-gradient(90deg, #3B82F6, #60A5FA); border-radius: 10px;">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Detailed Course Statistics Table -->
            <div class="card"
                style="padding: 0; overflow: hidden; border-radius: 20px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.06); background: var(--bg-white);">
                <div
                    style="padding: 24px 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="font-size: 18px; font-weight: 700; color: var(--text-primary);">Fənn üzrə Detallı
                        Statistika</h2>
                    <button class="btn btn-secondary" onclick="window.location.href='api/download_analytics_report.php'"
                        style="font-size: 12px; height: 34px; padding: 0 12px; border-radius: 8px; cursor: pointer; background: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                        <i data-lucide="download" style="width: 14px; height: 14px; margin-right: 6px;"></i>
                        Hesabatı yüklə
                    </button>
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; min-width: 900px;">
                        <thead>
                            <tr style="background: var(--gray-50); border-bottom: 1px solid var(--border-color);">
                                <th
                                    style="text-align: left; padding: 18px 30px; width: 35%; font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.05em;">
                                    Fənn Adı</th>
                                <th
                                    style="text-align: left; padding: 18px 15px; width: 15%; font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.05em;">
                                    Tələbələr</th>
                                <th
                                    style="text-align: left; padding: 18px 15px; width: 15%; font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.05em;">
                                    Dərs Sayı</th>
                                <th
                                    style="text-align: left; padding: 18px 15px; width: 15%; font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.05em;">
                                    Davamiyyət</th>
                                <th
                                    style="text-align: center; padding: 18px 30px; width: 10%; font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.05em;">
                                    Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($coursePerformance)): ?>
                                <tr>
                                    <td colspan="6" style="padding: 60px; text-align: center; color: #94A3B8;">Hələlik heç
                                        bir məlumat yoxdur.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($coursePerformance as $index => $cp):
                                    $gradientList = [
                                        ['#4A90E2', '#357ABD'],
                                        ['#10B981', '#059669'],
                                        ['#F59E0B', '#D97706'],
                                        ['#8B5CF6', '#7C3AED'],
                                        ['#EF4444', '#DC2626']
                                    ];
                                    $grad = $gradientList[$index % count($gradientList)];
                                    ?>
                                    <tr style="border-bottom: 1px solid var(--gray-50); transition: all 0.2s;"
                                        onmouseover="this.style.background='var(--gray-100)'"
                                        onmouseout="this.style.background='transparent'">
                                        <td style="padding: 20px 30px;">
                                            <div style="display: flex; align-items: center; gap: 15px;">
                                                <div
                                                    style="width: 50px; height: 50px; border-radius: 14px; background: linear-gradient(135deg, <?php echo $grad[0]; ?>, <?php echo $grad[1]; ?>); display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                                    <span
                                                        style="font-weight: 800; font-size: 20px;"><?php echo mb_substr($cp['course'], 0, 1); ?></span>
                                                </div>
                                                <div>
                                                    <div
                                                        style="font-weight: 700; color: var(--text-primary); font-size: 14px; margin-bottom: 2px;">
                                                        <?php echo $cp['course']; ?>
                                                    </div>
                                                    <?php if (!empty($cp['profession_name']) || !empty($cp['course_level'])): ?>
                                                        <div
                                                            style="font-size: 11px; color: #6366F1; font-weight: 600; display: flex; align-items: center; gap: 4px; margin-bottom: 2px;">
                                                            <i data-lucide="graduation-cap" style="width: 12px; height: 12px;"></i>
                                                            <?php echo e($cp['profession_name']); ?>
                                                            <?php if (!empty($cp['course_level'])): ?>
                                                                &bull; <?php echo $cp['course_level']; ?>-cü kurs
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($isAdmin && !empty($cp['instructor_name'])): ?>
                                                        <div
                                                            style="font-size: 11px; color: var(--primary); font-weight: 700; display: flex; align-items: center; gap: 4px; margin-bottom: 2px;">
                                                            <i data-lucide="user-check" style="width: 12px; height: 12px;"></i>
                                                            <?php echo e($cp['instructor_name']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div style="font-size: 11px; color: #94A3B8; font-weight: 600;">Fənn ID:
                                                        NDU-<?php echo 1000 + ($cp['id'] ?? 0); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 20px 15px;">
                                            <div style="font-weight: 700; color: var(--text-primary); font-size: 15px;">
                                                <?php echo $cp['total_students']; ?>
                                            </div>
                                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">tələbə
                                            </div>
                                        </td>
                                        <td style="padding: 20px 15px;">
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <?php if ($cp['m_done'] > 0): ?>
                                                    <div style="font-size: 12px; color: var(--text-primary);">
                                                        <span style="font-weight: 600; color: var(--text-muted);">Mühazirə:</span>
                                                        <span
                                                            style="color: #3B82F6; font-weight: 700;"><?php echo $cp['m_done']; ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($cp['s_done'] > 0): ?>
                                                    <div style="font-size: 12px; color: var(--text-primary);">
                                                        <span style="font-weight: 600; color: var(--text-muted);">Seminar:</span>
                                                        <span
                                                            style="color: #10B981; font-weight: 700;"><?php echo $cp['s_done']; ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($cp['l_done'] > 0): ?>
                                                    <div style="font-size: 12px; color: var(--text-primary);">
                                                        <span
                                                            style="font-weight: 600; color: var(--text-muted);">Laboratoriya:</span>
                                                        <span
                                                            style="color: #8B5CF6; font-weight: 700;"><?php echo $cp['l_done']; ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($cp['m_done'] == 0 && $cp['s_done'] == 0 && $cp['l_done'] == 0): ?>
                                                    <div style="font-size: 12px; color: var(--text-muted);">0 dərs</div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="padding: 20px 15px;">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span
                                                    style="font-weight: 800; color: #F59E0B; font-size: 14px;"><?php echo $cp['attendance']; ?>%</span>
                                                <div
                                                    style="width: 40px; height: 4px; background: var(--gray-200); border-radius: 4px;">
                                                    <div
                                                        style="width: <?php echo $cp['attendance']; ?>%; height: 100%; background: #F59E0B; border-radius: 4px;">
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td style="padding: 20px 30px; text-align: center;">
                                            <?php
                                            $badgeStyle = "background: #FEE2E2; color: #EF4444;";
                                            $text = "Kritik";
                                            if ($cp['status'] === 'Əla') {
                                                $badgeStyle = "background: #DCFCE7; color: #059669;";
                                                $text = "Əla";
                                            } elseif ($cp['status'] === 'Yaxşı') {
                                                $badgeStyle = "background: #DBEAFE; color: #2563EB;";
                                                $text = "Yaxşı";
                                            } elseif ($cp['status'] === 'Normal') {
                                                $badgeStyle = "background: #FEF3C7; color: #D97706;";
                                                $text = "Normal";
                                            }
                                            ?>
                                            <span
                                                style="padding: 6px 12px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; <?php echo $badgeStyle; ?>">
                                                <?php echo $text; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>