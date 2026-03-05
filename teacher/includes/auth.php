<?php
/**
 * Distant Təhsil - Müəllim Autentifikasiya Sistemi
 */

session_name('DISTANT_TEACHER_SESSION');
session_start();
date_default_timezone_set('Asia/Baku');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/tmis_api.php';

class Auth
{
    public function __construct()
    {
    }

    public function loginViaTmis(string $username, string $password): array
    {
        $tmisResult = TmisApi::loginTeacher($username, $password);

        if (!$tmisResult['success']) {
            return ['success' => false, 'message' => $tmisResult['message']];
        }

        $tmisData = $tmisResult['data'];

        // TMİS profil məlumatlarını al (əgər login cavabında yoxdursa)
        $profileData = [];
        if (isset($tmisData['user'])) {
            $profileData = $tmisData;
        } else {
            $profileResult = TmisApi::me($tmisData['access_token']);
            $profileData = $profileResult['success'] ? $profileResult['data'] : [];
        }

        // Məlumatları çıxar (Ssenari 2 API strukturuna uyğun)
        $p = $profileData['user'] ?? $profileData;

        // DEBUG: TMİS mәlumatlarını loga yaz
        file_put_contents(__DIR__ . '/../tmis_profile_debug.log', print_r($p, true));

        $fullName = $p['name'] ?? ($p['fullname'] ?? '');
        $email = $p['email'] ?? ($username . '@ndu.edu.az');

        // Ad və soyadı ayır (heuristic)
        $nameParts = explode(' ', $fullName);
        $firstName = $p['first_name'] ?? ($nameParts[1] ?? ($nameParts[0] ?? ''));
        $lastName = $p['last_name'] ?? ($nameParts[0] ?? '');

        $userData = [
            'tmis_id' => $tmisData['id'] ?? ($profileData['id'] ?? 0),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'role' => 'instructor',
            'faculty' => $p['faculty'] ?? ($p['faculty_name'] ?? ''),
            'department' => $p['department'] ?? '',
            'specialty' => $p['specialty'] ?? ($p['profession_name'] ?? ''),
            'group' => $p['group'] ?? ($p['class_name'] ?? ''),
            'avatar_url' => $p['avatar_url'] ?? ($p['avatar'] ?? ''),
            'academic_title' => $p['academic_title'] ?? 'Müəllim'
        ];

        // Lokal baza ilə sinxronizasiya et
        $localUserId = $this->syncUserWithDb($userData);

        if ($localUserId) {
            $userData['id'] = $localUserId;
        } else {
            $userData['id'] = $tmisData['id'] ?? (time() % 100000);
        }

        $this->createSession($userData, $tmisData, $username, $password);

        return ['success' => true, 'user' => $userData];
    }

    /**
     * TMİS-dən gələn məlumatları lokal baza ilə sinxronizasiya edir
     */
    private function syncUserWithDb(array $data): ?int
    {
        $logFile = __DIR__ . '/../auth_debug.log';
        $log = function ($msg) use ($logFile) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
        };

