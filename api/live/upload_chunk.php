<?php
/**
 * WebRTC Live Recording Chunk Upload API (V3 - Fixed Auth)
 * Periodic flush zamanı recording parçalarını serverə yazır
 */
require_once '../../teacher/includes/auth.php';
require_once '../../teacher/config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: not logged in']);
    exit;
}

$currentUser = $auth->getCurrentUser();
if (!in_array($currentUser['role'], ['instructor', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: ' . ($currentUser['role'] ?? 'no-role')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lessonId = $_POST['lesson_id'] ?? null;

    if (!$lessonId || !isset($_FILES['video_blob'])) {
        echo json_encode(['success' => false, 'message' => 'Missing data: lesson_id=' . ($lessonId ?? 'null') . ', video_blob=' . (isset($_FILES['video_blob']) ? 'yes' : 'no')]);
        exit;
    }

    $uploadDir = '../../uploads/live_recordings/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = 'lesson_' . $lessonId . '.webm';
    $filePath = $uploadDir . $fileName;

    // Gelen parçanı mövcud fayla APPEND et
    $tempFile = $_FILES['video_blob']['tmp_name'];
    $chunkData = file_get_contents($tempFile);

    if ($chunkData === false || strlen($chunkData) === 0) {
        echo json_encode(['success' => false, 'message' => 'Empty chunk data']);
        exit;
    }

    if (file_put_contents($filePath, $chunkData, FILE_APPEND | LOCK_EX)) {
        // Veritabanını yenilə
        try {
            $db = Database::getInstance();
            $db->query("UPDATE live_classes SET recording_path = ? WHERE id = ? AND (recording_path IS NULL OR recording_path = '')", [$fileName, $lessonId]);
        } catch (Exception $e) {
            // Log error if needed
        }

        echo json_encode([
            'success' => true,
            'message' => 'Chunk saved',
            'size' => filesize($filePath),
            'chunk_size' => strlen($chunkData)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write file to: ' . $filePath]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
}
