<?php
/**
 * Teacher Live Studio - (V8.0 - PREMIUM REDESIGN)
 * - Sidebar Integrated Layout
 * - Independent Scrolling
 * - Modern Glassmorphism UI
 */
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$auth = new Auth();
requireInstructor();
$db = Database::getInstance();
$lessonId = $_GET['id'] ?? null;
$subjectId = $_GET['subject_id'] ?? null;

// Bazanın adını tapmaq və dərsi dürüst axtarmaq (Support both Local ID and TMİS ID)
$lesson = $db->fetch(
    "SELECT lc.*, c.title as course_title 
     FROM live_classes lc 
     LEFT JOIN courses c ON lc.course_id = c.id 
     WHERE lc.id = ? OR lc.tmis_session_id = ?",
    [$lessonId, $lessonId]
);

// Fallback: Əgər id/tmis_session_id ilə tapılmadısa, course_id ilə aktiv dərsi axtar
if (!$lesson && $subjectId) {
    $lesson = $db->fetch(
        "SELECT lc.*, c.title as course_title 
         FROM live_classes lc 
         LEFT JOIN courses c ON lc.course_id = c.id 
         WHERE lc.course_id = ? AND lc.status = 'live' 
         ORDER BY lc.id DESC LIMIT 1",
        [$subjectId]
    );
    // Real DB id-ni istifadə et ki, bütün API çağırışları düzgün işləsin
    if ($lesson) {
        $lessonId = $lesson['id'];
    }
}

if (!$lesson) {
    if (!isset($_SESSION['mock_lesson_start_' . $lessonId])) {
        $_SESSION['mock_lesson_start_' . $lessonId] = date('Y-m-d H:i:s');
    }
    // Mock the lesson array if DB returns null
    $lesson = [
        'id' => $lessonId,
        'course_id' => $subjectId ?? $lessonId,
        'course_title' => "Canlı Studio",
        'started_at' => $_SESSION['mock_lesson_start_' . $lessonId],
        'status' => 'live'
    ];
}

// ============================================================
// Artıq bitmiş dərsi Studioda açmağın qarşısını al
// ============================================================
if (isset($lesson['status']) && in_array($lesson['status'], ['ended', 'completed'])) {
    header('Location: live-lessons.php?error=' . urlencode('Bu dərs artıq bitirilmişdir.'));
    exit;
}

// Global safety check
if (!isset($lesson['course_id']) || $lesson['course_id'] == $lessonId)
    $lesson['course_id'] = $_GET['subject_id'] ?? $lesson['course_id'] ?? $lessonId ?? 0;
if (!isset($lesson['course_title']))
    $lesson['course_title'] = "Canlı Studio";
if (!isset($lesson['started_at'])) {
    if (!isset($_SESSION['mock_lesson_start_' . $lessonId])) {
        $_SESSION['mock_lesson_start_' . $lessonId] = date('Y-m-d H:i:s');
    }
    $lesson['started_at'] = $_SESSION['mock_lesson_start_' . $lessonId];
}

if (empty($lesson['course_title'])) {
    $lesson['course_title'] = "Fənn " . $lesson['course_id'];
}

require_once 'includes/header.php';
?>
<style>
    /* Studio tam ekran: sidebar və top header gizlə */
    .sidebar,
    aside.sidebar,
    .top-header {
        display: none !important;
    }

    .main-wrapper {
        padding-left: 0 !important;
        margin-left: 0 !important;
    }

    body,
    html {
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden;
        background: #0f172a;
        font-family: 'Inter', sans-serif;
    }

    @keyframes blink {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    @keyframes pulse-red {
        0% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
        }

        70% {
            box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
        }
    }

    #chatMessages::-webkit-scrollbar,
    #liveAttendanceList::-webkit-scrollbar,
    #logBox::-webkit-scrollbar {
        width: 5px;
    }

    #chatMessages::-webkit-scrollbar-track,
    #liveAttendanceList::-webkit-scrollbar-track,
    #logBox::-webkit-scrollbar-track {
        background: transparent;
    }

    #chatMessages::-webkit-scrollbar-thumb,
    #liveAttendanceList::-webkit-scrollbar-thumb,
    #logBox::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
    }

    .control-btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(14px);
    }

    .control-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-3px);
    }

    .control-btn.active-green {
        background: #10b981 !important;
        border-color: #10b981;
    }

    .control-btn.active-red {
        background: #ef4444 !important;
        border-color: #ef4444;
    }

    .sidebar-section {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        padding: 15px;
        display: flex;
        flex-direction: column;
    }

    #whiteboardOverlay {
        position: fixed;
        inset: 0;
        background: #fdfdfd;
        z-index: 2000;
        display: none;
        flex-direction: column;
        font-family: 'Inter', sans-serif;
    }

    .wb-controls-floating {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(15, 23, 42, 0.98);
        backdrop-filter: blur(20px);
        padding: 10px;
        border-radius: 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.15);
        z-index: 2010;
    }

    .wb-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding-bottom: 8px;
    }

    .wb-group:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .wb-group-label {
        color: #94a3b8;
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 2px;
        text-align: center;
    }

    .wb-tool-btn {
        background: rgba(255, 255, 255, 0.06);
        border: 2px solid rgba(255, 255, 255, 0.08);
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        font-size: 18px;
        position: relative;
    }

    .wb-tool-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-2px) scale(1.02);
        border-color: rgba(255, 255, 255, 0.25);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
    }

    .wb-tool-btn:active {
        transform: translateY(0) scale(0.98);
    }

    .wb-tool-btn.active {
        background: #3b82f6;
        border-color: #60a5fa;
        box-shadow: 0 0 15px rgba(59, 130, 246, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    }

    .wb-color-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 3px;
    }

    .wb-color {
        width: 20px;
        height: 20px;
        border-radius: 6px;
        cursor: pointer;
        border: 2px solid rgba(255, 255, 255, 0.25);
        transition: all 0.2s;
    }

    .wb-color:hover {
        transform: scale(1.1);
        border-color: rgba(255, 255, 255, 0.5);
    }

    .wb-color.active {
        outline: 2px solid white;
        outline-offset: 1px;
        box-shadow: 0 0 10px currentColor;
    }

    #laserCursor {
        position: absolute;
        width: 12px;
        height: 12px;
        background: radial-gradient(circle, #ef4444 0%, transparent 70%);
        border: 2px solid white;
        border-radius: 50%;
        box-shadow: 0 0 10px #ef4444;
        pointer-events: none;
        display: none;
        z-index: 2020;
    }

    @keyframes zoomIn {
        from {
            opacity: 0;
            transform: translate(-50%, -20%) scale(0.9);
        }

        to {
            opacity: 1;
            transform: translate(-50%, 0) scale(1);
        }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translate(-50%, 40px);
        }

        to {
            opacity: 1;
            transform: translate(-50%, 0);
        }
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(30px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
</style>

<div class="main-wrapper" style="height: 100vh; display: flex; flex-direction: column; overflow: hidden; color: white;">
    <!-- STUDIO HEADER -->
    <div
        style="height: 65px; min-height: 65px; padding: 0 30px; background: #1e293b; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #334155; z-index: 100;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div
                style="display: flex; align-items: center; gap: 10px; background: rgba(239, 68, 68, 0.1); padding: 8px 15px; border-radius: 50px; border: 1px solid rgba(239, 68, 68, 0.2);">
                <div
                    style="width: 10px; height: 10px; background: #ef4444; border-radius: 50%; animation: blink 1s infinite; box-shadow: 0 0 10px #ef4444;">
                </div>
                <span style="color: #ef4444; font-weight: 800; font-size: 12px; letter-spacing: 1px;">STUDİO
                    CANLI</span>
            </div>
            <h1 style="font-size: 16px; margin: 0; font-weight: 700; color: #f8fafc;">
                <?php echo e($lesson['course_title'] ?? ("Dərs #" . $lessonId)); ?> <span
                    style="opacity: 0.4; margin-left: 8px; font-weight: 400;">#<?php echo $lessonId; ?></span>
            </h1>
        </div>

        <div style="display: flex; align-items: center; gap: 12px;">
            <button
                onclick="window.open('attendance_report.php?id=<?php echo $lessonId; ?>&minimal=1', 'AttendanceReport', 'width=1000,height=800,scrollbars=yes,resizable=yes')"
                style="background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); padding: 8px 18px; border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s; cursor: pointer;">
                📋 İştirakçı Jurnalı
            </button>
            <button onclick="stopAndUpload()"
                style="background: #ef4444; color: white; border: none; padding: 8px 22px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);">
                Dərsi Bitir
            </button>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div style="display: grid; grid-template-columns: 280px 1fr 380px; flex: 1; min-height: 0; overflow: hidden;">

        <!-- LEFT SIDEBAR: ATTENDANCE -->
        <div
            style="background: #1e293b; border-right: 2px solid #334155; display: flex; flex-direction: column; padding: 20px; gap: 20px; overflow: hidden; min-height: 0;">
            <div class="sidebar-section" style="flex: 1; min-height: 0; display: flex; flex-direction: column;">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-shrink: 0;">
                    <h3
                        style="font-size: 11px; font-weight: 800; color: #10b981; text-transform: uppercase; letter-spacing: 1px; margin: 0;">
                        Canlı İştirak
                    </h3>
                    <span id="liveAttendanceCount"
                        style="background: #10b981; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 900;">0/0</span>
                </div>
                <div id="liveAttendanceList" style="flex: 1; overflow-y: auto; padding-right: 5px;">
                    <div
                        style="padding: 20px; text-align: center; color: #64748b; font-size: 12px; font-style: italic;">
                        Yüklənir...
                    </div>
                </div>
            </div>
        </div>

        <!-- CENTER: PRODUCTION AREA -->
        <div
            style="position: relative; background: #020617; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">

            <!-- MAIN VIEWER -->
            <div
                style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 30px; position: relative;">
                <div id="mainVideoWrapper"
                    style="width: 100%; max-width: 1100px; aspect-ratio: 16/9; position: relative; background: #000; border-radius: 24px; overflow: hidden; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.8); border: 1px solid rgba(255,255,255,0.05);">
                    <video id="localVid" autoplay playsinline muted
                        style="width: 100%; height: 100%; object-fit: contain; background: #000;"></video>

                    <!-- SOURCE VIDEOS (HIDDEN) -->
                    <video id="camSource" autoplay playsinline muted style="display:none;"></video>
                    <video id="screenSource" autoplay playsinline muted style="display:none;"></video>

                    <!-- SPOTLIGHT OVERLAY -->
                    <div id="spotlightOverlay"
                        style="position: absolute; top: 20px; right: 20px; display: none; z-index: 50;">
                        <button onclick="resetMainVideo()"
                            style="background: #ef4444; color: white; border: none; padding: 8px 20px; border-radius: 50px; cursor: pointer; font-size: 12px; font-weight: 800; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);">
                            KAMERAMA QAYIT
                        </button>
                    </div>

                    <!-- TEACHER NAME BADGE -->
                    <div
                        style="position: absolute; top: 25px; left: 25px; background: rgba(0,0,0,0.5); padding: 8px 15px; border-radius: 10px; font-size: 12px; font-weight: 700; color: #fff; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(8px); z-index: 50; display: flex; align-items: center; gap: 8px;">
                        <div
                            style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; box-shadow: 0 0 8px #10b981;">
                        </div>
                        Müəllim: <?php echo e($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
                    </div>
                    <!-- DURATION TIMER -->
                    <div id="lessonTimer"
                        style="position: absolute; top: 25px; right: 25px; background: rgba(0,0,0,0.7); padding: 6px 12px; border-radius: 10px; font-size: 13px; font-weight: 800; color: #fff; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(8px); display: flex; align-items: center; gap: 8px; font-family: 'JetBrains Mono', monospace;">
                        <i data-lucide="clock" style="width: 14px; height: 14px; color: #3b82f6;"></i>
                        <span id="timerDisplay">00:00</span>
                    </div>
                </div>
            </div>

            <!-- CONTROLS BAR -->
            <div
                style="height: 120px; padding: 0 30px; display: flex; align-items: center; justify-content: center; gap: 30px; background: linear-gradient(to top, rgba(15,23,42,1), rgba(15,23,42,0)); z-index: 20;">

                <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                    <button id="btnMic" onclick="toggleMic()" class="control-btn" title="Mikrofon">
                        <i data-lucide="mic" style="width: 22px; height: 22px;"></i>
                    </button>
                    <span
                        style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">MİKROFON</span>
                </div>

                <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                    <button id="btnCam" onclick="toggleCam()" class="control-btn" title="Kamera">
                        <i data-lucide="video" style="width: 22px; height: 22px;"></i>
                    </button>
                    <span
                        style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">KAMERA</span>
                </div>

                <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                    <button id="btnScreen" onclick="toggleScreenShare()" class="control-btn" title="Ekran Paylaş">
                        <i data-lucide="monitor" style="width: 22px; height: 22px;"></i>
                    </button>
                    <span
                        style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">EKRAN</span>
                </div>

                <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                    <button id="btnWhiteboard" onclick="toggleWhiteboard()" class="control-btn" title="Ağ Lövhə">
                        <i data-lucide="pen-tool" style="width: 22px; height: 22px;"></i>
                    </button>
                    <span
                        style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">LÖVHƏ</span>
                </div>

                <div style="width: 1px; height: 40px; background: rgba(255,255,255,0.1); margin: 0 10px;"></div>

                <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                    <button
                        onclick="document.getElementById('logWrapper').style.display = document.getElementById('logWrapper').style.display === 'none' ? 'block' : 'none'"
                        class="control-btn" style="width: 50px; border-radius: 50%;">
                        <i data-lucide="activity" style="width: 22px; height: 22px;"></i>
                    </button>
                    <span
                        style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">LOGLAR</span>
                </div>
            </div>

            <!-- LOGS WRAPPER (HIDDEN BY DEFAULT) -->
            <div id="logWrapper"
                style="display: none; position: absolute; bottom: 110px; left: 30px; right: 30px; z-index: 100;">
                <div id="logBox"
                    style="height: 150px; background: rgba(15,23,42,0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 20px; font-family: 'JetBrains Mono', monospace; font-size: 11px; overflow-y: auto; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
                    <div
                        style="color: #60a5fa; font-weight: 800; margin-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 8px;">
                        SİSTEM HADİSƏLƏRİ</div>
                </div>
            </div>
        </div>

        <!-- RIGHT: SIDEBAR CORE -->
        <div
            style="background: #1e293b; border-left: 2px solid #334155; display: flex; flex-direction: column; padding: 20px; gap: 20px; overflow: visible; min-height: 0;">

            <!-- STUDENTS VIDEO GRID -->
            <!-- STUDENTS VIDEO GRID -->
            <div class="sidebar-section"
                style="flex: 2; display: flex; flex-direction: column; min-height: 0; padding-bottom: 0;">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-shrink: 0;">
                    <h3
                        style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin: 0;">
                        Tələbə Kameraları</h3>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button onclick="refreshAllStudentVideos()"
                            style="background: rgba(253, 224, 71, 0.1); border: 1px solid #fde047; color: #fde047; font-size: 10px; padding: 4px 8px; border-radius: 6px; cursor: pointer; font-weight: 700;"
                            title="Bütün kameraları yenidən başlat">🔄 Yenilə</button>
                        <span id="activeVideoCount"
                            style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 900;">0</span>
                    </div>
                </div>

                <style>
                    #studentsGrid::-webkit-scrollbar {
                        #studentsGrid::-webkit-scrollbar {
                            width: 6px;
                        }

                        #studentsGrid::-webkit-scrollbar-track {
                            background: transparent;
                        }

                        #studentsGrid::-webkit-scrollbar-thumb {
                            background: rgba(255, 255, 255, 0.15);
                            border-radius: 10px;
                        }

                        #studentsGrid::-webkit-scrollbar-thumb:hover {
                            background: rgba(255, 255, 255, 0.3);
                        }
                </style>

                <div id="studentsGrid"
                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; overflow-y: auto; overflow-x: hidden; padding-right: 5px; flex: 1; align-content: start;">
                    <div id="waitingMsg"
                        style="grid-column: 1/-1; padding: 30px 10px; text-align: center; border: 2px dashed rgba(255,255,255,0.05); border-radius: 12px; color: #64748b; font-size: 12px; align-self: center;">
                        Gözlənilir...
                    </div>
                </div>
            </div>


            <!-- CHAT AREA -->
            <div class="sidebar-section" style="flex: 1; min-height: 0; display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3
                        style="font-size: 11px; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 1px; margin: 0;">
                        Dərs Çatı</h3>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="openBulkNotificationModal()"
                            style="background: rgba(59, 130, 246, 0.1); border: 1px solid #3b82f6; color: #3b82f6; font-size: 10px; padding: 4px 8px; border-radius: 6px; cursor: pointer; font-weight: 700;">📣
                            Toplu Bildiriş</button>
                        <i data-lucide="message-square" style="width: 14px; height: 14px; color: #3b82f6;"></i>
                    </div>
                </div>

                <!-- PRIVATE CHAT TARGET INDICATOR -->
                <div id="privateTargetIndicator"
                    style="display: none; background: rgba(59, 130, 246, 0.2); padding: 5px 12px; border-radius: 8px; font-size: 11px; margin-bottom: 8px; align-items: center; justify-content: space-between;">
                    <span style="color: #3b82f6; font-weight: 700;">🔒 Özəl: <span id="targetName">Tələbə</span></span>
                    <button onclick="clearPrivateTarget()"
                        style="background: none; border: none; color: #ef4444; font-size: 14px; cursor: pointer; padding: 0;">&times;</button>
                </div>

                <div id="chatMessages"
                    style="flex: 1; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 12px; padding: 12px; margin-bottom: 12px; font-size: 13px; border: 1px solid rgba(255,255,255,0.02);">
                    <div
                        style="height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.2; text-align: center;">
                        <p style="font-size: 11px;">Hələ ki, mesaj yoxdur.</p>
                    </div>
                </div>

                <div style="display: flex; gap: 8px;">
                    <label for="fileInput"
                        style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;">
                        📎
                    </label>
                    <input type="file" id="fileInput" style="display: none;" onchange="handleFileSelect(this)">

                    <input type="text" id="chatInput" placeholder="Mesaj yazın..."
                        style="flex: 1; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 0 15px; color: white; font-size: 13px; outline: none;"
                        onkeypress="if(event.key==='Enter') sendChatMessage()">

                    <button onclick="sendChatMessage()"
                        style="background: #3b82f6; border: none; border-radius: 10px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                        🚀
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Start Production Overlay (Mandatory User Interaction for Fullscreen/Media) -->
<div id="startProductionOverlay"
    style="position: fixed; inset: 0; background: #0f172a; z-index: 30000; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px;">
    <div style="max-width: 500px;">
        <div
            style="width: 100px; height: 100px; background: rgba(59, 130, 246, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 30px;">
            <i data-lucide="play-circle" style="width: 50px; height: 50px; color: #3b82f6;"></i>
        </div>
        <h2 style="font-size: 28px; font-weight: 800; color: white; margin-bottom: 15px;">Dərs Yayımına Başlayın</h2>
        <p style="color: #94a3b8; margin-bottom: 40px; line-height: 1.6; font-size: 16px;">Video qeydiyyatın
            təhlükəsizliyi və tam ekran rejimi üçün yayımı aşağıdakı düymə ilə başladın.</p>
        <button onclick="startProductionNow()"
            style="background: #3b82f6; color: white; border: none; padding: 18px 40px; border-radius: 12px; font-size: 18px; font-weight: 800; cursor: pointer; box-shadow: 0 15px 35px rgba(59, 130, 246, 0.4); transition: all 0.2s;">🚀
            YAYIMI BAŞLAT (TAM EKRAN)</button>
        <p style="color: #64748b; font-size: 12px; margin-top: 20px;">Qeyd: Dərs əsnasında səhifəni yeniləməyin.</p>
    </div>
