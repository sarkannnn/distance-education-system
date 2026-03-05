<?php
/**
 * Dərslərim - Enrolled Courses (Fənlər)
 * TMİS API inteqrasiyası ilə.
 * API: GET /api/student/subjects
 */
$currentPage = 'live';
$pageTitle = 'Dərslərim';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireLogin();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// =========================================================================
//  FƏNN SİYAHISI
//  API: GET /api/student/subjects
// =========================================================================
$enrolledCourses = [];

$tmisSubjects = tmis_get('/student/subjects');
if ($tmisSubjects && is_array($tmisSubjects)) {
    foreach ($tmisSubjects as $subject) {
        $cTmisId = (int) ($subject['id'] ?? 0);

        // RECALCULATE for 'Online Only' progress from local DB
        // TMİS progress-i oflayn dərsləri də əhatə edə bilər, 
        // lakin biz "Distant" sistemdə yalnız onlayn dərsləri göstəririk.
        $onlineDone = $db->fetch(
            "SELECT COUNT(*) as cnt FROM live_classes 
             WHERE (course_id = ? OR tmis_subject_id = ?) 
             AND status IN ('ended', 'completed')",
            [$cTmisId, $cTmisId]
        );
        $totalDone = (int) ($onlineDone['cnt'] ?? 0);

        $lectureCount = intval($subject['lecture_count'] ?? 0);
        $seminarCount = intval($subject['seminar_count'] ?? 0);
        $totalPlanned = $lectureCount + $seminarCount;
        if ($totalPlanned == 0)
            $totalPlanned = intval($subject['total_lessons'] ?? 1);

        $progress = min(100, round(($totalDone / max(1, $totalPlanned)) * 100));

        $enrolledCourses[] = [
            'id' => $cTmisId,
            'title' => $subject['title'] ?? 'Fənn',
            'instructor' => $subject['instructor_name'] ?? 'Müəllim',
            'progress' => $progress,
            'status' => $subject['status'] ?? 'active',
            'enrolledDate' => isset($subject['enrolled_date']) ? formatDate($subject['enrolled_date']) : '',
            'totalLessons' => $totalPlanned,
            'completedLessons' => $totalDone,
            'stats' => [
                'lecture_count' => $lectureCount,
                'seminar_count' => $seminarCount,
                'lecture_done' => $totalDone, // Simplified to online-only
                'seminar_done' => 0
            ],
            'schedule' => [
                'days' => $subject['weekly_days'] ?? '',
                'time' => $subject['start_time'] ?? ''
            ],
            'course_level' => $subject['course_level'] ?? null,
            'nextLesson' => 'Dərs ' . ($totalDone + 1),
            'category' => $subject['category'] ?? 'Fənn',
            'hasLiveClass' => $subject['has_live_class'] ?? false,
            'liveClassId' => $subject['live_class_id'] ?? null
        ];
    }
} else {
    // Fallback: lokal bazadan
    try {
        $coursesData = $db->fetchAll(
            "SELECT c.id, c.title, c.description, c.status, c.total_lessons, c.created_at,
                    c.category_id, c.lecture_count, c.seminar_count, c.course_level, c.weekly_days, c.start_time,
                    cat.name as category_name
             FROM courses c
             JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
             LEFT JOIN categories cat ON c.category_id = cat.id
             WHERE c.status = 'active'
             ORDER BY c.created_at DESC",
            [$currentUser['id']]
        );

        foreach ($coursesData as $course) {
            $completedStats = $db->fetch(
                "SELECT COUNT(*) as total_done FROM live_classes 
                 WHERE course_id = ? AND status IN ('ended', 'completed')",
                [$course['id']]
            );

            $totalDone = intval($completedStats['total_done'] ?? 0);

            $instructorName = 'Müəllim';
            try {
                $instructor = $db->fetch(
                    "SELECT u.first_name, u.last_name FROM instructors i 
                     JOIN courses c2 ON c2.instructor_id = i.id 
                     JOIN users u ON i.user_id = u.id
                     WHERE c2.id = ?",
                    [$course['id']]
                );
                if ($instructor) {
                    $instructorName = trim(($instructor['first_name'] ?? '') . ' ' . ($instructor['last_name'] ?? ''));
                }
            } catch (Exception $e) {
            }

            $liveClass = null;
            try {
                $liveClass = $db->fetch(
                    "SELECT id, title FROM live_classes WHERE course_id = ? AND status = 'live' LIMIT 1",
                    [$course['id']]
                );
            } catch (Exception $e) {
            }

            $totalPlanned = ($course['lecture_count'] ?? 0) + ($course['seminar_count'] ?? 0);
            if ($totalPlanned == 0)
                $totalPlanned = $course['total_lessons'] > 0 ? $course['total_lessons'] : 1;

            $progress = min(100, round(($totalDone / $totalPlanned) * 100));

            $enrolledCourses[] = [
                'id' => $course['id'],
                'title' => $course['title'],
                'instructor' => $instructorName,
                'progress' => $progress,
                'status' => $course['status'],
                'enrolledDate' => formatDate($course['created_at'] ?? date('Y-m-d')),
                'totalLessons' => $totalPlanned,
                'completedLessons' => $totalDone,
                'stats' => [
                    'lecture_count' => $course['lecture_count'] ?? 0,
                    'seminar_count' => $course['seminar_count'] ?? 0,
                    'lecture_done' => $totalDone,
                    'seminar_done' => 0
                ],
                'schedule' => [
                    'days' => $course['weekly_days'] ?? '',
                    'time' => $course['start_time'] ? date('H:i', strtotime($course['start_time'])) : ''
                ],
                'course_level' => $course['course_level'],
                'nextLesson' => 'Dərs ' . ($totalDone + 1),
                'category' => $course['category_name'] ?? 'Ümumi',
                'hasLiveClass' => !empty($liveClass),
                'liveClassId' => $liveClass['id'] ?? null
            ];
        }
    } catch (Exception $e) {
        $enrolledCourses = [];
    }
}

