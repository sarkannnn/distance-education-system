<?php
/**
 * Teacher Schedule - Dərs Cədvəli
 */
$currentPage = 'schedule';
$pageTitle = 'Dərs Cədvəli';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireInstructor();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// Müəllimin instructor_id-sini tap
$instructor = $db->fetch(
    "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
    [$currentUser['id'], $currentUser['email']]
);

$events = [];
if ($instructor) {
    $isAdmin = ($_SESSION['user_role'] === 'admin');
    $whereClause = $isAdmin ? "c.status != 'draft'" : "c.instructor_id = ? AND c.status != 'draft'";
    $params = $isAdmin ? [] : [$instructor['id']];

    // Müəllimin bütün dərslərini və onların cədvəlini çək
    $coursesData = $db->fetchAll(
        "SELECT c.id, c.title, c.weekly_days, c.start_time, c.total_lessons, c.lecture_count, c.seminar_count, c.created_at,
                ins.name as instructor_name,
                (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.status IN ('ended', 'completed')) as completed_lessons,
                (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.lesson_type = 'lecture' AND lc.status IN ('ended', 'completed')) as completed_lectures,
                (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.lesson_type = 'seminar' AND lc.status IN ('ended', 'completed')) as completed_seminars,
                (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.lesson_type = 'laboratory' AND lc.status IN ('ended', 'completed')) as completed_laboratories
         FROM courses c
         LEFT JOIN instructors ins ON c.instructor_id = ins.id
         WHERE {$whereClause}
         ORDER BY c.start_time ASC",
        $params
    );

    $daysOfWeek = [
        'Bazar ertəsi',
        'Çərşənbə axşamı',
        'Çərşənbə',
        'Cümə axşamı',
        'Cümə',
        'Şənbə',
        'Bazar'
    ];

    $colors = ['#0d9488', '#2563eb', '#9333ea', '#16a34a', '#d97706', '#4f46e5', '#e11d48'];
    $colorIndex = 0;

    $todayName = $daysOfWeek[date('N') - 1];

    foreach ($daysOfWeek as $day) {
        $dayItems = [];
        foreach ($coursesData as $course) {
            if ($course['weekly_days']) {
                $courseDays = explode(', ', $course['weekly_days']);
                if (in_array($day, $courseDays)) {
                    $isDue = ($day === $todayName);

                    // Həftə hesabla (kurs yaradılma tarixindən indiyə)
                    $courseStart = strtotime($course['created_at']);
                    $weekNumber = ceil((time() - $courseStart) / (7 * 24 * 60 * 60));
                    if ($weekNumber < 1)
                        $weekNumber = 1;

                    // Progress
                    $totalLessons = intval($course['total_lessons']) ?: 10; // default 10
                    $completedLessons = intval($course['completed_lessons']) ?: 0;
                    $nextLesson = $completedLessons + 1;
                    $remainingLessons = max(0, $totalLessons - $completedLessons);

                    $dayItems[] = [
                        'course_id' => $course['id'],
                        'time' => date('H:i', strtotime($course['start_time'])),
                        'title' => $course['title'],
                        'instructor_name' => $course['instructor_name'],
                        'type' => 'Canlı Dərs',
                        'color' => $colors[$colorIndex % count($colors)],
                        'is_due' => $isDue,
                        'week_number' => $weekNumber,
                        'total_lessons' => $totalLessons,
                        'completed_lessons' => $completedLessons,
                        'next_lesson' => $nextLesson,
                        'remaining_lessons' => $remainingLessons,
                        'lecture_count' => $course['lecture_count'] ?: 0,
                        'seminar_count' => $course['seminar_count'] ?: 0,
                        'laboratory_count' => $course['laboratory_count'] ?: 0,
                        'completed_lectures' => $course['completed_lectures'] ?: 0,
                        'completed_seminars' => $course['completed_seminars'] ?: 0,
                        'completed_laboratories' => $course['completed_laboratories'] ?: 0
                    ];
                    $colorIndex++;
                }
            }
        }

        if (!empty($dayItems)) {
            $events[] = [
                'day' => $day,
                'date' => '', // Tarix artıq dinamik cədvəldə bəlkə lazım olar, amma hələlik boş qoyaq
                'items' => $dayItems
            ];
        }
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
                <h1>Dərs Cədvəli</h1>
                <p>Bu həftənin planlaşdırılmış fəaliyyətləri</p>
            </div>

            <div class="space-y-4">
                <?php foreach ($events as $event): ?>
                    <div class="card">
                        <div class="flex items-center gap-3 mb-6">
                            <i data-lucide="calendar" class="text-primary"></i>
                            <div>
                                <h3 style="font-weight: 600; font-size: 16px;">
                                    <?php echo $event['day']; ?>
                                </h3>
                                <p style="color: var(--text-muted); font-size: 13px;">
                                    <?php echo $event['date']; ?>
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <?php foreach ($event['items'] as $item): ?>
                                <div class="plan-item p-4 rounded-xl border-l-4"
                                    style="background: var(--gray-50); border-left-color: <?php echo $item['color']; ?>;">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <div class="flex items-center gap-2 mb-2">
                                                <i data-lucide="clock"
                                                    style="width: 14px; height: 14px; color: var(--text-muted);"></i>
                                                <span style="color: var(--accent); font-size: 14px; font-weight: 600;">
                                                    <?php echo $item['time']; ?>
                                                </span>
                                            </div>
                                            <h4 style="font-weight: 600; font-size: 15px;">
                                                <?php echo e($item['title']); ?>
                                            </h4>
                                            <?php if ($isAdmin && !empty($item['instructor_name'])): ?>
                                                <p style="font-size: 13px; font-weight: 600; color: var(--primary); margin: 2px 0;">
                                                    <i data-lucide="user-check"
                                                        style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-top: -2px;"></i>
                                                    <?php echo e($item['instructor_name']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="map-pin"
                                                    style="width: 14px; height: 14px; color: var(--text-muted);"></i>
                                                <span style="color: var(--text-muted); font-size: 13px;">
                                                    <?php echo $item['type']; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php if ($item['is_due']): ?>
                                            <button class="btn btn-primary"
                                                onclick="openStartLiveModal(<?php echo (int) $item['course_id']; ?>, '<?php echo htmlspecialchars(addslashes($item['title']), ENT_QUOTES); ?>', <?php echo (int) $item['lecture_count']; ?>, <?php echo (int) $item['seminar_count']; ?>, <?php echo (int) $item['completed_lectures']; ?>, <?php echo (int) $item['completed_seminars']; ?>, <?php echo (int) $item['laboratory_count']; ?>, <?php echo (int) $item['completed_laboratories']; ?>)"
                                                style="background: <?php echo $item['color']; ?>; border: none; padding: 8px 16px; font-size: 13px;">
                                                Qoşul
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-primary" disabled
                                                style="background: var(--gray-300); border: none; padding: 8px 16px; font-size: 13px; cursor: not-allowed; opacity: 0.6;">
                                                Qoşul
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<!-- Canlı Dərsi Başlat Modalı -->
<?php require_once 'includes/modal_start_live.php'; ?>

<?php require_once 'includes/footer.php'; ?>