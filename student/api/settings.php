<?php
/**
 * Settings API
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
        // Cari parametrləri al
        $settings = [
            'email_notifications' => (bool) $currentUser['email_notifications'],
            'push_notifications' => (bool) $currentUser['push_notifications'],
            'sms_notifications' => (bool) $currentUser['sms_notifications'],
            'lesson_reminders' => (bool) $currentUser['lesson_reminders'],
            'assignment_deadlines' => (bool) $currentUser['assignment_deadlines'],
            'grade_updates' => (bool) $currentUser['grade_updates'],
            'language' => $currentUser['language'],
            'timezone' => $currentUser['timezone']
        ];

        jsonResponse(['success' => true, 'settings' => $settings]);
        break;

    case 'POST':
        // Parametri yenilə
        $data = json_decode(file_get_contents('php://input'), true);
        $setting = $data['setting'] ?? null;
        $value = $data['value'] ?? null;

        if (!$setting) {
            jsonResponse(['success' => false, 'message' => 'Parametr adı tələb olunur'], 400);
        }

        // Icazə verilən parametrlər
        $allowedSettings = [
            'email_notifications',
            'push_notifications',
            'sms_notifications',
            'lesson_reminders',
            'assignment_deadlines',
            'grade_updates',
            'language',
            'timezone'
        ];

        if (!in_array($setting, $allowedSettings)) {
            jsonResponse(['success' => false, 'message' => 'Yanlış parametr'], 400);
        }

        // Boolean dəyərləri düzəlt
        if (
            in_array($setting, [
                'email_notifications',
                'push_notifications',
                'sms_notifications',
                'lesson_reminders',
                'assignment_deadlines',
                'grade_updates'
            ])
        ) {
            $value = $value ? 1 : 0;
        }

        $db->update('users', [$setting => $value], 'id = :id', ['id' => $currentUser['id']]);

        jsonResponse(['success' => true, 'message' => 'Parametr yeniləndi']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}
