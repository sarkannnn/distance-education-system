<?php
/**
 * TMİS API Client — Müəllim Paneli
 * Postman kolleksiyasındakı API strukturuna tam uyğunlaşdırılıb.
 * 
 * Bölmələr:
 *   0 - Auth (Login, Logout, Me)
 *   2 - Dashboard (dashboard-stats, schedule/today, activities)
 *   3 - Live Sessions (status, start, end)
 *   4 - Subject Details (subjects/{id}/details)
 *   5 - Archive (archive, archive/upload, archive/{id})
 *   6 - Analytics (summary, charts, course-stats)
 *   7 - Attendance Report (attendance-report/{live_session_id})
 */
class TmisApi
{
    private static $baseUrl = 'https://tmis.ndu.edu.az/api';

    // =====================================================
    // 0 - AUTH
    // =====================================================

    /**
     * POST /api/login (Müəllim/İnstruktor üçün)
     */
    public static function loginTeacher(string $username, string $password): array
    {
        return self::request('POST', '/login', [
            'username' => $username,
            'password' => $password
        ]);
    }

    /**
     * POST /api/student-login (Tələbə üçün)
     */
    public static function loginStudent(string $username, string $password): array
    {
        return self::request('POST', '/student-login', [
            'username' => $username,
            'password' => $password
        ]);
    }

    /**
     * GET /api/me — Profil məlumatları
     */
    public static function me(string $token): array
    {
        return self::request('GET', '/me', [], $token);
    }

    /**
     * POST /api/logout
     */
    public static function logout(string $token): array
    {
        return self::request('POST', '/logout', [], $token);
    }

    // =====================================================
    // 2 - DASHBOARD
    // =====================================================

    /**
     * GET /api/teacher/dashboard-stats
     * Cavab: { success, data: { total_subjects, total_students, total_live_lessons, total_teaching_hours, education_year_id } }
     */
    public static function getDashboardStats(string $token): array
    {
        return self::request('GET', '/teacher/dashboard-stats', [], $token);
    }

    /**
     * GET /api/teacher/schedule/today
     * Bu günün dərs cədvəli
     */
    public static function getScheduleToday(string $token): array
    {
        return self::request('GET', '/teacher/schedule/today', [], $token);
    }

    /**
     * GET /api/teacher/activities
     * Son fəaliyyətlər (bitmiş dərslər)
     */
    public static function getActivities(string $token): array
    {
        return self::request('GET', '/teacher/activities', [], $token);
    }

    // =====================================================
    // 3 - LIVE SESSIONS
    // =====================================================

    /**
     * GET /api/teacher/live-sessions/status
     * Canlı dərs statusu (aktiv sessiya varmı?)
     */
    public static function getLiveSessionStatus(string $token): array
    {
        return self::request('GET', '/teacher/live-sessions/status', [], $token);
    }

    /**
     * POST /api/teacher/live-sessions/start
     * Canlı dərsi başlatma
     */
    public static function startLiveSession(string $token, array $data): array
    {
        return self::request('POST', '/teacher/live-sessions/start', $data, $token);
    }

    /**
     * POST /api/teacher/live-sessions/end
     * Canlı dərsi bitirmə
     */
    public static function endLiveSession(string $token, array $data): array
    {
        return self::request('POST', '/teacher/live-sessions/end', $data, $token);
    }

    // =====================================================
    // 4 - SUBJECT DETAILS
    // =====================================================

    /**
     * GET /api/teacher/subjects-list
     * Müəllimin fənləri
     */
    public static function getSubjectsList(string $token): array
    {
        return self::request('GET', '/teacher/subjects-list', [], $token);
    }

    /**
     * GET /api/teacher/subjects/{id}/details
     * Fənn haqqında detallı məlumat
     */
    public static function getSubjectDetails(string $token, int $subjectId): array
    {
        return self::request('GET', '/teacher/subjects/' . $subjectId . '/details', [], $token);
    }

    // =====================================================
    // 5 - ARCHIVE
    // =====================================================

    /**
     * GET /api/teacher/archive
     * Arxiv materialları siyahısı
     */
    public static function getArchive(string $token): array
    {
        return self::request('GET', '/teacher/archive', [], $token);
    }

