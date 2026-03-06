<?php
header('Content-Type: application/json');
require_once '../student/config/database.php';

$db = Database::getInstance();

try {
    // Tələbə sessiyasını yoxla (əgər tələbə panelidirsə)
    session_name('DISTANT_STUDENT_SESSION');
    @session_start();
    $studentId = $_SESSION['user_id'] ?? null;
    $myCourseIds = $_SESSION['my_course_ids'] ?? [];

    if (empty($myCourseIds) && isset($_SESSION['tmis_token'])) {
        require_once '../student/includes/tmis_api.php';
        $tmisSubjects = TmisApi::subjects($_SESSION['tmis_token']);
        $allCourseIds = [];
        if ($tmisSubjects['success'] && isset($tmisSubjects['data'])) {
            $data = $tmisSubjects['data'];
            if (isset($data['success']) && isset($data['data'])) {
                $data = $data['data'];
            }
            foreach ($data as $s) {
                if (isset($s['id'])) {
                    $allCourseIds[] = (int) $s['id'];
                }
            }
        }

        // Lokal enrollments fallback
        if ($studentId) {
            $localEnrollments = $db->fetchAll("SELECT course_id FROM enrollments WHERE user_id = ?", [$studentId]);
            foreach ($localEnrollments as $e) {
                $allCourseIds[] = (int) $e['course_id'];
            }
        }

        $myCourseIds = array_unique($allCourseIds);
        $_SESSION['my_course_ids'] = $myCourseIds;
    }

    if (!$studentId) {
        // Tələbə giriş etməyibsə ona heç bir dərs bildirişi getməməlidir
        echo json_encode(['success' => true, 'alerts' => []]);
        exit;
    }

    // Get active alerts that haven't expired AND whose associated live class is still 'live'
    $sql = "SELECT a.*, COALESCE(i.name, CONCAT(u.first_name, ' ', u.last_name)) as instructor_name,
                   c.title as course_title
            FROM live_alerts a
            LEFT JOIN instructors i ON a.instructor_id = i.id
            LEFT JOIN users u ON i.user_id = u.id
            LEFT JOIN courses c ON a.course_id = c.id
            WHERE (a.expires_at IS NULL OR a.expires_at > NOW())
              AND (a.category = 'live_started' OR a.category = 'general')";

    $params = [];
    if ($studentId) {
        $allowedCourseIds = implode(',', array_map('intval', $myCourseIds));
        // Əgər tələbə giriş edibsə, yalnız qlobal və ya öz fənlərinə aid olanları görsün
        if (!empty($allowedCourseIds)) {
            $sql .= " AND (a.course_id IS NULL OR a.course_id IN ($allowedCourseIds) OR a.course_id IN (SELECT course_id FROM enrollments WHERE user_id = ?))";
        } else {
            $sql .= " AND (a.course_id IS NULL OR a.course_id IN (SELECT course_id FROM enrollments WHERE user_id = ?))";
        }
        $params[] = $studentId;
    }

    $sql .= " ORDER BY a.created_at DESC LIMIT 5";
    $alerts = $db->fetchAll($sql, $params);

    echo json_encode(['success' => true, 'alerts' => $alerts]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
