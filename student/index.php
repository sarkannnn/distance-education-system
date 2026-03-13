<?php
/**
 * Dashboard - İdarəetmə Paneli
 * TMİS API inteqrasiyası ilə.
 * API uğursuz olduqda lokal bazaya fallback edir.
 */
$currentPage = 'dashboard';
$pageTitle = 'İdarəetmə Paneli';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireLogin();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// =========================================================================
//  1. DASHBOARD STATİSTİKALARI
//     API: GET /api/student/dashboard-stats
// =========================================================================
$stats = [
    'onlineTotal' => 0,
    'liveThisWeek' => 0,
    'liveThisMonth' => 0,
    'totalArchives' => 0
];

// 1. Lokal bazadan ilkin statistikalar (Həmişə hesabla ki, dinamik olsun)
try {
    // TMİS-dən tələbənin fənlərini alaq
    $tmisSubjects = tmis_get('/student/subjects');
    $allCourseIds = [];
    if ($tmisSubjects && is_array($tmisSubjects)) {
        foreach ($tmisSubjects as $s) {
            if (isset($s['id']))
                $allCourseIds[] = (int) $s['id'];
        }
    }

    // Lokal enrollments-ləri də əlavə edək (fallback)
    $localEnrollments = $db->fetchAll("SELECT course_id FROM enrollments WHERE user_id = ?", [$currentUser['id']]);
    foreach ($localEnrollments as $e) {
        $allCourseIds[] = (int) $e['course_id'];
    }
    $allCourseIds = array_unique($allCourseIds);
    $_SESSION['my_course_ids'] = $allCourseIds;

    // Kurs sayı
    $stats['onlineTotal'] = count($allCourseIds);

    if (!empty($allCourseIds)) {
        $courseIdsList = implode(',', $allCourseIds);

        $courseMatchSql = "(course_id IN ($courseIdsList)";
        foreach ($allCourseIds as $cid) {
            $courseMatchSql .= " OR FIND_IN_SET(" . (int)$cid . ", stream_course_ids)";
        }
        $courseMatchSql .= ")";

        $liveWeek = $db->fetch(
            "SELECT COUNT(id) as count FROM live_classes 
             WHERE $courseMatchSql
             AND YEARWEEK(start_time, 1) = YEARWEEK(CURDATE(), 1)"
        );
        $stats['liveThisWeek'] = $liveWeek['count'] ?? 0;

        $liveMonth = $db->fetch(
            "SELECT COUNT(id) as count FROM live_classes 
             WHERE $courseMatchSql
             AND MONTH(start_time) = MONTH(CURRENT_DATE()) 
             AND YEAR(start_time) = YEAR(CURRENT_DATE())"
        );
        $stats['liveThisMonth'] = $liveMonth['count'] ?? 0;
    }

    if (!empty($allCourseIds)) {
        $archiveCountLive = $db->fetch(
            "SELECT COUNT(*) as count FROM live_classes lc
             WHERE $courseMatchSql
             AND lc.recording_path IS NOT NULL AND lc.recording_path != ''"
        );
        $archiveCountManual = $db->fetch(
            "SELECT COUNT(*) as count FROM archived_lessons
             WHERE course_id IN ($courseIdsList)"
        );
        $stats['totalArchives'] = ($archiveCountLive['count'] ?? 0) + ($archiveCountManual['count'] ?? 0);
    }
} catch (Exception $e) {
    // silently use defaults
}

// 2. TMİS API-dən məlumatları al və (lazım olsa) üzərinə yaz
$tmisStats = tmis_get('/student/dashboard-stats');
if ($tmisStats) {
    // Ümumi kurs və arxiv saylarını TMİS-dən gələn daha dəqiq ola bilər
    if (isset($tmisStats['total_courses']) && $tmisStats['total_courses'] > 0)
        $stats['onlineTotal'] = $tmisStats['total_courses'];

    if (isset($tmisStats['total_archives']) && $tmisStats['total_archives'] > 0)
        $stats['totalArchives'] = $tmisStats['total_archives'];

    // QEYD: live_this_week və live_this_month üçün TMİS-dən gələn statik ola bildiyi üçün, 
    // yuxarıda hesabladığımız lokal dinamik dəyərləri saxlayırıq.
}