        try {
            $db = Database::getInstance();
            $log("Sinxronizasiya başlayır: " . $data['email']);

            // 1. Users cədvəli
            $existingUser = $db->fetch("SELECT id FROM users WHERE email = ?", [$data['email']]);

            $userFields = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'role' => 'instructor',
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if (!empty($data['avatar_url'])) {
                $userFields['avatar'] = $data['avatar_url'];
            }

            if ($existingUser) {
                $log("Mövcud istifadəçi tapıldı (ID: " . $existingUser['id'] . "). Yenilənir...");
                // Database class-ındakı update metodunda mixing parameter problemi ola bilər, direct query istifadə edək
                $db->query(
                    "UPDATE users SET first_name = ?, last_name = ?, role = ?, is_active = ?, updated_at = ? WHERE id = ?",
                    [$userFields['first_name'], $userFields['last_name'], $userFields['role'], $userFields['is_active'], $userFields['updated_at'], $existingUser['id']]
                );
                $localUserId = $existingUser['id'];
            } else {
                $log("Yeni istifadəçi yaradılır...");
                $userFields['created_at'] = date('Y-m-d H:i:s');
                $userFields['password'] = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
                // Bəzi bazalarda student_id vacib ola bilər (Null deyilse)
                $userFields['student_id'] = 'INS-' . ($data['tmis_id'] ?? time());

                $localUserId = $db->insert('users', $userFields);
                $log("Yeni istifadəçi yaradıldı (ID: " . $localUserId . ")");
            }

            if (!$localUserId) {
                $log("XƏTA: İstifadəçi ID-si alınmadı.");
                return null;
            }

            // 2. Instructors cədvəli
            $existingInstructor = $db->fetch(
                "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
                [$localUserId, $data['email']]
            );

            $instructorFields = [
                'user_id' => $localUserId,
                'name' => trim($data['first_name'] . ' ' . $data['last_name']),
                'email' => $data['email'],
                'department' => $data['department'] ?? '',
                'title' => $data['academic_title'] ?? 'Müəllim',
                'faculty' => $data['faculty'] ?? '',
                'specialty' => $data['profession_name'] ?? $data['specialty'] ?? '',
                'academic_title' => $data['academic_title'] ?? 'Müəllim',
                'course_level' => $data['course_level'] ?? '-'
            ];

            if ($existingInstructor) {
                $log("Mövcud müəllim qeydi tapıldı (ID: " . $existingInstructor['id'] . "). Yenilənir...");
                $db->query(
                    "UPDATE instructors SET name = ?, email = ?, department = ?, title = ?, faculty = ?, specialty = ?, academic_title = ?, course_level = ? WHERE id = ?",
                    [
                        $instructorFields['name'],
                        $instructorFields['email'],
                        $instructorFields['department'],
                        $instructorFields['title'],
                        $instructorFields['faculty'],
                        $instructorFields['specialty'],
                        $instructorFields['academic_title'],
                        $instructorFields['course_level'],
                        $existingInstructor['id']
                    ]
                );
            } else {
                $log("Yeni müəllim qeydi yaradılır...");
                $db->insert('instructors', $instructorFields);
                $log("Yeni müəllim qeydi yaradıldı.");
            }

            $log("Sinxronizasiya uğurla başa çatdı.");
            return (int) $localUserId;
        } catch (\Exception $e) {
            $log("SİSTEM XƏTASI: " . $e->getMessage());
            error_log('Auth Sync Error: ' . $e->getMessage());
            return null;
        }
    }

    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn())
            return null;

        return [
            'id' => $_SESSION['user_id'] ?? 0,
            'email' => $_SESSION['user_email'] ?? '',
            'first_name' => explode(' ', $_SESSION['user_name'] ?? '')[0] ?? '',
            'last_name' => explode(' ', $_SESSION['user_name'] ?? '')[1] ?? '',
            'name' => $_SESSION['user_name'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'instructor',
            'faculty' => $_SESSION['teacher_faculty'] ?? '',
            'department' => $_SESSION['teacher_department'] ?? '',
            'specialty' => $_SESSION['teacher_specialty'] ?? '',
            'group' => $_SESSION['teacher_group'] ?? '',
            'avatar_url' => $_SESSION['teacher_avatar_url'] ?? '',
            'academic_title' => $_SESSION['user_academic_title'] ?? 'Müəllim',
            'course_level' => $_SESSION['teacher_course_level'] ?? '-',
        ];
    }

    private function createSession(array $user, ?array $tmisData = null, ?string $username = null, ?string $password = null): void
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $_SESSION['user_role'] = $user['role'] ?? 'instructor';

        $_SESSION['teacher_faculty'] = $user['faculty'] ?? '';
        $_SESSION['teacher_department'] = $user['department'] ?? '';
        $_SESSION['teacher_specialty'] = $user['specialty'] ?? '';
        $_SESSION['teacher_group'] = $user['group'] ?? '';
        $_SESSION['teacher_avatar_url'] = $user['avatar_url'] ?? '';
        $_SESSION['user_academic_title'] = $user['academic_title'] ?? 'Müəllim';
        $_SESSION['teacher_course_level'] = $user['course_level'] ?? '-';

        $_SESSION['logged_in'] = true;

        if ($tmisData) {
            $_SESSION['tmis_id'] = $tmisData['id'] ?? $user['id'];
            $_SESSION['tmis_token'] = $tmisData['access_token'] ?? '';
            $_SESSION['tmis_expires'] = time() + ($tmisData['expires_in'] ?? 3600);
        }

        if ($username)
            $_SESSION['tmis_username'] = $username;
        if ($password) {
            $key = substr(md5('DISTANT_TMIS_KEY_2024'), 0, 16);
            $iv = substr(md5('DISTANT_TMIS_IV_2024'), 0, 16);
            $_SESSION['tmis_pwd_enc'] = base64_encode(openssl_encrypt($password, 'AES-128-CBC', $key, 0, $iv));
        }
    }

    public function logout(): void
    {
        if (isset($_SESSION['tmis_token']))
            TmisApi::logout($_SESSION['tmis_token']);
        $_SESSION = [];
        session_destroy();
    }

    public function exitToPortal(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function isLoggedIn(): bool
    {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true)
            return false;
        if (isset($_SESSION['tmis_expires']) && time() > $_SESSION['tmis_expires']) {
            // Token bitib — əvvəlcə silent re-login cəhd et
            if ($this->silentReLogin()) {
                return true;
            }
            $this->logout();
            return false;
        }
        return true;
    }

    /**
     * Session-da saxlanılmış şifrələnmiş credentials ilə avtomatik yenidən giriş
     */
    private function silentReLogin(): bool
    {
        $username = $_SESSION['tmis_username'] ?? null;
        $encPwd = $_SESSION['tmis_pwd_enc'] ?? null;

        if (!$username || !$encPwd) {
            return false;
        }

        try {
            $key = substr(md5('DISTANT_TMIS_KEY_2024'), 0, 16);
            $iv = substr(md5('DISTANT_TMIS_IV_2024'), 0, 16);
            $password = openssl_decrypt(base64_decode($encPwd), 'AES-128-CBC', $key, 0, $iv);

            if (!$password) {
                return false;
            }

            $tmisResult = TmisApi::loginTeacher($username, $password);
            if (!$tmisResult['success']) {
                return false;
            }

            $tmisData = $tmisResult['data'];

            // Yalnız token məlumatlarını yenilə, session-u silmə
            $_SESSION['tmis_token'] = $tmisData['access_token'] ?? '';
            $_SESSION['tmis_expires'] = time() + ($tmisData['expires_in'] ?? 3600);
            $_SESSION['tmis_id'] = $tmisData['id'] ?? $_SESSION['user_id'];

            error_log('TMİS Teacher Silent Re-Login uğurlu: ' . $username);
            return true;
        } catch (\Exception $e) {
            error_log('TMİS Teacher Silent Re-Login xətası: ' . $e->getMessage());
            return false;
        }
    }
}

function requireLogin()
{
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireInstructor()
{
    requireLogin();
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'instructor' && $_SESSION['user_role'] !== 'admin') {
        (new Auth())->logout();
        header('Location: login.php?error=access_denied');
        exit;
    }
}
