<?php
/**
 * API: Tələbə İştirakı Analitikası
 * Returns attendance analytics data for the chart
 */
header('Content-Type: application/json');

require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Giriş tələb olunur']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// Get period from request (7, 30, or 'all')
$period = $_GET['period'] ?? '7';

// Find instructor
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

if (!$instructor) {
    echo json_encode(['success' => false, 'message' => 'Müəllim tapılmadı']);
    exit;
}

// Determine date range
$dateCondition = "";
if ($period === '7') {
    $dateCondition = "AND lc.start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($period === '30') {
    $dateCondition = "AND lc.start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}
// 'all' means no date filter

try {
    // Get classes with attendance data
    $classes = $db->fetchAll("
        SELECT 
            lc.id,
            lc.title,
            lc.course_id,
            c.title as course_title,
            c.initial_students,
            DATE(lc.start_time) as class_date,
            lc.start_time,
            (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = lc.course_id) as enrolled_count,
            (SELECT COUNT(DISTINCT user_id) FROM live_attendance la WHERE la.live_class_id = lc.id AND la.role = 'student') as attended_count
        FROM live_classes lc
        JOIN courses c ON lc.course_id = c.id
        WHERE lc.instructor_id = ? 
        AND lc.status IN ('ended', 'completed')
        {$dateCondition}
        ORDER BY lc.start_time DESC
        LIMIT 50
    ", [$instructor['id']]);

    // Calculate statistics
    $totalClasses = count($classes);
    $totalEnrolled = 0;
    $totalAttended = 0;
    $courseStats = [];
    $dailyStats = [];

    foreach ($classes as $class) {
        // Total students = max of initial_students vs actual enrollments
        $initialStudents = intval($class['initial_students'] ?? 0);
        $enrolledCount = intval($class['enrolled_count']);
        $totalStudents = max($initialStudents, $enrolledCount);

        $attended = intval($class['attended_count']);
        $rate = $totalStudents > 0 ? min(100, round(($attended / $totalStudents) * 100)) : 0;

        $totalEnrolled += $totalStudents;
        $totalAttended += $attended;

        // Course-wise stats
        $courseTitle = $class['course_title'];
        if (!isset($courseStats[$courseTitle])) {
            $courseStats[$courseTitle] = ['enrolled' => 0, 'attended' => 0, 'classes' => 0];
        }
        $courseStats[$courseTitle]['enrolled'] += $totalStudents;
        $courseStats[$courseTitle]['attended'] += $attended;
        $courseStats[$courseTitle]['classes']++;

        // Daily stats (group by date)
        $date = $class['class_date'];
        if (!isset($dailyStats[$date])) {
            $dailyStats[$date] = ['rate' => 0, 'classes' => 0];
        }
        $dailyStats[$date]['rate'] += $rate;
        $dailyStats[$date]['classes']++;
    }

    // Calculate averages for daily stats
    foreach ($dailyStats as $date => &$data) {
        $data['rate'] = round($data['rate'] / $data['classes']);
    }

    // Sort daily stats by date
    ksort($dailyStats);

    // Prepare chart data (last N days based on period)
    $chartLabels = [];
    $chartData = [];

    if ($period === '7') {
        // Last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dayName = ['Baz', 'B.e', 'Ç.a', 'Çər', 'C.a', 'Cüm', 'Şən'][date('w', strtotime($date))];
            $chartLabels[] = $dayName;
            $chartData[] = $dailyStats[$date]['rate'] ?? 0;
        }
    } elseif ($period === '30') {
        // Last 30 days - group by week
        for ($i = 4; $i >= 0; $i--) {
            $weekStart = date('Y-m-d', strtotime("-" . ($i * 7) . " days"));
            $weekEnd = date('Y-m-d', strtotime("-" . (($i - 1) * 7) . " days"));
            $chartLabels[] = date('d.m', strtotime($weekStart));

            // Calculate average for this week
            $weekTotal = 0;
            $weekCount = 0;
            foreach ($dailyStats as $d => $data) {
                if ($d >= $weekStart && $d < $weekEnd) {
                    $weekTotal += $data['rate'];
                    $weekCount++;
                }
            }
            $chartData[] = $weekCount > 0 ? round($weekTotal / $weekCount) : 0;
        }
    } else {
        // All time - group by course
        foreach ($courseStats as $course => $data) {
            $chartLabels[] = mb_substr($course, 0, 15) . (mb_strlen($course) > 15 ? '...' : '');
            $rate = $data['enrolled'] > 0 ? min(100, round(($data['attended'] / $data['enrolled']) * 100)) : 0;
            $chartData[] = $rate;
        }
    }

    // Calculate overall average
    $overallAverage = $totalEnrolled > 0 ? min(100, round(($totalAttended / $totalEnrolled) * 100)) : 0;

    // Find most active and least active courses
    $mostActive = 'Məlumat yoxdur';
    $leastActive = 'Məlumat yoxdur';

    if (!empty($courseStats)) {
        $courseRates = [];
        foreach ($courseStats as $course => $data) {
            if ($data['enrolled'] > 0) {
                $courseRates[$course] = ($data['attended'] / $data['enrolled']) * 100;
            }
        }
        if (!empty($courseRates)) {
            asort($courseRates);
            $leastActive = array_key_first($courseRates);
            $mostActive = array_key_last($courseRates);
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'labels' => $chartLabels,
            'values' => $chartData,
            'average' => $overallAverage,
            'mostActive' => $mostActive,
            'leastActive' => $leastActive,
            'totalClasses' => $totalClasses,
            'totalStudents' => $totalAttended
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Xəta: ' . $e->getMessage()]);
}
