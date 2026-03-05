<?php
/**
 * Canlı Dərslər - Live Classes
 * TMİS API inteqrasiyası ilə.
 */
$currentPage = 'live';
$pageTitle = 'Canlı Dərslər';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireLogin();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// =========================================================================
//  1. AKTİV CANLI DƏRSLƏR
//     API: GET /api/student/live-sessions/active
// =========================================================================
$liveClasses = [];

// 1. TMIS API-dən aktiv canlı dərsləri yoxla
$tmisLive = tmis_get('/student/live-sessions/active');
if ($tmisLive && is_array($tmisLive)) {
    foreach ($tmisLive as $item) {
        $liveClasses[] = [
            'id' => $item['id'] ?? 0,
            'title' => $item['title'] ?? 'Canlı Dərs',
            'course' => $item['course_title'] ?? 'Fənn',
            'instructor' => $item['instructor_name'] ?? 'Müəllim',
            'startTime' => $item['start_time'] ?? '10:00',
            'duration' => ($item['duration_minutes'] ?? 90) . ' dəqiqə',
            'participants' => $item['participants_count'] ?? 0,
            'maxParticipants' => $item['max_participants'] ?? 50,
            'status' => $item['status'] ?? 'live'
        ];
    }
}

// 2. HƏMİŞƏ lokal bazanı da yoxla (müəllim canlı dərsi lokal başladır)
//    Tələbənin fənn ID-lərini topla (TMİS + lokal enrollments)
$studentSubjectIds = [];
$tmisSubjectsForLive = tmis_get('/student/subjects');
if ($tmisSubjectsForLive && is_array($tmisSubjectsForLive)) {
    foreach ($tmisSubjectsForLive as $subj) {
        if (isset($subj['id'])) {
            $studentSubjectIds[] = intval($subj['id']);
        }
    }
}
try {
    $localEnrollments = $db->fetchAll(
        "SELECT course_id FROM enrollments WHERE user_id = ?",
        [$currentUser['id']]
    );
    foreach ($localEnrollments as $enr) {
        $studentSubjectIds[] = intval($enr['course_id']);
    }
} catch (Exception $e) {
}
$studentSubjectIds = array_unique(array_filter($studentSubjectIds));

// Lokal live_classes cədvəlindən birbaşa oxu (redundant metadata istifadə, JOIN lazım deyil)
try {
    if (!empty($studentSubjectIds)) {
        $placeholders = implode(',', array_fill(0, count($studentSubjectIds), '?'));
        $params = array_merge($studentSubjectIds, $studentSubjectIds);
        $dbLive = $db->fetchAll(
            "SELECT lc.* FROM live_classes lc 
             WHERE lc.status IN ('live', 'starting-soon', 'ending-soon')
             AND (lc.course_id IN ($placeholders) OR lc.tmis_subject_id IN ($placeholders))
             ORDER BY lc.start_time ASC",
            $params
        );
    } else {
        // Fənn filteri yoxdur — bütün aktiv canlı dərsləri göstər
        $dbLive = $db->fetchAll(
            "SELECT lc.* FROM live_classes lc 
             WHERE lc.status IN ('live', 'starting-soon', 'ending-soon')
             ORDER BY lc.start_time ASC"
        );
    }

    // TMIS nəticələri ilə birləşdir, dublikatları yoxla
    $existingIds = array_column($liveClasses, 'id');
    foreach ($dbLive as $row) {
        if (!in_array($row['id'], $existingIds)) {
            $liveClasses[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'course' => $row['subject_name'] ?: 'Fənn',
                'instructor' => trim($row['instructor_name'] ?? 'Müəllim'),
                'startTime' => date('H:i', strtotime($row['start_time'])),
                'duration' => ($row['duration_minutes'] ?: 90) . ' dəqiqə',
                'participants' => 0,
                'maxParticipants' => $row['max_participants'] ?? 100,
                'status' => $row['status']
            ];
        }
    }
} catch (Exception $e) {
    // Fail silently
}