</div>

<!-- Whiteboard Request Modal -->
<div id="wbRequestModal"
    style="display: none; position: fixed; inset: 0; background: rgba(2,6,23,0.8); backdrop-filter: blur(10px); z-index: 10000; align-items: center; justify-content: center;">
    <div
        style="background: #1e293b; border: 1px solid rgba(255,255,255,0.1); width: 450px; padding: 40px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); text-align: center; animation: zoomIn 0.3s ease-out;">
        <div
            style="width: 80px; height: 80px; background: rgba(59, 130, 246, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px;">
            <i data-lucide="pen-tool" style="width: 40px; height: 40px; color: #3b82f6;"></i>
        </div>
        <h3 style="font-size: 22px; font-weight: 850; margin-bottom: 10px; color: white;">Lövhə İstəyi</h3>
        <p style="color: #94a3b8; margin-bottom: 30px; line-height: 1.6;"><b><span id="wbRequesterName"
                    style="color: #60a5fa;"></span></b> lövhədən istifadə icazəsi istəyir. Təsdiqləyirsiniz?</p>
        <div style="display: flex; gap: 15px;">
            <button id="wbRejectBtn"
                style="flex: 1; background: rgba(255,255,255,0.05); color: white; border: 1px solid rgba(255,255,255,0.1); padding: 14px; border-radius: 12px; font-weight: 700; cursor: pointer;">RƏDD
                ET</button>
            <button id="wbApproveBtn"
                style="flex: 1; background: #3b82f6; color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);">İCAZƏ
                VER</button>
        </div>
    </div>
</div>

<!-- Mic Request Modal -->
<div id="micRequestModal"
    style="display: none; position: fixed; inset: 0; background: rgba(2,6,23,0.85); z-index: 1000; backdrop-filter: blur(10px); justify-content: center; align-items: center;">
    <div
        style="background: #1e293b; border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; padding: 40px; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 30px 60px rgba(0,0,0,0.5);">
        <div
            style="width: 80px; height: 80px; background: rgba(34, 197, 94, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px; font-size: 40px;">
            🎤</div>
        <h3 id="micRequestTitle" style="margin: 0 0 10px 0; font-size: 20px; font-weight: 800;">Tələbə Söz İstəyir</h3>
        <p id="micRequestText" style="color: #94a3b8; font-size: 14px; margin-bottom: 30px; line-height: 1.6;">Tələbənin
            mikrofonunu aktivləşdirmək istəyirsiniz?</p>
        <div style="display: flex; gap: 15px;">
            <button id="micRejectBtn"
                style="flex: 1; background: rgba(255,255,255,0.05); color: white; border: 1px solid rgba(255,255,255,0.1); padding: 14px; border-radius: 12px; font-weight: 700; cursor: pointer;">RƏDD
                ET</button>
            <button id="micApproveBtn"
                style="flex: 1; background: #22c55e; color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 10px 20px rgba(34, 197, 94, 0.3);">İCAZƏ
                VER</button>
        </div>
    </div>
</div>

<!-- Pure Whiteboard Overlay -->
<div id="whiteboardOverlay">
    <!-- Floating Toolbar -->
    <div class="wb-controls-floating">
        <!-- NÖV - Fon seçimi -->
        <div class="wb-group">
            <span class="wb-group-label">NÖV</span>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 4px;">
                <button id="bgPlain" class="wb-tool-btn active" onclick="setWBBackground('plain')"
                    title="Ağ Fon">⬜</button>
                <button id="bgGrid" class="wb-tool-btn" onclick="setWBBackground('grid')"
                    title="Riyaziyyat (Dama)">📏</button>
                <button id="bgLines" class="wb-tool-btn" onclick="setWBBackground('lines')"
                    title="Dil (Xətli)">📝</button>
            </div>
        </div>

        <!-- ADDIM - Geri/İrəli -->
        <div class="wb-group">
            <span class="wb-group-label">ADDIM</span>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                <button class="wb-tool-btn" onclick="undo()" title="Geri Al (Undo)">↩️</button>
                <button class="wb-tool-btn" onclick="redo()" title="Yenidən (Redo)">↪️</button>
            </div>
        </div>

        <!-- ALƏTLƏR - Əsas alətlər -->
        <div class="wb-group">
            <span class="wb-group-label">ALƏTLƏR</span>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                <button id="toolPencil" class="wb-tool-btn active" onclick="setWBTool('pencil')"
                    title="Qələm">✏️</button>
                <button id="toolEraser" class="wb-tool-btn" onclick="setWBTool('eraser')" title="Silgi">🧽</button>
                <button id="toolText" class="wb-tool-btn" onclick="setWBTool('text')" title="Mətn Yaz">🔤</button>
                <button id="toolLaser" class="wb-tool-btn" onclick="setWBTool('laser')"
                    title="Lazer Göstərici">🔦</button>
                <button class="wb-tool-btn" onclick="document.getElementById('wbImgInput').click()"
                    title="Şəkil Əlavə Et" style="grid-column: span 2;">🖼️</button>
            </div>
            <!-- Size control for pencil/eraser -->
            <div id="sizeControl"
                style="display: flex; align-items: center; gap: 4px; justify-content: center; margin-top: 6px;">
                <button class="wb-tool-btn" onclick="changeSize(-5)" title="Kiçilt"
                    style="width: 28px; height: 28px; font-size: 14px;">−</button>
                <div id="sizeDisplay"
                    style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; min-width: 40px; text-align: center;">
                    3px</div>
                <button class="wb-tool-btn" onclick="changeSize(5)" title="Böyüt"
                    style="width: 28px; height: 28px; font-size: 14px;">+</button>
            </div>
            <input type="file" id="wbImgInput" style="display:none" accept="image/*" onchange="wbUploadImage(this)">
        </div>

        <!-- FİQURLAR - Həndəsi formalar -->
        <div class="wb-group">
            <span class="wb-group-label">FİQURLAR</span>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                <button id="toolLine" class="wb-tool-btn" onclick="setWBTool('line')" title="Düz Xətt">➖</button>
                <button id="toolRect" class="wb-tool-btn" onclick="setWBTool('rect')" title="Dördbucaqlı">⬜</button>
                <button id="toolCircle" class="wb-tool-btn" onclick="setWBTool('circle')" title="Dairə">⭕</button>
                <button id="toolArrow" class="wb-tool-btn" onclick="setWBTool('arrow')" title="Ox İşarəsi">↗️</button>
            </div>
        </div>

        <!-- RƏNG - Rəng seçimi -->
        <div class="wb-group">
            <span class="wb-group-label">RƏNG</span>
            <div class="wb-color-grid" style="grid-template-columns: repeat(4, 1fr);">
                <div class="wb_color_item wb-color active" style="background: #000000;"
                    onclick="setWBColor('#000000', this)" title="Qara"></div>
                <div class="wb_color_item wb-color" style="background: #ef4444;" onclick="setWBColor('#ef4444', this)"
                    title="Qırmızı"></div>
                <div class="wb_color_item wb-color" style="background: #3b82f6;" onclick="setWBColor('#3b82f6', this)"
                    title="Mavi"></div>
                <div class="wb_color_item wb-color" style="background: #10b981;" onclick="setWBColor('#10b981', this)"
                    title="Yaşıl"></div>
                <div class="wb_color_item wb-color" style="background: #f59e0b;" onclick="setWBColor('#f59e0b', this)"
                    title="Narıncı"></div>
                <div class="wb_color_item wb-color" style="background: #8b5cf6;" onclick="setWBColor('#8b5cf6', this)"
                    title="Bənövşəyi"></div>
                <div class="wb_color_item wb-color" style="background: #ec4899;" onclick="setWBColor('#ec4899', this)"
                    title="Çəhrayı"></div>
                <div class="wb_color_btn wb-color"
                    style="background: linear-gradient(135deg, #fff, #ddd); border:2px dashed #94a3b8;"
                    onclick="openColorPicker()" title="Xüsusi Rəng">
                    <input type="color" id="customColorPicker"
                        style="opacity: 0; position: absolute; width: 0; height: 0;"
                        onchange="setCustomColor(this.value)">
                </div>
            </div>
        </div>

        <!-- ƏMƏLİYYATLAR - Saxla, Təmizlə, Çıx -->
        <div class="wb-group">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 4px;">
                <button class="wb-tool-btn" onclick="clearWhiteboard()" title="Təmizlə"
                    style="background: rgba(239, 68, 68, 0.15); border-color: #ef4444;">🗑️</button>
                <button class="wb-tool-btn" onclick="exportWhiteboard()" title="Yadda Saxla"
                    style="background: rgba(16, 185, 129, 0.2); border-color: #10b981;">💾</button>
                <button class="wb-tool-btn" onclick="toggleWhiteboard()" title="Kameraya Qayıt"
                    style="background: #ef4444; border-color: #ef4444;">❌</button>
            </div>
        </div>

        <!-- SƏHİFƏLƏR - Çoxsəhifəli sistem -->
        <div class="wb-group" style="border-bottom: none;">
            <span class="wb-group-label">SƏHİFƏ</span>
            <div style="display: flex; align-items: center; gap: 4px; justify-content: center;">
                <button class="wb-tool-btn" onclick="prevPage()" title="Əvvəlki"
                    style="width: 32px; height: 32px; font-size: 12px;">◀</button>
                <div id="pageIndicator"
                    style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; min-width: 40px; text-align: center;">
                    1/1</div>
                <button class="wb-tool-btn" onclick="nextPage()" title="Növbəti"
                    style="width: 32px; height: 32px; font-size: 12px;">▶</button>
                <button class="wb-tool-btn" onclick="addNewPage()" title="Yeni Səhifə"
                    style="width: 32px; height: 32px; font-size: 12px; background: rgba(16, 185, 129, 0.2); border-color: #10b981;">+</button>
                <button class="wb-tool-btn" onclick="deletePage()" title="Səhifəni Sil"
                    style="width: 32px; height: 32px; font-size: 14px; background: rgba(239, 68, 68, 0.15); border-color: #ef4444;">×</button>
            </div>
        </div>
    </div>

    <!-- Info Badge -->
    <div
        style="position: absolute; top: 20px; right: 20px; background: #1e293b; color: white; padding: 10px 20px; border-radius: 12px; font-weight: 800; font-size: 11px; letter-spacing: 1px; display: flex; align-items: center; gap: 10px; z-index: 2010; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
        <div style="width: 8px; height: 8px; background: #ef4444; border-radius: 50%; animation: blink 1s infinite;">
        </div>
        STUDİO WHITEBOARD PRO
    </div>

    <div id="laserCursor"></div>

    <!-- Image Placement Overlay -->
    <div id="imagePlacementOverlay"
        style="display: none; position: absolute; inset: 0; z-index: 3000; background: rgba(0,0,0,0.3);">
        <div id="imagePlacementContainer"
            style="position: absolute; cursor: move; border: 2px dashed #3b82f6; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <img id="placementImage" style="width: 100%; height: 100%; object-fit: contain; pointer-events: none;">
            <!-- Resize handle -->
            <div id="resizeHandle"
                style="position: absolute; bottom: -8px; right: -8px; width: 16px; height: 16px; background: #3b82f6; border: 2px solid white; border-radius: 50%; cursor: se-resize;">
            </div>
        </div>
        <div
            style="position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); display: flex; gap: 15px;">
            <button onclick="confirmImagePlacement()"
                style="background: #22c55e; color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 700; cursor: pointer; box-shadow: 0 5px 15px rgba(34, 197, 94, 0.4);">✓
                Yerləşdir</button>
            <button onclick="cancelImagePlacement()"
                style="background: #ef4444; color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 700; cursor: pointer; box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);">✕
                Ləğv Et</button>
        </div>
        <div
            style="position: absolute; top: 20px; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 600;">
            🖼️ Şəkli sürükləyin. Küncündən tutub ölçüsünü dəyişin.
        </div>
    </div>

    <div style="flex: 1; position: relative; background: #ffffff; cursor: crosshair; overflow: hidden;">
        <canvas id="wbCanvasInternal" style="display: block;"></canvas>
    </div>