    /**
     * POST /api/teacher/archive/upload
     * Yeni material yükləmə (multipart)
     */
    public static function uploadArchive(string $token, array $data, string $filePath = ''): array
    {
        // Fayl varsa, CURLFile olaraq əlavə et
        if (!empty($filePath) && file_exists($filePath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);

            // Office faylları üçün bəzən finfo səhv tip qaytara bilir, manuel düzəliş:
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $customMimes = [
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'pdf' => 'application/pdf',
                'mp4' => 'video/mp4'
            ];

            if (isset($customMimes[$ext])) {
                $mimeType = $customMimes[$ext];
            }

            $data['file'] = new \CURLFile($filePath, $mimeType, basename($filePath));
        }

        return self::requestMultipart('/teacher/archive/upload', $data, $token);
    }

    /**
     * DELETE /api/teacher/archive/{id}
     * Arxiv materialı silmə
     */
    public static function deleteArchive(string $token, int $archiveId): array
    {
        return self::request('DELETE', '/teacher/archive/' . $archiveId, [], $token);
    }

    // =====================================================
    // 6 - ANALYTICS
    // =====================================================

    /**
     * GET /api/teacher/analytics/summary
     * Ümumi statistika xülasəsi
     */
    public static function getAnalyticsSummary(string $token): array
    {
        return self::request('GET', '/teacher/analytics/summary', [], $token);
    }

    /**
     * GET /api/teacher/analytics/charts
     * Qrafik dataları (həftəlik iştirak, fənn proqresi)
     */
    public static function getAnalyticsCharts(string $token): array
    {
        return self::request('GET', '/teacher/analytics/charts', [], $token);
    }

    /**
     * GET /api/teacher/analytics/course-stats
     * Fənn üzrə detallı statistika cədvəli
     */
    public static function getAnalyticsCourseStats(string $token): array
    {
        return self::request('GET', '/teacher/analytics/course-stats', [], $token);
    }

    // =====================================================
    // 7 - ATTENDANCE REPORT
    // =====================================================

    /**
     * GET /api/teacher/attendance-report/{live_session_id}
     * Dərs üçün iştirakçı jurnalı
     */
    public static function getAttendanceReport(string $token, int $liveSessionId): array
    {
        return self::request('GET', '/teacher/attendance-report/' . $liveSessionId, [], $token);
    }

    // =====================================================
    // HELPER: Session-dan token alma
    // =====================================================

    /**
     * Session-dan TMİS tokenini al
     * Əgər token yoxdursa və ya vaxtı bitibsə null qaytarır
     */
    public static function getToken(): ?string
    {
        if (empty($_SESSION['tmis_token'])) {
            return null;
        }
        if (isset($_SESSION['tmis_expires']) && time() > $_SESSION['tmis_expires']) {
            return null;
        }
        return $_SESSION['tmis_token'];
    }

    // =====================================================
    // REQUEST ENGINE
    // =====================================================

    /**
     * Ümumi request funksiyası (cURL) — GET, POST, DELETE dəstəyi
     */
    private static function request(string $method, string $endpoint, array $data = [], ?string $token = null): array
    {
        $url = self::$baseUrl . $endpoint;

        // GET sorğularında data-nı query string olaraq URL-ə əlavə et
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        $ch = curl_init($url);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];

        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        // SSL verification (Production-da TRUE olmalıdır)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'Bağlantı xətası: ' . $error];
        }

        $decoded = json_decode($response, true);

        // Token vaxtı bitib — 401
        if ($httpCode === 401) {
            return [
                'success' => false,
                'message' => 'Sessiya müddəti bitib. Yenidən daxil olun.',
                'code' => 401,
                'expired' => true
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            // API öz cavabında success/data strukturu qaytarırsa, onu birbaşa ötür
            if (isset($decoded['success'])) {
                return $decoded;
            }
            return ['success' => true, 'data' => $decoded];
        }

        return [
            'success' => false,
            'message' => $decoded['error'] ?? $decoded['message'] ?? 'API xətası (Kod: ' . $httpCode . ')',
            'code' => $httpCode
        ];
    }

    /**
     * Multipart/form-data request (fayl yükləmə üçün)
     */
    private static function requestMultipart(string $endpoint, array $data, string $token): array
    {
        $ch = curl_init(self::$baseUrl . $endpoint);

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $token
            // Content-Type əlavə etmirik — cURL multipart üçün avtomatik təyin edəcək
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3600); // 1 hour for large file uploads
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data); // array olaraq göndər — multipart olacaq

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'Yükləmə xətası: ' . $error];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            if (isset($decoded['success'])) {
                return $decoded;
            }
            return ['success' => true, 'data' => $decoded];
        }

        return [
            'success' => false,
            'message' => $decoded['error'] ?? $decoded['message'] ?? 'Yükləmə xətası (Kod: ' . $httpCode . ')',
            'code' => $httpCode
        ];
    }
}
