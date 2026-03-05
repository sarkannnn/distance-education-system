<?php
/**
 * Dashboard - İdarəetmə Paneli (Müəllim)
 */
$currentPage = 'dashboard';
$pageTitle = 'İdarəetmə Paneli';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireInstructor();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// Müəllimin instructor_id-sini tap
$instructor = $db->fetch(
    "SELECT id FROM instructors WHERE user_id = ?",
    [$currentUser['id']]
);

if (!$instructor) {
    $instructor = $db->fetch(
        "SELECT id FROM instructors WHERE email = ?",
        [$currentUser['email']]
    );
}

$instructorId = $instructor ? $instructor['id'] : null;
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

// ============================================================
// TMİS Token — bütün API sorğuları üçün
// ============================================================
$tmisToken = TmisApi::getToken();
if ($tmisToken === null) {
    // Token yoxdursa və ya vaxtı bitibsə login-ə yönləndir
}

// ============================================================
// TMİS API-dən Bu Günün Dərs Cədvəlini çək
// ============================================================
$todaySchedule = [];
if ($tmisToken) {
    try {
        $scheduleResult = TmisApi::getScheduleToday($tmisToken);

        if ($scheduleResult['success'] && isset($scheduleResult['data'])) {
            $todaySchedule = $scheduleResult['data'];
        }
    } catch (Exception $e) {
        error_log('TMİS Schedule/Today xətası: ' . $e->getMessage());
        $todaySchedule = [];
    }
}

// ============================================================
// TMİS API-dən Dashboard Statistikalarını çək
// ============================================================
$stats = [
    'totalCourses' => 0,
    'totalStudents' => 0,
    'liveClassesThisMonth' => 0,
    'totalHours' => 0
];

if ($tmisToken) {
    try {
        $dashResult = TmisApi::getDashboardStats($tmisToken);

        if ($dashResult['success'] && isset($dashResult['data'])) {
            $dashData = $dashResult['data'];

            $stats['totalCourses'] = $dashData['total_subjects'] ?? 0;
            $stats['totalStudents'] = $dashData['total_students'] ?? 0;

            // Lokal fallback əgər tələbə sayı 0-dırsa
            if ($stats['totalStudents'] == 0 && $instructorId) {
                $countResult = $db->fetch(
                    "SELECT COUNT(DISTINCT e.user_id) as total FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.instructor_id = ?",
                    [$instructorId]
                );
                if ($countResult) {
                    $stats['totalStudents'] = (int) $countResult['total'];
                }
            }

            $stats['liveClassesThisMonth'] = $dashData['total_live_lessons'] ?? 0;

            // Tədris saatını formatla
            $totalTeachingHours = $dashData['total_teaching_hours'] ?? 0;
            $hours = floor($totalTeachingHours);
            $minutes = round(($totalTeachingHours - $hours) * 60);
            $stats['totalHours'] = "{$hours}<small style='font-size: 14px; margin-left: 4px; opacity: 0.8;'>s.</small> {$minutes}<small style='font-size: 14px; margin-left: 4px; opacity: 0.8;'>dəq.</small>";
        }

        if (isset($dashResult['expired']) && $dashResult['expired']) {
            header('Location: login.php?error=session_expired');
            exit;
        }
    } catch (Exception $e) {
        error_log('TMİS Dashboard Stats xətası: ' . $e->getMessage());
    }
}

// ============================================================
// Lokal Bazadan Son Fəaliyyətləri (Online Dərslər) çək
// ============================================================
$recentActivities = [];
$actualTotalMinutes = 0;
$archiveTotalMinutes = 0;