// =========================================================================
//  2. GƏLƏCƏK DƏRSLƏR
//     API: GET /api/student/schedule/upcoming
// =========================================================================
$upcomingClasses = [];

$tmisUpcoming = tmis_get('/student/schedule/upcoming');
if ($tmisUpcoming && is_array($tmisUpcoming)) {
    foreach ($tmisUpcoming as $item) {
        $upcomingClasses[] = [
            'type' => 'one-time',
            'timestamp' => isset($item['date']) ? strtotime($item['date'] . ' ' . ($item['time'] ?? '10:00')) : time(),
            'title' => $item['course_title'] ?? ($item['title'] ?? 'Dərs'),
            'course' => $item['lesson_type'] ?? 'Mühazirə',
            'instructor' => $item['instructor_name'] ?? 'Müəllim',
            'date_display' => isset($item['date']) ? formatDate($item['date']) : ($item['day_name'] ?? ''),
            'time_display' => $item['time'] ?? '10:00',
            'sort_key' => isset($item['date']) ? strtotime($item['date'] . ' ' . ($item['time'] ?? '10:00')) : time()
        ];
    }
} else {
    // Fallback: lokal bazadan
    try {
        $scheduledLive = $db->fetchAll(
            "SELECT lc.*, c.title as course_title, i.name as instructor_name, i.title as instructor_title 
             FROM live_classes lc 
             JOIN courses c ON lc.course_id = c.id 
             JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
             JOIN instructors i ON lc.instructor_id = i.id 
             WHERE lc.status = 'scheduled'
             AND lc.start_time > NOW()
             ORDER BY lc.start_time ASC LIMIT 5",
            [$currentUser['id']]
        );

        foreach ($scheduledLive as $row) {
            $upcomingClasses[] = [
                'type' => 'one-time',
                'timestamp' => strtotime($row['start_time']),
                'title' => $row['title'],
                'course' => $row['course_title'],
                'instructor' => $row['instructor_title'] . ' ' . $row['instructor_name'],
                'date_display' => formatDate(date('Y-m-d', strtotime($row['start_time']))),
                'time_display' => date('H:i', strtotime($row['start_time'])),
                'sort_key' => strtotime($row['start_time'])
            ];
        }

        // Recurring courses from weekly schedule
        $recurringCourses = $db->fetchAll(
            "SELECT c.id, c.title, c.weekly_days, c.start_time,
                    cat.name as category_name,
                    i.first_name, i.last_name
             FROM courses c
             JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
             LEFT JOIN categories cat ON c.category_id = cat.id
             LEFT JOIN instructors i ON c.instructor_id = i.id
             LEFT JOIN users u ON i.user_id = u.id
             WHERE c.status = 'active' 
             AND c.weekly_days IS NOT NULL 
             AND c.weekly_days != ''
             AND c.start_time IS NOT NULL",
            [$currentUser['id']]
        );

        $azDaysToEn = [
            'Bazar ertəsi' => 'Monday',
            'Çərşənbə axşamı' => 'Tuesday',
            'Çərşənbə' => 'Wednesday',
            'Cümə axşamı' => 'Thursday',
            'Cümə' => 'Friday',
            'Şənbə' => 'Saturday',
            'Bazar' => 'Sunday'
        ];

        $now = time();

        foreach ($recurringCourses as $course) {
            $days = explode(',', $course['weekly_days']);
            $startTime = $course['start_time'];

            foreach ($days as $dayAz) {
                $dayAz = trim($dayAz);
                if (!isset($azDaysToEn[$dayAz]))
                    continue;

                $dayEn = $azDaysToEn[$dayAz];
                $todayDate = date('Y-m-d');
                $candidateTimeToday = strtotime("$todayDate $startTime");
                $todayEn = date('l');

                $targetTimestamp = 0;
                if ($todayEn === $dayEn && $candidateTimeToday > $now) {
                    $targetTimestamp = $candidateTimeToday;
                } else {
                    $targetTimestamp = strtotime("next $dayEn $startTime");
                }

                if ($targetTimestamp > $now) {
                    $instructorName = trim(($course['first_name'] ?? '') . ' ' . ($course['last_name'] ?? ''));
                    if (empty($instructorName))
                        $instructorName = 'Müəllim';

                    $upcomingClasses[] = [
                        'type' => 'recurring',
                        'timestamp' => $targetTimestamp,
                        'title' => $course['title'],
                        'course' => "Mühazirə/Seminar",
                        'instructor' => $instructorName,
                        'date_display' => formatDate(date('Y-m-d', $targetTimestamp)),
                        'time_display' => date('H:i', $targetTimestamp),
                        'sort_key' => $targetTimestamp
                    ];
                }
            }
        }

        usort($upcomingClasses, function ($a, $b) {
            return $a['sort_key'] - $b['sort_key'];
        });
        $upcomingClasses = array_slice($upcomingClasses, 0, 5);

    } catch (Exception $e) {
        $upcomingClasses = [];
    }
}