</div>

<script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
<script>
    const LOG = (msg, color = "#a5f3fc") => {
        const d = document.getElementById('logBox');
        if (!d) {
            console.log("LOG:", msg);
            return;
        }
        d.innerHTML += `<div style="color: ${color}; margin-bottom: 5px;">[${new Date().toLocaleTimeString()}] ${msg}</div>`;
        d.scrollTop = d.scrollHeight;
    };

    window.onerror = function (msg, url, line) {
        LOG(`🚨 JS ERROR: ${msg} (Line: ${line})`, "#ef4444");
        console.error(msg, url, line);
        return false;
    };

    var stream, peer;
    var camStream = null;
    var screenStream = null;
    var allDataConns = [];
    const lID = "<?php echo $lessonId ?? '0'; ?>";
    const courseId = "<?php echo $lesson['course_id'] ?? '0'; ?>";
    var isScreenSharing = false;
    var isWhiteboardActive = false;
    var wbCanvas = null;
    var wbCtx = null;
    var isDrawing = false;
    var startX = 0;
    var startY = 0;
    var lastX = 0;
    var lastY = 0;
    var wbColor = '#000000';
    var wbTool = 'pencil';
    var wbSnapshot = null;
    var wbBgType = 'plain';
    var eraserSize = 30; // Default eraser size
    var pencilSize = 3;  // Default pencil size

    // Laser pointer position (for streaming to students)
    var laserX = 0;
    var laserY = 0;
    var laserActive = false;

    // Student Spotlight / Screen Share State
    var isStudentSpotlight = false;
    var spotlightPeerId = null;
    var spotlightName = "";
    var studentScreenVidElement = null;
    var studentCamVidElement = null;

    // Multi-page system
    let wbPages = []; // Array of page data (ImageData)
    let currentPageIndex = 0;

    function saveCurrentPage() {
        if (wbCanvas && wbCtx) {
            wbPages[currentPageIndex] = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
        }
    }

    function loadPage(index) {
        if (wbPages[index]) {
            wbCtx.putImageData(wbPages[index], 0, 0);
        } else {
            // New blank page
            wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
        }
        updatePageIndicator();
    }

    function addNewPage() {
        saveCurrentPage(); // Save current page first
        currentPageIndex = wbPages.length; // Go to new page
        wbPages.push(null); // Placeholder for new page
        wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height); // Clear canvas
        updatePageIndicator();
        LOG("📄 Yeni səhifə əlavə edildi: " + (currentPageIndex + 1), "#3b82f6");
    }

    function prevPage() {
        if (currentPageIndex > 0) {
            saveCurrentPage();
            currentPageIndex--;
            loadPage(currentPageIndex);
            LOG("◀️ Səhifə: " + (currentPageIndex + 1) + "/" + wbPages.length, "#64748b");
        }
    }

    function nextPage() {
        if (currentPageIndex < wbPages.length - 1) {
            saveCurrentPage();
            currentPageIndex++;
            loadPage(currentPageIndex);
            LOG("▶️ Səhifə: " + (currentPageIndex + 1) + "/" + wbPages.length, "#64748b");
        }
    }

    function deletePage() {
        if (wbPages.length <= 1) {
            LOG("⚠️ Son səhifəni silə bilməzsiniz!", "#f59e0b");
            return;
        }
        if (confirm("Bu səhifəni silmək istəyirsiniz?")) {
            wbPages.splice(currentPageIndex, 1);
            if (currentPageIndex >= wbPages.length) {
                currentPageIndex = wbPages.length - 1;
            }
            loadPage(currentPageIndex);
            LOG("🗑️ Səhifə silindi. Qalan: " + wbPages.length, "#ef4444");
        }
    }

    function updatePageIndicator() {
        const indicator = document.getElementById('pageIndicator');
        if (indicator) {
            indicator.innerText = (currentPageIndex + 1) + '/' + wbPages.length;
        }
    }

    // Undo/Redo Stacks
    let undoStack = [];
    let redoStack = [];
    const MAX_HISTORY = 30;

    function saveState() {
        if (undoStack.length >= MAX_HISTORY) undoStack.shift();
        undoStack.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height));
        redoStack = []; // Clear redo on new action
    }

    function undo() {
        if (undoStack.length > 0) {
            redoStack.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height));
            const state = undoStack.pop();
            wbCtx.putImageData(state, 0, 0);
            LOG("⏪ Geri qaytarıldı.");
        }
    }

    function redo() {
        if (redoStack.length > 0) {
            undoStack.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height));
            const state = redoStack.pop();
            wbCtx.putImageData(state, 0, 0);
            LOG("⏩ İrəli qaytarıldı.");
        }
    }

    // Whiteboard Functions
    function toggleWhiteboard() {
        isWhiteboardActive = !isWhiteboardActive;
        const overlay = document.getElementById('whiteboardOverlay');
        const btn = document.getElementById('btnWhiteboard');

        if (isWhiteboardActive) {
            overlay.style.display = 'flex';
            btn.classList.add('active-blue');
            initWBCanvas();
            LOG("🎨 Advanced Whiteboard aktivdir.", "#3b82f6");
        } else {
            overlay.style.display = 'none';
            btn.classList.remove('active-blue');
            document.getElementById('laserCursor').style.display = 'none';
            LOG("🎥 Normal görünüşə qayıtdı.");
        }
    }

    function initWBCanvas() {
        if (wbCanvas) return;
        wbCanvas = document.getElementById('wbCanvasInternal');
        wbCtx = wbCanvas.getContext('2d');

        const resize = () => {
            const container = wbCanvas.parentElement;
            // Capture existing content if any
            let tempImg = null;
            if (wbCanvas.width > 0 && wbCanvas.height > 0) {
                tempImg = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
            }

            wbCanvas.width = container.clientWidth;
            wbCanvas.height = container.clientHeight;

            // Clear to transparent (CSS background shows through)
            wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);

            // Restore previous content
            if (tempImg) wbCtx.putImageData(tempImg, 0, 0);
            else saveState(); // First state

            // Apply CSS background based on current type
            setWBBackground(wbBgType);
        };

        window.addEventListener('resize', resize);
        resize();

        // Initialize first page
        if (wbPages.length === 0) {
            wbPages.push(null); // First page placeholder
            updatePageIndicator();
        }

        wbCanvas.onmousedown = (e) => {
            if (wbTool === 'laser') return;
            if (wbTool === 'text') { drawText(e.offsetX, e.offsetY); return; }

            saveState(); // Record state BEFORE action
            isDrawing = true;
            [startX, startY] = [e.offsetX, e.offsetY];
            [lastX, lastY] = [e.offsetX, e.offsetY];
            wbSnapshot = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
        };

        wbCanvas.onmousemove = (e) => {
            // Handle Laser Pointer
            const laser = document.getElementById('laserCursor');
            if (wbTool === 'laser') {
                laser.style.display = 'block';
                laser.style.left = (e.clientX - 6) + 'px';
                laser.style.top = (e.clientY - 6) + 'px';

                // Store laser position relative to canvas for streaming
                laserX = e.offsetX;
                laserY = e.offsetY;
                laserActive = true;
            } else {
                laser.style.display = 'none';
                laserActive = false;
            }

            if (!isDrawing) return;

            if (wbTool === 'pencil' || wbTool === 'eraser') {
                drawFreehand(e.offsetX, e.offsetY);
            } else if (wbTool !== 'text' && wbTool !== 'laser') {
                wbCtx.putImageData(wbSnapshot, 0, 0);
                drawShape(e.offsetX, e.offsetY);
            }
        };

        wbCanvas.onmouseup = () => { isDrawing = false; wbSnapshot = null; };
        wbCanvas.onmouseout = () => {
            isDrawing = false;
            laserCursor.style.display = 'none';
            laserActive = false; // Hide laser when mouse leaves canvas
        };
    }

    function drawBackground() {
        if (!wbCanvas || !wbCtx) {
            console.warn("drawBackground: Canvas not ready yet");
            return;
        }

        console.log("drawBackground called with type:", wbBgType, "canvas size:", wbCanvas.width, "x", wbCanvas.height);

        // Clear everything first
        wbCtx.fillStyle = '#ffffff';
        wbCtx.fillRect(0, 0, wbCanvas.width, wbCanvas.height);

        if (wbBgType === 'plain') return;

        wbCtx.beginPath();
        wbCtx.strokeStyle = '#94a3b8'; // More visible gray color
        wbCtx.lineWidth = 1;

        if (wbBgType === 'grid') {
            const step = 30;
            for (let x = step; x < wbCanvas.width; x += step) {
                wbCtx.moveTo(x, 0);
                wbCtx.lineTo(x, wbCanvas.height);
            }
            for (let y = step; y < wbCanvas.height; y += step) {
                wbCtx.moveTo(0, y);
                wbCtx.lineTo(wbCanvas.width, y);
            }
            console.log("Grid drawn:", Math.floor(wbCanvas.width / step), "x", Math.floor(wbCanvas.height / step), "lines");
        } else if (wbBgType === 'lines') {
            const step = 25;
            for (let y = step; y < wbCanvas.height; y += step) {
                wbCtx.moveTo(0, y);
                wbCtx.lineTo(wbCanvas.width, y);
            }
            console.log("Lines drawn:", Math.floor(wbCanvas.height / step), "horizontal lines");
        }
        wbCtx.stroke();
    }

    function setWBBackground(type) {
        wbBgType = type;
        document.querySelectorAll('[id^="bg"]').forEach(b => b.classList.remove('active'));
        document.getElementById('bg' + type.charAt(0).toUpperCase() + type.slice(1)).classList.add('active');

        // Apply background via CSS - doesn't affect canvas content
        const container = wbCanvas ? wbCanvas.parentElement : document.getElementById('whiteboardOverlay');
        const canvasEl = wbCanvas || document.getElementById('wbCanvasInternal');

        if (type === 'grid') {
            // Create grid pattern
            canvasEl.style.backgroundImage = `
                linear-gradient(#94a3b8 1px, transparent 1px),
                linear-gradient(90deg, #94a3b8 1px, transparent 1px)
            `;
            canvasEl.style.backgroundSize = '30px 30px';
            canvasEl.style.backgroundColor = 'white';
        } else if (type === 'lines') {
            // Create horizontal lines pattern
            canvasEl.style.backgroundImage = 'linear-gradient(#94a3b8 1px, transparent 1px)';
            canvasEl.style.backgroundSize = '100% 25px';
            canvasEl.style.backgroundColor = 'white';
        } else {
            // Plain white
            canvasEl.style.backgroundImage = 'none';
            canvasEl.style.backgroundColor = 'white';
        }

        const bgLabels = { 'plain': 'Ağ Fon', 'grid': 'Riyaziyyat (Dama)', 'lines': 'Dil (Xətli)' };
        LOG("📋 Fon dəyişdirildi: " + bgLabels[type], "#3b82f6");
    }

    function drawText(x, y) {
        const text = prompt("Mətn daxil edin:");
        if (text) {
            saveState();
            wbCtx.font = "24px 'Inter', sans-serif";
            wbCtx.fillStyle = wbColor;
            wbCtx.fillText(text, x, y);
        }
    }

    // Image placement variables
    var placementImg = null;
    var isDraggingImage = false;
    var isResizingImage = false;
    var imgDragStartX = 0;
    var imgDragStartY = 0;
    var imgStartLeft = 0;
    var imgStartTop = 0;
    var imgStartWidth = 0;
    var imgStartHeight = 0;
    var imgAspectRatio = 1;

    function wbUploadImage(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            LOG("🖼️ Şəkil yüklənir: " + file.name, "#3b82f6");

            const reader = new FileReader();
            reader.onload = (e) => {
                placementImg = new Image();
                placementImg.onload = () => {
                    showImagePlacement(placementImg);
                };
                placementImg.onerror = () => {
                    LOG("❌ Şəkil yüklənə bilmədi!", "#ef4444");
                };
                placementImg.src = e.target.result;
            };
            reader.onerror = () => {
                LOG("❌ Fayl oxuna bilmədi!", "#ef4444");
            };
            reader.readAsDataURL(file);

            // Reset input so same file can be selected again
            input.value = '';
        }
    }

    function showImagePlacement(img) {
        const overlay = document.getElementById('imagePlacementOverlay');
        const container = document.getElementById('imagePlacementContainer');
        const imgEl = document.getElementById('placementImage');

        imgEl.src = img.src;
        imgAspectRatio = img.width / img.height;

        // Calculate initial size (50% of canvas)
        const maxW = wbCanvas.width * 0.5;
        const maxH = wbCanvas.height * 0.5;
        let w, h;

        if (img.width / img.height > maxW / maxH) {
            w = maxW;
            h = w / imgAspectRatio;
        } else {
            h = maxH;
            w = h * imgAspectRatio;
        }

        // Center the image
        const left = (wbCanvas.width - w) / 2;
        const top = (wbCanvas.height - h) / 2;

        container.style.left = left + 'px';
        container.style.top = top + 'px';
        container.style.width = w + 'px';
        container.style.height = h + 'px';

        overlay.style.display = 'block';

        // Setup drag events
        container.onmousedown = startImageDrag;
        document.getElementById('resizeHandle').onmousedown = startImageResize;
    }

    function startImageDrag(e) {
        if (e.target.id === 'resizeHandle') return;
        e.preventDefault();
        isDraggingImage = true;
        const container = document.getElementById('imagePlacementContainer');
        imgDragStartX = e.clientX;
        imgDragStartY = e.clientY;
        imgStartLeft = parseInt(container.style.left);
        imgStartTop = parseInt(container.style.top);

        document.onmousemove = dragImage;
        document.onmouseup = stopImageDrag;
    }

    function dragImage(e) {
        if (!isDraggingImage) return;
        const container = document.getElementById('imagePlacementContainer');
        const dx = e.clientX - imgDragStartX;
        const dy = e.clientY - imgDragStartY;
        container.style.left = (imgStartLeft + dx) + 'px';
        container.style.top = (imgStartTop + dy) + 'px';
    }

    function stopImageDrag() {
        isDraggingImage = false;
        document.onmousemove = null;
        document.onmouseup = null;
    }

    function startImageResize(e) {
        e.preventDefault();
        e.stopPropagation();
        isResizingImage = true;
        const container = document.getElementById('imagePlacementContainer');
        imgDragStartX = e.clientX;
        imgDragStartY = e.clientY;
        imgStartWidth = parseInt(container.style.width);
        imgStartHeight = parseInt(container.style.height);

        document.onmousemove = resizeImage;
        document.onmouseup = stopImageResize;
    }

    function resizeImage(e) {
        if (!isResizingImage) return;
        const container = document.getElementById('imagePlacementContainer');
        const dx = e.clientX - imgDragStartX;

        // Maintain aspect ratio
        let newW = Math.max(50, imgStartWidth + dx);
        let newH = newW / imgAspectRatio;

        container.style.width = newW + 'px';
        container.style.height = newH + 'px';
    }

    function stopImageResize() {
        isResizingImage = false;
        document.onmousemove = null;
        document.onmouseup = null;
    }

    function confirmImagePlacement() {
        const container = document.getElementById('imagePlacementContainer');
        const overlay = document.getElementById('imagePlacementOverlay');

        const x = parseInt(container.style.left);
        const y = parseInt(container.style.top);
        const w = parseInt(container.style.width);
        const h = parseInt(container.style.height);

        saveState();
        wbCtx.drawImage(placementImg, x, y, w, h);

        overlay.style.display = 'none';
        placementImg = null;

        LOG("✅ Şəkil yerləşdirildi: " + w + "x" + h + "px", "#10b981");
    }

    function cancelImagePlacement() {
        document.getElementById('imagePlacementOverlay').style.display = 'none';
        placementImg = null;
        LOG("❌ Şəkil yerləşdirmə ləğv edildi", "#f59e0b");
    }

    function drawFreehand(currX, currY) {
        wbCtx.beginPath();
        wbCtx.moveTo(lastX, lastY);
        wbCtx.lineTo(currX, currY);

        if (wbTool === 'eraser') {
            // Use destination-out to make pixels transparent
            // This way the CSS background (grid/lines) shows through
            wbCtx.globalCompositeOperation = 'destination-out';
            wbCtx.strokeStyle = 'rgba(0,0,0,1)';
            wbCtx.lineWidth = eraserSize;
        } else {
            wbCtx.globalCompositeOperation = 'source-over';
            wbCtx.strokeStyle = wbColor;
            wbCtx.lineWidth = pencilSize;
        }

        wbCtx.lineCap = 'round';
        wbCtx.lineJoin = 'round';
        wbCtx.stroke();

        // Reset composite operation for other tools
        wbCtx.globalCompositeOperation = 'source-over';

        [lastX, lastY] = [currX, currY];
    }

    function drawShape(currX, currY) {
        wbCtx.beginPath();
        wbCtx.strokeStyle = wbColor;
        wbCtx.lineWidth = 3;
        wbCtx.lineCap = 'round';

        if (wbTool === 'line') {
            wbCtx.moveTo(startX, startY);
            wbCtx.lineTo(currX, currY);
        } else if (wbTool === 'rect') {
            wbCtx.strokeRect(startX, startY, currX - startX, currY - startY);
        } else if (wbTool === 'circle') {
            let radius = Math.sqrt(Math.pow(currX - startX, 2) + Math.pow(currY - startY, 2));
            wbCtx.arc(startX, startY, radius, 0, 2 * Math.PI);
        } else if (wbTool === 'arrow') {
            drawArrow(startX, startY, currX, currY);
        }
        wbCtx.stroke();
    }

    function drawArrow(fromx, fromy, tox, toy) {
        const headlen = 15;
        const angle = Math.atan2(toy - fromy, tox - fromx);
        wbCtx.moveTo(fromx, fromy);
        wbCtx.lineTo(tox, toy);
        wbCtx.lineTo(tox - headlen * Math.cos(angle - Math.PI / 6), toy - headlen * Math.sin(angle - Math.PI / 6));
        wbCtx.moveTo(tox, toy);
        wbCtx.lineTo(tox - headlen * Math.cos(angle + Math.PI / 6), toy - headlen * Math.sin(angle + Math.PI / 6));
    }

    function setWBTool(tool) {
        wbTool = tool;
        document.querySelectorAll('.wb-tool-btn').forEach(btn => btn.classList.remove('active'));
        const btnId = 'tool' + tool.charAt(0).toUpperCase() + tool.slice(1);
        const btn = document.getElementById(btnId);
        if (btn) btn.classList.add('active');

        // Show/hide size control based on tool
        const sizeControl = document.getElementById('sizeControl');
        if (sizeControl) {
            if (tool === 'eraser' || tool === 'pencil') {
                sizeControl.style.display = 'flex';
                updateSizeDisplay();
            } else {
                sizeControl.style.display = 'none';
            }
        }
    }

    function changeSize(delta) {
        if (wbTool === 'eraser') {
            eraserSize = Math.max(10, Math.min(100, eraserSize + delta));
        } else {
            pencilSize = Math.max(1, Math.min(20, pencilSize + delta));
        }
        updateSizeDisplay();
    }

    function updateSizeDisplay() {
        const display = document.getElementById('sizeDisplay');
        if (display) {
            const size = wbTool === 'eraser' ? eraserSize : pencilSize;
            display.innerText = size + 'px';
        }
    }

    function setWBColor(color, el) {
        wbColor = color;
        document.querySelectorAll('.wb_color_item').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
    }

    function openColorPicker() {
        document.getElementById('customColorPicker').click();
    }

    function setCustomColor(color) {
        wbColor = color;
        document.querySelectorAll('.wb_color_item').forEach(c => c.classList.remove('active'));
        // Highlight the custom color button
        const customBtn = document.querySelector('.wb_color_btn');
        if (customBtn) {
            customBtn.style.background = color;
            customBtn.classList.add('active');
        }
        LOG("🎨 Xüsusi rəng seçildi: " + color, color);
    }

    function clearWhiteboard() {
        if (confirm("Lövhə təmizlənsin?")) {
            saveState();
            // Clear canvas to transparent - CSS background will show through
            wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
            LOG("🧹 Lövhə təmizləndi.", "#f59e0b");
        }
    }

    function exportWhiteboard() {
        const link = document.createElement('a');
        link.download = `Whiteboard_Arxiv_${lID}_${Date.now()}.png`;
        link.href = wbCanvas.toDataURL("image/png");
        link.click();
        LOG("📸 Lövhə şəkli yadda saxlanıldı (Arxiv).", "#10b981");
    }

    // Compositing (Canvas)
    let mediaRecorder;
    let recordedChunks = [];
    let canvas, ctx;
    let canvasLoopId;
    let destStream;
    let chunkFlushInterval = null;
    let isFlushingChunks = false;
    let isFirstChunkSent = false;
    const recordingSessionId = "sess_" + Math.random().toString(36).substr(2, 9);

    // ============================================================
    // Periodic Chunk Flush — hər 30 saniyədən bir parçaları serverə göndər
    // Refresh zamanı recording itirilməsin deyə
    // ============================================================
    function flushChunksToServer() {
        if (isFlushingChunks || recordedChunks.length === 0) return Promise.resolve();

        // Kiçik parçaları yığmaq üçün limit (məsələn 10KB-dan azdırsa gözləsin)
        const currentSize = recordedChunks.reduce((acc, c) => acc + c.size, 0);
        if (currentSize < 10240 && !mediaRecorder.state === 'inactive') return Promise.resolve();

        isFlushingChunks = true;
        const chunksToSend = recordedChunks.slice();
        recordedChunks = [];

        const blob = new Blob(chunksToSend, { type: 'video/webm' });
        const fd = new FormData();
        fd.append('lesson_id', lID);
        fd.append('video_blob', blob);
        fd.append('session_id', recordingSessionId);

        // Dinamik URL təyini
        const chunkUrl = window.location.pathname.includes('/teacher/') ? '../api/live/upload_chunk.php' : '/api/live/upload_chunk.php';

        return fetch(chunkUrl, { method: 'POST', body: fd })
            .then(async r => {
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error("Server cavabı JSON deyil: " + text.substring(0, 100));
                }
            })
            .then(data => {
                if (data.success) {
                    LOG(`💾 Video parçası serverə yazıldı (${(blob.size / 1024).toFixed(0)} KB)`, "#10b981");
                } else {
                    recordedChunks = chunksToSend.concat(recordedChunks);
                    LOG(`⚠️ Parça yazılmadı: ${data.message}`, "#f59e0b");
                    console.error('Server error upload_chunk:', data);
                }
                isFlushingChunks = false;
            })
            .catch(err => {
                recordedChunks = chunksToSend.concat(recordedChunks);
                isFlushingChunks = false;
                LOG(`❌ Bağlantı xətası (Chunk): ${err.message}`, "#ef4444");
                console.error('Chunk flush error:', err);
            });
    }

    function startProductionNow() {
        const overlay = document.getElementById('startProductionOverlay');

        // 1. Request Fullscreen
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen().catch(e => {
                LOG("⚠️ Tam ekran rejimi aktivləşdirilə bilmədi.", "#f59e0b");
            });
        }

        // 2. Hide Overlay
        overlay.style.transition = 'opacity 0.5s';
        overlay.style.opacity = '0';
        setTimeout(() => overlay.style.display = 'none', 500);

        // 3. Initialize Media if not already done or restart
        init();

        LOG("🎬 Yayım və tam ekran rejimi başladıldı.", "#10b981");

        // 4. Monitoring Fullscreen Exit
        document.onfullscreenchange = () => {
            if (!document.fullscreenElement) {
                LOG("⚠️ Diqqət: Tam ekran rejimi dayandırıldı!", "#ef4444");
            }
        };
    }

    function flushChunksBeacon() {
        if (recordedChunks.length === 0) return;
        const blob = new Blob(recordedChunks, { type: 'video/webm' });
        const fd = new FormData();
        fd.append('lesson_id', lID);
        fd.append('video_blob', blob);
        navigator.sendBeacon('../api/live/upload_chunk.php', fd);
        recordedChunks = [];
    }

    // CHAT & FILE
    function handleFileSelect(input) {
        const file = input.files[0];
        if (!file) return;

        // 5MB Limit Check
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            LOG("⚠️ Fayl çox böyükdür! Maksimum limit 5MB-dır.", "#ef4444");
            alert("Xəta: Faylın ölçüsü 5MB-dan çox ola bilməz.");
            input.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        const xhr = new XMLHttpRequest();
        const progressId = "up-" + Date.now();
        LOG(`<div id="${progressId}" style="display:inline-block;">📤 Fayl yüklənir: 0%</div>`, "#3b82f6");

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                const el = document.getElementById(progressId);
                if (el) el.innerHTML = `📤 Fayl yüklənir: ${percent}%`;
            }
        };

        xhr.onload = () => {
            const el = document.getElementById(progressId);
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success) {
                    if (el) el.innerHTML = `✅ Yükləndi: ${file.name}`;
                    const msgObj = { type: 'file', fileData: data.url, fileName: data.fileName, sender: 'Müəllim' };
                    broadcastData(msgObj);
                    appendFileMessage('Mən', data.fileName, data.url, '#3b82f6');
                } else {
                    if (el) el.innerHTML = `❌ Xəta: ${data.message}`;
                    LOG("Yükləmə xətası: " + data.message, "#ef4444");
                }
            } catch (e) {
                if (el) el.innerHTML = `❌ Server xətası`;
            }
        };

        xhr.onerror = () => {
            const el = document.getElementById(progressId);
            if (el) el.innerHTML = `❌ Bağlantı xətası`;
        };

        xhr.open('POST', '../api/upload_chat_file.php');
        xhr.send(formData);
        input.value = '';
    }

    let privateTarget = null; // { peer: '...', name: '...' }

    function setPrivateTarget(peerId, name) {
        privateTarget = { peer: peerId, name: name };
        document.getElementById('targetName').innerText = name;
        document.getElementById('privateTargetIndicator').style.display = 'flex';
        document.getElementById('chatInput').placeholder = `${name} üçün özəl mesaj...`;
        document.getElementById('chatInput').focus();
    }

    function clearPrivateTarget() {
        privateTarget = null;
        document.getElementById('privateTargetIndicator').style.display = 'none';
        document.getElementById('chatInput').placeholder = "Mesaj yazın...";
    }

    function sendChatMessage() {
        const input = document.getElementById('chatInput');
        const msg = input.value.trim();
        if (!msg) return;

        if (privateTarget) {
            const conn = allDataConns.find(c => c.peer === privateTarget.peer);
            if (conn && conn.open) {
                const msgObj = { type: 'chat', message: msg, sender: 'Müəllim (Özəl)', isPrivate: true };
                conn.send(msgObj);
                appendChatMessage(`Mən -> ${privateTarget.name}`, msg, '#3b82f6');
                LOG(`🔒 ${privateTarget.name} üçün özəl mesaj göndərildi.`, "#3b82f6");
            } else {
                LOG("⚠️ Seçilmiş tələbə artıq canlı dərsi tərk edib.", "#ef4444");
                clearPrivateTarget();
            }
        } else {
            const msgObj = { type: 'chat', message: msg, sender: 'Müəllim' };
            broadcastData(msgObj);
            appendChatMessage('Mən', msg, '#3b82f6');
        }
        input.value = '';
    }

    function broadcastData(data, excludePeerId = null) {
        allDataConns.forEach(conn => { if (conn.open && conn.peer !== excludePeerId) conn.send(data); });
    }

    function showMicRequestModal(senderName, conn) {
        const modal = document.getElementById('micRequestModal');
        document.getElementById('micRequestTitle').textContent = `${senderName} söz istəyir`;
        modal.style.display = 'flex';

        const aprove = document.getElementById('micApproveBtn');
        const reject = document.getElementById('micRejectBtn');

        const cleanup = () => { modal.style.display = 'none'; aprove.onclick = null; reject.onclick = null; };

        aprove.onclick = () => { conn.send({ type: 'mic_approved' }); LOG(`${senderName} mikrofonu açıldı.`, "#22c55e"); cleanup(); };
        reject.onclick = () => { conn.send({ type: 'mic_rejected' }); LOG(`${senderName} rədd edildi.`, "#fde047"); cleanup(); };
    }

    function appendFileMessage(sender, fileName, fileData, color = "#fff") {
        const box = document.getElementById('chatMessages');
        if (box.innerText.includes('Hələ ki')) box.innerHTML = '';
        box.innerHTML += `<div style="margin-bottom: 12px; line-height: 1.4;"><strong style="color: ${color}; font-size: 11px; display: block; margin-bottom: 2px;">${sender}</strong><a href="${fileData}" target="_blank" style="color: #60a5fa; text-decoration: none; padding: 5px 10px; background: rgba(255,255,255,0.05); border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">📎 ${fileName}</a></div>`;
        box.scrollTop = box.scrollHeight;
    }

    function appendChatMessage(sender, msg, color = "#fff") {
        const box = document.getElementById('chatMessages');
        if (box.innerText.includes('Hələ ki')) box.innerHTML = '';
        box.innerHTML += `<div style="margin-bottom: 12px; line-height: 1.4;"><strong style="color: ${color}; font-size: 11px; display: block; margin-bottom: 2px;">${sender}</strong><span style="color: #e2e8f0;">${msg}</span></div>`;
        box.scrollTop = box.scrollHeight;
    }

    const iceServers = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
            { urls: 'stun:stun2.l.google.com:19302' },
            { urls: 'stun:stun3.l.google.com:19302' },
            { urls: 'stun:stun4.l.google.com:19302' },
            {
                urls: 'turn:openrelay.metered.ca:80',
                username: 'openrelayproject',
                credential: 'openrelayproject'
            },
            {
                urls: 'turn:openrelay.metered.ca:443',
                username: 'openrelayproject',
                credential: 'openrelayproject'
            },
            {
                urls: 'turn:openrelay.metered.ca:443?transport=tcp',
                username: 'openrelayproject',
                credential: 'openrelayproject'
            }
        ],
        sdpSemantics: 'unified-plan',
        iceCandidatePoolSize: 0
    };

    const peerConfig = {
        debug: 1,
        config: iceServers,
        host: window.location.hostname,
        port: 9000,
        secure: false,
        path: '/myapp'
    };

    async function init() {
        try {
            LOG("Sistem yoxlanılır...", "#3b82f6");

            // 1. Secure Context Warning
            if (!window.isSecureContext && window.location.hostname !== 'localhost') {
                LOG("⚠️ HTTPS TƏLƏB OLUNUR: Kamera/Mikrofon bloklana bilər!", "#ef4444");
                alert("DİQQƏT: Təhlükəsiz bağlantı (HTTPS) yoxdur. Kamera və mikrofon brauzer tərəfindən bloklana bilər.");
            }

            // 2. Try Media Access
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                LOG("Media cihazları yoxlanılır...", "#3b82f6");
                const tryGetMedia = async (constraints) => {
                    try { return await navigator.mediaDevices.getUserMedia(constraints); } catch (e) { return null; }
                };

                // HD -> SD -> Any
                camStream = await tryGetMedia({ video: { width: { ideal: 1280 }, height: { ideal: 720 } }, audio: { echoCancellation: true } });
                if (!camStream) camStream = await tryGetMedia({ video: true, audio: { echoCancellation: true } });

                if (camStream) {
                    const camVid = document.getElementById('camSource');
                    camVid.srcObject = camStream;
                    camVid.play().catch(e => LOG("⚠️ Kamera avtomatik başlamadı. Ekrana klikləyin.", "#f59e0b"));
                    LOG("✅ Kamera aktivdir.", "#10b981");
                }
            }

            // 3. Fallback to dummy if failed
            if (!camStream) {
                LOG("⚠️ Kamera tapılmadı. Görüntüsüz davam edilir.", "#f59e0b");
                const dummyCanvas = document.createElement('canvas');
                dummyCanvas.width = 640; dummyCanvas.height = 480;
                const dummyCtx = dummyCanvas.getContext('2d');
                dummyCtx.fillStyle = "black"; dummyCtx.fillRect(0, 0, 640, 480);
                camStream = dummyCanvas.captureStream();
                try {
                    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = audioCtx.createOscillator();
                    const dst = osc.connect(audioCtx.createMediaStreamDestination());
                    osc.start();
                    camStream.addTrack(dst.stream.getAudioTracks()[0]);
                } catch (e) { }
                document.getElementById('camSource').srcObject = camStream;
            }

            function showWhiteboardRequestModal(name, conn) {
                document.getElementById('wbRequesterName').innerText = name;
                const modal = document.getElementById('wbRequestModal');
                modal.style.display = 'flex';

                document.getElementById('wbApproveBtn').onclick = () => {
                    conn.send({ type: 'whiteboard_approved' });
                    modal.style.display = 'none';
                };
                document.getElementById('wbRejectBtn').onclick = () => {
                    conn.send({ type: 'whiteboard_rejected' });
                    modal.style.display = 'none';
                };
            }

            // 4. Start Production
            startCanvasCompositing();
            stream = destStream;
            document.getElementById('localVid').srcObject = stream;

            // 5. Connection Setup
            let sessionUnique = Math.floor(Math.random() * 100000);
            let uniqueID = 'ndu-live-' + lID + '-' + sessionUnique;

            function startPeer(useCloud = false) {
                const config = useCloud ? {
                    debug: 1, host: '0.peerjs.com', port: 443, secure: true, config: iceServers
                } : peerConfig;

                if (peer) peer.destroy();
                peer = new Peer(uniqueID, config);

                peer.on('open', (id) => {
                    LOG(useCloud ? "🚀 Bulud serverinə qoşuldu!" : "🚀 Lokal server hazır!", "#10b981");
                    const serverType = useCloud ? 'cloud' : 'local';
                    fetch(`api/update_peer_id.php?live_class_id=${lID}&peer_id=${id}&server=${serverType}&t=${Date.now()}`)
                        .then(r => r.json()).catch(e => console.error("DB Update Error"));
                    trackAttendance('join');
                });

                peer.on('error', (err) => {
                    LOG("⚠️ Peer xətası: " + err.type, "#f59e0b");
                    if (err.type === 'id-taken') {
                        sessionUnique = Math.floor(Math.random() * 1000000);
                        uniqueID = 'ndu-live-' + lID + '-' + sessionUnique;
                        setTimeout(() => startPeer(useCloud), 1000);
                    } else if (!useCloud && (['socket-error', 'network', 'server-error'].includes(err.type))) {
                        LOG("⚠️ Buluda keçid edilir...", "#f59e0b");
                        setTimeout(() => startPeer(true), 1500);
                    }
                });

                peer.on('connection', (conn) => {
                    if (conn.metadata && conn.metadata.type === 'rejoin_request') {
                        showRejoinRequestModal(conn.metadata.name, conn, conn.metadata.userId);
                        return;
                    }
                    allDataConns.push(conn);
                    conn.on('data', (d) => {
                        if (d.type === 'chat' || d.type === 'file') {
                            const isPrivate = d.isPrivate || false;
                            const color = isPrivate ? '#3b82f6' : '#10b981';
                            if (d.type === 'chat') {
                                appendChatMessage(d.sender, d.message, color);
                                if (!isPrivate) broadcastData(d, conn.peer);
                            } else {
                                appendFileMessage(d.sender, d.fileName, d.fileData, color);
                                broadcastData(d, conn.peer);
                            }
                        } else if (d.type === 'mic_request') {
                            showMicRequestModal(d.sender, conn);
                        } else if (d.type === 'whiteboard_request') {
                            showWhiteboardRequestModal(d.sender, conn);
                        } else if (d.type === 'whiteboard_ended') {
                            if (conn.peer === spotlightPeerId) stopStudentSpotlight();
                        } else if (d.type === 'screen_share_request') {
                            showScreenShareRequestModal(d.sender, conn);
                        } else if (d.type === 'screen_share_ended') {
                            if (conn.peer === spotlightPeerId) stopStudentSpotlight();
                        }
                    });
                });

                peer.on('call', (call) => {
                    const meta = call.metadata || {};
                    const name = meta.name || "Tələbə";
                    const sid = meta.userId || meta.id || meta.student_id;
                    const isScreenShare = meta.type === 'screen_share';

                    // Force stop spotlight if student calling again with normal stream
                    if (!isScreenShare && call.peer === spotlightPeerId) {
                        stopStudentSpotlight();
                    }

                    if (stream) {
                        const vtr = stream.getVideoTracks().length;
                        const atr = stream.getAudioTracks().length;
                        LOG(`📡 Zəng cavablandırılır: ${vtr} video, ${atr} audio track göndərilir.`);
                    } else {
                        LOG("⚠️ DİQQƏT: Zəngə cavab verilərkən stream tapılmadı!", "#ef4444");
                    }
                    call.answer(stream);

                    if (sid) {
                        const existing = document.querySelector(`.student-card[data-student-id='${sid}']`);
                        if (existing && !isScreenShare) {
                            const oldId = existing.getAttribute('data-peer-id');
                            if (oldId && oldId !== call.peer) {
                                const idx = allDataConns.findIndex(c => c.peer === oldId);
                                if (idx > -1) { allDataConns[idx].close(); allDataConns.splice(idx, 1); }
                                removeStudentVideo(oldId);
                            }
                        }
                    }

                    call.on('stream', (rem) => {
                        if (isScreenShare) {
                            LOG(`🖥️ ${name} ekran paylaşımına başladı.`, "#3b82f6");
                            startStudentSpotlight(call.peer, rem, name);
                        } else {
                            addStudentVideo(call.peer, rem, name, sid);
                        }
                    });
                    call.on('close', () => {
                        if (isScreenShare) {
                            if (call.peer === spotlightPeerId) stopStudentSpotlight();
                            LOG(`${name} ekran paylaşımını bitirdi.`, "#3b82f6");
                        } else {
                            removeStudentVideo(call.peer);
                            LOG(`${name} yayımı dayandırdı.`, "#f59e0b");
                        }
                    });
                });
            }

            startPeer(true); // Default to cloud for stability
            startLessonTimer();

        } catch (e) {
            LOG("❌ Studiya Xətası: " + e.message, "#ef4444");
            console.error(e);
        }
    }

    function startCanvasCompositing() {
        canvas = document.createElement('canvas');
        canvas.width = 1280; canvas.height = 720;
        ctx = canvas.getContext('2d');
        drawToCanvas();

        const canvasStream = canvas.captureStream(30);
        // Add audio track from camera stream
        if (camStream && camStream.getAudioTracks().length > 0) {
            canvasStream.addTrack(camStream.getAudioTracks()[0]);
        }
        destStream = canvasStream;

        const types = ['video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4'];
        let supportedType = '';
        for (let t of types) { if (MediaRecorder.isTypeSupported(t)) { supportedType = t; break; } }

        try {
            mediaRecorder = new MediaRecorder(destStream, { mimeType: supportedType });

            mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) {
                    recordedChunks.push(e.data);

                    // İlk parçanı (WebM Header) dərhal göndər
                    if (!isFirstChunkSent) {
                        isFirstChunkSent = true;
                        setTimeout(flushChunksToServer, 100);
                    }

                    // Əgər recordedChunks həcmi 1MB-dan çoxdursa, dərhal göndər
                    const currentSize = recordedChunks.reduce((acc, c) => acc + c.size, 0);
                    if (currentSize > 1024 * 1024) {
                        flushChunksToServer();
                    }
                }
            };

            mediaRecorder.start(1000);
            LOG(`Arxiv qeydiyyatı aktivdir. 🔴`, "#ef4444");

            // Start the rendering loop only after canvas/ctx are ready
            if (canvasLoopId) clearInterval(canvasLoopId);
            canvasLoopId = setInterval(drawToCanvas, 33);
        } catch (e) { LOG("MediaRecorder Xətası", "#ef4444"); }
    }

    function drawToCanvas() {
        // --- MODE: STUDENT SPOTLIGHT (Priority) ---
        if (isStudentSpotlight && studentScreenVidElement && studentScreenVidElement.readyState >= 2) {
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            const sW = studentScreenVidElement.videoWidth;
            const sH = studentScreenVidElement.videoHeight;
            const sRatio = sW / sH;
            const canvasRatio = canvas.width / canvas.height;

            let dW, dH, dx, dy;
            if (sRatio > canvasRatio) {
                dW = canvas.width;
                dH = canvas.width / sRatio;
                dx = 0;
                dy = (canvas.height - dH) / 2;
            } else {
                dH = canvas.height;
                dW = canvas.height * sRatio;
                dx = (canvas.width - dW) / 2;
                dy = 0;
            }

            ctx.save();
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(studentScreenVidElement, dx, dy, dW, dH);
            ctx.restore();

            // Draw student's name badge
            const badgeText = `TƏLƏBƏ EKRANI: ${spotlightName.toUpperCase()}`;
            ctx.save();
            ctx.font = 'bold 14px Inter, sans-serif';
            const tw = ctx.measureText(badgeText).width;
            ctx.fillStyle = 'rgba(0,0,0,0.6)';
            ctx.fillRect(20, 20, tw + 30, 30);
            ctx.fillStyle = '#3b82f6';
            ctx.fillRect(20, 20, 4, 30);
            ctx.fillStyle = '#fff';
            ctx.fillText(badgeText, 35, 41);
            ctx.restore();

            // Draw sharer's camera in corner if available
            if (studentCamVidElement && studentCamVidElement.readyState >= 2) {
                const cW = studentCamVidElement.videoWidth;
                const cH = studentCamVidElement.videoHeight;
                const cRatio = cW / cH;
                const camDrawW = 220;
                const camDrawH = camDrawW / cRatio;

                ctx.save();
                ctx.shadowBlur = 20;
                ctx.shadowColor = 'rgba(0,0,0,0.5)';
                ctx.strokeStyle = '#3b82f6';
                ctx.lineWidth = 3;
                // Clip round rect
                const cx = canvas.width - camDrawW - 30;
                const cy = canvas.height - camDrawH - 30;
                if (ctx.roundRect) {
                    ctx.beginPath();
                    ctx.roundRect(cx, cy, camDrawW, camDrawH, 12);
                    ctx.stroke();
                    ctx.clip();
                }
                ctx.drawImage(studentCamVidElement, cx, cy, camDrawW, camDrawH);
                ctx.restore();
            }

            // Draw "Stop Spotlight" button indicator for teacher (UI only, composite gets a badge)
            return;
        }

        const camVid = document.getElementById('camSource');
        const screenVid = document.getElementById('screenSource');
        const camTrack = camStream ? camStream.getVideoTracks()[0] : null;

        // Diagnostic log: Only log if camera is expected but not ready
        if (camTrack && camTrack.enabled && (!camVid || camVid.readyState < 2)) {
            if (!window.lastCamWarn || Date.now() - window.lastCamWarn > 5000) {
                LOG("⚠️ Kamera hazır deyil (State: " + (camVid ? camVid.readyState : 'null') + "). Gözlənilir...", "#f59e0b");
                window.lastCamWarn = Date.now();
            }
        }

        const isCamActive = camVid && camVid.readyState >= 2 && camTrack && camTrack.enabled;

        // 1. Fill Background
        ctx.fillStyle = '#0f172a';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // 1.5 WHITEBOARD MODE: 
        if (isWhiteboardActive && wbCanvas) {
            // First draw white background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Draw grid or lines pattern for stream (CSS background doesn't transfer to video)
            if (wbBgType === 'grid') {
                ctx.strokeStyle = '#94a3b8';
                ctx.lineWidth = 1;
                ctx.beginPath();
                const step = 30 * (canvas.width / wbCanvas.width); // Scale step size
                for (let x = step; x < canvas.width; x += step) {
                    ctx.moveTo(x, 0);
                    ctx.lineTo(x, canvas.height);
                }
                for (let y = step; y < canvas.height; y += step) {
                    ctx.moveTo(0, y);
                    ctx.lineTo(canvas.width, y);
                }
                ctx.stroke();
            } else if (wbBgType === 'lines') {
                ctx.strokeStyle = '#94a3b8';
                ctx.lineWidth = 1;
                ctx.beginPath();
                const step = 25 * (canvas.height / wbCanvas.height);
                for (let y = step; y < canvas.height; y += step) {
                    ctx.moveTo(0, y);
                    ctx.lineTo(canvas.width, y);
                }
                ctx.stroke();
            }

            // Draw whiteboard canvas content on top of background
            ctx.drawImage(wbCanvas, 0, 0, canvas.width, canvas.height);

            // Draw laser pointer on stream for students to see
            if (laserActive && wbTool === 'laser') {
                // Calculate scaled position
                const scaleX = canvas.width / wbCanvas.width;
                const scaleY = canvas.height / wbCanvas.height;
                const scaledLaserX = laserX * scaleX;
                const scaledLaserY = laserY * scaleY;

                // Draw glowing laser effect
                ctx.save();

                // Outer glow
                const gradient = ctx.createRadialGradient(scaledLaserX, scaledLaserY, 0, scaledLaserX, scaledLaserY, 25);
                gradient.addColorStop(0, 'rgba(239, 68, 68, 0.8)');
                gradient.addColorStop(0.3, 'rgba(239, 68, 68, 0.4)');
                gradient.addColorStop(1, 'rgba(239, 68, 68, 0)');
                ctx.fillStyle = gradient;
                ctx.beginPath();
                ctx.arc(scaledLaserX, scaledLaserY, 25, 0, Math.PI * 2);
                ctx.fill();

                // Inner bright dot
                ctx.beginPath();
                ctx.arc(scaledLaserX, scaledLaserY, 8, 0, Math.PI * 2);
                ctx.fillStyle = '#ef4444';
                ctx.fill();
                ctx.strokeStyle = 'white';
                ctx.lineWidth = 2;
                ctx.stroke();

                ctx.restore();
            }

            // Draw small camera in corner during whiteboard
            if (isCamActive) {
                const cW = camVid.videoWidth;
                const cH = camVid.videoHeight;
                const cRatio = cW / cH;
                const camDrawW = 200;
                const camDrawH = camDrawW / cRatio;

                ctx.save();
                ctx.shadowBlur = 10;
                ctx.shadowColor = 'rgba(0,0,0,0.3)';
                ctx.drawImage(camVid, canvas.width - camDrawW - 20, canvas.height - camDrawH - 20, camDrawW, camDrawH);
                ctx.restore();
            }
            return;
        }

        const canvasRatio = canvas.width / canvas.height;

        // 2. SCREEN SHARING: Maximize Readability (80/20)
        if (isScreenSharing && screenVid && screenVid.readyState >= 2) {

            let screenSlotW, camSlotW;
            if (isCamActive) {
                // 80% for Screen to maximize text size
                screenSlotW = canvas.width * 0.82;
                camSlotW = canvas.width * 0.18;
            } else {
                screenSlotW = canvas.width;
                camSlotW = 0;
            }

            // --- DRAW SCREEN (Maximize Height) ---
            const sW = screenVid.videoWidth;
            const sH = screenVid.videoHeight;
            const sRatio = sW / sH;

            let dW, dH, dx, dy;
            // Fill height primarily to keep text large
            dH = canvas.height;
            dW = dH * sRatio;

            if (dW > screenSlotW) {
                dW = screenSlotW;
                dH = dW / sRatio;
            }

            dx = (screenSlotW - dW) / 2;
            dy = (canvas.height - dH) / 2;

            ctx.save();
            // Sharp rendering
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(screenVid, dx, dy, dW, dH);
            ctx.restore();

            // --- DRAW CAMERA (Top Align in Slot) ---
            if (isCamActive) {
                const cW = camVid.videoWidth;
                const cH = camVid.videoHeight;
                const cRatio = cW / cH;

                let cDrawW = camSlotW - 10;
                let cDrawH = cDrawW / cRatio;

                const cx = screenSlotW + (camSlotW - cDrawW) / 2;
                const cy = 20;
                const radius = 12;

                ctx.save();
                // Shadow
                ctx.shadowBlur = 15;
                ctx.shadowColor = 'rgba(0,0,0,0.5)';
                ctx.fillStyle = '#1e293b';
                ctx.beginPath();
                if (ctx.roundRect) ctx.roundRect(cx - 3, cy - 3, cDrawW + 6, cDrawH + 6, radius + 2);
                else ctx.rect(cx - 3, cy - 3, cDrawW + 6, cDrawH + 6);
                ctx.fill();

                // Clip Cam
                ctx.beginPath();
                if (ctx.roundRect) ctx.roundRect(cx, cy, cDrawW, cDrawH, radius);
                else ctx.rect(cx, cy, cDrawW, cDrawH);
                ctx.clip();
                ctx.drawImage(camVid, cx, cy, cDrawW, cDrawH);
                ctx.restore();

                // Compact Badge
                const badgeW = 70;
                const badgeH = 18;
                const bx = cx + (cDrawW / 2) - (badgeW / 2);
                const by = cy + cDrawH - 9;

                ctx.save();
                ctx.fillStyle = '#3b82f6';
                ctx.beginPath();
                if (ctx.roundRect) ctx.roundRect(bx, by, badgeW, badgeH, 4);
                else ctx.rect(bx, by, badgeW, badgeH);
                ctx.fill();
                ctx.fillStyle = '#fff';
                ctx.font = '900 9px Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('MÜƏLLİM', bx + (badgeW / 2), by + (badgeH / 2));
                ctx.restore();
            }

        }
        // 3. CAMERA ONLY: Full frame
        else if (camVid && camVid.readyState >= 2) {
            const cW = camVid.videoWidth;
            const cH = camVid.videoHeight;
            const cRatio = cW / cH;

            let dW, dH, dx, dy;
            if (cRatio > canvasRatio) {
                dW = canvas.width;
                dH = canvas.width / cRatio;
                dx = 0;
                dy = (canvas.height - dH) / 2;
            } else {
                dH = canvas.height;
                dW = canvas.height * cRatio;
                dx = (canvas.width - dW) / 2;
                dy = 0;
            }
            ctx.drawImage(camVid, dx, dy, dW, dH);
        }
    }

    const activeStudents = new Set();
    function addStudentVideo(peerId, stream, name, studentId = null) {
        const existingCard = document.getElementById('card-' + peerId);
        if (existingCard) {
            LOG(`${name} yayımı yeniləndi.`, "#3b82f6");
            const vid = document.getElementById('vid-' + peerId);
            if (vid) {
                vid.srcObject = stream;
                vid.play().catch(e => console.warn("Video play failed on update:", e));
            }
            return;
        }
        document.getElementById('waitingMsg').style.display = 'none';
        const grid = document.getElementById('studentsGrid');

        const card = document.createElement('div');
        card.id = 'card-' + peerId;
        card.className = 'student-card'; // Identify as student card
        if (studentId) card.setAttribute('data-student-id', studentId);
        else {
            // Fallback: Try to find studentId in the sidebar attendance list by peerId
            const sidebarAtt = document.querySelector(`[onclick*="${peerId}"]`);
            if (sidebarAtt) {
                // Extract uID from the onclick attribute if possible, or wait for refresh
                console.log("Fallback studentId discovery for:", peerId);
            }
        }
        card.setAttribute('data-peer-id', peerId);

        // CSS HACK: Use padding-top for aspect ratio to force correct height in Grid
        card.style.cssText = "background:#000; border-radius:12px; overflow:hidden; width:100%; padding-top:56.25%; position:relative; border:1px solid rgba(255,255,255,0.1); cursor:pointer; transition: 0.2s;";

        const vid = document.createElement('video');
        vid.id = 'vid-' + peerId;
        vid.autoplay = true;
        vid.playsInline = true;
        vid.muted = true; // Essential for autoplay
        // Absolute position to fill the padded area
        vid.style.cssText = "position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; background:#000;";
        vid.srcObject = stream;

        // Monitoring tracks
        const checkTracks = () => {
            const vTracks = stream.getVideoTracks();
            console.log(`Student ${name} (${peerId}) tracks at start:`, stream.getTracks().map(t => t.kind));
            if (vTracks.length === 0) {
                console.warn(`Student ${name} sent NO video tracks yet.`);
            } else {
                vTracks.forEach(t => {
                    t.onunmute = () => console.log(`Track unmuted for ${name}`);
                    t.onmute = () => console.warn(`Track muted for ${name}`);
                });
            }
        };
        checkTracks();

        // Handle late arriving tracks
        stream.onaddtrack = (e) => {
            console.log(`Late track arrived for ${name}:`, e.track.kind);
            vid.srcObject = stream; // Refresh
            vid.play().catch(() => { });
        };

        vid.onloadedmetadata = () => {
            vid.play().catch(err => console.warn("Student video play failed:", err));
        };

        // Name Badge
        const badge = document.createElement('div');
        badge.style.cssText = "position:absolute; bottom:8px; left:8px; background:rgba(0,0,0,0.7); color:white; padding:4px 10px; border-radius:6px; font-size:10px; font-weight:700; backdrop-filter:blur(4px); display:flex; align-items:center; gap:6px;";
        badge.innerHTML = `<span style="width:6px; height:6px; background:#10b981; border-radius:50%; box-shadow:0 0 6px #10b981;"></span>${name}`;

        // 3-Dot Menu Button
        const menuBtn = document.createElement('button');
        menuBtn.innerHTML = '⋮';
        menuBtn.title = "Tələbə Əməliyyatları";
        menuBtn.style.cssText = "position:absolute; top:8px; right:8px; background:rgba(0,0,0,0.5); border:none; border-radius:8px; width:28px; height:28px; font-size:16px; cursor:pointer; color:white; display:flex; align-items:center; justify-content:center; z-index:20; backdrop-filter:blur(4px); transition:0.2s;";

        // Create dropdown and append to BODY (fixed positioning)
        const dropdown = document.createElement('div');
        dropdown.className = 'student-dropdown';
        dropdown.id = 'dropdown-' + peerId;
        dropdown.style.cssText = "position:fixed; background:#1e293b; border:1px solid rgba(255,255,255,0.15); border-radius:12px; padding:8px 0; min-width:180px; z-index:99999; display:none; box-shadow:0 15px 40px rgba(0,0,0,0.8); backdrop-filter:blur(10px);";
        document.body.appendChild(dropdown);

        // Dropdown Items
        const createMenuItem = (icon, text, color, onClick) => {
            const item = document.createElement('button');
            item.innerHTML = `<span style="font-size:14px;">${icon}</span><span>${text}</span>`;
            item.style.cssText = `display:flex; align-items:center; gap:10px; width:100%; padding:12px 18px; background:none; border:none; color:${color}; font-size:13px; font-weight:600; cursor:pointer; text-align:left; transition:0.15s;`;
            item.onmouseenter = () => item.style.background = 'rgba(255,255,255,0.08)';
            item.onmouseleave = () => item.style.background = 'none';
            item.onclick = (e) => {
                e.stopPropagation();
                onClick();
                dropdown.style.display = 'none';
                dropdown.classList.remove('open');
                menuBtn.style.background = 'rgba(0,0,0,0.5)';
            };
            return item;
        };

        // Sound Toggle
        let isMuted = true;
        const soundItem = createMenuItem('🔇', 'Səsi Aç', '#10b981', () => {
            vid.muted = !vid.muted;
            isMuted = vid.muted;
            soundItem.innerHTML = vid.muted
                ? '<span style="font-size:14px;">🔇</span><span>Səsi Aç</span>'
                : '<span style="font-size:14px;">🔊</span><span>Səsi Bağla</span>';
            soundItem.querySelector('span:last-child').style.color = vid.muted ? '#10b981' : '#f59e0b';
        });

        // Spotlight
        const spotlightItem = createMenuItem('🎯', 'Böyüt (Spotlight)', '#3b82f6', () => {
            focusStudent(stream, name);
        });

        // Private Message
        const chatItem = createMenuItem('💬', 'Özəl Mesaj', '#8b5cf6', () => {
            setPrivateTarget(peerId, name);
        });

        // Divider
        const divider = document.createElement('div');
        divider.style.cssText = "height:1px; background:rgba(255,255,255,0.1); margin:8px 0;";

        // Kick
        const kickItem = createMenuItem('🚫', 'Dərsdən Uzaqlaşdır', '#ef4444', () => {
            kickStudent(peerId, name, studentId);
        });

        dropdown.append(soundItem, spotlightItem, chatItem, divider, kickItem);

        // Menu Toggle with fixed positioning
        menuBtn.onclick = (e) => {
            e.stopPropagation();

            // Close all other dropdowns
            document.querySelectorAll('.student-dropdown').forEach(d => {
                if (d !== dropdown) { d.style.display = 'none'; d.classList.remove('open'); }
            });

            const isOpen = dropdown.style.display === 'block';

            if (!isOpen) {
                // Calculate position based on button location
                const rect = menuBtn.getBoundingClientRect();
                dropdown.style.top = (rect.bottom + 5) + 'px';
                dropdown.style.left = (rect.left - 150) + 'px'; // Position to the left of button
                dropdown.style.display = 'block';
                dropdown.classList.add('open');
                menuBtn.style.background = 'rgba(59, 130, 246, 0.8)';
            } else {
                dropdown.style.display = 'none';
                dropdown.classList.remove('open');
                menuBtn.style.background = 'rgba(0,0,0,0.5)';
            }
        };

        menuBtn.onmouseenter = () => menuBtn.style.background = 'rgba(59, 130, 246, 0.8)';
        menuBtn.onmouseleave = () => { if (!dropdown.classList.contains('open')) menuBtn.style.background = 'rgba(0,0,0,0.5)'; };

        // Close dropdown when clicking anywhere
        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && e.target !== menuBtn) {
                dropdown.style.display = 'none';
                dropdown.classList.remove('open');
            }
        });

        card.append(vid, badge, menuBtn);
        grid.append(card);
        document.getElementById('activeVideoCount').innerText = grid.children.length - (document.getElementById('waitingMsg').style.display === 'none' ? 0 : 1);
    }

    function muteStudent(peerId, name) {
        if (confirm(`${name} adlı tələbənin səsini kəsmək istəyirsiniz?`)) {
            const conn = allDataConns.find(c => c.peer === peerId);
            if (conn) { conn.send({ type: 'mute_force' }); LOG(`${name} səsi kəsildi.`, "#ef4444"); }
        }
    }

    function refreshAllStudentVideos() {
        LOG("🔄 Bütün tələbə yayımları yenilənir...", "#fde047");
        allDataConns.forEach(conn => {
            if (conn.open) conn.send({ type: 'refresh_stream' });
        });
    }

    function removeStudentVideo(peerId) {
        const card = document.getElementById('card-' + peerId);
        if (card) card.remove();
        const grid = document.getElementById('studentsGrid');
        const count = grid.children.length - (document.getElementById('waitingMsg').style.display === 'none' ? 0 : 1);
        document.getElementById('activeVideoCount').innerText = Math.max(0, count);
        if (grid.children.length === 1) document.getElementById('waitingMsg').style.display = 'block';
    }


    function focusStudent(studentStream, name) {
        document.getElementById('localVid').srcObject = studentStream;
        document.getElementById('localVid').muted = false;
        document.getElementById('spotlightOverlay').style.display = 'block';
        LOG("Spotlight: " + name, "#3b82f6");
    }

    function resetMainVideo() {
        document.getElementById('localVid').srcObject = stream;
        document.getElementById('localVid').muted = true;
        document.getElementById('spotlightOverlay').style.display = 'none';
    }

    function toggleMic() {
        const t = camStream.getAudioTracks()[0];
        if (t) {
            t.enabled = !t.enabled;
            document.getElementById('btnMic').classList.toggle('active-red', !t.enabled);
        }
    }
    function toggleCam() {
        const t = camStream.getVideoTracks()[0];
        if (t) {
            t.enabled = !t.enabled;
            document.getElementById('btnCam').classList.toggle('active-red', !t.enabled);
        }
    }
    async function toggleScreenShare() {
        const btn = document.getElementById('btnScreen');
        if (isScreenSharing) {
            if (screenStream) screenStream.getTracks().forEach(t => t.stop());
            screenStream = null;
            isScreenSharing = false;
            btn.classList.remove('active-green');
            LOG("Ekran paylaşımı dayandırıldı.", "#f59e0b");
        } else {
            try {
                screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                document.getElementById('screenSource').srcObject = screenStream;
                isScreenSharing = true;
                btn.classList.add('active-green');
                LOG("Ekran paylaşımı aktivdir.", "#10b981");

                screenStream.getVideoTracks()[0].onended = () => {
                    if (isScreenSharing) toggleScreenShare();
                };
            } catch (e) {
                LOG("Ekran paylaşımı ləğv edildi.", "#f59e0b");
            }
        }
    }

    function showScreenShareRequestModal(name, conn) {
        const modal = document.createElement('div');
        modal.id = 'ss-request-modal';
        modal.style = "position:fixed; top:80px; left:50%; transform:translateX(-50%); background:#1e293b; border:2px solid #10b981; border-radius:20px; padding:30px; z-index:10000; box-shadow:0 20px 50px rgba(0,0,0,0.7); color:white; min-width:350px; text-align:center; animation: zoomIn 0.3s ease-out;";
        modal.innerHTML = `
            <div style="font-size:50px; margin-bottom:20px;">🖥️</div>
            <div style="font-size:14px; font-weight:700; margin-bottom:10px; color:#10b981; text-transform:uppercase; letter-spacing:1px;">Ekran Paylaşımı Sorğusu</div>
            <div style="font-size:18px; margin-bottom:25px;"><b>${name}</b> öz ekranını dərslə paylaşmaq istəyir.</div>
            <div style="display:flex; gap:15px; justify-content:center;">
                <button id="btnRejectSS" style="background:rgba(244, 63, 94, 0.1); border:1px solid #f43f5e; color:#f43f5e; padding:10px 25px; border-radius:10px; cursor:pointer; font-weight:700;">Rədd et</button>
                <button id="btnApproveSS" style="background:#10b981; border:none; color:white; padding:10px 25px; border-radius:10px; cursor:pointer; font-weight:700;">İcazə ver</button>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById('btnApproveSS').onclick = () => {
            conn.send({ type: 'screen_share_approved' });
            LOG(`✅ ${name} üçün ekran paylaşımına icazə verildi.`, "#10b981");
            modal.remove();
        };
        document.getElementById('btnRejectSS').onclick = () => {
            conn.send({ type: 'screen_share_rejected' });
            LOG(`❌ ${name} üçün ekran paylaşım sorğusu rədd edildi.`, "#f43f5e");
            modal.remove();
        };
    }

    function startStudentSpotlight(peerId, screenStream, name) {
        isStudentSpotlight = true;
        spotlightPeerId = peerId;
        spotlightName = name;

        // Create a hidden video element to feed the canvas
        studentScreenVidElement = document.createElement('video');
        studentScreenVidElement.srcObject = screenStream;
        studentScreenVidElement.muted = true;
        studentScreenVidElement.playsInline = true;

        studentScreenVidElement.onloadedmetadata = () => {
            console.log(`[SPOTLIGHT] Video Loaded: ${studentScreenVidElement.videoWidth}x${studentScreenVidElement.videoHeight}`);
            studentScreenVidElement.play()
                .then(() => LOG(`✨ ${name} ekranı/lövhəsi başladı.`, "#3b82f6"))
                .catch(e => LOG("❌ Video Play Xətası: " + e.message, "#ef4444"));
        };

        // If metadata doesn't load for some reason, try playing anyway
        setTimeout(() => {
            if (studentScreenVidElement.paused) {
                studentScreenVidElement.play().catch(e => console.error("Force play failed", e));
            }
        }, 1000);

        // Also try to find their camera stream among active cards
        const studentCard = document.getElementById('card-' + peerId);
        if (studentCard) {
            const camVid = studentCard.querySelector('video');
            if (camVid) studentCamVidElement = camVid;
        }

        // Display a control button to stop spotlight
        let stopBtn = document.getElementById('btnStopSpotlight');
        if (!stopBtn) {
            stopBtn = document.createElement('button');
            stopBtn.id = 'btnStopSpotlight';
            stopBtn.innerHTML = "⏹️ Tələbə Paylaşımını Dayandır";
            stopBtn.style = "position:fixed; bottom:100px; left:50%; transform:translateX(-50%); background:#ef4444; color:white; border:none; padding:12px 25px; border-radius:12px; font-weight:800; z-index:5000; cursor:pointer; box-shadow:0 10px 30px rgba(239, 68, 68, 0.4); animation: slideUp 0.3s ease-out;";
            stopBtn.onclick = stopStudentSpotlight;
            document.body.appendChild(stopBtn);
        }

        LOG(`✨ ${name} spotlight sorğusu qəbul edildi.`, "#3b82f6");
    }

    function stopStudentSpotlight() {
        if (spotlightPeerId) {
            const conn = allDataConns.find(c => c.peer === spotlightPeerId && c.open);
            if (conn) {
                conn.send({ type: 'whiteboard_force_stop' });
                LOG("🛑 Tələbəyə lövhəni dayandırmaq əmri göndərildi.", "#f59e0b");
            }
        }

        isStudentSpotlight = false;
        spotlightPeerId = null;
        if (studentScreenVidElement) {
            studentScreenVidElement.srcObject = null;
            studentScreenVidElement = null;
        }
        studentCamVidElement = null;

        const stopBtn = document.getElementById('btnStopSpotlight');
        if (stopBtn) stopBtn.remove();

        LOG("⏹️ Spotlight dayandırıldı, müəllim kamerasını qayıdırıq.", "#f59e0b");
    }

    function showMicRequestModal(name, conn) {
        const modal = document.createElement('div');
        modal.style = "position:fixed; top:20px; right:20px; background:#1e293b; border:1px solid #3b82f6; border-radius:12px; padding:20px; z-index:10000; box-shadow:0 10px 30px rgba(0,0,0,0.5); color:white; min-width:300px; animation: slideIn 0.3s ease-out;";
        modal.innerHTML = `
            <div style="font-size:14px; font-weight:700; margin-bottom:15px; color:#3b82f6;">🎙️ MİKROFON SORĞUSU</div>
            <div style="font-size:16px; margin-bottom:20px;"><b>${name}</b> söz istəyir. Mikrofonu aktiv edilsin?</div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button id="btnRejectMic" style="background:none; border:none; color:#f43f5e; cursor:pointer; font-weight:700;">Rədd et</button>
                <button id="btnApproveMic" style="background:#3b82f6; border:none; color:white; padding:8px 15px; border-radius:8px; cursor:pointer; font-weight:700;">İcazə ver</button>
            </div>
        `;
        document.body.appendChild(modal);
        document.getElementById('btnApproveMic').onclick = () => {
            conn.send({ type: 'mic_approved' });
            modal.remove();
        };
        document.getElementById('btnRejectMic').onclick = () => {
            conn.send({ type: 'mic_rejected' });
            modal.remove();
        };
    }

    function showRejoinRequestModal(name, conn, userId) {
        if (document.getElementById('rejoin-modal-' + userId)) return;

        const modal = document.createElement('div');
        modal.id = 'rejoin-modal-' + userId;
        modal.style = "position:fixed; top:100px; left:50%; transform:translateX(-50%); background:#1e293b; border:2px solid #f59e0b; border-radius:20px; padding:30px; z-index:10000; box-shadow:0 20px 50px rgba(0,0,0,0.7); color:white; min-width:350px; text-align:center; animation: zoomIn 0.3s ease-out;";
        modal.innerHTML = `
            <div style="font-size:50px; margin-bottom:20px;">✋</div>
            <div style="font-size:14px; font-weight:700; margin-bottom:10px; color:#f59e0b; text-transform:uppercase;">Giriş İstəyi</div>
            <div style="font-size:18px; margin-bottom:25px;">Uzaqlaşdırılmış tələbə <b>${name}</b> yenidən dərsə girmək üçün icazə istəyir.</div>
            <div style="display:flex; gap:15px; justify-content:center;">
                <button id="btnRejectRejoin-${userId}" style="background:rgba(244, 63, 94, 0.1); border:1px solid #f43f5e; color:#f43f5e; padding:10px 25px; border-radius:10px; cursor:pointer; font-weight:700;">Rədd et</button>
                <button id="btnApproveRejoin-${userId}" style="background:#f59e0b; border:none; color:#000; padding:10px 25px; border-radius:10px; cursor:pointer; font-weight:700;">İcazə ver</button>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById('btnApproveRejoin-' + userId).onclick = () => {
            LOG(`🔄 ${name} (ID: ${userId}) üçün giriş icazəsi bazaya ötürülür...`, "#f59e0b");

            // Use the teacher API endpoint properly
            const formData = new FormData();
            formData.append('live_class_id', lID);
            formData.append('user_id', userId);

            fetch('api/approve_rejoin.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        conn.send({ type: 'entry_approved' });
                        LOG(`✅ ${name} üçün yenidən giriş icazəsi verildi`, "#10b981");
                        modal.remove();
                    } else {
                        alert("Xəta: " + (data.message || 'Naməlum xəta'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Sorğu zamanı xəta yarandı.");
                });
        };
        document.getElementById('btnRejectRejoin-' + userId).onclick = () => {
            conn.send({ type: 'entry_rejected' });
            LOG(`❌ ${name} üçün giriş sorğusu rədd edildi`, "#f43f5e");
            modal.remove();
        };
    }

    function replaceTrack(newTrack) {
        Object.values(peer.connections).forEach(cs => cs.forEach(c => {
            if (c.peerConnection) {
                const s = c.peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
                if (s) s.replaceTrack(newTrack);
            }
        }));
    }

    <?php
    $lessonStartTime = 'Date.now()';
    if ($lesson && isset($lesson['started_at']) && $lesson['started_at']) {
        $lessonStartTime = strtotime($lesson['started_at']) * 1000;
    }
    ?>
    let lessonStartedAt = <?php echo $lessonStartTime; ?>;

    function startLessonTimer() {
        if (!document.getElementById('timerDisplay')) return;

        setInterval(() => {
            const now = Date.now();
            const diff = Math.floor((now - lessonStartedAt) / 1000);

            // Show absolute duration since started_at
            const totalSeconds = Math.max(0, diff);
            const m = Math.floor(totalSeconds / 60);
            const s = totalSeconds % 60;

            document.getElementById('timerDisplay').innerText =
                (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
        }, 1000);
    }

    function stopAndUpload() {
        if (!confirm("Dərsi bitirmək və arxivləmək istəyirsiniz?")) return;

        LOG("🏁 Dərs bitirilir...", "#f59e0b");
        broadcastData({ type: 'lesson_ended' });

        // Periodic flush-u dayandır
        if (chunkFlushInterval) { clearInterval(chunkFlushInterval); chunkFlushInterval = null; }

        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            LOG("🎥 Yazı dayandırılır...", "#f59e0b");
            mediaRecorder.stop();
        }

        LOG("⏳ Video emal olunur, xahiş olunur gözləyin...", "#3b82f6");

        // Əvvəlcə qalan parçaları serverə göndər, sonra final upload et
        setTimeout(() => {
            const finalFlush = (recordedChunks.length > 0) ? flushChunksToServer() : Promise.resolve();

            finalFlush.then(() => {
                const fd = new FormData();
                fd.append('lesson_id', lID);
                fd.append('course_id', '<?php echo $lesson['course_id']; ?>');
                fd.append('has_chunks', '1'); // Serverə pre-saved parçalar olduğunu bildir

                if (recordedChunks.length === 0) {
                    // Bütün parçalar artıq serverə göndərilib, əlavə video yoxdur
                    fd.append('no_video', '1');
                } else {
                    const blob = new Blob(recordedChunks, { type: 'video/webm' });
                    LOG(`📦 Video Blobu yaradıldı: ${(blob.size / 1024 / 1024).toFixed(2)} MB`, "#10b981");
                    fd.append('video', blob);
                }

                LOG("🚀 Serverə göndərilir...", "#3b82f6");

                fetch('api/upload_recording.php', { method: 'POST', body: fd })
                    .then(r => r.text())
                    .then(text => {
                        LOG("📥 Server cavabı alındı.", "#10b981");
                        try {
                            const d = JSON.parse(text);
                            if (d.success) {
                                LOG("✅ Dərs uğurla tamamlandı!", "#10b981");
                                alert(d.message || "Dərs uğurla bitirildi!");
                                window.location.href = 'live-lessons.php';
                            } else {
                                LOG("❌ Server xətası: " + d.message, "#ef4444");
                                alert("Xəta: " + d.message);
                                window.location.href = 'live-lessons.php';
                            }
                        } catch (e) {
                            console.error("Non-JSON response:", text);
                            LOG("❌ Cavab oxunarkən xəta yarandı.", "#ef4444");
                            alert("Server Xətası: Məlumat yadda saxlanıla bilmədi.");
                            window.location.href = 'live-lessons.php';
                        }
                    })
                    .catch(err => {
                        LOG("❌ Şəbəkə xətası: " + err.message, "#ef4444");
                        alert("Şəbəkə Xətası: Video yüklənə bilmədi.");
                        window.location.href = 'live-lessons.php';
                    });
            });
        }, 2000);
    }

    function kickStudent(pId, name, uId) {
        // Fallback: If uId is missing, try to get it from the card attribute
        if (!uId || uId === 'undefined' || uId === 'null') {
            const card = document.getElementById('card-' + pId);
            if (card && card.getAttribute('data-student-id')) {
                uId = card.getAttribute('data-student-id');
                console.log(`Found uId from card: ${uId}`);
            }
        }

        // Second Fallback: Try to find in the global attendees list if possible
        if (!uId || uId === 'undefined' || uId === 'null') {
            LOG(`❌ Xəta: Bu tələbənin ID-si tapılmadı (${name}). ID: ${uId}`, "#ef4444");
            console.error("Kick Error: uId is invalid", { pId, name, uId });
            return;
        }

        if (!confirm(`${name} uzaqlaşdırılsın?`)) return;

        LOG(`🚀 ${name} uzaqlaşdırılır...`, "#f59e0b");

        const fd = new FormData();
        fd.append('live_class_id', lID);
        fd.append('user_id', uId);

        fetch('api/kick_student.php', { method: 'POST', body: fd })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP error! status: ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    LOG(`✅ Bazada qeyd edildi: ${name}`, "#22c55e");

                    // Notify via WebRTC
                    let notified = false;
                    if (pId && peer && peer.connections && peer.connections[pId]) {
                        peer.connections[pId].forEach(conn => {
                            if (conn.type === 'data' && conn.open) {
                                conn.send({ type: 'kick_user' });
                                notified = true;
                            }
                        });
                    }

                    if (notified) {
                        LOG(`📡 Canlı siqnal göndərildi: ${name}`, "#22c55e");
                    } else {
                        LOG(`⚠️ Canlı bağlantı yoxdur, tələbə yenilənmədə uzaqlaşacaq.`, "#f59e0b");
                    }

                    if (pId) removeStudentVideo(pId);

                    // Refresh UI
                    refreshLiveAttendance();
                } else {
                    LOG(`❌ Xəta: ${data.message}`, "#ef4444");
                    alert("Xəta: " + data.message);
                }
            })
            .catch(err => {
                LOG(`❌ Şəbəkə xətası: ${err.message}`, "#ef4444");
                console.error("Kick Error:", err);
            });
    }

    function approveStudentRejoin(uId, name) {
        LOG(`✅ ${name} (ID: ${uId}) üçün yenidən giriş icazəsi təsdiqlənir...`, "#f59e0b");

        fetch(`../student/api/unkick_student.php?live_class_id=${lID}&user_id=${uId}&t=${Date.now()}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    LOG(`✨ ${name} artıq daxil ola bilər.`, "#10b981");
                    refreshLiveAttendance();
                } else {
                    alert("Xəta: " + (data.message || 'Parametr xətası'));
                }
            })
            .catch(err => {
                LOG(`❌ API Xətası: ${err.message}`, "#ef4444");
            });
    }

    // --- ATTENDANCE MENU SYSTEM ---
    let attendanceMenuEl = null;

    function openAttendanceMenu(e, userId, name, isOnline, isKicked, peerId) {
        e.stopPropagation();

        // Create menu if not exists
        if (!attendanceMenuEl) {
            attendanceMenuEl = document.createElement('div');
            attendanceMenuEl.className = 'student-dropdown';
            attendanceMenuEl.style.cssText = "position:fixed; background:#1e293b; border:1px solid rgba(255,255,255,0.15); border-radius:12px; padding:6px 0; min-width:180px; z-index:99999; display:none; box-shadow:0 15px 40px rgba(0,0,0,0.8); backdrop-filter:blur(10px);";
            document.body.appendChild(attendanceMenuEl);

            // Close on outside click
            document.addEventListener('click', (event) => {
                if (attendanceMenuEl && attendanceMenuEl.style.display === 'block' && !attendanceMenuEl.contains(event.target)) {
                    attendanceMenuEl.style.display = 'none';
                }
            });
        }

        // Helper to create items
        const createItem = (icon, text, color, onClick) => {
            return `
                <button onclick="${onClick}" style="display:flex; align-items:center; gap:10px; width:100%; padding:10px 16px; background:none; border:none; color:${color}; font-size:13px; font-weight:600; cursor:pointer; text-align:left; transition:0.15s;" onmouseenter="this.style.background='rgba(255,255,255,0.08)'" onmouseleave="this.style.background='none'">
                    <span style="font-size:16px;">${icon}</span>
                    <span>${text}</span>
                </button>
            `;
        };

        let menuHtml = '';

        // Header
        menuHtml += `<div style="padding:8px 16px; font-size:11px; color:#64748b; font-weight:700; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:4px;">${name}</div>`;

        if (isKicked) {
            // UNKICK - approveStudentRejoin now uses the correct API
            menuHtml += createItem('🔓', 'Girişə İcazə Ver', '#f59e0b', `approveStudentRejoin('${userId}', '${name.replace(/'/g, "\\'").replace(/"/g, '\\"')}')`);
        } else if (isOnline) {
            // ONLINE ACTIONS
            menuHtml += createItem('💬', 'Özəl Mesaj', '#3b82f6', `setPrivateTarget('${peerId}', '${name.replace(/'/g, "\\'").replace(/"/g, '\\"')}')`);
            menuHtml += createItem('🚫', 'Dərsdən Uzaqlaşdır', '#ef4444', `kickStudent('${peerId}', '${name.replace(/'/g, "\\'").replace(/"/g, '\\"')}', '${userId}')`);
        } else {
            // OFFLINE ACTIONS
            menuHtml += createItem('🔔', 'Bildiriş Göndər', '#94a3b8', `openBulkNotificationModal('${userId}', '${name.replace(/'/g, "\\'").replace(/"/g, '\\"')}')`);
        }

        attendanceMenuEl.innerHTML = menuHtml;

        // Position and Show
        const rect = e.target.getBoundingClientRect();
        attendanceMenuEl.style.top = (rect.bottom + 5) + 'px';
        attendanceMenuEl.style.left = (rect.left - 150) + 'px'; // Shift left a bit
        attendanceMenuEl.style.display = 'block';
    }

    function refreshLiveAttendance() {
        LOG("🔄 Canlı iştirak yenilənir...", "#60a5fa");
        fetch('api/get_live_attendance.php?id=' + lID + '&subject_id=' + courseId)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                return res.json();
            })
            .then(data => {
                LOG(`✅ İştirak məlumatı alındı: ${data.online_count}/${data.total_count}`, "#10b981");
                if (data.success) {
                    document.getElementById('liveAttendanceCount').innerText = data.online_count + ' / ' + data.total_count;
                    const list = document.getElementById('liveAttendanceList');
                    if (data.attendees.length === 0) {
                        list.innerHTML = '<div style="color: #64748b; font-style: italic; text-align:center; padding:20px; font-size:12px;">Hələ ki, qoşulub yoxdur.</div>';
                    }
                    else {
                        list.innerHTML = data.attendees.map(att => {
                            const uID = att.userId || att.user_id || att.id || 'N/A';

                            // SYNC: If student is online and has a peer_id, update their video card's student-id
                            if (att.is_online && att.peer_id) {
                                const card = document.getElementById('card-' + att.peer_id);
                                if (card && !card.getAttribute('data-student-id')) {
                                    card.setAttribute('data-student-id', uID);
                                    console.log(`Synced ID ${uID} for peer ${att.peer_id}`);
                                }
                            }

                            return `
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: rgba(255,255,255,0.03); border-radius: 8px; margin-bottom: 6px; border: 1px solid rgba(255,255,255,0.05); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.06)'" onmouseout="this.style.background='rgba(255,255,255,0.03)'">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 32px; height: 32px; background: ${att.is_online ? 'rgba(16, 185, 129, 0.15)' : 'rgba(255,255,255,0.05)'}; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; color: ${att.is_online ? '#10b981' : '#64748b'};">
                                        ${att.role === 'instructor' ? '🎓' : '👤'}
                                    </div>
                                    <div>
                                        <div style="font-size: 13px; font-weight: 600; color: #f8fafc; line-height: 1.2;">${att.name}</div>
                                        <div style="font-size: 10px; color: ${att.is_kicked ? '#ef4444' : (att.is_online ? '#10b981' : '#64748b')}; font-weight: 700; display: flex; align-items: center; gap: 4px; margin-top: 2px;">
                                            <span style="width: 4px; height: 4px; background: currentColor; border-radius: 50%;"></span>
                                            ${att.is_kicked ? 'UZAQLAŞDIRILIB' : (att.is_online ? 'CANLI' : 'OFFLINE')}
                                        </div>
                                    </div>
                                </div>
                                
                                <button onclick="openAttendanceMenu(event, '${uID}', '${att.name.replace(/'/g, "\\'").replace(/"/g, '\\"')}', ${att.is_online}, ${att.is_kicked}, '${att.peer_id || ''}')" 
                                    style="width:28px; height:28px; border:none; background:transparent; color:#94a3b8; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; border-radius:6px; transition:0.2s;" 
                                    onmouseover="this.style.background='rgba(255,255,255,0.1)'; this.style.color='white'" 
                                    onmouseout="this.style.background='transparent'; this.style.color='#94a3b8'">
                                    ⋮
                                </button>
                            </div>
                        `;
                        }).join('');
                    }
                } else {
                    LOG("⚠️ API xətası: " + (data.message || 'Unknown'), "#ef4444");
                }
            })
            .catch(err => {
                LOG("❌ Canlı iştirak xətası: " + err.message, "#ef4444");
                console.error("Attendance Error:", err);
            });
    }

    // --- NOTIFICATION SYSTEM ---
    function openBulkNotificationModal(userId = null, name = null) {
        const modal = document.createElement('div');
        modal.id = 'bulkNotifyModal';
        modal.style = "position:fixed; inset:0; background:rgba(2,6,23,0.85); z-index:20000; backdrop-filter:blur(10px); display:flex; justify-content:center; align-items:center;";

        const isIndividual = userId != null;
        const targetLabel = isIndividual ? `Tələbə: ${name}` : "Bütün Kurs Tələbələrinə Toplu Mesaj";

        modal.innerHTML = `
            <div style="background:#1e293b; border:1px solid rgba(255,255,255,0.1); border-radius:24px; padding:30px; width:100%; max-width:450px; box-shadow:0 30px 60px rgba(0,0,0,0.5);">
                <div style="font-size:18px; font-weight:800; color:white; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
                    ${isIndividual ? '📧 Fərdi Mesaj' : '📣 Toplu Bildiriş'}
                    <button onclick="this.closest('#bulkNotifyModal').remove()" style="background:none; border:none; color:#64748b; font-size:24px; cursor:pointer;">&times;</button>
                </div>
                <div style="font-size:12px; color:#94a3b8; margin-bottom:15px; background:rgba(59, 130, 246, 0.1); padding:8px 12px; border-radius:8px; border-left:3px solid #3b82f6;">
                    🎯 ${targetLabel}
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block; color:#94a3b8; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px;">Başlıq</label>
                    <input id="notifyTitle" type="text" value="Müəllim Bildirişi" style="width:100%; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:10px; color:white; outline:none; font-size:14px;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block; color:#94a3b8; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px;">Mesaj</label>
                    <textarea id="notifyMessage" placeholder="Tələbələrə nə demək istəyirsiniz?" style="width:100%; height:120px; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:10px; color:white; outline:none; font-size:14px; resize:none;"></textarea>
                </div>
                <div style="display:flex; gap:12px;">
                    <button onclick="this.closest('#bulkNotifyModal').remove()" style="flex:1; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:12px; cursor:pointer; font-weight:700;">Ləğv et</button>
                    <button id="sendNotifyBtn" onclick="sendNotification(${isIndividual ? `'individual', '${userId}'` : `'bulk', '${<?php echo $lesson['course_id']; ?>}'`})" style="flex:2; background:#3b82f6; border:none; color:white; padding:12px; border-radius:12px; cursor:pointer; font-weight:800; display:flex; align-items:center; justify-content:center; gap:8px;">
                        Göndər 🚀
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    function sendNotification(type, targetId) {
        const titleEl = document.getElementById('notifyTitle');
        const messageEl = document.getElementById('notifyMessage');
        const title = titleEl ? titleEl.value : '';
        const message = messageEl ? messageEl.value : '';
        const btn = document.getElementById('sendNotifyBtn');
        if (!message) { alert("Mesaj boş ola bilməz!"); return; }
        btn.disabled = true;
        btn.innerHTML = 'Göndərilir...';
        fetch('api/send_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target_type: type, target_id: targetId, title: title, message: message, type: 'info' })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    LOG(`✅ Bildiriş uğurla göndərildi.`, "#10b981");

                    // Broadcast via WebRTC for real-time delivery to students in class
                    const msgObj = {
                        type: 'notification',
                        title: title,
                        message: message,
                        style: 'info'
                    };

                    if (type === 'bulk') {
                        broadcastData(msgObj);
                        LOG(`📡 Toplu bildiriş bütün tələbələrə yayımlandı.`, "#3b82f6");
                    } else {
                        // Send to specific student (find their peer connection)
                        let sent = false;
                        if (peer && peer.connections) {
                            Object.values(peer.connections).forEach(conns => {
                                conns.forEach(conn => {
                                    if (conn.type === 'data' && conn.metadata && String(conn.metadata.userId) === String(targetId)) {
                                        conn.send(msgObj);
                                        sent = true;
                                    }
                                });
                            });
                        }
                        if (sent) {
                            LOG(`📡 Fərdi bildiriş tələbəyə çatdırıldı.`, "#3b82f6");
                        } else {
                            LOG(`⚠️ Tələbə canlı deyilsə, bildirişi girişdə görəcək.`, "#f59e0b");
                        }
                    }

                    document.getElementById('bulkNotifyModal').remove();
                } else {
                    alert("Xəta: " + data.message);
                    btn.disabled = false;
                    btn.innerHTML = 'Göndər 🚀';
                }
            });
    }

    // --- ATTENDANCE TRACKING ---
    function trackAttendance(type) {
        const params = { type: type, live_class_id: lID };
        if (peer && peer.id) params.peer_id = peer.id;
        if (type === 'leave') {
            const blob = new Blob([JSON.stringify(params)], { type: 'application/json' });
            navigator.sendBeacon('api/track_attendance.php', blob);
            return;
        }
        fetch('api/track_attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(params),
            credentials: 'include'
        }).catch(err => console.error("Attendance Track Error:", err));
    }

    setInterval(() => { if (peer && peer.open) trackAttendance('heartbeat'); }, 30000);

    // Start everything correctly on load
    window.onload = () => {
        // init(); // Now called via startProductionNow() for user gesture compliance
        setTimeout(() => {
            LOG("📊 İlk iştirak məlumatı yüklənir...", "#3b82f6");
            refreshLiveAttendance();
        }, 1000);
        setInterval(refreshLiveAttendance, 5000);

        // Hər 5 saniyədən bir recording parçalarını serverə göndər (Daha sürətli yaddaş)
        chunkFlushInterval = setInterval(flushChunksToServer, 5000);
    };

    window.addEventListener('beforeunload', (e) => {
        trackAttendance('leave');
        // Refresh/bağlanma zamanı qalan parçaları sendBeacon ilə göndər
        flushChunksBeacon();

        // Refresh qadağan etmək üçün brauzer xəbərdarlığı
        e.preventDefault();
        e.returnValue = 'Dərs gedir, səhifəni yeniləmək qeydiyyatın itməsinə səbəb ola bilər. Çıxmaq istədiyinizə əminsiniz?';
    });

    // Klaviatura qısayollarını (F5, Ctrl+R) blokla
    window.addEventListener('keydown', function (e) {
        if ((e.which || e.keyCode) == 116 || (e.ctrlKey && (e.which || e.keyCode) == 82)) {
            e.preventDefault();
            LOG("⚠️ Səhifəni yeniləmək qadağandır!", "#ef4444");
        }
    });
</script>
<?php require_once 'includes/footer.php'; ?>