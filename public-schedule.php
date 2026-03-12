<?php
/**
 * 🎓 NDU Distant Təhsil Sistemi - Publik Cədvəl & Arxiv (V2 UI)
 * Image-based exact recreation of the public-schedule layout.
 */
require_once 'student/config/database.php';
require_once 'student/includes/helpers.php';

$db = Database::getInstance();

// Bu günün Azərbaycan dilində adını al
$weekdays = [
    1 => 'Bazar ertəsi',
    2 => 'Çərşənbə axşamı',
    3 => 'Çərşənbə',
    4 => 'Cümə axşamı',
    5 => 'Cümə',
    6 => 'Şənbə',
    0 => 'Bazar'
];
$todayName = $weekdays[date('w')];

// 1. Bugünkü dərslər - Sadece HAL-HAZIRDA canlı olan dərsləri göstər (Live)
$todayLessons = [];
try {
    $todayLessons = $db->fetchAll(
        "SELECT lc.id, lc.title as topic_name, lc.status, lc.start_time,
                COALESCE(NULLIF(lc.subject_name, 'Fənn'), c.title, 'Fənn') as course_title, 
                (CASE WHEN lc.is_stream = 1 AND lc.specialty_name IS NOT NULL AND lc.specialty_name != '' AND lc.specialty_name != 'Axın (çoxlu ixtisas)' THEN lc.specialty_name ELSE COALESCE(NULLIF(NULLIF(lc.specialty_name, ''), 'Axın (çoxlu ixtisas)'), i.specialty, i.department, 'Ümumi') END) as specialization_name,
                COALESCE(NULLIF(lc.course_level, '-'), i.course_level, '-') as course_level_val,
                COALESCE(NULLIF(lc.instructor_name, ''), NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), i.name, 'Müəllim təyin edilməyib') as instructor_display_name, 
                COALESCE(NULLIF(lc.instructor_title, ''), i.title, '') as instructor_title
         FROM live_classes lc
         LEFT JOIN courses c ON lc.course_id = c.tmis_subject_id OR lc.course_id = c.id
         LEFT JOIN instructors i ON lc.instructor_id = i.user_id OR lc.instructor_id = i.id
         LEFT JOIN users u ON i.user_id = u.id OR lc.instructor_id = u.id
         WHERE lc.status = 'live'
         ORDER BY lc.start_time ASC"
    );
} catch (Exception $e) {
    if (isset($_GET['debug']))
        echo "Today Error: " . $e->getMessage();
    $todayLessons = [];
}