$activeLiveCount = count(array_filter($liveClasses, fn($c) => $c['status'] === 'live'));

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
            <div class="flex items-center justify-between" style="flex-wrap: wrap; gap: 16px;">
                <div class="page-header" style="margin-bottom: 0;">
                    <h1>Canlı Dərslər</h1>
                    <p>Hazırda davam edən və tezliklə başlayacaq dərslər</p>
                </div>

                <?php if ($activeLiveCount > 0): ?>
                    <div class="badge badge-live" style="padding: 10px 20px; font-size: 14px;">
                        <span
                            style="width: 12px; height: 12px; background: white; border-radius: 50%; display: inline-block; animation: pulse 2s infinite;"></span>
                        <?php echo $activeLiveCount; ?> Canlı Dərs
                    </div>
                <?php endif; ?>
            </div>

            <style>
                .nav-tabs {
                    display: flex;
                    border-bottom: 2px solid #e5e7eb;
                    margin-bottom: 24px;
                    gap: 32px;
                }

                .nav-tab {
                    padding: 12px 0;
                    font-weight: 500;
                    color: var(--text-muted);
                    border-bottom: 2px solid transparent;
                    margin-bottom: -2px;
                    cursor: pointer;
                    text-decoration: none;
                    transition: all 0.3s;
                    font-size: 16px;
                }

                .nav-tab:hover {
                    color: var(--primary);
                }

                .nav-tab.active {
                    color: var(--primary);
                    border-bottom-color: var(--primary);
                }
            </style>

            <div class="nav-tabs">
                <a href="live-classes.php" class="nav-tab active">Canlı Cədvəl</a>
                <a href="lessons.php" class="nav-tab">Fənlərim</a>
            </div>

            <!-- Active Live Classes -->
            <div class="space-y-6">
                <?php if (empty($liveClasses)): ?>
                    <div class="card p-12 text-center">
                        <div
                            style="width: 80px; height: 80px; background: var(--gray-100); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                            <i data-lucide="video-off" style="width: 40px; height: 40px; color: var(--text-muted);"></i>
                        </div>
                        <h2 style="font-size: 20px; font-weight: 700; color: var(--primary-dark); margin-bottom: 8px;">
                            Hazırda canlı dərs yoxdur</h2>
                        <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto;">
                            Bu gün üçün nəzərdə tutulmuş canlı dərslər hələ başlamayıb və ya artıq yekunlaşıb.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($liveClasses as $liveClass): ?>
                        <div class="live-class-card <?php echo $liveClass['status']; ?>">
                            <div class="flex gap-6" style="flex-wrap: wrap;">
                                <!-- Left Section - Class Info -->
                                <div style="flex: 1; min-width: 300px;">
                                    <!-- Status Badge -->
                                    <div style="margin-bottom: 16px;">
                                        <?php if ($liveClass['status'] === 'live'): ?>
                                            <span class="live-status live">
                                                <i data-lucide="wifi" style="width: 16px; height: 16px;"></i>
                                                CANLI YAYIM
                                            </span>
                                        <?php elseif ($liveClass['status'] === 'starting-soon'): ?>
                                            <span class="live-status starting-soon">
                                                <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                                                TEZLİKLƏ BAŞLAYIR
                                            </span>
                                        <?php else: ?>
                                            <span class="live-status ending-soon">
                                                <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                                                TEZLİKLƏ BİTİR
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Title and Course -->
                                    <h2 class="live-class-title">
                                        <?php echo e($liveClass['title']); ?>
                                    </h2>
                                    <p class="live-class-course">
                                        <?php echo e($liveClass['course']); ?>
                                    </p>
                                    <p class="live-class-instructor">
                                        <?php echo e($liveClass['instructor']); ?>
                                    </p>

                                    <!-- Time and Participants -->
                                    <div class="live-class-meta">
                                        <div class="live-meta-item">
                                            <i data-lucide="clock"></i>
                                            <span>Başlama:
                                                <?php echo $liveClass['startTime']; ?> | Müddət:
                                                <?php echo $liveClass['duration']; ?>
                                            </span>
                                        </div>
                                        <div class="live-meta-item">
                                            <i data-lucide="users"></i>
                                            <span>
                                                <?php echo $liveClass['participants']; ?>/
                                                <?php echo $liveClass['maxParticipants']; ?> iştirakçı
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Progress Bar for Participants -->
                                    <div class="progress-bar" style="margin: 16px 0;">
                                        <div class="progress-bar-fill <?php echo ($liveClass['maxParticipants'] > 0 && ($liveClass['participants'] / $liveClass['maxParticipants']) * 100 > 80) ? 'red' : 'green'; ?>"
                                            style="width: <?php echo $liveClass['maxParticipants'] > 0 ? ($liveClass['participants'] / $liveClass['maxParticipants']) * 100 : 0; ?>%;">
                                        </div>
                                    </div>

                                </div>

                                <!-- Right Section - Join Actions -->
                                <div class="live-actions">
                                    <?php if ($liveClass['status'] === 'live'): ?>
                                        <a href="live-view.php?id=<?php echo $liveClass['id']; ?>"
                                            class="btn btn-danger btn-lg btn-block">
                                            <i data-lucide="video"></i>
                                            Canlı Dərsə Qoşul
                                        </a>
                                    <?php elseif ($liveClass['status'] === 'starting-soon'): ?>
                                        <button class="btn btn-primary btn-lg btn-block">
                                            Hazırlaş (Tezliklə başlayır)
                                        </button>
                                    <?php else: ?>
                                        <a href="live-view.php?id=<?php echo $liveClass['id']; ?>"
                                            class="btn btn-warning btn-lg btn-block">
                                            <i data-lucide="video"></i>
                                            Dərsə Qoşul
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Upcoming Classes -->
            <?php if (count($upcomingClasses) > 0): ?>
                <div class="card card-dark">
                    <h2 style="font-size: 18px; margin-bottom: 20px; color: white;">Gələcək Dərslər</h2>

                    <div class="space-y-4">
                        <?php foreach ($upcomingClasses as $upcomingClass): ?>
                            <div
                                style="padding: 16px; background: var(--gray-700); border-radius: 8px; display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <h3 style="color: white; font-weight: 500;">
                                        <?php echo e($upcomingClass['title']); ?>
                                    </h3>
                                    <p style="color: var(--gray-400); font-size: 14px;">
                                        <?php echo e($upcomingClass['course']); ?>
                                    </p>
                                    <p style="color: var(--gray-400); font-size: 14px;">
                                        <?php echo e($upcomingClass['instructor']); ?>
                                    </p>
                                </div>
                                <div style="text-align: right;">
                                    <p style="color: white; font-weight: 500;">
                                        <?php echo e($upcomingClass['time_display']); ?>
                                    </p>
                                    <p style="color: var(--gray-400); font-size: 14px;">
                                        <?php echo e($upcomingClass['date_display']); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>