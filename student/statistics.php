<?php
/**
 * Statistika - Statistics
 * TMİS API inteqrasiyası ilə.
 * API: GET /api/student/statistics
 * Yeni kartlar: Davamiyyət faizi, Tədris saatı
 */
$currentPage = 'settings';
$pageTitle = 'Statistika';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireLogin();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// =========================================================================
//  STATİSTİKA
//  API: GET /api/student/statistics
// =========================================================================
$stats = [
    'totalCourses' => 0,
    'totalArchives' => 0,
    'liveLessons' => 0,
    'totalViews' => 0
];

$tmisStats = tmis_get('/student/statistics');
if ($tmisStats) {
    $stats['totalCourses'] = $tmisStats['total_courses'] ?? 0;
    $stats['totalArchives'] = $tmisStats['total_archives'] ?? 0;
    $stats['liveLessons'] = $tmisStats['live_lessons_this_month'] ?? 0;
    $stats['totalViews'] = $tmisStats['total_views'] ?? 0;
} else {
    // Fallback: lokal bazadan
    try {
        $courseCount = $db->fetch(
            "SELECT COUNT(*) as count FROM courses c 
             JOIN enrollments e ON e.course_id = c.id 
             WHERE c.status = 'active' AND e.user_id = ?",
            [$currentUser['id']]
        );
        $stats['totalCourses'] = $courseCount['count'] ?? 0;

        $manualArchives = $db->fetch(
            "SELECT COUNT(*) as count FROM archived_lessons al
             JOIN enrollments e ON e.course_id = al.course_id
             WHERE e.user_id = ?",
            [$currentUser['id']]
        );
        $autoArchives = $db->fetch(
            "SELECT COUNT(*) as count FROM live_classes lc
             JOIN enrollments e ON e.course_id = lc.course_id
             WHERE lc.recording_path IS NOT NULL AND e.user_id = ?",
            [$currentUser['id']]
        );
        $stats['totalArchives'] = ($manualArchives['count'] ?? 0) + ($autoArchives['count'] ?? 0);

        $liveCount = $db->fetch(
            "SELECT COUNT(*) as count FROM live_classes lc
             JOIN enrollments e ON e.course_id = lc.course_id
             WHERE MONTH(lc.start_time) = MONTH(CURRENT_DATE()) 
             AND YEAR(lc.start_time) = YEAR(CURRENT_DATE()) AND e.user_id = ?",
            [$currentUser['id']]
        );
        $stats['liveLessons'] = $liveCount['count'] ?? 0;

        $viewsCount = $db->fetch(
            "SELECT SUM(al.views) as total FROM archived_lessons al
             JOIN enrollments e ON e.course_id = al.course_id
             WHERE e.user_id = ?",
            [$currentUser['id']]
        );
        $stats['totalViews'] = $viewsCount['total'] ?? 0;
    } catch (Exception $e) {
        // use defaults
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
                <h1>Statistika</h1>
                <p>Distant təhsil platforması üzrə ümumi məlumatlar</p>
            </div>

            <!-- Key Metrics -->
            <div class="stats-grid-mockup">
                <div class="stat-card-mockup orange">
                    <div class="stat-icon-mockup orange">
                        <i data-lucide="book-open"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $stats['totalCourses']; ?></div>
                    <div class="stat-label-mockup orange">Aktiv Fənlər</div>
                </div>

                <div class="stat-card-mockup green">
                    <div class="stat-icon-mockup green">
                        <i data-lucide="archive"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $stats['totalArchives']; ?></div>
                    <div class="stat-label-mockup green">Arxiv Materialları</div>
                </div>

                <div class="stat-card-mockup blue">
                    <div class="stat-icon-mockup blue">
                        <i data-lucide="video"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $stats['liveLessons']; ?></div>
                    <div class="stat-label-mockup blue">Bu Ay Canlı Dərslər</div>
                </div>

                <div class="stat-card-mockup purple">
                    <div class="stat-icon-mockup purple">
                        <i data-lucide="eye"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $stats['totalViews']; ?></div>
                    <div class="stat-label-mockup purple">Ümumi Baxışlar</div>
                </div>
            </div>


            <!-- Info Card -->
            <div class="card" style="text-align: center; padding: 40px;">
                <i data-lucide="info"
                    style="width: 48px; height: 48px; color: var(--primary); margin: 0 auto 16px;"></i>
                <h3 style="font-size: 20px; margin-bottom: 12px;">Distant Təhsil Platforması</h3>
                <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto;">
                    Bu platformada müəllimlərin yaratdığı dərslərə, arxiv materiallarına və canlı dərslərə çıxış əldə
                    edə bilərsiniz.
                    Tapşırıq və quiz statistikaları üçün TMİS sistemindən istifadə edin.
                </p>
            </div>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>