<?php
/**
 * Delete Live Class Recording / Entry API
 */

header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
requireInstructor();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $currentUser = $auth->getCurrentUser();
    $tmisToken = TmisApi::getToken();

    // Yalnız Admin (Super User) canlı dərsləri arxivdən silə bilər
    if (strtolower($_SESSION['user_role'] ?? '') !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Bu əməliyyat üçün səlahiyyətiniz yoxdur. Yalnız Super User canlı dərsləri silə bilər.']);
        exit;
    }

    // Müəllimin instructor_id-sini tap
    $instructor = $db->fetch(
        "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
        [$currentUser['id'], $currentUser['email']]
    );

    if (!$instructor) {
        echo json_encode(['success' => false, 'message' => 'Müəllim məlumatları tapılmadı']);
        exit;
    }

    $liveClassId = intval($_POST['live_class_id'] ?? 0);

    if ($liveClassId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID yanlışdır']);
        exit;
    }

    try {
        $deletedFromTmis = false;
        $deletedFromLocal = false;

        // 1. TMİS-dən silməyə çalış
        if ($tmisToken) {
            $tmisResult = TmisApi::deleteArchive($tmisToken, $liveClassId);
            if ($tmisResult['success']) {
                $deletedFromTmis = true;
            }
        }

        // 2. Lokal bazadan silməyə çalış (Əgər bu müəllimə aiddirsə)
        $class = $db->fetch(
            "SELECT id FROM live_classes WHERE id = ? AND (instructor_id = ? OR ? = 'admin')",
            [$liveClassId, $instructor['id'], $_SESSION['user_role']]
        );

        if ($class) {
            $db->delete('live_classes', 'id = ?', [$liveClassId]);
            $deletedFromLocal = true;
        }

        if ($deletedFromTmis || $deletedFromLocal) {
            echo json_encode(['success' => true, 'message' => 'Canlı dərs arxivdən silindi']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Dərs tapılmadı və ya silinmə zamanı xəta baş verdi']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Xəta baş verdi: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Yalnız POST sorğusu qəbul edilir']);
}
