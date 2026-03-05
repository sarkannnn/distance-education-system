<?php
/**
 * Statistics API
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

if ($method !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Əsas statistikalar
$stats = $db->fetch("
    SELECT * FROM user_statistics WHERE user_id = ?
", [$currentUser['id']]);

// Həftəlik performans
$weeklyPerformance = $db->fetchAll("
    SELECT week_number, score 
    FROM weekly_performance 
    WHERE user_id = ? AND year = YEAR(CURDATE())
    ORDER BY week_number ASC
    LIMIT 8
", [$currentUser['id']]);

// Kurs irəliləyişi
$courseProgress = $db->fetchAll("
    SELECT c.title, e.progress_percent 
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.user_id = ? AND e.status = 'active'
", [$currentUser['id']]);

// Həftəlik fəaliyyət
$weeklyActivity = $db->fetchAll("
    SELECT day_of_week, hours 
    FROM weekly_activity 
    WHERE user_id = ? AND week_start >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY FIELD(day_of_week, 'Bazar ertəsi', 'Çərşənbə axşamı', 'Çərşənbə', 'Cümə axşamı', 'Cümə', 'Şənbə', 'Bazar')
", [$currentUser['id']]);

jsonResponse([
    'success' => true,
    'statistics' => $stats,
    'weekly_performance' => $weeklyPerformance,
    'course_progress' => $courseProgress,
    'weekly_activity' => $weeklyActivity
]);
