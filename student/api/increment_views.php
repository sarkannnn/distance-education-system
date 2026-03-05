<?php
/**
 * API to update view count for videos
 * Həm lokal bazada həm də TMİS-də baxış sayını artırır.
 * 
 * POST /api/student/archive/{id}/view  (TMİS)
 */
require_once '../includes/auth.php';
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessiya müddəti bitib', 'redirect' => 'login.php']);
    exit;
}

$db = Database::getInstance();
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$id = $data['id'] ?? null; // Format: 'live_123' or 'arch_456'

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit;
}

try {
    $realId = null;

    // Lokal bazada baxış artır
    if (strpos($id, 'live_') === 0) {
        $realId = str_replace('live_', '', $id);
        $db->query("UPDATE live_classes SET views = COALESCE(views, 0) + 1 WHERE id = ?", [$realId]);
    } elseif (strpos($id, 'arch_') === 0) {
        $realId = str_replace('arch_', '', $id);
        $db->query("UPDATE archived_lessons SET views = COALESCE(views, 0) + 1 WHERE id = ?", [$realId]);
    }

    // TMİS-ə də göndər (əgər token varsa)
    if ($realId) {
        $token = $_SESSION['tmis_token'] ?? '';
        if (!empty($token)) {
            try {
                tmis_post('/student/archive/' . $realId . '/view', [
                    'archive_id' => intval($realId),
                    'viewed_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $tmisErr) {
                // TMİS xətası lokal əməliyyatı dayandırmamalıdır
                error_log('[TMİS] View increment error: ' . $tmisErr->getMessage());
            }
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
