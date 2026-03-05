<?php
/**
 * TMİS API Client
 * Distant Təhsil Sistemi - Tələbə Paneli üçün TMİS inteqrasiya modulu.
 * 
 * Bütün TMİS API sorğuları bu fayl üzərindən keçir.
 * Müəllim və Tələbə loginləri fərqli endpointlərlə icra olunur.
 * Tələbə panelinin bütün endpoint-ləri burada metodlar şəklində təyin olunub.
 */
class TmisApi
{
    private static $baseUrl = 'https://tmis.ndu.edu.az/api';

    // =========================================================================
    //  AUTH ENDPOINTS
    // =========================================================================

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
     * GET /api/me  — profil məlumatları
     */
    public static function me(string $token): array
    {
        return self::request('GET', '/me', [], $token);
    }

    /**
     * GET /api/student/me  — tələbə profil (ətraflı)
     */
    public static function studentProfile(string $token): array
    {
        return self::request('GET', '/student/me', [], $token);
    }

    /**
     * POST /api/logout
     */
    public static function logout(string $token): array
    {
        return self::request('POST', '/logout', [], $token);
    }

    // =========================================================================
    //  STUDENT DASHBOARD / STATISTICS
    // =========================================================================

    /**
     * GET /api/student/dashboard-stats
     * Dashboard üçün 4 statistika kartı
     */
    public static function dashboardStats(string $token): array
    {
        return self::request('GET', '/student/dashboard-stats', [], $token);
    }

    /**
     * GET /api/student/statistics
     * Statistika səhifəsi üçün ümumi göstəricilər
     */
    public static function statistics(string $token): array
    {
        return self::request('GET', '/student/statistics', [], $token);
    }

    // =========================================================================
    //  SCHEDULE
    // =========================================================================

    /**
     * GET /api/student/schedule/today
     * Bu günün dərsləri
     */
    public static function scheduleToday(string $token): array
    {
        return self::request('GET', '/student/schedule/today', [], $token);
    }

    /**
     * GET /api/student/schedule/upcoming
     * Gələcək dərslər (növbəti 5)
     */
    public static function scheduleUpcoming(string $token): array
    {
        return self::request('GET', '/student/schedule/upcoming', [], $token);
    }

    // =========================================================================
    //  ARCHIVE
    // =========================================================================

    /**
     * GET /api/student/recent-archives
     * Son 4 arxiv materialı (dashboard üçün)
     */
    public static function recentArchives(string $token): array
    {
        return self::request('GET', '/student/recent-archives', [], $token);
    }

    /**
     * GET /api/student/archive
     * Bütün arxiv materialları (paginated)
     */
    public static function archives(string $token, int $perPage = 20, int $page = 1): array
    {
        $query = '?per_page=' . $perPage . '&page=' . $page;
        return self::request('GET', '/student/archive' . $query, [], $token);
    }

    /**
     * POST /api/student/archive/{id}/view
     * Baxış sayğacını artır
     */
    public static function incrementArchiveView(string $token, int $lessonId): array
    {
        return self::request('POST', '/student/archive/' . $lessonId . '/view', [
            'archive_id' => $lessonId,
            'viewed_at' => date('Y-m-d H:i:s')
        ], $token);
    }

    // =========================================================================
    //  LIVE SESSIONS
    // =========================================================================

    /**
     * GET /api/student/live-sessions/active
     * Hazırda aktiv olan canlı dərslər
     */
    public static function activeLiveSessions(string $token): array
    {
        return self::request('GET', '/student/live-sessions/active', [], $token);
    }

    /**
     * POST /api/student/live-sessions/join
     * Canlı dərsə qoşulma bildirişi
     */
    public static function joinLiveSession(string $token, int $liveSessionId): array
    {
        return self::request('POST', '/student/live-sessions/join', [
            'live_session_id' => $liveSessionId,
            'joined_at' => date('Y-m-d H:i:s')
        ], $token);
    }

    /**
     * POST /api/student/live-sessions/leave
     * Canlı dərsdən çıxma bildirişi
     */
    public static function leaveLiveSession(string $token, int $liveSessionId, int $durationMinutes): array
    {
        return self::request('POST', '/student/live-sessions/leave', [
            'live_session_id' => $liveSessionId,
            'left_at' => date('Y-m-d H:i:s'),
            'duration_minutes' => $durationMinutes
        ], $token);
    }