$stats = [];

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
                <h1>Canlı Dərslər</h1>
                <p>Qeydiyyatdan keçdiyiniz fənlər və onların irəliləyişi</p>
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
                <a href="live-classes.php" class="nav-tab">Canlı Cədvəl</a>
                <a href="lessons.php" class="nav-tab active">Fənlərim</a>
            </div>

            <!-- Search -->
            <div style="margin-bottom: 24px;">
                <div class="form-input-icon">
                    <i data-lucide="search"></i>
                    <input type="text" class="form-input" placeholder="Fənn və ya müəllim axtar..." id="course-search">
                </div>
            </div>

            <!-- Course Cards -->
            <div class="grid-3" id="courses-container">
                <?php foreach ($enrolledCourses as $course): ?>
                    <div class="course-card" data-status="<?php echo $course['status']; ?>">
                        <!-- Course Header -->
                        <div class="course-header">
                            <div>
                                <div class="course-category">
                                    <i data-lucide="book-open"></i>
                                    <span><?php
                                    $catDisplay = $course['category'];
                                    if ($catDisplay === 'TMİS Fənləri' || strtolower($catDisplay) === 'fenn' || $catDisplay === 'Fənn') {
                                        echo 'Fənn';
                                    } else {
                                        echo str_replace('TMİS ', '', e($catDisplay));
                                    }
                                    ?></span>
                                </div>
                                <h3 class="course-title"><?php echo e($course['title']); ?></h3>
                            </div>

                            <!-- Status Badge -->
                            <?php if ($course['hasLiveClass']): ?>
                                <span class="badge badge-danger" style="background: #ef4444;">
                                    <i data-lucide="video" style="width: 14px; height: 14px;"></i>
                                    Canlı
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Instructor & Details -->
                        <div class="course-instructor mb-4">
                            <div class="instructor-avatar">
                                <i data-lucide="user"></i>
                            </div>
                            <div style="flex: 1;">
                                <p class="instructor-name">
                                    <?php echo e($course['instructor']); ?>
                                </p>
                                <div class="text-muted text-sm mt-2">
                                    <?php if (!empty($course['schedule']['days'])): ?>
                                        <div class="flex items-center gap-2 mb-1">
                                            <i data-lucide="calendar-days"
                                                style="width: 14px; height: 14px; color: var(--primary);"></i>
                                            <span
                                                style="font-weight: 500; color: var(--text-dark);"><?php echo $course['schedule']['days']; ?></span>
                                        </div>
                                        <?php if (!empty($course['schedule']['time'])): ?>
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="clock"
                                                    style="width: 14px; height: 14px; color: var(--text-muted);"></i>
                                                <span><?php echo $course['schedule']['time']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="flex items-center gap-2 text-muted" style="opacity: 0.7;">
                                            <i data-lucide="calendar-off" style="width: 14px; height: 14px;"></i>
                                            <span>Vaxt təyin olunmayıb</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Dərs Sayları -->
                        <div
                            style="display: flex; gap: 16px; margin-top: 8px; padding: 12px 16px; background: var(--gray-50, #f9fafb); border-radius: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i data-lucide="book-open" style="width: 16px; height: 16px; color: var(--primary);"></i>
                                <span style="font-size: 14px; font-weight: 600; color: var(--text-primary);">Mühazirə:
                                    <?php echo $course['stats']['lecture_done']; ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i data-lucide="users" style="width: 16px; height: 16px; color: var(--primary);"></i>
                                <span style="font-size: 14px; font-weight: 600; color: var(--text-primary);">Seminar:
                                    <?php echo $course['stats']['seminar_done']; ?></span>
                            </div>
                        </div>

                        <?php if (isset($course['nextLesson']) && $course['status'] !== 'completed'): ?>
                            <div class="next-lesson">
                                <p class="next-lesson-label">Növbəti dərs:</p>
                                <p class="next-lesson-title"><?php echo e($course['nextLesson']); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($course['status'] === 'completed'): ?>
                            <div class="completed-badge">
                                ✓ Fənn uğurla tamamlandı
                            </div>
                        <?php endif; ?>

                        <!-- Enrollment Date -->
                        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
                            <p style="font-size: 12px; color: var(--text-muted);">Qeydiyyat tarixi:
                                <?php echo e($course['enrolledDate']); ?>
                            </p>
                        </div>

                        <!-- Action Button -->
                        <?php if ($course['hasLiveClass']): ?>
                            <a href="live-view.php?id=<?php echo $course['liveClassId']; ?>" class="btn btn-danger btn-block"
                                style="margin-top: 16px; background: #ef4444;">
                                <i data-lucide="video"></i>
                                Canlı Dərsə Qoşul
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<script>
    // Search functionality
    document.getElementById('course-search').addEventListener('input', function (e) {
        const query = e.target.value.toLowerCase();
        document.querySelectorAll('.course-card').forEach(card => {
            const title = card.querySelector('.course-title').textContent.toLowerCase();
            const instructor = card.querySelector('.instructor-name').textContent.toLowerCase();

            if (title.includes(query) || instructor.includes(query)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>