<?php
/**
 * Notifications API
 */
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Giriş tələb olunur'], 401);
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // 1. Standart bildirişləri müvəqqəti söndürürük (Yalnız canlı dərslər üçün tələb var)
        $notifications = []; // Əvvəllər: $db->fetchAll(...)

        $myCourseIds = $_SESSION['my_course_ids'] ?? [];
        if (empty($myCourseIds) && isset($_SESSION['tmis_token'])) {
            $tmisSubjects = tmis_get('/student/subjects');
            $allCourseIds = [];
            if ($tmisSubjects && is_array($tmisSubjects)) {
                foreach ($tmisSubjects as $s) {
                    if (isset($s['id']))
                        $allCourseIds[] = (int) $s['id'];
                }
            }
            $localEnrollments = $db->fetchAll("SELECT course_id FROM enrollments WHERE user_id = ?", [$currentUser['id']]);
            foreach ($localEnrollments as $e) {
                $allCourseIds[] = (int) $e['course_id'];
            }
            $myCourseIds = array_unique($allCourseIds);
            $_SESSION['my_course_ids'] = $myCourseIds;
        }

        $allowedCourseIds = implode(',', array_map('intval', $myCourseIds));

        $whereClause = "(a.course_id IS NULL OR a.course_id IN (SELECT course_id FROM enrollments WHERE user_id = ?))";
        if (!empty($allowedCourseIds)) {
            $whereClause = "(a.course_id IS NULL OR a.course_id IN ($allowedCourseIds) OR a.course_id IN (SELECT course_id FROM enrollments WHERE user_id = ?))";
        }

        $whereClause .= " AND (a.expires_at IS NULL OR a.expires_at > NOW()) AND (a.category = 'live_started' OR a.category = 'general')";

        // Canlı yayım bildirişlərini (Alerts) al
        $liveAlerts = $db->fetchAll("
            SELECT a.id, 
                   CONCAT('Canlı Bildiriş: ', COALESCE(i.name, CONCAT(u.first_name, ' ', u.last_name))) as title, 
                   a.message, a.type, 0 as is_read, a.created_at, 'live' as source,
                   c.title as course_title
            FROM live_alerts a
            LEFT JOIN instructors i ON a.instructor_id = i.id
            LEFT JOIN users u ON i.user_id = u.id
            LEFT JOIN courses c ON a.course_id = c.id
            WHERE $whereClause
            ORDER BY a.created_at DESC LIMIT 15
        ", [$currentUser['id']]);

        // İkisini birləşdir və sırala (Canlı bildirişləri və yeni tarixliləri önə çək)
        $allNotifications = array_merge($notifications, $liveAlerts);
        usort($allNotifications, function ($a, $b) {
            // Source 'live' olanları həmişə ən başda göstər
            if ($a['source'] === 'live' && $b['source'] !== 'live')
                return -1;
            if ($a['source'] !== 'live' && $b['source'] === 'live')
                return 1;

            // Eyni tipdəsə tarixə görə sırala
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        $unreadCount = $db->fetch("
            SELECT COUNT(*) as count FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ", [$currentUser['id']]);

        // Live alert-lər həmişə "oxunmamış" kimi sayılsın (və ya sadəcə indikator üçün)
        $totalUnread = $unreadCount['count'] + count($liveAlerts);

        jsonResponse([
            'success' => true,
            'notifications' => array_slice($allNotifications, 0, 20),
            'unread_count' => $totalUnread
        ]);
        break;

    case 'POST':
        // Bildirişi oxunmuş kimi işarələ
        $data = json_decode(file_get_contents('php://input'), true);
        $notificationId = $data['notification_id'] ?? null;

        if ($notificationId) {
            $db->update(
                'notifications',
                ['is_read' => 1],
                'id = :id AND user_id = :user_id',
                ['id' => $notificationId, 'user_id' => $currentUser['id']]
            );
        } else {
            // Hamısını oxunmuş kimi işarələ
            $db->query("
                UPDATE notifications SET is_read = 1 WHERE user_id = ?
            ", [$currentUser['id']]);
        }

        jsonResponse(['success' => true, 'message' => 'Bildirişlər yeniləndi']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}
