<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/tmis_api.php';

$auth = new Auth();
requireInstructor();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $user = $auth->getCurrentUser();
    $tmisToken = TmisApi::getToken();

    $archive_id = $_POST['archive_id'] ?? null;

    if (!$archive_id) {
        echo json_encode(['success' => false, 'message' => 'ID tapılmadı']);
        exit;
    }

    try {
        $deletedFromTmis = false;
        $deletedFromLocal = false;

        // 1. TMİS-dən silməyə çalış
        if ($tmisToken) {
            $tmisResult = TmisApi::deleteArchive($tmisToken, (int) $archive_id);
            if ($tmisResult['success']) {
                $deletedFromTmis = true;
            }
        }

        // 2. Lokal bazadan silməyə çalış
        // Müəllimin instructor_id-sini tap
        $instructor = $db->fetch(
            "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
            [$user['id'], $user['email']]
        );

        if ($instructor) {
            $archive = $db->fetch("SELECT * FROM archived_lessons WHERE id = ? AND instructor_id = ?", [$archive_id, $instructor['id']]);
            if ($archive) {
                $db->delete('archived_lessons', 'id = :id', ['id' => $archive_id]);
                $deletedFromLocal = true;
            }
        }

        if ($deletedFromTmis || $deletedFromLocal) {
            echo json_encode(['success' => true, 'message' => 'Material silindi']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Material tapılmadı və ya silinmə zamanı xəta baş verdi']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Yanlış sorğu']);
}