// =========================================================================
//  2. BU GÜNÜN CƏDVƏLİ
//     API: GET /api/student/schedule/today
// =========================================================================
// CHANGE: Show only currently live lessons in "Today's Schedule"
// Remove random/sample lessons and filter the list to include only lessons where $isLive === true.
// If no live lesson exists, display the message: "Hazırda aktiv onlayn dərs yoxdur."
$todaySchedule = [];

$tmisToday = tmis_get('/student/schedule/today');
if ($tmisToday && is_array($tmisToday)) {
    foreach ($tmisToday as $item) {
        $isLive = (!empty($item['live_class_id']) || ($item['status'] ?? '') === 'in-progress');
        if (!$isLive) continue;
        
        $startTime = $item['start_time'] ?? '10:00';
        $endTime = $item['end_time'] ?? '11:30';

        $todaySchedule[] = [
            'id' => $item['id'] ?? 0,
            'live_class_id' => $item['live_class_id'] ?? null,
            'time' => $startTime . ' - ' . $endTime,
            'course' => $item['course_title'] ?? 'Fənn',
            'instructor' => $item['instructor_name'] ?? 'Müəllim',
            'type' => $isLive ? 'live' : ($item['lesson_type'] ?? 'lecture'),
            'status' => $item['status'] ?? 'scheduled'
        ];
    }
} else {
    // Fallback: lokal bazadan
    try {
        $weekdays = [
            1 => 'Bazar ertəsi',
            2 => 'Çərşənbə axşamı',
            3 => 'Çərşənbə',
            4 => 'Cümə axşamı',
            5 => 'Cümə',
            6 => 'Şənbə',
            0 => 'Bazar'
        ];
        $todayWeekday = $weekdays[date('w')];

        if (!empty($allCourseIds)) {
            $coursesData = $db->fetchAll(
                "SELECT c.id, c.title, c.created_at, c.start_time, c.weekly_days,
                        u.first_name, u.last_name,
                        lc.id as live_class_id, lc.status as live_status
                 FROM courses c
                 LEFT JOIN instructors i ON c.instructor_id = i.id
                 LEFT JOIN users u ON i.user_id = u.id
                 LEFT JOIN live_classes lc ON lc.course_id = c.id AND lc.status = 'live'
                 WHERE c.status = 'active' AND c.id IN ($courseIdsList)
                 AND (c.weekly_days LIKE ? OR c.weekly_days IS NULL OR lc.id IS NOT NULL)
                 ORDER BY (lc.id IS NOT NULL) DESC, c.start_time ASC",
                ['%' . $todayWeekday . '%']
            );
        } else {
            $coursesData = [];
        }

        foreach ($coursesData as $row) {
            $instructorName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if (empty($instructorName)) {
                $instructorName = 'Müəllim';
            }

            $isLive = !empty($row['live_class_id']) && $row['live_status'] === 'live';
            if (!$isLive) continue;

            $startTime = $row['start_time'] ? date('H:i', strtotime($row['start_time'])) : '10:00';
            $endTime = $row['start_time'] ? date('H:i', strtotime($row['start_time'] . ' +90 minutes')) : '11:30';

            $todaySchedule[] = [
                'id' => $row['id'],
                'live_class_id' => $row['live_class_id'] ?? null,
                'time' => $startTime . ' - ' . $endTime,
                'course' => $row['title'],
                'instructor' => 'Prof. ' . $instructorName,
                'type' => $isLive ? 'live' : 'lecture',
                'status' => $isLive ? 'in-progress' : 'scheduled'
            ];
        }
    } catch (Exception $e) {
        $todaySchedule = [];
    }
}

// =========================================================================
//  3. SON ARXİV MATERİALLARI
//     API: GET /api/student/recent-archives
// =========================================================================
$recentActivities = [];

