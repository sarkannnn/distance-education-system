<?php
/**
 * Teacher Notifications API
 */
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Giriş tələb olunur']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Müəllim üçün bildirişləri al
        $notifications = $db->fetchAll("
            SELECT id, title, message, type, is_read, created_at
            FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 20
        ", [$currentUser['id']]);

        $unreadCount = $db->fetch("
            SELECT COUNT(*) as count FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ", [$currentUser['id']]);

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => (int) $unreadCount['count']
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

        echo json_encode(['success' => true, 'message' => 'Bildirişlər yeniləndi']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