// 2. Keçirilmiş dərslər (Arxiv) - Ən sonuncu dərsi ən üstdə göstər
$archivedLessons = [];
try {
    // a. Canlı dərslərin yazıları
    $liveRecs = $db->fetchAll(
        "SELECT lc.id, 
                lc.title as topic_name, 
                COALESCE(NULLIF(lc.subject_name, 'Fənn'), c.title, 'Fənn') as course_title, 
                (CASE WHEN lc.is_stream = 1 AND lc.specialty_name IS NOT NULL AND lc.specialty_name != '' AND lc.specialty_name != 'Axın (çoxlu ixtisas)' THEN lc.specialty_name ELSE COALESCE(NULLIF(NULLIF(lc.specialty_name, ''), 'Axın (çoxlu ixtisas)'), i.specialty, i.department, 'Ümumi') END) as specialization_name,
                COALESCE(NULLIF(lc.course_level, '-'), i.course_level, '-') as course_level_val,
                COALESCE(NULLIF(lc.instructor_name, ''), NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), i.name, 'Müəllim təyin edilməyib') as instructor_display_name, 
                COALESCE(NULLIF(lc.instructor_title, ''), i.title, '') as instructor_title,
                lc.start_time as activity_date,
                lc.end_time as end_time,
                COALESCE(lc.duration_minutes, 0) as duration,
                0 as views,
                lc.recording_path as video_url,
                NULL as pdf_url,
                'live' as record_type
         FROM live_classes lc
         LEFT JOIN courses c ON lc.course_id = c.tmis_subject_id OR lc.course_id = c.id
         LEFT JOIN instructors i ON lc.instructor_id = i.user_id OR lc.instructor_id = i.id
         LEFT JOIN users u ON i.user_id = u.id OR lc.instructor_id = u.id
         WHERE lc.status = 'ended' AND lc.recording_path IS NOT NULL AND lc.recording_path != ''"
    );

    // b. Manual yüklənən arxivlər
    $manualArchives = $db->fetchAll(
        "SELECT al.id, 
                al.title as topic_name, 
                COALESCE(NULLIF(al.subject_name, 'Fənn'), c.title, 'Fənn') as course_title, 
                COALESCE(NULLIF(NULLIF(al.specialty_name, ''), 'Axın (çoxlu ixtisas)'), i.specialty, i.department, 'Ümumi') as specialization_name,
                COALESCE(NULLIF(al.course_level, '-'), i.course_level, '-') as course_level_val,
                COALESCE(NULLIF(al.instructor_name, ''), NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), i.name, 'Müəllim təyin edilməyib') as instructor_display_name, 
                COALESCE(NULLIF(al.instructor_title, ''), i.title, '') as instructor_title,
                al.created_at as activity_date,
                al.created_at as end_time,
                al.duration as duration,
                al.views as views,
                al.video_url as video_url,
                al.pdf_url as pdf_url,
                'manual' as record_type
         FROM archived_lessons al
         LEFT JOIN courses c ON al.course_id = c.id
         LEFT JOIN instructors i ON al.instructor_id = i.id OR al.instructor_id = i.user_id
         LEFT JOIN users u ON i.user_id = u.id"
    );

    $allArchives = array_merge($liveRecs, $manualArchives);
    // Sort by date (newest first - DESC)
    usort($allArchives, function ($a, $b) {
        $t1 = strtotime($a['activity_date']);
        $t2 = strtotime($b['activity_date']);
        return $t2 - $t1;
    });
    $archivedLessons = $allArchives;
} catch (Exception $e) {
    if (isset($_GET['debug']))
        echo "Archive Error: " . $e->getMessage();
    $archivedLessons = [];
}
?>
<!DOCTYPE html>
<html lang="az">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distant Təhsil Cədvəli - Naxçıvan Dövlət Universiteti</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --ndu-blue: #0e3760;
            --ndu-accent: #0284c7;
            --bg-gray: #f1f5f9;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-gray);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            /* Global scroll ləğv edildi */
        }

        /* Top Bar / Header */
        .ndu-header {
            background-color: var(--ndu-blue);
            color: white;
            text-align: center;
            padding: 25px 20px;
            flex-shrink: 0;
        }

        .ndu-header h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }

        .ndu-header p {
            font-size: 14px;
            opacity: 0.8;
        }

        /* Main Content wrapper */
        .main-container {
            flex: 1;
            padding: 25px 20px;
            overflow: hidden;
            display: flex;
            justify-content: center;
            min-height: 0;
            /* Vacibdir */
        }

        .content-grid {
            max-width: 1350px;
            width: 100%;
            display: flex;
            gap: 25px;
            height: 100%;
            min-height: 0;
        }

        /* Column Styles */
        .schedule-column {
            background: #ffffff;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex: 1;
            height: 100%;
            min-height: 0;
            overflow: hidden;
        }

        /* Portal Login Sidebar */
        .portal-sidebar {
            width: 320px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 20px;
            height: 100%;
            overflow: hidden;
        }

        .sidebar-portal-box {
            background: #ffffff;
            border-radius: 16px;
            padding: 30px 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .sidebar-portal-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--portal-accent, #0ea5e9);
        }

        .sidebar-portal-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border-color: var(--portal-accent, #0ea5e9);
        }

        .sidebar-portal-icon {
            width: 52px;
            height: 52px;
            background: color-mix(in srgb, var(--portal-accent, #0ea5e9) 12%, white);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--portal-accent, #0ea5e9);
            border: 1px solid color-mix(in srgb, var(--portal-accent, #0ea5e9) 25%, transparent);
        }

        .sidebar-portal-icon i {
            width: 24px;
            height: 24px;
        }

        .sidebar-portal-box h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.3;
            margin: 0;
        }

        .sidebar-portal-box p {
            font-size: 14px;
            line-height: 1.6;
            color: var(--text-muted);
            margin: 0;
        }

        .sidebar-portal-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: var(--portal-accent, #0ea5e9);
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 5px;
        }

        .sidebar-portal-btn:hover {
            filter: brightness(1.1);
            box-shadow: 0 4px 14px color-mix(in srgb, var(--portal-accent, #0ea5e9) 40%, transparent);
            transform: scale(1.02);
        }

        .column-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--ndu-blue);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--bg-gray);
            flex-shrink: 0;
        }

        /* Scrollable Container - Robust Scroll Fix */
        .cards-scroll-area {
            flex: 1 1 auto;
            height: 0;
            /* Valideynin qalan sahəsinə sığması üçün vacibdir */
            overflow-y: auto;
            min-height: 0;
            padding-right: 8px;
            margin-right: -8px;
        }

        /* Custom Scrollbar Styles */
        .cards-scroll-area::-webkit-scrollbar {
            width: 6px;
        }

        .cards-scroll-area::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.02);
        }

        .cards-scroll-area::-webkit-scrollbar-thumb {
            background: #94a3b8;
            /* Daha tünd rəng */
            border-radius: 10px;
        }

        .cards-scroll-area::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        .lesson-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 18px;
            /* Slightly reduced padding */
            margin-bottom: 12px;
            /* Slightly reduced margin */
            border-left: 5px solid var(--ndu-blue);
            transition: all 0.3s ease;
        }

        .lesson-card:last-child {
            margin-bottom: 0;
        }

        .lesson-card:hover {
            transform: translateX(5px);
            background: #f1f5f9;
        }

        .lesson-card.live {
            border-left-color: #ef4444;
            position: relative;
        }

        .card-header-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .badge-live {
            background-color: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 4px;
            animation: blink 2s infinite;
            text-transform: uppercase;
        }

        .badge-live::before {
            content: '';
            width: 6px;
            height: 6px;
            background-color: white;
            border-radius: 50%;
            display: inline-block;
        }

        @keyframes blink {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.6;
            }

            100% {
                opacity: 1;
            }
        }

        .card-header-title {
            font-size: 16px;
            /* Slightly reduced font size */
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0;
            flex: 1;
        }

        .card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 15px;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        .meta-item i {
            width: 16px;
            height: 16px;
            color: #94a3b8;
            stroke-width: 2px;
        }

        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px 25px;
            margin-top: 10px;
        }

        /* Premium Card Metadata Styles */
        .card-metadata-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 20px;
        }

        .card-metadata-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            line-height: 1.4;
        }

        .card-metadata-label {
            color: var(--text-muted);
            width: 95px;
            flex-shrink: 0;
            font-weight: 600;
        }

        .card-metadata-value {
            color: #1e293b;
            font-weight: 500;
            flex: 1;
        }

        .card-metadata-value.subject {
            color: var(--ndu-accent);
        }

        .archive-card-footer {
            display: flex;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
        }

        .archive-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .archive-btn-primary {
            background: var(--ndu-blue);
            color: white;
            flex: 1;
        }

        .archive-btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .archive-btn-secondary {
            background: #f1f5f9;
            color: var(--ndu-accent);
            width: 44px;
            padding: 0;
        }

        .archive-btn-secondary:hover {
            background: #e2e8f0;
        }

        .ndu-footer {
            background-color: #01111d;
            background-image:
                radial-gradient(at 50% 100%, #075985 0px, transparent 40%),
                radial-gradient(at 0% 0%, rgba(12, 74, 110, 0.3) 0px, transparent 50%);
            color: white;
            padding: 30px 20px 20px;
            flex-shrink: 0;
            border-top: 1px solid rgba(56, 189, 248, 0.15);
            position: relative;
            overflow: hidden;
        }

        .ndu-footer::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.6'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.05'/%3E%3C/svg%3E");
            pointer-events: none;
        }

        .footer-container {
            max-width: 1250px;
            margin: 0 auto;
            position: relative;
            z-index: 10;
        }

        .footer-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 40px;
            margin-bottom: 25px;
        }

        /* Contact Section */
        .contact-side {
            flex: 1;
        }

        .footer-section-tag {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #38bdf8;
            margin-bottom: 12px;
            display: block;
            opacity: 0.7;
        }

        .contact-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
        }

        .contact-glass-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 12px 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
        }

        .contact-glass-card:hover {
            background: rgba(255, 255, 255, 0.07);
            border-color: rgba(56, 189, 248, 0.3);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .contact-icon-box {
            width: 34px;
            height: 34px;
            background: rgba(56, 189, 248, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.2);
            flex-shrink: 0;
        }

        .contact-glass-card:hover .contact-icon-box {
            background: #38bdf8;
            color: #01111d;
            box-shadow: 0 0 20px rgba(56, 189, 248, 0.4);
        }

        .contact-info-text h4 {
            font-size: 9px;
            color: rgba(255, 255, 255, 0.4);
            margin-bottom: 2px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .contact-info-text p {
            font-size: 13px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        }

        /* Portal Side */
        .portal-side {
            display: flex;
            gap: 20px;
        }

        .portal-premium-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 16px;
            backdrop-filter: blur(20px);
            width: 220px;
            display: flex;
            flex-direction: column;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .portal-premium-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--box-accent, #0ea5e9), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .portal-premium-box:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .portal-premium-box:hover::before {
            opacity: 1;
        }

        .portal-icon-wrapper {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            color: var(--box-accent, #0ea5e9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .portal-icon-wrapper i {
            width: 16px;
            height: 16px;
        }

        .portal-premium-box:hover .portal-icon-wrapper {
            background: var(--box-accent, #0ea5e9);
            color: white;
            box-shadow: 0 0 15px rgba(14, 165, 233, 0.2);
        }

        .portal-premium-box h3 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 4px;
            color: #ffffff;
            letter-spacing: -0.01em;
        }

        .portal-premium-box p {
            font-size: 11px;
            line-height: 1.4;
            color: rgba(255, 255, 255, 0.3);
            margin-bottom: 15px;
            flex-grow: 1;
        }

        .premium-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--box-accent, #0ea5e9);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .premium-action-btn:hover {
            transform: scale(1.02);
            filter: brightness(1.1);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .footer-bottom-info {
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.3);
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .dev-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.4);
            font-weight: 800;
        }

        .dev-badge span {
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.1);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 9px;
        }

        /* Generic responsive tables */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
        }

        @media (max-width: 1200px) {
            body {
                height: auto;
                overflow: visible;
            }

            .main-container {
                height: auto;
                overflow: visible;
                display: block;
            }

            .content-grid {
                flex-direction: column;
                height: auto;
            }

            .portal-sidebar {
                width: 100%;
                flex-direction: row;
                height: auto;
            }

            .sidebar-portal-box {
                flex: 1;
            }

            .schedule-column {
                height: auto;
                overflow: visible;
                min-height: auto;
            }

            .cards-scroll-area {
                height: 450px;
                overflow-y: auto;
            }
        }

        @media (max-width: 1100px) {
            .footer-flex {
                flex-direction: column;
                gap: 30px;
            }

            .portal-side {
                width: 100%;
            }
        }

        @media (max-width: 992px) {
            .footer-main {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .contact-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .content-grid {
                gap: 15px;
            }

            .portal-sidebar {
                flex-direction: column;
            }

            .ndu-header {
                padding: 15px 10px;
            }

            .ndu-header h1 {
                font-size: 20px;
            }

            .ndu-header p {
                font-size: 13px;
            }

            .main-container {
                padding: 15px 10px;
            }

            .schedule-column {
                padding: 15px;
            }

            .column-title {
                font-size: 18px;
                margin-bottom: 15px;
            }

            .card-header-wrapper {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .lesson-card {
                padding: 15px;
            }

            .meta-row {
                flex-direction: column;
                gap: 8px;
            }

            .card-meta {
                flex-direction: column;
                gap: 8px;
            }

            .footer-grid {
                grid-template-columns: 1fr;
            }

            .sidebar-portal-box {
                padding: 20px 15px;
            }
        }

        @media (max-width: 480px) {
            .contact-cards-grid {
                grid-template-columns: 1fr;
            }

            .card-metadata-row {
                flex-direction: column;
                gap: 2px;
            }

            .card-metadata-label {
                width: 100%;
            }

            .footer-bottom-info {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>

<body>

    <div class="ndu-header">
        <h1>Naxçıvan Dövlət Universiteti</h1>
        <p>Distant Təhsil Mərkəzi</p>
    </div>

    <div class="main-container">
        <div class="content-grid">

            <!-- Portal Login Sidebar (Sol) -->
            <div class="portal-sidebar">
                <div class="sidebar-portal-box" style="--portal-accent: #0ea5e9;">
                    <div class="sidebar-portal-icon">
                        <i data-lucide="graduation-cap"></i>
                    </div>
                    <h3>Tələbə Portalı</h3>
                    <p>Canlı dərslərə qoşulmaq, tapşırıqları yerinə yetirmək və arxiv videoları izləmək üçün daxil olun.
                    </p>
                    <a href="student/login.php" class="sidebar-portal-btn">
                        <span>Tələbə Girişi</span>
                        <i data-lucide="arrow-right" style="width:14px;"></i>
                    </a>
                </div>

                <div class="sidebar-portal-box" style="--portal-accent: #6366f1;">
                    <div class="sidebar-portal-icon">
                        <i data-lucide="user-check"></i>
                    </div>
                    <h3>Müəllim Portalı</h3>
                    <p>Canlı dərsləri idarə etmək, tələbə davamiyyətini izləmək və materialları yükləmək üçün daxil
                        olun.</p>
                    <a href="teacher/login.php" class="sidebar-portal-btn">
                        <span>Müəllim Girişi</span>
                        <i data-lucide="arrow-right" style="width:14px;"></i>
                    </a>
                </div>
            </div>


            <div class="schedule-column">
                <h2 class="column-title">Bu Günün Dərsləri - <?php echo $todayName; ?></h2>

                <?php if (empty($todayLessons)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                        <i data-lucide="calendar-off" size="48" style="margin-bottom: 15px; opacity: 0.2;"></i>
                        <p>Bu gün üçün hələ ki, heç bir dərs planlaşdırılmayıb.</p>
                    </div>
                <?php else: ?>
                    <div class="cards-scroll-area">
                        <?php foreach ($todayLessons as $lesson):
                            $isLive = ($lesson['status'] === 'live');
                            $startTime = date('H:i', strtotime($lesson['start_time']));
                            $endTime = date('H:i', strtotime($lesson['start_time'] . ' +90 minutes'));
                            ?>
                            <div class="lesson-card <?php echo $isLive ? 'live' : ''; ?>">
                                <div class="card-header-wrapper">
                                    <h3 class="card-header-title">
                                        <?php echo e($lesson['topic_name'] ?: $lesson['course_title']); ?>
                                    </h3>
                                    <?php if ($isLive): ?>
                                        <div class="badge-live">Canlı</div>
                                    <?php endif; ?>
                                </div>

                                <div class="card-meta-rows" style="margin-top: 15px;">
                                    <div class="meta-row">
                                        <div class="meta-item">
                                            <i data-lucide="clock"></i>
                                            <span><?php echo $startTime; ?> - <?php echo $endTime; ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <i data-lucide="user"></i>
                                            <span><?php echo e($lesson['instructor_display_name'] ?: 'Müəllim təyin edilməyib'); ?></span>
                                        </div>
                                    </div>
                                    <div class="meta-row" style="margin-top: 12px;">
                                        <div class="meta-item">
                                            <i data-lucide="graduation-cap"></i>
                                            <span style="font-weight: 500;">Kurs: <span
                                                    style="font-weight: 400;"><?php echo $lesson['course_level_val']; ?>-ci
                                                    kurs</span></span>
                                        </div>
                                        <div class="meta-item">
                                            <i data-lucide="map-pin"></i>
                                            <span style="font-weight: 500;">İxtisas: <span
                                                    style="font-weight: 400;"><?php echo e($lesson['specialization_name']); ?></span></span>
                                        </div>
                                    </div>
                                    <div class="meta-row" style="margin-top: 12px;">
                                        <div class="meta-item">
                                            <i data-lucide="book"></i>
                                            <span style="font-weight: 500;">Fənn: <span
                                                    style="font-weight: 400;"><?php echo e($lesson['course_title']); ?></span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="schedule-column">
                <h2 class="column-title">Keçirilmiş Dərslər</h2>

                <?php if (empty($archivedLessons)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                        <i data-lucide="video-off" size="48" style="margin-bottom: 15px; opacity: 0.2;"></i>
                        <p>Hələ ki, heç bir keçirilmiş dərs yazısı yoxdur.</p>
                    </div>
                <?php else: ?>
                    <div class="cards-scroll-area">
                        <?php foreach ($archivedLessons as $archive): ?>
                            <div class="lesson-card">
                                <h3 class="card-header-title" style="margin-bottom: 20px;">
                                    <?php echo e($archive['topic_name'] ?: $archive['course_title']); ?>
                                </h3>

                                <div class="card-metadata-list">
                                    <div class="card-metadata-row">
                                        <span class="card-metadata-label">Fənn:</span>
                                        <span
                                            class="card-metadata-value subject"><?php echo e($archive['course_title']); ?></span>
                                    </div>
                                    <div class="card-metadata-row">
                                        <span class="card-metadata-label">İxtisas:</span>
                                        <span
                                            class="card-metadata-value"><?php echo e($archive['specialization_name']); ?></span>
                                    </div>
                                    <div class="card-metadata-row">
                                        <span class="card-metadata-label">Kurs:</span>
                                        <span class="card-metadata-value"><?php echo $archive['course_level_val']; ?>-cü
                                            kurs</span>
                                    </div>
                                    <div class="card-metadata-row">
                                        <span class="card-metadata-label">Dərs mövzusu:</span>
                                        <span class="card-metadata-value"><?php echo e($archive['topic_name']); ?></span>
                                    </div>
                                    <div class="card-metadata-row">
                                        <span class="card-metadata-label">Müddət:</span>
                                        <span class="card-metadata-value">
                                            <?php
                                            if (is_numeric($archive['duration'])) {
                                                echo $archive['duration'] . ' dəq';
                                            } else {
                                                echo $archive['duration'] ?: '0 dəq';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="card-meta" style="border-top: 1px solid #f1f5f9; padding-top: 15px;">
                                    <div class="meta-item">
                                        <i data-lucide="calendar"></i>
                                        <span><?php echo date('d.m.Y', strtotime($archive['activity_date'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div class="ndu-footer">
        <div class="footer-container">
            <div class="footer-flex">
                <!-- Left side: Contact Cards -->
                <div class="contact-side">
                    <span class="footer-section-tag">Dəstək və Əlaqə</span>
                    <div class="contact-cards-grid">
                        <a href="mailto:distant@ndu.edu.az" class="contact-glass-card">
                            <div class="contact-icon-box"><i data-lucide="mail"></i></div>
                            <div class="contact-info-text">
                                <h4>E-poçt Ünvanı</h4>
                                <p>distant@ndu.edu.az</p>
                            </div>
                        </a>
                        <a href="tel:+994365440861" class="contact-glass-card">
                            <div class="contact-icon-box"><i data-lucide="phone"></i></div>
                            <div class="contact-info-text">
                                <h4>Qaynar Xətt</h4>
                                <p>+994 (36) 544 08 61</p>
                            </div>
                        </a>
                        <div class="contact-glass-card">
                            <div class="contact-icon-box"><i data-lucide="map-pin"></i></div>
                            <div class="contact-info-text">
                                <h4>Fiziki Ünvan</h4>
                                <p>Naxçıvan ş., Universitet şəhərciyi</p>
                            </div>
                        </div>
                    </div>
                </div>


            </div>

            <div class="footer-bottom-info">
                <div class="copyright-text">
                    © 2026 Naxçıvan Dövlət Universiteti • Distant Təhsil Mərkəzi
                </div>
                <div class="dev-badge">
                    Developed by <span>NSU IT Department</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>