$tmisRecent = tmis_get('/student/recent-archives');
if ($tmisRecent && is_array($tmisRecent)) {
    foreach ($tmisRecent as $item) {
        $type = ($item['type'] ?? 'manual') === 'live' ? 'lesson' : 'material';
        $duration = '';
        if (isset($item['duration_minutes']) && $item['duration_minutes']) {
            $duration = $item['duration_minutes'] . ' dəq';
        } else {
            $duration = 'Material';
        }

        $recentActivities[] = [
            'id' => $item['id'] ?? 0,
            'type' => $type,
            'title' => $item['title'] ?? 'Material',
            'date' => isset($item['date']) ? formatDate($item['date']) : '',
            'duration' => $duration,
            'status' => $item['status'] ?? 'Tamamlandı'
        ];
    }
    // Limit to 4
    $recentActivities = array_slice($recentActivities, 0, 4);
} else {
    // Fallback: lokal bazadan
    try {
        if (!empty($allCourseIds)) {
            // Manual Arxivlər
            $archivesManual = $db->fetchAll(
                "SELECT al.id, al.title, al.created_at, c.title as course_title, 'manual' as source
                 FROM archived_lessons al
                 JOIN courses c ON al.course_id = c.id
                 WHERE al.course_id IN ($courseIdsList)
                 ORDER BY al.created_at DESC LIMIT 5"
            );

            // Canlı dərslərin yazıları
            $archivesLive = $db->fetchAll(
                "SELECT lc.id, lc.title, lc.created_at, lc.duration_minutes, c.title as course_title, 'live' as source
                 FROM live_classes lc
                 JOIN courses c ON lc.course_id = c.id
                 WHERE lc.course_id IN ($courseIdsList)
                 AND lc.recording_path IS NOT NULL AND lc.recording_path != ''
                 ORDER BY lc.created_at DESC LIMIT 5"
            );
        } else {
            $archivesManual = [];
            $archivesLive = [];
        }

        $combined = [];
        foreach ($archivesManual as $a) {
            $combined[] = [
                'id' => $a['id'],
                'type' => 'manual',
                'title' => $a['title'],
                'date' => $a['created_at'],
                'course' => $a['course_title']
            ];
        }
        foreach ($archivesLive as $a) {
            $combined[] = [
                'id' => $a['id'],
                'type' => 'live',
                'title' => !empty($a['title']) ? $a['title'] : $a['course_title'],
                'date' => $a['created_at'],
                'course' => $a['course_title'],
                'duration' => ($a['duration_minutes'] ?? 0) . ' dəq'
            ];
        }

        usort($combined, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        $combined = array_slice($combined, 0, 4);

        foreach ($combined as $item) {
            $recentActivities[] = [
                'id' => $item['id'],
                'type' => $item['type'] === 'live' ? 'lesson' : 'material',
                'title' => $item['title'],
                'date' => formatDate($item['date']),
                'duration' => $item['duration'] ?? 'Material',
                'status' => 'Tamamlandı'
            ];
        }
    } catch (Exception $e) {
        $recentActivities = [];
    }
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
                <h1>İdarəetmə Paneli</h1>
                <p>Xoş gəldiniz, <?php echo !empty($currentUser['name']) ? e($currentUser['name']) : 'Tələbə'; ?>! Bu
                    gün və bu həftə üçün tədris planınıza ümumi baxış</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid-mockup">
                <div class="stat-card-mockup orange">
                    <div class="stat-icon-mockup orange">
                        <i data-lucide="video"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $stats['onlineTotal']; ?></div>
                    <div class="stat-label-mockup orange">Tədris Olunan Fənlər</div>
                </div>

                <div class="stat-card-mockup blue">
                    <div class="stat-icon-mockup blue">
                        <i data-lucide="calendar"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $stats['liveThisWeek']; ?></div>
                    <div class="stat-label-mockup blue">Bu Həftə Canlı Dərslər</div>
                </div>

                <div class="stat-card-mockup purple">
                    <div class="stat-icon-mockup purple">
                        <i data-lucide="monitor"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $stats['liveThisMonth']; ?></div>
                    <div class="stat-label-mockup purple">Bu Ay Canlı Dərslər</div>
                </div>

                <div class="stat-card-mockup green">
                    <div class="stat-icon-mockup green">
                        <i data-lucide="archive"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $stats['totalArchives']; ?></div>
                    <div class="stat-label-mockup green">Arxiv Materialları</div>
                </div>
            </div>

            <div class="grid-2">
                <!-- Today's Schedule -->
                <div class="card">
                    <div class="card-header">
                        <i data-lucide="calendar"></i>
                        <h2>Bu günün cədvəli</h2>
                    </div>

                    <div class="space-y-4">
                        <?php if (empty($todaySchedule)): ?>
                            <div style="padding: 40px 20px; text-align: center;">
                                <div
                                    style="width: 60px; height: 60px; background: var(--gray-50); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                                    <i data-lucide="video-off"
                                        style="width: 28px; height: 28px; color: var(--text-muted);"></i>
                                </div>
                                <p style="color: var(--text-muted); font-size: 14px;">Hazırda aktiv onlayn dərs yoxdur.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($todaySchedule as $lesson):
                                $isLive = ($lesson['status'] === 'in-progress' || $lesson['type'] === 'live') && !empty($lesson['live_class_id']);
                                $tag = $isLive ? 'a' : 'div';
                                $href = $isLive ? 'href="live-view.php?id=' . $lesson['live_class_id'] . '"' : '';
                                ?>
                                <<?php echo $tag; ?>         <?php echo $href; ?> class="schedule-item
                                    <?php echo $lesson['status']; ?>         <?php echo $isLive ? 'hover-scale' : ''; ?>"
                                    style="<?php echo $isLive ? 'display: block; text-decoration: none; border-left: 4px solid var(--error);' : ''; ?>">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <?php if ($isLive): ?>
                                                <div class="schedule-time" style="margin-bottom: 8px;">
                                                    <span class="badge badge-live">
                                                        <i data-lucide="video"></i>
                                                        Canlı
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            <h3 class="schedule-title" style="margin-bottom: 0;">
                                                <?php echo e($lesson['course']); ?>
                                            </h3>
                                        </div>
                                        <div>
                                            <?php if ($lesson['status'] === 'completed'): ?>
                                                <i data-lucide="check-circle" style="color: var(--success);"></i>
                                            <?php elseif ($isLive): ?>
                                                <button class="btn btn-sm btn-danger"
                                                    style="border-radius: 20px; padding: 6px 16px;">
                                                    Qoşul
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </<?php echo $tag; ?>>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>



                <!-- Recent Activities -->
                <div class="card">
                    <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 20px;">Son Arxiv Materialları</h2>

                    <div class="space-y-4"
                        style="max-height: 380px; overflow-y: auto; padding-right: 8px; scrollbar-width: thin; scrollbar-color: var(--gray-200) transparent;">
                        <style>
                            .space-y-4::-webkit-scrollbar {
                                width: 5px;
                            }

                            .space-y-4::-webkit-scrollbar-track {
                                background: transparent;
                            }

                            .space-y-4::-webkit-scrollbar-thumb {
                                background: var(--gray-200);
                                border-radius: 10px;
                            }

                            .space-y-4::-webkit-scrollbar-thumb:hover {
                                background: var(--gray-300);
                            }
                        </style>
                        <?php if (empty($recentActivities)): ?>
                            <div style="padding: 40px 20px; text-align: center;">
                                <p style="color: var(--text-muted); font-size: 14px;">Hələ ki, arxiv materialı yoxdur.
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="schedule-item" style="background: var(--gray-50); margin-bottom: 12px;">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: <?php
                                            echo $activity['type'] === 'lesson' ? 'rgba(59, 130, 246, 0.1)' : 'rgba(249, 115, 22, 0.1)';
                                            ?>;">
                                                <i data-lucide="<?php echo $activity['type'] === 'lesson' ? 'video' : 'file-text'; ?>"
                                                    style="color: <?php echo $activity['type'] === 'lesson' ? '#3b82f6' : '#f97316'; ?>; width: 20px; height: 20px;"></i>
                                            </div>
                                            <div>
                                                <h3 style="font-weight: 500; margin-bottom: 2px;">
                                                    <?php echo e($activity['title']); ?>
                                                </h3>
                                                <p style="font-size: 13px; color: var(--text-muted);">
                                                    <?php echo $activity['date']; ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <?php if (isset($activity['duration'])): ?>
                                                <div style="font-size: 14px; color: var(--text-muted);">
                                                    <?php echo $activity['duration']; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div style="font-size: 12px; color: var(--text-muted); text-transform: capitalize;">
                                                <?php echo $activity['status']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>

</script>

<style>
    @keyframes slideDown {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
</style>

<?php require_once 'includes/footer.php'; ?>