    // =========================================================================
    //  SUBJECTS / COURSES
    // =========================================================================

    /**
     * GET /api/student/subjects
     * Tələbənin bütün aktiv fənləri
     */
    public static function subjects(string $token): array
    {
        return self::request('GET', '/student/subjects', [], $token);
    }

    // =========================================================================
    //  HELPER: API call with fallback tracking
    // =========================================================================

    /**
     * TMİS API sorğusu göndərir. 
     * Uğursuzluq halında lokal verilənlər bazasına fallback etmək üçün
     * `success => false` qaytarır.
     */
    private static function request(string $method, string $endpoint, array $data = [], ?string $token = null): array
    {
        $url = self::$baseUrl . $endpoint;
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        // SSL verification (Production-da TRUE olmalıdır)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('[TMİS API] Connection error (' . $endpoint . '): ' . $error);
            return ['success' => false, 'message' => 'Bağlantı xətası: ' . $error];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $decoded];
        }

        error_log('[TMİS API] HTTP ' . $httpCode . ' (' . $endpoint . '): ' . substr($response, 0, 500));

        return [
            'success' => false,
            'message' => $decoded['error'] ?? $decoded['message'] ?? 'API xətası (Kod: ' . $httpCode . ')',
            'code' => $httpCode
        ];
    }
}

// =========================================================================
//  QLOBAL HELPER FUNKSIYALAR
//  Mövcud PHP fayllarından asanlıqla istifadə üçün prosedural wrapper-lər
// =========================================================================

/**
 * Session-dakı TMİS token-ini qaytarır.
 */
function tmis_token(): string
{
    return $_SESSION['tmis_token'] ?? '';
}

// =========================================================================
//  Prosedural helper funksiyalar (birbaşa cURL istifadə edən)
//  İstifadə olunan funksiyalar: tmis_get(), tmis_post(), tmis_get_full()
// =========================================================================

/**
 * Prosedural TMİS API sorğusu (GET)
 */
function _tmis_request(string $method, string $endpoint, array $data = [], ?string $token = null): array
{
    $baseUrl = 'https://tmis.ndu.edu.az/api';
    $url = $baseUrl . $endpoint;
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'Bağlantı xətası: ' . $error];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $decoded];
    }

    return [
        'success' => false,
        'message' => $decoded['error'] ?? $decoded['message'] ?? 'API xətası (Kod: ' . $httpCode . ')',
        'code' => $httpCode
    ];
}

/**
 * TMİS API GET sorğusu — sadələşdirilmiş versiya.
 * API cavabından data hissəsini çıxarır.
 * Uğursuz olduqda null qaytarır ki, fallback mümkün olsun.
 */
function tmis_get(string $endpoint): ?array
{
    $token = tmis_token();
    if (empty($token))
        return null;

    $result = _tmis_request('GET', $endpoint, [], $token);
    if (!$result['success'] || !isset($result['data']))
        return null;

    $d = $result['data'];

    // TMİS cavab formatı: {success: true, data: {...}}
    if (is_array($d) && isset($d['success']) && $d['success'] !== false) {
        return $d['data'] ?? $d;
    }
    // Və ya birbaşa data kimi
    if (is_array($d) && isset($d['data'])) {
        return $d['data'];
    }
    return $d;
}

/**
 * TMİS API GET sorğusu — tam cavabı qaytarır (stats, summary ilə birlikdə).
 */
function tmis_get_full(string $endpoint): ?array
{
    $token = tmis_token();
    if (empty($token))
        return null;

    $result = _tmis_request('GET', $endpoint, [], $token);
    if (!$result['success'] || !isset($result['data']))
        return null;

    $d = $result['data'];
    // TMİS {success: true, ...rest} formatında gəlir
    if (is_array($d) && isset($d['success']) && $d['success'] !== false) {
        return $d;
    }
    return $d;
}

/**
 * TMİS API POST sorğusu — sadələşdirilmiş versiya.
 */
function tmis_post(string $endpoint, array $data = []): ?array
{
    $token = tmis_token();
    if (empty($token))
        return null;

    $result = _tmis_request('POST', $endpoint, $data, $token);
    if (!$result['success'] || !isset($result['data']))
        return null;

    return $result['data'];
}
