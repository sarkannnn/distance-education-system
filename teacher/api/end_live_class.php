<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/tmis_api.php';

$auth = new Auth();
requireInstructor();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $live_class_id = $_POST['live_class_id'];

    try {
        error_log("Ending live class. Received ID: " . $live_class_id);

        // 1. Canlı dərsi bitir (Status: ended) və faktiki müddəti hesabla
        $classInfo = $db->fetch("SELECT id, start_time, course_id, instructor_id, tmis_session_id FROM live_classes WHERE id = ?", [$live_class_id]);

        // Fallback: tmis_session_id ilə axtar
        if (!$classInfo) {
            $classInfo = $db->fetch("SELECT id, start_time, course_id, instructor_id, tmis_session_id FROM live_classes WHERE tmis_session_id = ?", [$live_class_id]);
            if ($classInfo) {
                $live_class_id = $classInfo['id']; // Real DB id
            }
        }

        $duration = 0;
        $tmisSessionId = $classInfo['tmis_session_id'] ?? (is_numeric($live_class_id) ? $live_class_id : null);

        if ($classInfo) {
            $startTime = strtotime($classInfo['start_time']);
            $endTime = time();
            $duration = round(($endTime - $startTime) / 60); // dəqiqə ilə
            if ($duration < 1)
                $duration = 1;

            $db->update(
                'live_classes',
                [
                    'status' => 'ended',
                    'end_time' => date('Y-m-d H:i:s'),
                    'duration_minutes' => $duration
                ],
                'id = :id',
                ['id' => $live_class_id]
            );

            // 1b. Aktiv alertləri bitir
            $db->update(
                'live_alerts',
                ['expires_at' => date('Y-m-d H:i:s')],
                'course_id = :cid AND instructor_id = :iid AND expires_at > NOW()',
                ['cid' => $classInfo['course_id'], 'iid' => $classInfo['instructor_id']]
            );

            $db->update(
                'schedule',
                ['status' => 'completed'],
                'live_class_id = :id OR (type = "live" AND status = "in-progress" AND course_id = :cid)',
                ['id' => $live_class_id, 'cid' => $classInfo['course_id']]
            );
        }

        // ============================================================
        // TMİS API-yə dərsin bitməsi haqqında bildiriş göndər
        // ============================================================
        $tmisToken = TmisApi::getToken();
        if ($tmisToken) {
            try {
                // Əgər lokalda tmis_session_id yoxdursa, API-dən cari aktiv sessiyanı soruşaq
                if (!$tmisSessionId || !is_numeric($tmisSessionId)) {
                    $statusRes = TmisApi::getLiveSessionStatus($tmisToken);
                    if ($statusRes['success'] && !empty($statusRes['data']) && $statusRes['data']['has_active_session']) {
                        $tmisSessionId = $statusRes['data']['session']['id'] ?? $statusRes['data']['session']['live_session_id'] ?? null;
                    }
                }

                if ($tmisSessionId) {
                    error_log("Calling TMIS to end session: " . $tmisSessionId);
                    $tmisEndResult = TmisApi::endLiveSession($tmisToken, [
                        'live_session_id' => (int) $tmisSessionId,
                        'duration_minutes' => ($duration > 0 ? $duration : 1)
                    ]);

                    if (!$tmisEndResult['success']) {
                        error_log('TMİS End Session xətası: ' . ($tmisEndResult['message'] ?? 'Naməlum xəta'));
                    } else {
                        error_log("TMIS End Session Success");
                        // Lokal bazanı həmin ID ilə yenilə
                        if ($live_class_id) {
                            $db->update('live_classes', ['tmis_session_id' => $tmisSessionId], 'id = ?', [$live_class_id]);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('TMİS End Session Exception: ' . $e->getMessage());
            }
        }

        header('Location: ../live-lessons.php?ended=1');
    } catch (Exception $e) {
        header('Location: ../live-lessons.php?error=' . urlencode($e->getMessage()));
    }
} else {
    header('Location: ../live-lessons.php');
}
