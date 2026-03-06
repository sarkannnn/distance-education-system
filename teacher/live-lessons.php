<?php
/**
 * Teacher Live Lessons - Canlı Dərslər
 */
$currentPage = 'live';
$pageTitle = 'Canlı Dərslər';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireInstructor();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();
$isAdmin = ($_SESSION['user_role'] === 'admin');

// Müəllimin instructor_id-sini tap (Daha dəqiq axtarış)
$instructor = null;
try {
    $instructor = $db->fetch(
        "SELECT id, name FROM instructors WHERE user_id = ? OR email = ?",
        [$currentUser['id'], $currentUser['email']]
    );

    // Dublyajın qarşısını almaq üçün fallback: Ad ilə axtarış
    if (!$instructor && !empty($currentUser['name'])) {
        $instructor = $db->fetch(
            "SELECT id, name FROM instructors WHERE name LIKE ?",
            ['%' . $currentUser['name'] . '%']
        );
    }
} catch (Exception $e) {
}

// ============================================================
// TMİS Token — bütün API sorğuları üçün
// ============================================================
$tmisToken = TmisApi::getToken();

// ============================================================
// 1. Əvvəlcə LOKAL bazadan aktiv (live) dərsi yoxla
// ============================================================
$activeLesson = null;
$upcomingLessons = [];

// Lokal DB-dən aktiv dərsi axtar (əsas mənbə)
try {
    $localActiveSql = "SELECT lc.*, c.title as course_title 
                       FROM live_classes lc 
                       LEFT JOIN courses c ON lc.course_id = c.id 
                       WHERE lc.status = 'live'";
    $localActiveParams = [];

    if ($instructor) {
        $localActiveSql .= " AND lc.instructor_id = ?";
        $localActiveParams[] = $instructor['id'];
    }
    $localActiveSql .= " ORDER BY lc.id DESC LIMIT 1";

    $localActive = $db->fetch($localActiveSql, $localActiveParams);

    if ($localActive) {
        $startTimeFormatted = date('H:i', strtotime($localActive['started_at'] ?? $localActive['start_time']));
        $endTimeFormatted = date('H:i', strtotime($localActive['end_time']));

        $activeLesson = [
            'id' => $localActive['id'],
            'title' => $localActive['title'] ?? 'Canlı Dərs',
            'course_title' => $localActive['course_title'] ?? '',
            'start_time' => $localActive['started_at'] ?? $localActive['start_time'],
            'end_time' => $localActive['end_time'],
            'max_participants' => 50,
            'instructor_name' => $localActive['instructor_name'] ?? '',
            'status' => 'live'
        ];
    }
} catch (Exception $e) {
    error_log('Lokal aktiv dərs yoxlanması xətası: ' . $e->getMessage());
}

// 2. Əgər lokal DB-də aktiv dərs yoxdursa, TMİS API-dən yoxla
if (!$activeLesson && $tmisToken) {
    try {
        $statusResult = TmisApi::getLiveSessionStatus($tmisToken);

        if ($statusResult['success'] && isset($statusResult['data'])) {
            $statusData = $statusResult['data'];

            if ($statusData['has_active_session'] && !empty($statusData['session'])) {
                $session = $statusData['session'];
                $tmisLessonId = $session['id'] ?? $session['live_session_id'] ?? 0;

                // TMİS aktiv deyirsə, LOKAL bazada həmin dərsin statusunu yoxla
                // ID, Session ID və ya Mövzu+Müəllim kombinasiyası ilə yoxlayırıq ki, "ruh" dərslər qalmasın
                $localCheckSql = "SELECT status FROM live_classes 
                                  WHERE (id = ? OR tmis_session_id = ?)";
                $localCheckParams = [$tmisLessonId, $tmisLessonId];

                $topicToCheck = $session['topic'] ?? ($session['title'] ?? '');
                if (!empty($topicToCheck) && $instructor) {
                    $localCheckSql .= " OR (title = ? AND instructor_id = ? AND started_at > DATE_SUB(NOW(), INTERVAL 1 DAY))";
                    $localCheckParams[] = $topicToCheck;
                    $localCheckParams[] = $instructor['id'];
                }

                $localCheckSql .= " ORDER BY id DESC LIMIT 1";
                $localCheck = $db->fetch($localCheckSql, $localCheckParams);
                $localStatus = $localCheck['status'] ?? null;

                if (!$localStatus || $localStatus === 'live') {
                    // Lokal DB-də ya yoxdur, ya da hələ canlıdır — TMİS-ə etibar et
                    $activeLesson = [
                        'id' => $tmisLessonId,
                        'title' => $topicToCheck ?: 'Canlı Dərs',
                        'course_title' => $session['course_title'] ?? '',
                        'start_time' => $session['started_at'] ?? ($session['start_time'] ?? date('H:i')),
                        'end_time' => $session['end_time'] ?? date('H:i', strtotime('+90 minutes')),
                        'max_participants' => $session['max_participants'] ?? 50,
                        'instructor_name' => $session['instructor_name'] ?? '',
                        'status' => 'live'
                    ];
                }
                // else: Lokal DB-də "ended"/"completed" yazılıb — TMİS-i nəzərə alma
            }
        }
    } catch (Exception $e) {
        error_log('TMİS Live Session Status xətası: ' . $e->getMessage());
    }
}