try {
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
            error_log('TMİS SubjectsList xətası: ' . $e->getMessage());
        }
    }

    $localActivities = $db->fetchAll("
        SELECT 
            lc.id,
            lc.title as topic,
            lc.lesson_type,
            lc.start_time as date,
            lc.duration_minutes,
            lc.course_id
        FROM live_classes lc
        WHERE lc.status IN ('ended', 'completed')
        ORDER BY lc.start_time DESC
        LIMIT 10
    ");

    foreach ($localActivities as $item) {
        $cId = (int) $item['course_id'];
        $sInfo = $subjectMap[$cId] ?? [];

        // TMİS-dən metadata al, yoxdursa lokal bazadan axtar
        $facultyName = $sInfo['faculty_name'] ?? '';
        $specialtyName = $sInfo['profession_name'] ?? '';
        $groupName = $sInfo['class_name'] ?? '*';
        $courseLevel = $sInfo['course'] ?? '-';
        $courseName = $sInfo['subject_name'] ?? '';

        // Əgər TMİS-dən fənn adı tapılmadısa, lokal bazadan axtar
        if (empty($courseName)) {
            $courseRow = $db->fetch("SELECT title FROM courses WHERE tmis_subject_id = ?", [$cId]);
            if (!$courseRow) {
                $courseRow = $db->fetch("SELECT title FROM courses WHERE id = ?", [$cId]);
            }
            $courseName = $courseRow ? $courseRow['title'] : ('Fənn #' . $cId);
        }

        if (empty($facultyName))
            $facultyName = 'NDU';
        if (empty($specialtyName))
            $specialtyName = '-';

        $recentActivities[] = [
            'id' => $item['id'],
            'faculty' => $facultyName,
            'specialty' => $specialtyName,
            'group' => $groupName,
            'course_level' => $courseLevel,
            'course' => $courseName,
            'lesson_type' => ($item['lesson_type'] === 'lecture' ? 'Mühazirə' : ($item['lesson_type'] === 'seminar' ? 'Seminar' : 'Laboratoriya')),
            'topic' => $item['topic'],
            'date' => $item['date']
        ];
    }

    // Tədris saatı üçün arxiv
    if ($tmisToken) {
        $archiveResult = TmisApi::getArchive($tmisToken);
        if ($archiveResult['success'] && isset($archiveResult['data'])) {
            $materials = $archiveResult['data']['materials'] ?? ($archiveResult['data'] ?? []);
            foreach ($materials as $itm) {
                $type = strtolower($itm['type'] ?? '');
                if ($type === 'video' || strpos($type, 'mp4') !== false) {
                    $archiveTotalMinutes += (int) ($itm['duration_minutes'] ?? ($itm['duration'] ?? 0));
                }
            }
        }
    }
} catch (Exception $e) {
    error_log('Local Dashboard Activity xətası: ' . $e->getMessage());
}


