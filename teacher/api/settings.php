<?php
/**
 * API: Parametrləri Yenilə
 */
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Səlahiyyətiniz yoxdur'], 401);
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// JSON data al
$input = json_decode(file_get_contents('php://input'), true);

if (!$input && isset($_POST['action'])) {
    $input = $_POST;
}

if (!$input) {
    jsonResponse(['success' => false, 'message' => 'Məlumat tapılmadı']);
}

$action = $input['action'] ?? '';

try {
    if ($action === 'update_notifications') {
        $db->update('users', [
            'email_notifications' => isset($input['email_notifications']) && $input['email_notifications'] ? 1 : 0,
            'push_notifications' => isset($input['push_notifications']) && $input['push_notifications'] ? 1 : 0,
            'sms_notifications' => isset($input['sms_notifications']) && $input['sms_notifications'] ? 1 : 0,
            'lesson_reminders' => isset($input['lesson_reminders']) && $input['lesson_reminders'] ? 1 : 0,
            'assignment_deadlines' => isset($input['assignment_deadlines']) && $input['assignment_deadlines'] ? 1 : 0,
            'grade_updates' => isset($input['grade_updates']) && $input['grade_updates'] ? 1 : 0
        ], 'id = :id', ['id' => $currentUser['id']]);

        jsonResponse(['success' => true, 'message' => 'Bildiriş parametrləri yeniləndi']);
    }

    if ($action === 'update_preferences') {
        $db->update('users', [
            'language' => $input['language'] ?? 'az',
            'timezone' => $input['timezone'] ?? 'Asia/Baku'
        ], 'id = :id', ['id' => $currentUser['id']]);

        jsonResponse(['success' => true, 'message' => 'Dil və region parametrləri yeniləndi']);
    }

    // Single setting update (from main.js initToggleSwitches)
    if (isset($input['setting'])) {
        $setting = $input['setting'];
        $value = $input['value'] ? 1 : 0;

        // İcazə verilən sütunlar
        $allowed = ['email_notifications', 'push_notifications', 'sms_notifications', 'lesson_reminders', 'assignment_deadlines', 'grade_updates'];

        if (in_array($setting, $allowed)) {
            $db->update('users', [$setting => $value], 'id = :id', ['id' => $currentUser['id']]);
            jsonResponse(['success' => true, 'message' => 'Parametr yeniləndi']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Yanlış parametr']);
        }
    }

    jsonResponse(['success' => false, 'message' => 'Yanlış əməliyyat']);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Xəta: ' . $e->getMessage()]);
}