// Əgər aktiv dərs yoxdursa, bugünkü dərsləri gətir (TMİS + Fallback)
if (!$activeLesson && $instructor) {
    try {
        $daysOfWeek = ['Bazar ertəsi', 'Çərşənbə axşamı', 'Çərşənbə', 'Cümə axşamı', 'Cümə', 'Şənbə', 'Bazar'];
        $todayName = $daysOfWeek[date('N') - 1];

        // 1. TMİS API-dən bugünkü cədvəli yoxla
        if ($tmisToken) {
            $tmisSchedule = TmisApi::getScheduleToday($tmisToken);
            if ($tmisSchedule['success'] && !empty($tmisSchedule['data']) && is_array($tmisSchedule['data'])) {
                foreach ($tmisSchedule['data'] as $item) {
                    // TMİS datası varsa, onu formatla
                    $courseId = (int) ($item['id'] ?? ($item['course_id'] ?? 0));

                    // Lokal bazadan tam datanı çək (metadata üçün)
                    $course = $db->fetch("SELECT c.*, ins.name as instructor_name, ins.faculty, ins.specialty, ins.course_level,
                        (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.status IN ('ended', 'completed')) as completed_lessons,
                        (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.lesson_type = 'lecture' AND lc.status IN ('ended', 'completed')) as completed_lectures,
                        (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.lesson_type = 'seminar' AND lc.status IN ('ended', 'completed')) as completed_seminars,
                        (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.lesson_type = 'laboratory' AND lc.status IN ('ended', 'completed')) as completed_laboratories
                                         FROM courses c 
                                         LEFT JOIN instructors ins ON c.instructor_id = ins.id
                                         WHERE c.id = ? OR c.tmis_subject_id = ?", [$courseId, $courseId]);

                    if ($course) {
                        $courseStart = strtotime($course['created_at']);
                        $weekNumber = ceil((time() - $courseStart) / (7 * 24 * 60 * 60));
                        $weekNumber = max(1, $weekNumber);

                        $totalL = (int) ($course['total_lessons'] ?: 10);
                        $completedL = (int) ($course['completed_lessons'] ?: 0);

                        $upcomingLessons[] = [
                            'id' => $course['id'],
                            'title' => $course['title'],
                            'instructor_name' => $course['instructor_name'] ?? 'Müəllim',
                            'time' => $item['start_time'] ?? (date('H:i', strtotime($course['start_time']))),
                            'week_number' => $weekNumber,
                            'next_lesson' => $completedL + 1,
                            'total_lessons' => $totalL,
                            'completed_lessons' => $completedL,
                            'remaining_lessons' => max(0, $totalL - $completedL),
                            'lecture_count' => $course['lecture_count'] ?: 0,
                            'seminar_count' => $course['seminar_count'] ?: 0,
                            'laboratory_count' => $course['laboratory_count'] ?: 0,
                            'completed_lectures' => $course['completed_lectures'] ?: 0,
                            'completed_seminars' => $course['completed_seminars'] ?: 0,
                            'completed_laboratories' => $course['completed_laboratories'] ?: 0,
                            'faculty' => $course['faculty'] ?? '',
                            'specialty' => $course['specialty'] ?? '',
                            'course_level' => $course['course_level'] ?? '-'
                        ];
                    }
                }
            }
        }

        // 2. TMİS boşdursa, lokal fallback et (weekly_days dərsləri)
        if (empty($upcomingLessons)) {
            $allCourses = $db->fetchAll(
                "SELECT c.id, c.title, c.weekly_days, c.start_time, c.total_lessons, c.lecture_count, c.seminar_count, c.laboratory_count, c.created_at,
                        ins.name as instructor_name, ins.faculty as ins_faculty, ins.specialty as ins_specialty, ins.course_level as ins_level,
                        (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.status IN ('ended', 'completed')) as completed_lessons,
                        (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.lesson_type = 'lecture' AND lc.status IN ('ended', 'completed')) as completed_lectures,
                        (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.lesson_type = 'seminar' AND lc.status IN ('ended', 'completed')) as completed_seminars,
                        (SELECT COUNT(*) FROM live_classes lc WHERE lc.course_id = c.id AND lc.lesson_type = 'laboratory' AND lc.status IN ('ended', 'completed')) as completed_laboratories
                 FROM courses c
                 LEFT JOIN instructors ins ON c.instructor_id = ins.id
                 WHERE c.instructor_id = ? AND c.status != 'draft'",
                [$instructor['id']]
            );

            foreach ($allCourses as $course) {
                if ($course['weekly_days']) {
                    $courseDays = array_map('trim', explode(',', $course['weekly_days']));
                    if (in_array($todayName, $courseDays)) {
                        $courseStart = strtotime($course['created_at']);
                        $weekNumber = ceil((time() - $courseStart) / (7 * 24 * 60 * 60));
                        $weekNumber = max(1, $weekNumber);

                        $totalL = (int) ($course['total_lessons'] ?: 10);
                        $completedL = (int) ($course['completed_lessons'] ?: 0);

                        $upcomingLessons[] = [
                            'id' => $course['id'],
                            'title' => $course['title'],
                            'instructor_name' => $course['instructor_name'],
                            'time' => date('H:i', strtotime($course['start_time'])),
                            'week_number' => $weekNumber,
                            'next_lesson' => $completedL + 1,
                            'total_lessons' => $totalL,
                            'completed_lessons' => $completedL,
                            'remaining_lessons' => max(0, $totalL - $completedL),
                            'lecture_count' => $course['lecture_count'] ?: 0,
                            'seminar_count' => $course['seminar_count'] ?: 0,
                            'laboratory_count' => $course['laboratory_count'] ?: 0,
                            'completed_lectures' => $course['completed_lectures'] ?: 0,
                            'completed_seminars' => $course['completed_seminars'] ?: 0,
                            'completed_laboratories' => $course['completed_laboratories'] ?: 0,
                            'faculty' => $course['ins_faculty'] ?: '',
                            'specialty' => $course['ins_specialty'] ?: '',
                            'course_level' => $course['ins_level'] ?: '-'
                        ];
                    }
                }
            }
        }

        usort($upcomingLessons, function ($a, $b) {
            return strcmp($a['time'], $b['time']);
        });

    } catch (Exception $e) {
        // Fail silently
    }
    // $isAdmin defined at the top
}

// Qalan vaxtı hesabla
$remainingTime = '00:00:00';
if ($activeLesson) {
    $endTime = strtotime($activeLesson['end_time']);
    $now = time();
    if ($endTime > $now) {
        $diff = $endTime - $now;
        $remainingTime = sprintf('%02d:%02d:%02d', ($diff / 3600), ($diff / 60 % 60), $diff % 60);
    }
}

// İştirakçıları bazadan çək
$participants = [];
if ($activeLesson) {
    try {
        $participants = $db->fetchAll("SELECT u.first_name, u.last_name, lp.joined_at 
                                      FROM live_class_participants lp 
                                      JOIN users u ON lp.user_id = u.id 
                                      WHERE lp.live_class_id = ? 
                                      ORDER BY lp.joined_at DESC",
            [$activeLesson['id']]
        );
    } catch (Exception $e) {
    }
}

// Statistikalar və İştirak Məlumatları
$participationStats = [
    'avg' => 0,
    'most_active' => 'Məlumat yoxdur',
    'least_active' => 'Məlumat yoxdur',
    'daily' => array_fill(0, 7, 0)
];

if ($instructor) {
    try {
        // Son 7 günün dərsləri və iştirakçıları
        $weeklyClasses = $db->fetchAll(
            "SELECT lc.id, lc.course_id, c.title, 
                    COALESCE(lc.started_at, lc.start_time, lc.created_at) as effective_start,
                    c.initial_students,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = lc.course_id) as enrollment_count,
                    (SELECT COUNT(DISTINCT user_id) FROM live_attendance la WHERE la.live_class_id = lc.id AND la.role = 'student') as participant_count
             FROM live_classes lc 
             JOIN courses c ON lc.course_id = c.id 
             WHERE lc.instructor_id = ? AND COALESCE(lc.started_at, lc.start_time, lc.created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             AND lc.status IN ('ended', 'completed', 'live')",
            [$instructor['id']]
        );

        if (!empty($weeklyClasses)) {
            $courseParticipation = [];
            $dailyStats = array_fill(0, 7, ['sum' => 0, 'count' => 0]);
            $totalP = 0;
            $count = 0;

            // Bu həftənin (Bazar ertəsindən başlayan) başlanğıc tarixi
            $thisWeekStart = strtotime('monday this week 00:00:00');

            foreach ($weeklyClasses as $class) {
                $classStartTime = strtotime($class['effective_start']);

                // Real tələbə sayını hesabla
                $totalStudents = intval($class['enrollment_count']);
                if ($totalStudents === 0) {
                    $totalStudents = intval($class['initial_students'] ?? 0);
                }

                if ($totalStudents > 0) {
                    $rate = ($class['participant_count'] / $totalStudents) * 100;
                    $rate = min(100, $rate);

                    $totalP += $rate;
                    $count++;

                    // Kurs üzrə qruplaşdır
                    if (!isset($courseParticipation[$class['title']])) {
                        $courseParticipation[$class['title']] = ['sum' => 0, 'count' => 0];
                    }
                    $courseParticipation[$class['title']]['sum'] += $rate;
                    $courseParticipation[$class['title']]['count']++;

                    // Gün üzrə qruplaşdır (Həftənin gününə görə: 0=B.e, ..., 6=Baz)
                    $dayOfWeek = (int) date('N', $classStartTime) - 1; // 1 (Mon) - 7 (Sun) -> 0 - 6
                    if ($classStartTime >= $thisWeekStart) {
                        $dailyStats[$dayOfWeek]['sum'] += $rate;
                        $dailyStats[$dayOfWeek]['count']++;
                    }
                }
            }

            if ($count > 0) {
                $participationStats['avg'] = round($totalP / $count);

                // Günlük faizləri hesabla
                foreach ($dailyStats as $idx => $ds) {
                    $participationStats['daily'][$idx] = $ds['count'] > 0 ? ($ds['sum'] / $ds['count']) : 0;
                }

                // Ən aktiv / Ən aşağı
                $courseAverages = [];
                foreach ($courseParticipation as $title => $data) {
                    if ($data['count'] > 0) {
                        $courseAverages[$title] = $data['sum'] / $data['count'];
                    }
                }

                if (!empty($courseAverages)) {
                    asort($courseAverages);
                    $participationStats['least_active'] = array_key_first($courseAverages);
                    $participationStats['most_active'] = array_key_last($courseAverages);
                }
            }
        }
    } catch (Exception $e) {
    }
}


// İştirakçı sayını aktiv dərs məlumatına əlavə et
$participantCount = count($participants);

// Müəllimin fənlərini (kurslarını) al - TMİS API vasitəsilə
$instructorCourses = [];
if ($tmisToken) {
    try {
        $coursesResult = TmisApi::getSubjectsList($tmisToken);
        if ($coursesResult['success'] && isset($coursesResult['data'])) {
            // Fənləri əlifba sırası ilə düzürük
            $subjects = $coursesResult['data'];
            usort($subjects, function ($a, $b) {
                return strcmp($a['subject_name'] ?? '', $b['subject_name'] ?? '');
            });

            foreach ($subjects as $cs) {
                $subjName = trim($cs['subject_name'] ?? '');
                $profName = trim($cs['profession_name'] ?? '');
                $courseLevel = isset($cs['course']) ? $cs['course'] . '-ci kurs' : '';

                $title = $subjName;
                if (!empty($profName)) {
                    $title .= " - " . $profName;
                }
                if (!empty($courseLevel)) {
                    $title .= " (" . $courseLevel . ")";
                }

                $instructorCourses[] = [
                    'id' => $cs['id'],
                    'title' => $title
                ];
            }
        }
    } catch (Exception $e) {
        error_log('TMİS Subjects List xətası: ' . $e->getMessage());
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
        <?php if (isset($_GET['error'])): ?>
            <div
                style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 20px; border-radius: 12px; margin: 20px 30px 0; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px;">
                ⚠️ <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['ended'])): ?>
            <div
                style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 14px 20px; border-radius: 12px; margin: 20px 30px 0; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px;">
                ✅ Dərs uğurla bitirildi.
            </div>
        <?php endif; ?>
        <div class="content-container space-y-6">
            <!-- Tab Navigation -->
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
                <a href="live-lessons.php" class="nav-tab active">Canlı Cədvəl</a>
                <a href="courses.php" class="nav-tab">Fənlərim</a>
            </div>

            <!-- Main Live Lesson Card -->
            <div class="gradient-stat blue" style="padding: 32px; border-radius: 20px;">
                <div
                    class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 <?php echo (!$activeLesson && !empty($upcomingLessons)) ? 'border-b border-white/20' : ''; ?>">
                    <?php if ($activeLesson): ?>
                        <div>
                            <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 12px;">Dərs:
                                <?php echo e($activeLesson['title']); ?>
                            </h2>
                            <div style="display: flex; flex-direction: column; gap: 8px; opacity: 0.9;">
                                <p style="font-size: 15px;">
                                    <strong>Saat:</strong>
                                    <?php echo date('H:i', strtotime($activeLesson['start_time'])); ?> -
                                    <?php echo date('H:i', strtotime($activeLesson['end_time'])); ?>
                                    <?php if ($isAdmin && !empty($activeLesson['instructor_name'])): ?>
                                        <span
                                            style="opacity: 0.8; margin-left: 8px; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 6px; font-size: 13px;">
                                            Müəllim: <?php echo e($activeLesson['instructor_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span style="opacity: 0.7; margin-left: 8px;">(qalan vaxt:
                                        <span id="countdown"><?php echo $remainingTime; ?></span>)
                                    </span>
                                </p>
                                <p style="font-size: 15px;">
                                    <strong>İştirak:</strong>
                                    <?php echo $participantCount; ?> / <?php echo $activeLesson['max_participants']; ?>
                                    tələbə
                                </p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="flex: 1;">
                            <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 8px;">Salam,
                                <?php echo e($currentUser['first_name'] ?? ''); ?>!
                            </h2>
                            <p style="opacity: 0.9; font-size: 15px;">
                                Hazırda aktiv canlı dərsiniz yoxdur.
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center gap-3">
                        <?php if ($activeLesson): ?>
                            <button onclick="copyLiveLink('<?php echo 'studio?id=' . $activeLesson['id']; ?>')"
                                class="header-btn" title="Linki Kopyala"
                                style="background: rgba(255,255,255,0.2); color: white; width: 45px; height: 45px;">
                                <i data-lucide="copy"></i>
                            </button>
                            <a href="live-studio?id=<?php echo $activeLesson['id']; ?>" class="btn btn-primary btn-lg"
                                style="background: white; color: var(--accent); border: none; font-weight: 700; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                                <i data-lucide="video"></i>
                                Studiyanı Aç
                            </a>
                        <?php else: ?>
                            <div class="flex items-center gap-3">
                                <a href="courses" class="btn btn-primary btn-lg"
                                    style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); font-weight: 700; display: flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 12px;">
                                    <i data-lucide="book-open"></i>
                                    Fənlərim
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="grid-3">
                <!-- Left Content (2 columns) -->
                <div class="col-span-2 space-y-6">
                    <!-- Send Live Alert Card -->
                    <div class="card">
                        <div class="card-header">
                            <i data-lucide="megaphone" style="color: var(--error);"></i>
                            <h2>Canlı Bildiriş Yayınla</h2>
                        </div>
                        <form id="sendAlertForm" action="api/send_alert.php" method="POST" class="space-y-4">
                            <div class="form-group">
                                <label>Bildiriş Mesajı</label>
                                <textarea name="message" class="form-input" rows="2"
                                    placeholder="Məsələn: Dərs 15 dəqiqə sonra başlayacaq..." required
                                    style="border-radius: 12px;"></textarea>
                            </div>
                            <div class="grid-2 gap-4">
                                <div class="form-group">
                                    <label>Hədəf Kütlə (Fənn)</label>
                                    <select name="course_id" class="form-input"
                                        style="border-radius: 12px; height: 50px;">
                                        <option value="">Bütün tələbələr</option>
                                        <?php foreach ($instructorCourses as $c): ?>
                                            <option value="<?php echo $c['id']; ?>"><?php echo e($c['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Bildiriş Tipi</label>
                                    <select name="type" class="form-input" style="border-radius: 12px; height: 50px;">
                                        <option value="info">Məlumat (Mavi)</option>
                                        <option value="warning">Xəbərdarlıq (Sarı)</option>
                                        <option value="error">Təcili (Qırmızı)</option>
                                        <option value="success">Uğurlu (Yaşıl)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Görünmə Müddəti (dəqiqə)</label>
                                <input type="number" name="duration" class="form-input" value="15" min="1" max="4320"
                                    style="border-radius: 12px; height: 50px;">
                            </div>
                            <button type="submit" class="btn btn-primary btn-block"
                                style="border-radius: 12px; height: 50px; font-weight: bold;">
                                <i data-lucide="send"></i>
                                İndi Yayınla
                            </button>
                        </form>
                    </div>

                    <!-- Statistics Card -->
                    <div class="card">
                        <div class="card-header"
                            style="border-bottom: 1px solid rgba(0,0,0,0.05); margin-bottom: 20px; padding-bottom: 15px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i data-lucide="bar-chart-2" style="color: var(--primary);"></i>
                                <h2 style="font-size: 18px; font-weight: 700;">Tələbə İştirakı - Bu həftə</h2>
                            </div>
                        </div>

                        <!-- Top Stats Row -->
                        <div
                            style="display: grid; grid-template-columns: 80px 1fr 1fr; gap: 20px; margin-bottom: 30px; background: #f8fafc; padding: 20px; border-radius: 16px; border: 1px solid #f1f5f9;">
                            <div>
                                <p
                                    style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 2px;">
                                    Ortalama</p>
                                <p style="font-size: 24px; font-weight: 800; color: #1e293b;">
                                    <?php echo $participationStats['avg']; ?>%
                                </p>
                            </div>
                            <div style="min-width: 0;">
                                <p
                                    style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 2px;">
                                    Ən Aktiv Fənn</p>
                                <p style="font-size: 13px; font-weight: 700; color: #4f46e5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                    title="<?php echo e($participationStats['most_active']); ?>">
                                    <?php echo e($participationStats['most_active']); ?>
                                </p>
                            </div>
                            <style>
                                .stat-label {
                                    font-size: 10px;
                                    text-transform: uppercase;
                                    color: #64748b;
                                    font-weight: 700;
                                    margin-bottom: 2px;
                                }

                                .stat-value-error {
                                    font-size: 13px;
                                    font-weight: 700;
                                    color: #ef4444;
                                    white-space: nowrap;
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                }
                            </style>
                            <div style="min-width: 0;">
                                <p class="stat-label">Ən Aşağı Fənn</p>
                                <p class="stat-value-error"
                                    title="<?php echo e($participationStats['least_active']); ?>">
                                    <?php echo e($participationStats['least_active']); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Bar Chart Area -->
                        <div
                            style="height: 180px; display: flex; align-items: flex-end; gap: 12px; padding: 0 10px; position: relative;">
                            <?php
                            $daysAZ = ['B.e', 'Ç.a', 'Çər', 'C.a', 'Cüm', 'Şən', 'Baz'];
                            foreach ($participationStats['daily'] as $index => $h):
                                ?>
                                <div
                                    style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end;">
                                    <!-- The Bar -->
                                    <div style="width: 80%; background: <?php echo $h > 0 ? 'linear-gradient(to top, var(--primary), #6366f1)' : '#f1f5f9'; ?>; height: <?php echo max(8, $h); ?>%; border-radius: 6px; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; box-shadow: <?php echo $h > 0 ? '0 4px 12px rgba(79, 70, 229, 0.15)' : 'none'; ?>;"
                                        title="<?php echo round($h); ?>%">
                                        <?php if ($h > 10): ?>
                                            <span
                                                style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 10px; color: #475569; font-weight: 800;"><?php echo round($h); ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Day Label -->
                                    <span style="font-size: 10px; color: #94a3b8; margin-top: 8px; font-weight: 600;">
                                        <?php echo $daysAZ[$index]; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

                <!-- Right Content (1 column) -->
                <div class="space-y-6">
                    <!-- Participants List - Yalnız canlı dərs olduqda göstər -->
                    <?php if ($activeLesson): ?>
                        <div class="card" style="background: var(--primary-dark); color: white;">
                            <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 20px;">Hazırda Aktivdir</h3>
                            <div
                                style="height: 180px; background: rgba(255,255,255,0.1); border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="video" style="width: 48px; height: 48px; opacity: 0.5;"></i>
                            </div>
                            <div class="space-y-4">
                                <?php if (empty($participants)): ?>
                                    <p style="text-align: center; opacity: 0.6; font-size: 13px; padding: 20px 0;">Hələ ki,
                                        heç
                                        kim qoşulmayıb.</p>
                                <?php else: ?>
                                    <?php foreach ($participants as $p): ?>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    style="width: 32px; height: 32px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">
                                                    <?php echo mb_substr($p['first_name'], 0, 1) . mb_substr($p['last_name'], 0, 1); ?>
                                                </div>
                                                <span style="font-size: 14px;">
                                                    <?php echo e($p['first_name'] . ' ' . $p['last_name']); ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span style="font-size: 11px; opacity: 0.7;">
                                                    <?php echo date('H:i', strtotime($p['joined_at'])); ?>
                                                </span>
                                                <button
                                                    style="width: 24px; height: 24px; border-radius: 4px; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center;">
                                                    <i data-lucide="mic" style="width: 12px; height: 12px;"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Notifications -->
                    <div class="card">
                        <div class="card-header">
                            <i data-lucide="bell"></i>
                            <h2>Bildirişlər</h2>
                        </div>
                        <div class="space-y-3">
                            <?php
                            $notifs = [];
                            try {
                                $notifs = $db->fetchAll("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3", [$currentUser['id']]);
                            } catch (Exception $e) {
                            }

                            if (empty($notifs)): ?>
                                <p style="text-align: center; opacity: 0.6; font-size: 13px; padding: 10px 0;">Yeni
                                    bildiriş
                                    yoxdur.</p>
                            <?php else:
                                foreach ($notifs as $n):
                                    $bgColor = 'rgba(59, 130, 246, 0.1)';
                                    $borderColor = 'rgba(59, 130, 246, 0.2)';
                                    $textColor = '#1d4ed8';

                                    if ($n['type'] === 'warning') {
                                        $bgColor = 'rgba(245, 158, 11, 0.1)';
                                        $borderColor = 'rgba(245, 158, 11, 0.2)';
                                        $textColor = '#b45309';
                                    } elseif ($n['type'] === 'error') {
                                        $bgColor = 'rgba(239, 68, 68, 0.1)';
                                        $borderColor = 'rgba(239, 68, 68, 0.2)';
                                        $textColor = '#b91c1c';
                                    } elseif ($n['type'] === 'success') {
                                        $bgColor = 'rgba(16, 185, 129, 0.1)';
                                        $borderColor = 'rgba(16, 185, 129, 0.2)';
                                        $textColor = '#047857';
                                    }
                                    ?>
                                    <div class="p-3 rounded-lg"
                                        style="background: <?php echo $bgColor; ?>; border: 1px solid <?php echo $borderColor; ?>;">
                                        <p style="font-size: 12px; color: <?php echo $textColor; ?>; font-weight: 500;">
                                            <?php echo e($n['title']); ?>
                                        </p>
                                        <p
                                            style="font-size: 11px; color: <?php echo $textColor; ?>; opacity: 0.8; margin-top: 2px;">
                                            <?php echo e($n['message']); ?>
                                        </p>
                                    </div>
                                <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <?php if ($activeLesson): ?>
                        <form action="api/end_live_class.php" method="POST">
                            <input type="hidden" name="live_class_id" value="<?php echo $activeLesson['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-block btn-lg"
                                style="border-radius: 16px; font-weight: 700;">
                                Dərsi Bitir
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php if ($activeLesson): ?>
    <script>     // Countdown Timer logic     let timeLeft = "<?php echo $remainingTime; ?>".split(':').reduce((acc, time) => (60 * acc) + +time);     const countdownEl = document.getElementById('countdown');
        if (timeLeft > 0) { const interval = setInterval(() => { timeLeft--; if (timeLeft <= 0) { clearInterval(interval); countdownEl.innerText = "00:00:00"; return; } const h = Math.floor(timeLeft / 3600).toString().padStart(2, '0'); const m = Math.floor((timeLeft % 3600) / 60).toString().padStart(2, '0'); const s = (timeLeft % 60).toString().padStart(2, '0'); countdownEl.innerText = `${h}:${m}:${s}`; }, 1000); }
        // Avtomatik Studiyanı aç (Yalnız dərs yeni başlayanda)     const urlParams = new URLSearchParams(window.location.search);     if (urlParams.get('started') === '1') {         const studioLink = "live-studio?id=<?php echo $activeLesson['id']; ?>";         setTimeout(() => {             window.location.href = studioLink;         }, 1000);     }
    </script>
<?php endif; ?>

<script>
    function copyLiveLink(link) {
        if (!link || link === "https://zoom.us/j/000000000") {
            alert("Canlı dərs linki hazır deyil.");
            return;
        }

        navigator.clipboard.writeText(link).then(() => {
            alert("Link kopyalandı! Tələbələrə göndərə bilərsiniz.");
        }).catch(err => {
            console.error('Xəta:', err);
        });
    }

    // Handle Send Alert Form
    document.getElementById('sendAlertForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i data-lucide="loader" class="animate-spin"></i> Göndərilir...';
        if (typeof lucide !== 'undefined') lucide.createIcons();

        try {
            const formData = new FormData(form);
            const response = await fetch('./api/send_alert', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alert('Bildiriş uğurla yayındı!');
                form.reset();
            } else {
                alert('Xəta: ' + result.message);
                console.error('Server error:', result);
            }
        } catch (error) {
            console.error('Fetch error:', error);
            alert('Sistem xətası baş verdi.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (typeof closeStartLiveModal === 'function') closeStartLiveModal();
        }
    });
</script>

<?php require_once 'includes/modal_start_live.php'; ?>

<?php require_once 'includes/footer.php'; ?>