// İstifadəçinin xüsusi istəyi: Tədris saatı faktiki arxiv videoların cəmi olsun
if ($archiveTotalMinutes > 0) {
    if ($archiveTotalMinutes < 60) {
        $stats['totalHours'] = "0<small style='font-size: 14px; margin-left: 4px; opacity: 0.8;'>s.</small> {$archiveTotalMinutes}<small style='font-size: 14px; margin-left: 4px; opacity: 0.8;'>dəq.</small>";
    } else {
        $h = floor($archiveTotalMinutes / 60);
        $m = $archiveTotalMinutes % 60;
        $stats['totalHours'] = "{$h}<small style='font-size: 14px; margin-left: 4px; opacity: 0.8;'>s.</small> {$m}<small style='font-size: 14px; margin-left: 4px; opacity: 0.8;'>dəq.</small>";
    }
} else if ($actualTotalMinutes > 0) {
    // Fallback if archive is empty but activities have time
    $h = floor($actualTotalMinutes / 60);
    $m = $actualTotalMinutes % 60;
    $stats['totalHours'] = "{$h}<small style='font-size: 14px; margin-left: 4px; opacity: 0.8;'>s.</small> {$m}<small style='font-size: 14px; margin-left: 4px; opacity: 0.8;'>dəq.</small>";
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
                <h1>Xoş gəldiniz, <?php echo e($currentUser['first_name']); ?>!</h1>
                <p>Bu gün və bu həftənin tədris fəaliyyətlərinə ümumi baxış.</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid-mockup">
                <div class="stat-card-mockup pink">
                    <div class="stat-icon-mockup pink"><i data-lucide="book-open"></i></div>
                    <div class="stat-value-mockup"><?php echo $stats['totalCourses']; ?></div>
                    <div class="stat-label-mockup pink">Fənn</div>
                </div>

                <div class="stat-card-mockup purple">
                    <div class="stat-icon-mockup purple"><i data-lucide="graduation-cap"></i></div>
                    <div class="stat-value-mockup"><?php echo $stats['totalStudents']; ?></div>
                    <div class="stat-label-mockup purple">Tələbə</div>
                </div>

                <div class="stat-card-mockup blue">
                    <div class="stat-icon-mockup blue"><i data-lucide="video"></i></div>
                    <div class="stat-value-mockup"><?php echo $stats['liveClassesThisMonth']; ?></div>
                    <div class="stat-label-mockup blue">Canlı Dərslər</div>
                </div>

                <div class="stat-card-mockup green">
                    <div class="stat-icon-mockup green"><i data-lucide="clock"></i></div>
                    <div class="stat-value-mockup"><?php echo $stats['totalHours']; ?></div>
                    <div class="stat-label-mockup green">Tədris Saatı</div>
                </div>
            </div>

            <style>
                .dashboard-grid-row {
                    display: grid;
                    grid-template-columns: 1fr 400px;
                    gap: 24px;
                    align-items: stretch;
                }

                @media (max-width: 1200px) {
                    .dashboard-grid-row {
                        grid-template-columns: 1fr;
                    }
                }

                .dashboard-grid-row .card {
                    height: 100%;
                    display: flex;
                    flex-direction: column;
                }

                .scroll-container {
                    max-height: 380px;
                    overflow-y: auto;
                    padding-right: 4px;
                }

                .scroll-container::-webkit-scrollbar {
                    width: 4px;
                }

                .scroll-container::-webkit-scrollbar-thumb {
                    background: var(--gray-200);
                    border-radius: 10px;
                }
            </style>

            <div class="dashboard-grid-row">
                <!-- 1. Today's Schedule -->
                <div class="card">
                    <div class="card-header">
                        <i data-lucide="calendar"></i>
                        <h2>Bu günün dərsləri</h2>
                    </div>
                    <div class="scroll-container space-y-4">
                        <?php if (empty($todaySchedule)): ?>
                            <div style="padding: 40px 20px; text-align: center;">
                                <div
                                    style="width: 60px; height: 60px; background: var(--gray-50); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                                    <i data-lucide="calendar-off"
                                        style="width: 28px; height: 28px; color: var(--text-muted);"></i>
                                </div>
                                <p style="color: var(--text-muted); font-size: 14px;">Bu gün üçün heç bir dərs
                                    planlaşdırılmayıb.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($todaySchedule as $lesson):
                                $isLive = (($lesson['status'] ?? '') === 'live' || ($lesson['status'] ?? '') === 'in-progress');
                                $startTime = isset($lesson['start_time']) ? date('H:i', strtotime($lesson['start_time'])) : '--:--';
                                ?>
                                <div class="schedule-item <?php echo $isLive ? 'live' : ''; ?>"
                                    style="<?php echo $isLive ? 'border-left: 4px solid var(--error);' : ''; ?>">
                                    <div>
                                        <div class="schedule-time">
                                            <i data-lucide="clock"></i>
                                            <span><?php echo $startTime; ?></span>
                                            <?php if ($isLive): ?><span class="badge badge-live">Canlı</span><?php endif; ?>
                                        </div>
                                        <h3 class="schedule-title" style="font-size: 14px;">
                                            <?php echo e($lesson['title'] ?? 'Dərs'); ?>
                                        </h3>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Live Support Card -->
                <div class="card card-dark"
                    style="background: linear-gradient(135deg, var(--primary-dark), #1e293b); display: flex; flex-direction: column; justify-content: center; border: none; padding: 20px; position: relative; overflow: hidden;">
                    <div
                        style="position: absolute; top: -20px; right: -20px; width: 120px; height: 120px; background: rgba(255,255,255,0.03); border-radius: 50%;">
                    </div>
                    <h3
                        style="color: white; font-size: 18px; margin-bottom: 8px; font-weight: 600; position: relative; z-index: 1;">
                        Canlı Dərs Dəstəyi</h3>
                    <p
                        style="color: rgba(255,255,255,0.7); font-size: 12px; margin-bottom: 15px; line-height: 1.5; position: relative; z-index: 1;">
                        Problemləriniz üçün təlimatları oxuyun və ya texniki dəstəyə müraciət edin.
                    </p>
                    <a href="help.php" class="btn btn-white"
                        style="background: white; color: var(--primary); font-weight: 600; border-radius: 10px; height: 42px; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 13px; transition: all 0.2s; position: relative; z-index: 1;">
                        <i data-lucide="help-circle" style="margin-right: 8px; width: 16px;"></i>
                        Yardım Mərkəzi
                    </a>
                </div>
            </div>

            <!-- 3. Recent Activities (Detailed Table) -->
            <div class="card" style="border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.04);">
                <div class="card-header" style="border-bottom: 1px solid var(--gray-100); padding: 20px 24px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width: 40px; height: 40px; background: var(--gray-50); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                            <i data-lucide="history" style="width: 20px;"></i>
                        </div>
                        <div>
                            <h2 style="font-size: 16px; font-weight: 700; color: #1e293b; margin: 0;">Son keçirilmiş
                                dərslər</h2>
                            <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">Sizin tərəfinizdən
                                yekunlaşdırılmış dərslərin siyahısı</p>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table" style="font-size: 13px; margin: 0; width: 100%; table-layout: auto;">
                        <thead>
                            <tr style="background: #fdfdfd; border-bottom: 1px solid var(--gray-100);">
                                <th
                                    style="padding: 14px 16px; font-weight: 600; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;">
                                    Fakültə</th>
                                <th
                                    style="padding: 14px 16px; font-weight: 600; color: #64748b; font-size: 11px; text-transform: uppercase; white-space: nowrap;">
                                    İxtisas</th>
                                <th
                                    style="padding: 14px 16px; font-weight: 600; color: #64748b; font-size: 11px; text-transform: uppercase; text-align: center; white-space: nowrap;">
                                    Qrup/Kurs</th>
                                <th
                                    style="padding: 14px 16px; font-weight: 600; color: #64748b; font-size: 11px; text-transform: uppercase; white-space: nowrap;">
                                    Fənn / Mövzu</th>
                                <th
                                    style="padding: 14px 16px; font-weight: 600; color: #64748b; font-size: 11px; text-transform: uppercase; white-space: nowrap;">
                                    Tip</th>
                                <th
                                    style="padding: 14px 16px; font-weight: 600; color: #64748b; font-size: 11px; text-transform: uppercase; text-align: right; white-space: nowrap;">
                                    Tarix</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentActivities)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 50px;">
                                        Hələ ki, heç bir fəaliyyət yoxdur.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                    <tr style="border-bottom: 1px solid #f8fafc; transition: background 0.2s;"
                                        onmouseover="this.style.background='#fcfdff'"
                                        onmouseout="this.style.background='transparent'">
                                        <td style="padding: 14px 16px;">
                                            <span
                                                style="display: block; font-weight: 600; color: #334155; font-size: 12px;"><?php echo e($activity['faculty']); ?></span>
                                        </td>
                                        <td style="padding: 14px 16px;">
                                            <span
                                                style="display: block; color: #475569; font-size: 12px; font-weight: 500;"><?php echo e($activity['specialty']); ?></span>
                                        </td>
                                        <td style="padding: 14px 16px; text-align: center;">
                                            <div style="font-weight: 500; color: #1e293b; font-size: 12px;">
                                                <?php echo e($activity['group']); ?>
                                            </div>
                                            <div style="font-size: 11px; color: var(--text-muted);">
                                                <?php echo e($activity['course_level']); ?>-cü kurs
                                            </div>
                                        </td>
                                        <td style="padding: 14px 16px;">
                                            <span
                                                style="display: block; font-weight: 700; color: var(--primary); margin-bottom: 2px; font-size: 12px;"><?php echo e($activity['course']); ?></span>
                                            <span
                                                style="display: block; font-size: 11px; color: var(--text-muted);"><?php echo e($activity['topic']); ?></span>
                                        </td>
                                        <td style="padding: 14px 16px;">
                                            <span
                                                style="background: #eef2ff; color: #4f46e5; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; white-space: nowrap;">
                                                <?php echo e($activity['lesson_type']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 14px 16px; text-align: right; white-space: nowrap;">
                                            <span
                                                style="display: block; font-size: 12px; color: #64748b; font-weight: 500;"><?php echo date('d.m.Y', strtotime($activity['date'])); ?></span>
                                            <span
                                                style="display: block; font-size: 10px; color: #94a3b8;"><?php echo date('H:i', strtotime($activity['date'])); ?></span>
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