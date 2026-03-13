<?php
/**
 * Ümumi köməkçi funksiyalar
 */

/**
 * Tarix formatlaşdırma
 */
function formatDate($date, $format = 'd F Y')
{
    $months_az = [
        'January' => 'Yanvar',
        'February' => 'Fevral',
        'March' => 'Mart',
        'April' => 'Aprel',
        'May' => 'May',
        'June' => 'İyun',
        'July' => 'İyul',
        'August' => 'Avqust',
        'September' => 'Sentyabr',
        'October' => 'Oktyabr',
        'November' => 'Noyabr',
        'December' => 'Dekabr'
    ];

    $formatted = date($format, strtotime($date));
    return str_replace(array_keys($months_az), array_values($months_az), $formatted);
}

/**
 * Vaxt fərqi hesabla
 */
function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return 'İndicə';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' dəqiqə əvvəl';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' saat əvvəl';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' gün əvvəl';
    } else {
        return formatDate($datetime);
    }
}

/**
 * Gün qaldığını hesabla
 */
function daysRemaining($dueDate)
{
    $due = strtotime($dueDate);
    $now = time();
    $diff = floor(($due - $now) / 86400);
    return $diff;
}

/**
 * Prosent hesabla
 */
function calculatePercentage($completed, $total)
{
    if ($total == 0)
        return 0;
    return round(($completed / $total) * 100, 1);
}

/**
 * HTML escape
 */
function e($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF token yarad
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF token yoxla
 */
function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Status rəngini al
 */
function getStatusColor($status)
{
    $colors = [
        'active' => 'green',
        'inactive' => 'gray',
        'completed' => 'purple',
        'pending' => 'yellow',
        'submitted' => 'blue',
        'graded' => 'green',
        'overdue' => 'red',
        'live' => 'red',
        'starting-soon' => 'blue',
        'ending-soon' => 'yellow'
    ];

    return $colors[$status] ?? 'gray';
}

/**
 * Status mətnini al
 */
function getStatusText($status)
{
    $texts = [
        'active' => 'Aktiv',
        'inactive' => 'Qeyri-aktiv',
        'completed' => 'Tamamlandı',
        'pending' => 'Gözləyir',
        'submitted' => 'Təqdim edilib',
        'graded' => 'Qiymətləndirilib',
        'overdue' => 'Gecikmiş',
        'live' => 'Canlı',
        'starting-soon' => 'Tezliklə başlayır',
        'ending-soon' => 'Tezliklə bitir'
    ];

    return $texts[$status] ?? $status;
}

/**
 * Həftənin günlərini al
 */
function getDaysOfWeek()
{
    return [
        'Bazar ertəsi',
        'Çərşənbə axşamı',
        'Çərşənbə',
        'Cümə axşamı',
        'Cümə',
        'Şənbə',
        'Bazar'
    ];
}

/**
 * Ayları al
 */
function getMonths()
{
    return [
        'Yanvar',
        'Fevral',
        'Mart',
        'Aprel',
        'May',
        'İyun',
        'İyul',
        'Avqust',
        'Sentyabr',
        'Oktyabr',
        'Noyabr',
        'Dekabr'
    ];
}
/**
 * Mətni müəyyən uzunluğa qədər qısalt
 */
function truncate($text, $length = 45)
{
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length, 'UTF-8') . '...';
}

/**
 * JSON Response helper
 */
function jsonResponse($data, $statusCode = 200)
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}
