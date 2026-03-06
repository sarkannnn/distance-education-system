<?php
/**
 * Student Live View - (V8.0 - PREMIUM REDESIGN)
 * - Sidebar Chat
 * - Large Main Screen
 * - Consistent with Teacher UI
 */
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}
$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();
$uName = e(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
$lessonId = $_GET['id'] ?? null;
$lesson = $db->fetch("
    SELECT lc.*, c.title as course_title, u.first_name, u.last_name 
    FROM live_classes lc 
    JOIN courses c ON lc.course_id = c.id 
    LEFT JOIN instructors i ON lc.instructor_id = i.id 
    LEFT JOIN users u ON i.user_id = u.id 
    WHERE lc.id = ?
", [$lessonId]);

if (!$lesson) {
    die("Dərs tapılmadı.");
}


require_once 'includes/header.php';
?>
<!-- Meta refresh removed - using JavaScript heartbeat instead to preserve WebRTC connections -->
<style>
    body,
    html {
        margin: 0;
        padding: 0;
        min-height: 100vh;
        overflow: hidden;
        background: #0f172a;
    }

    .main-wrapper {
        padding-left: 0 !important;
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

    #chatMessages::-webkit-scrollbar {
        width: 6px;
    }

    #chatMessages::-webkit-scrollbar-track {
        background: transparent;
    }

    #chatMessages::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 10px;
    }

    #chatMessages::-webkit-scrollbar-thumb:hover {
        background: #475569;
    }

    #chatInput:focus {
        border-color: #3b82f6 !important;
    }

    .msg-mən {
        background: #3b82f6;
        color: white;
        margin-left: auto;
        border-bottom-right-radius: 4px !important;
    }

    .msg-müəllim {
        background: rgba(255, 255, 255, 0.1);
        color: #e2e8f0;
        margin-right: auto;
        border-bottom-left-radius: 4px !important;
    }

    .msg-özəl {
        background: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.3);
        margin-right: auto;
        border-bottom-left-radius: 4px !important;
        font-weight: 600;
    }

    @keyframes fadeInRight {
        from {
            opacity: 0;
            transform: translateX(40px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes scaleUp {
        from {
            opacity: 0;
            transform: scale(0.8);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @keyframes heartbeat {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
        }
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
</style>

<div class="main-wrapper"
    style="margin: 0; padding: 0; background: #0f172a; height: 100vh; color: white; display: flex; flex-direction: column; overflow: hidden;">
    <!-- STUDIO HEADER -->
    <div
        style="height: 65px; min-height: 65px; padding: 0 30px; background: #1e293b; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #334155; z-index: 100;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div
                style="width: 12px; height: 12px; background: #ef4444; border-radius: 50%; box-shadow: 0 0 10px #ef4444; animation: blink 1s infinite;">
            </div>
            <h1 style="font-size: 16px; margin: 0; font-weight: 700;">
                <?php echo e($lesson['course_title']); ?> <span
                    style="opacity: 0.5; margin-left: 8px; font-weight: 400;">#<?php echo $lessonId; ?></span>
            </h1>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <span id="connectionStatus"
                style="font-size: 12px; color: #94a3b8; font-family: monospace;">Gözlənilir...</span>
            <button onclick="leaveLesson()" class="btn btn-danger btn-sm"
                style="background: #ef4444; border: none; color: white; font-weight: 700; padding: 8px 20px; border-radius: 10px;">Canlı
                Dərsdən Ayrıl</button>
        </div>
    </div>

    <!-- MAIN STUDIO AREA -->
    <div style="display: grid; grid-template-columns: 1fr 380px; flex: 1; min-height: 0; overflow: hidden;">
        <!-- LEFT: VIDEO SCREEN -->
        <div
            style="position: relative; background: #020617; display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; padding: 20px; min-height: 0;">

            <div id="videoContainer"
                style="width: 100%; max-height: 100%; aspect-ratio: 16/9; position: relative; background: #000; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); border: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center;">

                <!-- REMOTE VIDEO (Müəllim) -->
                <video id="remVid" autoplay playsinline
                    style="width: 100%; height: 100%; object-fit: contain; background: #000;"></video>

                <!-- UNMUTE FALLBACK -->
                <button id="unmuteBtn" onclick="unmuteRemoteVideo()"
                    style="display: none; position: absolute; bottom: 30px; right: 30px; background: #ef4444; color: white; border: none; padding: 12px 24px; border-radius: 50px; font-weight: bold; z-index: 100; cursor: pointer; align-items: center; gap: 8px; box-shadow: 0 10px 20px rgba(239, 68, 68, 0.4);">
                    <i data-lucide="volume-2"></i> SƏSİ AKTİVLƏŞDİR
                </button>

                <!-- STATUS ALERTS -->
                <div id="alertBox"
                    style="position: absolute; top: 30px; left: 50%; transform: translateX(-50%); background: rgba(239, 68, 68, 0.9); color: white; padding: 12px 25px; border-radius: 12px; font-size: 14px; font-weight: bold; display: none; z-index: 110; backdrop-filter: blur(5px);">
                </div>

                <!-- DURATION TIMER -->
                <div id="lessonTimer"
                    style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.7); padding: 6px 12px; border-radius: 10px; font-size: 13px; font-weight: 800; color: #fff; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(8px); display: none; align-items: center; gap: 8px; font-family: 'JetBrains Mono', monospace; z-index: 110;">
                    <i data-lucide="clock" style="width: 14px; height: 14px; color: #3b82f6;"></i>
                    <span id="timerDisplay">00:00</span>
                </div>

                <!-- TEACHER NAME BADGE -->
                <div
                    style="position: absolute; top: 75px; left: 25px; background: rgba(0,0,0,0.5); padding: 8px 15px; border-radius: 10px; font-size: 12px; font-weight: 700; color: #fff; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(8px); z-index: 110; display: flex; align-items: center; gap: 8px;">
                    <div
                        style="width: 8px; height: 8px; background: #3b82f6; border-radius: 50%; box-shadow: 0 0 8px #3b82f6;">
                    </div>
                    Müəllim: <?php echo e($lesson['first_name'] . ' ' . $lesson['last_name']); ?>
                </div>

                <!-- OVERLAY (JOIN SCREEN) -->
                <div id="overlay"
                    style="position: absolute; inset: 0; background: rgba(15,23,42,0.98); display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 150; backdrop-filter: blur(10px);">
                    <div style="text-align: center; margin-bottom: 40px;">
                        <h2 style="font-size: 28px; font-weight: 850; color: white; margin-bottom: 10px;">Dərsə
                            hazırsınız?</h2>
                        <p style="color: #94a3b8;">Müəllim və yoldaşlarınız sizi gözləyir.</p>
                    </div>
                    <button id="joinBtn" onclick="initStudent()"
                        style="background: #ef4444; color: white; border: none; border-radius: 50px; padding: 25px 80px; font-weight: 800; font-size: 22px; cursor: pointer; transition: all 0.3s; box-shadow: 0 15px 30px rgba(239, 68, 68, 0.3);">
                        DƏRSƏ QOŞUL
                    </button>
                    <div id="status"
                        style="color: #64748b; margin-top: 40px; font-size: 14px; text-align: center; font-family: monospace; letter-spacing: 1px;">
                        Səhifə yüklənir...</div>
                </div>

                <!-- LIVE BADGE -->
                <div
                    style="position: absolute; top: 25px; left: 25px; background: rgba(239, 68, 68, 0.9); color: white; padding: 5px 15px; border-radius: 8px; font-size: 11px; font-weight: 900; z-index: 10; display: flex; align-items: center; gap: 8px; backdrop-filter: blur(4px);">
                    <div
                        style="width: 8px; height: 8px; background: white; border-radius: 50%; animation: blink 1s infinite;">
                    </div>
                    CANLI
                </div>

                <!-- LOCAL PREVIEW & CONTROLS (LEFT side to avoid overlap with teacher's embedded camera) -->
                <div id="localControlsWrapper"
                    style="position: absolute; bottom: 30px; left: 30px; z-index: 120; display: none;">
                    <div style="position: relative; margin-bottom: 15px;">
                        <video id="localPreview" muted autoplay playsinline
                            style="width: 180px; height: 120px; object-fit: cover; border-radius: 16px; border: 2px solid rgba(255,255,255,0.2); background: #000; box-shadow: 0 10px 20px rgba(0,0,0,0.5);">
                        </video>
                        <div
                            style="position: absolute; bottom: 10px; left: 10px; background: rgba(0,0,0,0.6); padding: 4px 10px; border-radius: 8px; font-size: 10px; font-weight: 700; color: #fff; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 5px;">
                            <div style="width: 5px; height: 5px; background: #fff; border-radius: 50%; opacity: 0.8;">
                            </div>
                            Mən
                        </div>
                    </div>
                    <div
                        style="display: flex; gap: 20px; justify-content: center; background: rgba(15,23,42,0.6); padding: 15px 25px; border-radius: 24px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1);">
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                            <button id="btnMicItem" onclick="toggleMicRequest()" title="Söz İstə"
                                style="background: #ef4444; border: none; border-radius: 50%; width: 45px; height: 45px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0,0,0,0.4); transition: all 0.2s;">
                                <i data-lucide="hand" style="width: 20px; height: 20px; color: white;"></i>
                            </button>
                            <span
                                style="font-size: 9px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Söz
                                İstə</span>
                        </div>

                        <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                            <button id="btnCamItem" onclick="toggleCam()"
                                style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 50%; width: 45px; height: 45px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0,0,0,0.4); transition: all 0.2s;">
                                <i data-lucide="video" style="width: 20px; height: 20px; color: white;"></i>
                            </button>
                            <span
                                style="font-size: 9px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Kamera</span>
                        </div>

                        <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                            <button id="btnScreenItem" onclick="toggleScreenShare()"
                                style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 50%; width: 45px; height: 45px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0,0,0,0.4); transition: all 0.2s;">
                                <i data-lucide="monitor" style="width: 20px; height: 20px; color: white;"></i>
                            </button>
                            <span
                                style="font-size: 9px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Ekran</span>
                        </div>

                        <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                            <button id="btnWhiteboardItem" onclick="toggleWhiteboard()"
                                style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 50%; width: 45px; height: 45px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0,0,0,0.4); transition: all 0.2s;">
                                <i data-lucide="pen-tool" style="width: 20px; height: 20px; color: white;"></i>
                            </button>
                            <span
                                style="font-size: 9px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Lövhə</span>
                        </div>
                    </div>
                </div>

                <!-- SYSTEM LOGS (Optional, hidden by default) -->
                <div id="debugLogWrapper" style="display: none; width: 100%; max-width: 1100px; margin-top: 20px;">
                    <div id="debugLog"
                        style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; height: 100px; overflow-y: auto; font-family: monospace; font-size: 11px; color: #64748b;">
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: SIDEBAR (CHAT & PARTICIPANTS) -->
        <div
            style="background: #1e293b; border-left: 2px solid #334155; display: flex; flex-direction: column; overflow: hidden; padding: 25px;">

            <!-- CHAT AREA -->
            <div style="flex: 1; display: flex; flex-direction: column; min-height: 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3
                        style="font-size: 15px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin: 0; display: flex; align-items: center; gap: 10px;">
                        <i data-lucide="message-square" style="width: 18px; height: 18px; color: #3b82f6;"></i>
                        Dərs Çatı
                    </h3>
                    <button id="btnPrivateTeacher" onclick="togglePrivateTeacher()"
                        style="background: rgba(59, 130, 246, 0.1); border: 1px solid #3b82f6; color: #3b82f6; font-size: 10px; padding: 4px 10px; border-radius: 6px; cursor: pointer; font-weight: 700;">🔒
                        Müəllimə Özəl</button>
                </div>

                <!-- PRIVATE CHAT TARGET INDICATOR -->
                <div id="privateTargetIndicator"
                    style="display: none; background: rgba(59, 130, 246, 0.2); padding: 5px 12px; border-radius: 8px; font-size: 11px; margin-bottom: 15px; align-items: center; justify-content: space-between;">
                    <span style="color: #3b82f6; font-weight: 700;">🔒 Müəllimə ÖZƏL yazılır...</span>
                    <button onclick="clearPrivateTarget()"
                        style="background: none; border: none; color: #ef4444; font-size: 14px; cursor: pointer; padding: 0;">&times;</button>
                </div>

                <div id="chatMessages"
                    style="flex: 1; overflow-y: auto; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 16px; margin-bottom: 20px; scroll-behavior: smooth; border: 1px solid rgba(255,255,255,0.05);">
                    <div
                        style="height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.3; text-align: center; padding: 0 20px;">
                        <i data-lucide="messages-square" style="width: 48px; height: 48px; margin-bottom: 10px;"></i>
                        <p style="font-size: 13px;">Hələ ki, mesaj yoxdur. Sualınız varsa yazın!</p>
                    </div>
                </div>

                <div
                    style="display: flex; flex-direction: column; gap: 10px; background: rgba(255,255,255,0.02); padding: 15px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="chatInput" placeholder="Mesaj yazın..."
                            style="flex: 1; background: rgba(0,0,0,0.3); border: 1px solid #334155; border-radius: 12px; padding: 12px 15px; color: white; font-size: 14px; outline: none; transition: border-color 0.2s;"
                            onkeypress="if(event.key==='Enter') sendChatMessage()">
                        <button onclick="sendChatMessage()"
                            style="background: #3b82f6; border: none; border-radius: 12px; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.2s;">
                            <i data-lucide="send"
                                style="width: 18px; height: 18px; color: white; margin-left: 2px;"></i>
                        </button>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <label for="fileInput"
                            style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #94a3b8; cursor: pointer; padding: 5px 10px; border-radius: 8px; transition: background 0.2s;">
                            <i data-lucide="paperclip" style="width: 14px; height: 14px;"></i> Fayl Əlavə Et
                        </label>
                        <input type="file" id="fileInput" style="display: none;" onchange="handleFileSelect(this)">
                    </div>
                </div>
            </div>

            <!-- FOOTER INFO -->
            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #334155;">
                <div style="display: flex; align-items: center; gap: 10px; font-size: 12px; color: #64748b;">
                    <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i>
                    Uçtan-uca şifrələnmiş bağlantı
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes blink {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    #chatMessages::-webkit-scrollbar {
        width: 6px;
    }

    #chatMessages::-webkit-scrollbar-track {
        background: transparent;
    }

    #chatMessages::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 10px;
    }

    #chatMessages::-webkit-scrollbar-thumb:hover {
        background: #475569;
    }

    #chatInput:focus {
        border-color: #3b82f6 !important;
    }

    .msg-mən {
        background: #3b82f6;
        color: white;
        margin-left: auto;
        border-bottom-right-radius: 4px !important;
    }

    .msg-müəllim {
        background: rgba(255, 255, 255, 0.1);
        color: #e2e8f0;
        margin-right: auto;
        border-bottom-left-radius: 4px !important;
    }

    #localPreview {
        transform: scaleX(-1);
    }
</style>

<script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
<script>
    const lID = "<?php echo $lessonId; ?>";
    const uName = "<?php echo e($uName); ?>";
    const uID = "<?php echo $currentUser['id']; ?>";
    let p, discoveryInterval, localMediaStream = null, dataConn = null;
    let signalReady = false;
    let isConnecting = false;
    let isDummyStream = false;

    // Media States
    let isScreenSharing = false;
    let camVideoTrack = null;
    let screenStream = null;

    // Mic Mgmt
    let micApproved = false;
    let micRequested = false;

    // Screen Share Mgmt
    let screenRequested = false;
    let screenApproved = false;

    // --- LOGGING & UI ---
    const DBG = (m) => {
        const d = document.getElementById('debugLog');
        if (d) d.innerHTML += `<div>[${new Date().toLocaleTimeString()}] ${m}</div>`;
        const s = document.getElementById('connectionStatus');
        if (s) s.innerText = m.length > 20 ? m.substring(0, 17) + "..." : m;
        console.log("LOG:", m);
    };

    function LOG(msg, color = '#64748b') {
        const box = document.getElementById('debugLog'); // Using debugLog for consistency
        if (box) {
            const div = document.createElement('div');
            div.style.color = color; div.style.marginBottom = '5px';
            div.innerHTML = `<span style="opacity:0.5; font-size:10px;">[${new Date().toLocaleTimeString()}]</span> ${msg}`;
            box.appendChild(div); box.scrollTop = box.scrollHeight;
        }
    }

    let lessonStartTime = new Date("<?php echo $lesson['start_time']; ?>").getTime();
    function startLessonTimer() {
        document.getElementById('lessonTimer').style.display = 'flex';
        setInterval(() => {
            const now = new Date().getTime();
            const diff = Math.floor((now - lessonStartTime) / 1000);
            if (diff < 0) return; // Prevent negative timer if join before official start

            const m = Math.floor(diff / 60);
            const s = diff % 60;
            document.getElementById('timerDisplay').innerText =
                (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
        }, 1000);
    }

    let alertTimeout = null;
    function showAlert(msg, type = 'info', autoHide = true) {
        const a = document.getElementById('alertBox');
        if (!a) return;

        if (alertTimeout) clearTimeout(alertTimeout);

        const colors = {
            'success': '#10b981',
            'error': '#ef4444',
            'info': 'rgba(0,0,0,0.8)'
        };
        a.style.background = colors[type] || colors.info;
        a.innerText = msg;
        a.style.display = 'block';

        if (autoHide) {
            alertTimeout = setTimeout(() => a.style.display = 'none', 4000);
        }
    }

    // --- CONTROL FUNCTIONS ---
    function toggleMicRequest() {
        if (micApproved) {
            setMic(false);
            micApproved = false;
            document.getElementById('btnMicItem').innerHTML = '<i data-lucide="hand" style="width: 20px; height: 20px; color: white;"></i>';
            if (window.lucide) lucide.createIcons();
            document.getElementById('btnMicItem').style.background = '#ef4444';
            showAlert("Mikrofonunuzu bağladınız.");
            return;
        }

        if (micRequested) {
            showAlert("Sorğunuz artıq göndərilib, gözləyin...");
            return;
        }

        if (!dataConn || !dataConn.open) {
            showAlert("Bağlantı qurulmayıb!");
            return;
        }

        dataConn.send({ type: 'mic_request', sender: uName });
        micRequested = true;
        document.getElementById('btnMicItem').innerHTML = '<i data-lucide="loader-2" class="animate-spin" style="width: 20px; height: 20px; color: white;"></i>';
        if (window.lucide) lucide.createIcons();
        document.getElementById('btnMicItem').style.background = '#f59e0b';
        showAlert("Söz istəyi göndərildi...");
    }

    function setMic(enabled) {
        if (localMediaStream) localMediaStream.getAudioTracks().forEach(t => t.enabled = enabled);
    }

    async function toggleCam() {
        if (!localMediaStream) return;

        // If we are on dummy stream, try to get real camera instead of just toggling
        if (isDummyStream) {
            showAlert("Kamera yenidən yoxlanılır...");
            try {
                // Try to get real media (A+V or just V)
                let stream;
                try {
                    stream = await getMediaStream(true, true);
                } catch (e) {
                    stream = await getMediaStream(false, true);
                    DBG("Kamera aktivləşdi (səssiz).");
                }

                isDummyStream = false;

                // Replace video track in current stream
                const newVideoTrack = stream.getVideoTracks()[0];
                const oldVideoTrack = localMediaStream.getVideoTracks()[0];

                if (oldVideoTrack) localMediaStream.removeTrack(oldVideoTrack);
                localMediaStream.addTrack(newVideoTrack);

                // If we also got a new audio track and current one is dummy, replace it too
                if (stream.getAudioTracks().length > 0) {
                    const newAudioTrack = stream.getAudioTracks()[0];
                    const oldAudioTrack = localMediaStream.getAudioTracks()[0];
                    // Check if it's a dummy audio track (placeholder)
                    if (oldAudioTrack) localMediaStream.removeTrack(oldAudioTrack);
                    localMediaStream.addTrack(newAudioTrack);
                    newAudioTrack.enabled = false; // Keep muted by default
                }

                // Update preview
                document.getElementById('localPreview').srcObject = localMediaStream;

                // If there's an active peer connection, we must re-call or replace track
                if (p && dataConn && dataConn.peer && dataConn.open) {
                    DBG("📡 Yeni kamera yayımı müəllimə göndərilir...");
                    p.call(dataConn.peer, localMediaStream, { metadata: { name: uName, userId: uID } });
                }

                newVideoTrack.enabled = true; // Ensure it's active
                updateCamUI(true);
                showAlert("Kamera uğurla aktivləşdirildi! ✅", "success");
                return; // Exit here, no need to toggle
            } catch (err) {
                console.error("Camera acquisition failed:", err);
                // Show specific error to help user debug
                if (err.name === 'NotAllowedError') {
                    showAlert("İcazə rədd edildi! Brauzer ayarlarından kameraya icazə verin.", "error");
                } else if (err.name === 'NotFoundError') {
                    showAlert("Kamera cihazı tapılmadı!", "error");
                } else if (err.name === 'NotReadableError') {
                    showAlert("Kamera başqa proqram tərəfindən istifadə edilir!", "error");
                } else {
                    showAlert("Kamera xətası: " + err.name, "error");
                }
                return;
            }
        }

        // Normal toggle for real stream
        const tracks = localMediaStream.getVideoTracks();
        if (tracks.length > 0) {
            const newState = !tracks[0].enabled;
            tracks.forEach(t => t.enabled = newState);
            updateCamUI(newState);

            // If we just turned on the camera after it was off, 
            // maybe we should ensure teacher sees it (some browsers might need re-negotiation)
            if (newState && p && dataConn && dataConn.peer && dataConn.open) {
                // p.call(dataConn.peer, localMediaStream, { metadata: { name: uName, userId: uID } });
            }
        }
    }

    function updateCamUI(enabled) {
        const btn = document.getElementById('btnCamItem');
        if (!btn) return;
        btn.style.background = enabled ? "rgba(59, 130, 246, 0.2)" : "#ef4444";
        btn.style.borderColor = enabled ? "#3b82f6" : "rgba(239, 68, 68, 0.4)";
        btn.innerHTML = enabled ? '<i data-lucide="video" style="width: 20px; height: 20px; color: white;"></i>' : '<i data-lucide="video-off" style="width: 20px; height: 20px; color: white;"></i>';
        if (window.lucide) lucide.createIcons();
    }

    // --- CHAT LOGIC ---
    function appendChatMessage(sender, msg, type = 'müəllim') {
        const box = document.getElementById('chatMessages');
        if (box.querySelector('p')) box.innerHTML = '';

        const isSelf = type === 'mən';
        const msgHtml = `
            <div style="display: flex; flex-direction: column; margin-bottom: 15px; ${isSelf ? 'align-items: flex-end' : 'align-items: flex-start'}">
                <div style="font-size: 11px; color: #94a3b8; font-weight: 700; margin-bottom: 4px; padding: 0 12px;">${sender}</div>
                <div class="msg-${type}" style="max-width: 80%; padding: 10px 15px; border-radius: 16px; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); line-height: 1.4;">
                    ${msg}
                </div>
                <div style="font-size: 10px; color: #475569; margin-top: 4px; padding: 0 12px;">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
            </div>
        `;
        box.innerHTML += msgHtml;
        box.scrollTop = box.scrollHeight;
    }

    function appendFileMessage(sender, fileName, fileData, type = 'müəllim') {
        const box = document.getElementById('chatMessages');
        if (box.querySelector('p')) box.innerHTML = '';

        const isSelf = type === 'mən';
        const linkHTML = `<a href="${fileData}" target="_blank" download="${fileName}" style="color: ${isSelf ? '#fff' : '#3b82f6'}; text-decoration: underline; font-weight: 700; display: flex; align-items: center; gap: 8px;"><i data-lucide="file-text" style="width:16px;"></i> ${fileName}</a>`;

        const msgHtml = `
            <div style="display: flex; flex-direction: column; margin-bottom: 15px; ${isSelf ? 'align-items: flex-end' : 'align-items: flex-start'}">
                <div style="font-size: 11px; color: #94a3b8; font-weight: 700; margin-bottom: 4px; padding: 0 12px;">${sender}</div>
                <div class="msg-${type}" style="max-width: 80%; padding: 10px 15px; border-radius: 16px; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    ${linkHTML}
                </div>
            </div>
        `;
        box.innerHTML += msgHtml;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        box.scrollTop = box.scrollHeight;
    }

    let isPrivateMode = false;

    function togglePrivateTeacher() {
        isPrivateMode = true;
        document.getElementById('privateTargetIndicator').style.display = 'flex';
        document.getElementById('chatInput').placeholder = "Müəllimə özəl mesaj...";
        document.getElementById('chatInput').focus();
    }

    function clearPrivateTarget() {
        isPrivateMode = false;
        document.getElementById('privateTargetIndicator').style.display = 'none';
        document.getElementById('chatInput').placeholder = "Mesaj yazın...";
    }

    function sendChatMessage() {
        const input = document.getElementById('chatInput');
        const msg = input.value.trim();
        if (!msg) return;

        if (dataConn && dataConn.open) {
            const msgObj = {
                type: 'chat',
                message: msg,
                sender: uName + (isPrivateMode ? ' (Özəl)' : ''),
                isPrivate: isPrivateMode
            };
            dataConn.send(msgObj);
            appendChatMessage('Mən' + (isPrivateMode ? ' -> Müəllim' : ''), msg, isPrivateMode ? 'özəl' : 'mən');
            input.value = '';
        } else {
            showAlert("Hələ qoşulmamısınız!");
        }
    }

    function handleFileSelect(input) {
        const file = input.files[0];
        if (!file) return;

        // 5MB Limit Check
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            showAlert("Fayl çox böyükdür (Max 5MB)!", "error");
            alert("Xəta: Seçdiyiniz faylın ölçüsü 5MB-dan çoxdur. Zəhmət olmasa daha kiçik fayl seçin.");
            input.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        const xhr = new XMLHttpRequest();
        showAlert(`Gözləyin: 0% yüklənir...`, "info", false);

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                showAlert(`Yüklənir: ${percent}%...`, "info", false);
            }
        };

        xhr.onload = () => {
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success) {
                    showAlert("Fayl yükləndi! ✅", "success", true);
                    if (dataConn && dataConn.open) {
                        dataConn.send({ type: 'file', fileData: data.url, fileName: data.fileName, sender: uName });
                        appendFileMessage("Mən", data.fileName, data.url, "mən");
                    } else { alert("Bağlantı yoxdur."); }
                } else {
                    showAlert("Yükləmə alınmadı ❌", "error", true);
                    alert("Xəta: " + data.message);
                }
            } catch (e) {
                showAlert("Server xətası ❌", "error", true);
            }
        };

        xhr.onerror = () => {
            showAlert("Bağlantı xətası ❌", "error", true);
        };

        xhr.open('POST', '../api/upload_chat_file.php');
        xhr.send(formData);
        input.value = '';
    }


    // --- WEBRTC LOGIC ---
    async function getMediaStream(withAudio = true, withVideo = true) {
        const constraints = {};
        if (withAudio) constraints.audio = true;
        if (withVideo) constraints.video = true;

        try {
            return await navigator.mediaDevices.getUserMedia(constraints);
        } catch (err) {
            console.error(`Media access failed (A:${withAudio}, V:${withVideo}):`, err.name, err.message);

            // If permission denied, request via alert
            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                showAlert("Kamera/Mikrofon icazəsi rədd edildi! URL sətrindən icazə verin.", "error");
                throw err;
            }

            // If camera is busy (NotReadableError) AND we want video, try generic fallbacks
            if (withVideo && (err.name === 'NotReadableError' || err.name === 'TrackStartError')) {
                console.warn("Camera busy/locked. Trying to find alternative camera...");
                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const videoDevices = devices.filter(d => d.kind === 'videoinput');
                    // If we have multiple cameras, try the others one by one
                    if (videoDevices.length > 1) {
                        for (const device of videoDevices) {
                            // Try this specific device
                            console.log("Trying alternative device:", device.label);
                            const altConstraints = { ...constraints }; // copy
                            altConstraints.video = { deviceId: { exact: device.deviceId } };
                            try {
                                const s = await navigator.mediaDevices.getUserMedia(altConstraints);
                                console.log("Success with alternative device:", device.label);
                                return s;
                            } catch (e2) {
                                console.warn("Failed with alternative device:", e2);
                            }
                        }
                    }
                } catch (enumErr) {
                    console.error("Device enumeration failed:", enumErr);
                }
            }

            throw err;
        }
    }

    function createDummyStream(realAudioTrack = null) {
        const canvas = document.createElement('canvas');
        canvas.width = 640; canvas.height = 480;
        const ctx = canvas.getContext('2d');

        ctx.fillStyle = "#111827"; ctx.fillRect(0, 0, 640, 480);
        const grad = ctx.createRadialGradient(320, 240, 50, 320, 240, 300);
        grad.addColorStop(0, "#1f2937");
        grad.addColorStop(1, "#111827");
        ctx.fillStyle = grad; ctx.fillRect(0, 0, 640, 480);

        ctx.fillStyle = "#ef4444"; ctx.font = "bold 30px sans-serif";
        ctx.textAlign = "center";
        ctx.fillText("📷 Kamera Tapılmadı / Məşğul", 320, 220);

        ctx.fillStyle = "#94a3b8"; ctx.font = "14px sans-serif";
        ctx.fillText("Digər tabda (Müəllim) kamera açıqdırsa, onu bağlayın.", 320, 260);

        ctx.fillStyle = "rgba(255,255,255,0.7)"; ctx.font = "italic 16px sans-serif";
        ctx.fillText("Aşağıdakı kamera düyməsi ilə yenidən yoxlaya bilərsiniz", 320, 300);

        ctx.fillStyle = "#3b82f6"; ctx.font = "600 18px sans-serif";
        ctx.fillText("Tələbə: <?php echo e($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>", 320, 350);

        const vidStream = canvas.captureStream(10);
        const vTrack = vidStream.getVideoTracks()[0];

        let aTrack = realAudioTrack;
        if (!aTrack) {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioCtx.createOscillator();
                const dst = oscillator.connect(audioCtx.createMediaStreamDestination());
                oscillator.start();
                aTrack = dst.stream.getAudioTracks()[0];
            } catch (e) { console.error("Dummy audio failed", e); }
        }

        localMediaStream = new MediaStream();
        if (vTrack) localMediaStream.addTrack(vTrack);
        if (aTrack) localMediaStream.addTrack(aTrack);

        isDummyStream = true;
        return localMediaStream;
    }

    async function initStudent() {
        document.getElementById('overlay').style.display = 'none';

        try {
            try {
                // Step 1: Try both Audio and Video
                localMediaStream = await getMediaStream(true, true);
                isDummyStream = false;
            } catch (err) {
                try {
                    // Step 2: Try Video only
                    localMediaStream = await getMediaStream(false, true);
                    isDummyStream = false;
                    DBG("⚠️ Mikrofon tapılmadı, yalnız kamera ilə davam edilir.");
                } catch (err2) {
                    // No video available. Try to at least get real audio or go full dummy.
                    let realA = null;
                    try {
                        const aOnly = await getMediaStream(true, false);
                        realA = aOnly.getAudioTracks()[0];
                        DBG("⚠️ Kamera tapılmadı, yalnız mikrofon aktivdir.");
                    } catch (err3) {
                        DBG("⚠️ Kamera və mikrofon tapılmadı.");
                    }
                    createDummyStream(realA);
                }
            }
        } catch (e) {
            console.error("Media init error:", e);
            createDummyStream();
        }

        if (localMediaStream) {
            localMediaStream.getAudioTracks().forEach(t => t.enabled = false);
            document.getElementById('localPreview').srcObject = localMediaStream;
            document.getElementById('localControlsWrapper').style.display = 'block';
            updateCamUI(!isDummyStream);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        // Start connection attempt after media setup
        setTimeout(() => tryConnect(false), 500);
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

    function tryConnect(useCloud = false) {
        DBG("Sinyal serverinə qoşulur...");
        const config = useCloud ? {
            debug: 1,
            host: '0.peerjs.com',
            port: 443,
            secure: true,
            config: iceServers
        } : peerConfig;

        if (p) p.destroy();
        p = new Peer(config);

        p.on('open', (id) => {
            signalReady = true;
            DBG(useCloud ? "✅ Bulud serverinə qoşuldu! Müəllim axtarılır..." : "✅ Lokal serverə qoşuldu! Müəllim axtarılır...");

            // Start discovery only after peer is open
            if (discoveryInterval) clearInterval(discoveryInterval);
            discoveryInterval = setInterval(startDiscovery, 800);
            startDiscovery();
        });

        p.on('call', (call) => {
            DBG(`📞 Müəllimdən zəng gəldi`);
            call.answer(localMediaStream);
            call.on('stream', (remoteStream) => {
                DBG(`📹 Müəllim görüntüsü alındı ✅`);
                showRemoteVideo(remoteStream);
            });
            call.on('close', () => {
                DBG(`❌ Zəng bağlandı`);
                attemptReconnect();
            });
        });

        p.on('error', (err) => {
            console.error("PeerJS Error:", err);
            if (err.type === 'peer-unavailable') {
                DBG("⚠️ Müəllim tapılmadı. Yenidən cəhd edilir...");
                attemptReconnect();
            } else if (!useCloud && (err.type === 'network' || err.type === 'server-error' || err.type === 'socket-error')) {
                DBG("⚠️ Lokal server (9000) tapılmadı. Buluda keçid edilir...");
                setTimeout(() => tryConnect(true), 2000);
            } else {
                DBG("❌ Xəta: " + err.type);
            }
        });
    }

    function attemptReconnect() {
        isConnecting = false;
        dataConn = null;
        DBG(`🔄 Yenidən axtarılır...`);
        startDiscovery();
    }

    async function startDiscovery() {
        if (isConnecting || !signalReady) return;
        try {
            const resp = await fetch('api/get_current_peer.php?id=' + lID + '&t=' + Date.now());
            const data = await resp.json();

            if (data.success && data.peer_id) {
                const teacherServerIsCloud = (data.server === 'cloud');
                const currentServerIsCloud = p.options.host === '0.peerjs.com';

                // 1. Check for server mismatch
                if (teacherServerIsCloud !== currentServerIsCloud) {
                    DBG(`🔄 Server dəyişdi (${data.server}). Keçid edilir...`);
                    tryConnect(teacherServerIsCloud);
                    return;
                }

                // 2. Check if peer ID changed (Refreshed)
                if (dataConn && dataConn.peer !== data.peer_id) {
                    DBG("🔄 Müəllim sessiyası yeniləndi. Yenidən qoşulur...");
                    try { dataConn.close(); } catch (e) { }
                    const vid = document.getElementById('remVid');
                    if (vid) vid.srcObject = null;
                    dataConn = null;
                    isConnecting = false;
                }

                // 3. Connect if no active connection
                if (!dataConn || !dataConn.open) {
                    DBG(`📡 Müəllim hazır (${data.server}). Qoşulur...`);
                    isConnecting = true;
                    connectToTeacher(data.peer_id);
                }
            }
        } catch (e) {
            isConnecting = false;
        }
    }

    function connectToTeacher(teacherId) {
        let connTimeout = setTimeout(() => {
            if (isConnecting) {
                DBG("⚠️ Bağlantı zamanı aşımı (NAT problemi). Yenidən cəhd edilir...");
                isConnecting = false;
                if (dataConn) { try { dataConn.close(); } catch (e) { } }
                dataConn = null;
                startDiscovery();
            }
        }, 4000);

        dataConn = p.connect(teacherId, { serialization: 'json', metadata: { name: uName, userId: uID } });

        dataConn.on('open', () => {
            clearTimeout(connTimeout);
            isConnecting = false;
            DBG("🔗 Müəllim ilə data bağlantısı quruldu ✅");
            // Call teacher immediately
            initiateCall(teacherId);
        });

        dataConn.on('data', (d) => {
            if (d.type === 'chat') {
                const type = d.isPrivate ? 'özəl' : (d.sender.includes('Müəllim') ? 'müəllim' : 'tələbə');
                appendChatMessage(d.sender, d.message, type);
            } else if (d.type === 'file') {
                appendFileMessage(d.sender, d.fileName, d.fileData);
            } else if (d.type === 'mic_approved') {
                micApproved = true; micRequested = false; setMic(true);
                showAlert("Söz verildi! 🎤");
                document.getElementById('btnMicItem').innerHTML = '<i data-lucide="mic" style="width: 20px; height: 20px; color: white;"></i>';
                if (window.lucide) lucide.createIcons();
                document.getElementById('btnMicItem').style.background = '#22c55e';
            }
            if (d.type === 'mute_force' || d.type === 'mic_rejected') {
                micApproved = false; micRequested = false; setMic(false);
                document.getElementById('btnMicItem').innerHTML = '<i data-lucide="hand" style="width: 20px; height: 20px; color: white;"></i>';
                if (window.lucide) lucide.createIcons();
                document.getElementById('btnMicItem').style.background = '#ef4444';
            } else if (d.type === 'whiteboard_approved') {
                startActualWhiteboard();
            } else if (d.type === 'whiteboard_rejected') {
                showAlert("Müəllim lövhə istəyini rədd etdi.", "error");
                document.getElementById('btnWhiteboardItem').style.background = 'rgba(255,255,255,0.1)';
            } else if (d.type === 'whiteboard_force_stop') {
                if (isWhiteboardActive) {
                    stopWhiteboard();
                    showAlert("⚠️ Müəllim lövhəni bağladı!", "error");
                }
            }
            if (d.type === 'notification') {
                const colors = {
                    'info': '#3b82f6',
                    'success': '#10b981',
                    'warning': '#f59e0b',
                    'error': '#ef4444'
                };
                const color = colors[d.style] || colors.info;

                // Show a beautiful notification toast
                const toast = document.createElement('div');
                toast.className = 'live-notification-toast';
                toast.style = `position:fixed; top:80px; right:20px; background:#1e293b; border:1px solid rgba(255,255,255,0.1); border-left:5px solid ${color}; color:white; padding:20px; border-radius:16px; z-index:99999; box-shadow:0 25px 50px -12px rgba(0,0,0,0.8); min-width:320px; max-width:400px; backdrop-filter:blur(10px); animation: fadeInRight 0.4s cubic-bezier(0.16, 1, 0.3, 1);`;
                toast.innerHTML = `
                    <div style="font-weight:850; font-size:12px; margin-bottom:8px; color:${color}; text-transform:uppercase; letter-spacing:1.5px; display:flex; align-items:center; justify-content:space-between;">
                        <span style="display:flex; align-items:center; gap:8px;"><span style="font-size:18px;">📢</span> ${d.title || 'Müəllim Bildirişi'}</span>
                        <button onclick="this.closest('.live-notification-toast').remove()" style="background:none; border:none; color:#64748b; font-size:24px; cursor:pointer; line-height:1;">&times;</button>
                    </div>
                    <div style="font-size:14px; line-height:1.6; color:#cbd5e1; font-weight:500;">${d.message}</div>
                `;
                document.body.appendChild(toast);

                // Add to chat history as well
                appendChatMessage("📢 " + (d.title || 'Bildiriş'), d.message, 'özəl');

                // Auto-remove after 12 seconds
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateX(20px)';
                        toast.style.transition = 'all 0.5s ease-in';
                        setTimeout(() => toast.remove(), 500);
                    }
                }, 12000);
            } else if (d.type === 'screen_share_approved') {
                screenApproved = true;
                screenRequested = false;
                showAlert("Ekran paylaşımı təsdiqləndi! 🚀", "success");
                startActualScreenShare();
            } else if (d.type === 'screen_share_rejected') {
                screenRequested = false;
                showAlert("Ekran paylaşımı müəllim tərəfindən rədd edildi.", "error");
                document.getElementById('btnScreenItem').style.background = "rgba(255,255,255,0.1)";
            }
            if (d.type === 'kick_user') {
                // Stop any streaming immediately
                if (localMediaStream) localMediaStream.getTracks().forEach(t => t.stop());

                // Create a beautiful full-screen overlay for the kick message
                const overlay = document.createElement('div');
                overlay.style = "position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.98); z-index:9999; display:flex; flex-direction:column; align-items:center; justify-content:center; backdrop-filter:blur(10px); color:white; font-family:sans-serif; text-align:center; padding:20px;";
                overlay.innerHTML = `
                    <div style="background:rgba(239, 68, 68, 0.1); width:100px; height:100px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:50px; margin-bottom:20px; border:2px solid #ef4444; color:#ef4444; animation: heartbeat 1.5s ease-in-out infinite;">⚠️</div>
                    <h1 style="font-size:32px; font-weight:850; margin-bottom:12px; letter-spacing:-1px;">Giriş Məhdudlaşdırıldı</h1>
                    <p style="font-size:18px; color:#94a3b8; max-width:400px; line-height:1.6; font-weight:500;">Müəllim tərəfindən dərsi tərk etməniz tələb olundu.</p>
                    <div style="margin-top:40px; font-size:14px; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:1px;">3 saniyə ərzində yönləndirilirsiniz...</div>
                `;
                document.body.appendChild(overlay);

                // Track leave removed
                setTimeout(() => {
                    window.location.href = 'live-classes.php';
                }, 3500);
            }
            if (d.type === 'lesson_ended') {
                if (localMediaStream) localMediaStream.getTracks().forEach(t => t.stop());

                // Clear any existing overlays
                const oldOverlay = document.getElementById('lessonEndOverlay');
                if (oldOverlay) oldOverlay.remove();

                const overlay = document.createElement('div');
                overlay.id = 'lessonEndOverlay';
                overlay.style = "position:fixed; inset:0; background:rgba(2, 6, 23, 0.98); z-index:20000; display:flex; flex-direction:column; align-items:center; justify-content:center; backdrop-filter:blur(20px); color:white; font-family:'Inter', sans-serif; text-align:center; padding:40px; animation: fadeIn 0.5s ease-out;";
                overlay.innerHTML = `
                    <div style="background:rgba(16, 185, 129, 0.1); width:120px; height:120px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:60px; margin-bottom:30px; border:2px solid #10b981; color:#10b981; box-shadow: 0 0 30px rgba(16, 185, 129, 0.2); animation: scaleUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);">🎬</div>
                    <h1 style="font-size:42px; font-weight:900; margin-bottom:15px; letter-spacing:-1.5px; background: linear-gradient(to right, #fff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Dərs Uğurla Bitdi</h1>
                    <p style="font-size:20px; color:#94a3b8; max-width:500px; line-height:1.6; font-weight:500; margin-bottom:40px;">Müəllim dərsi tamamladı. İştirakınız və aktivliyiniz üçün təşəkkür edirik!</p>
                    
                    <div style="display:flex; flex-direction:column; gap:15px; align-items:center;">
                         <div style="background:rgba(255,255,255,0.05); padding:10px 20px; border-radius:100px; color:#64748b; font-size:13px; font-weight:700; letter-spacing:1px; text-transform:uppercase; display:flex; align-items:center; gap:8px;">
                            <span style="width:8px; height:8px; background:#10b981; border-radius:50%;"></span>
                            Yönləndirilirsiniz...
                        </div>
                        <a href="archive.php" style="margin-top:20px; background:#3b82f6; color:white; text-decoration:none; padding:12px 30px; border-radius:12px; font-weight:700; font-size:15px; transition:0.3s; box-shadow: 0 10px 20px rgba(59, 130, 246, 0.2);">
                            Arxivə Get 📚
                        </a>
                    </div>
                `;
                document.body.appendChild(overlay);

                // trackAttendance removed
                setTimeout(() => {
                    window.location.href = 'live-classes.php';
                }, 6000);
            } else if (d.type === 'refresh_stream') {
                showAlert("Müəllim yayımı yeniləmənizi istədi...");
                initiateCall(teacherId);
            }
        });

        // Handle data connection close (teacher refreshed)
        dataConn.on('close', () => {
            DBG("❌ Müəllim ilə bağlantı kəsildi. Yenidən qoşulur...");
            // Clear video to prevent frozen image
            const vid = document.getElementById('remVid');
            if (vid) vid.srcObject = null;

            isConnecting = false;
            dataConn = null;

            // Aggressive discovery restart
            if (discoveryInterval) clearInterval(discoveryInterval);
            discoveryInterval = setInterval(startDiscovery, 1000);
            startDiscovery();
        });

        dataConn.on('error', (err) => {
            DBG("❌ Bağlantı xətası: " + err);
            isConnecting = false;
        });
    }

    function initiateCall(teacherId) {
        DBG("📞 Müəllimə zəng edilir...");
        const call = p.call(teacherId, localMediaStream, { metadata: { name: uName, userId: uID } });
        call.on('stream', (remoteStream) => {
            DBG("📹 Müəllim görüntüsü alındı (call tərəfindən) ✅");
            showRemoteVideo(remoteStream);
        });
        call.on('close', () => DBG("❌ Müəllim ilə əlaqə kəsildi"));
    }

    function showRemoteVideo(stream) {
        const vid = document.getElementById('remVid');
        const vTracks = stream.getVideoTracks().length;
        const aTracks = stream.getAudioTracks().length;

        DBG(`📹 Müəllim yayımı: ${vTracks} video, ${aTracks} audio track tapıldı.`);

        if (vTracks === 0) {
            DBG("⚠️ XƏBƏRDARLIQ: Müəllimdən görüntü gəlmir!");
        }

        vid.srcObject = stream;
        vid.muted = false;
        vid.play().catch(e => {
            DBG("🔇 Otomatik səs bloklandı, 'Unmute' düyməsini istifadə edin.");
            vid.muted = true;
            vid.play();
            document.getElementById('unmuteBtn').style.display = 'flex';
        });
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function unmuteRemoteVideo() {
        const vid = document.getElementById('remVid');
        vid.muted = false;
        vid.play().then(() => document.getElementById('unmuteBtn').style.display = 'none');
    }

    // --- OTHER UI LOGIC ---
    function toggleScreenShare() {
        if (!dataConn || !dataConn.peer) return;

        if (isScreenSharing) {
            stopScreenShare();
        } else if (screenRequested) {
            showAlert("Sorğu artıq göndərilib, gözləyin...");
        } else {
            // Send request to teacher
            screenRequested = true;
            dataConn.send({ type: 'screen_share_request', sender: uName });
            document.getElementById('btnScreenItem').style.background = "#f59e0b";
            showAlert("Ekran paylaşımı üçün sorğu göndərildi...");
        }
    }

    async function startActualScreenShare() {
        try {
            screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
            const screenTrack = screenStream.getVideoTracks()[0];
            camVideoTrack = localMediaStream.getVideoTracks()[0];

            screenTrack.onended = () => { if (isScreenSharing) stopScreenShare(); };

            localMediaStream.removeTrack(camVideoTrack);
            localMediaStream.addTrack(screenTrack);
            document.getElementById('localPreview').srcObject = localMediaStream;

            // Call teacher with specific screen_share metadata
            p.call(dataConn.peer, localMediaStream, {
                metadata: {
                    name: uName,
                    userId: uID,
                    type: 'screen_share'
                }
            });

            document.getElementById('btnScreenItem').style.background = "#10b981";
            isScreenSharing = true;
            showAlert("Ekranınız paylaşılır 🖥️", "success");
        } catch (e) {
            console.error("Screen share error:", e);
            screenRequested = false;
            screenApproved = false;
            document.getElementById('btnScreenItem').style.background = "rgba(255,255,255,0.1)";
        }
    }

    function stopScreenShare() {
        if (screenStream) screenStream.getTracks().forEach(t => t.stop());
        screenStream = null;

        if (camVideoTrack) {
            localMediaStream.removeTrack(localMediaStream.getVideoTracks()[0]);
            localMediaStream.addTrack(camVideoTrack);
            document.getElementById('localPreview').srcObject = localMediaStream;
        }

        // Call teacher back with normal stream (re-establish default feed)
        p.call(dataConn.peer, localMediaStream, { metadata: { name: uName, userId: uID } });

        // Instant data signal to teacher to switch back
        if (dataConn && dataConn.open) {
            dataConn.send({ type: 'screen_share_ended' });
        }

        document.getElementById('btnScreenItem').style.background = "rgba(255,255,255,0.1)";
        isScreenSharing = false;
        screenApproved = false;
        screenRequested = false;
        showAlert("Ekran paylaşımı dayandırıldı.");
    }

    // === HEARTBEAT INTERVAL (IMG-based for reliability) ===
    let heartbeatInterval = null;

    function sendHeartbeat() {
        // Only send heartbeat if signal is ready
        if (!signalReady) return;

        const peerId = (p && p.id) ? p.id : '';
        const img = new Image();
        img.src = `api/heartbeat.php?id=${lID}&peer_id=${peerId}&t=${Date.now()}`;
        img.onload = () => console.log('💓 Heartbeat:', new Date().toLocaleTimeString());
        img.onerror = () => console.error('❌ Heartbeat xətası');
    }

    function startHeartbeat() {
        if (heartbeatInterval) clearInterval(heartbeatInterval);
        sendHeartbeat();
        console.log('✅ Heartbeat sistemi aktivləşdirildi');
        // Hər 5 saniyədə bir (12 saniyəlik timeout üçün mükəmməldir)
        heartbeatInterval = setInterval(sendHeartbeat, 5000);
    }

    // Start after 3 seconds
    setTimeout(startHeartbeat, 3000);

    // Restart when tab becomes visible
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            console.log('📱 Tab aktiv - heartbeat restart');
            startHeartbeat();
        }
    });

    window.addEventListener('beforeunload', () => {
        const peerId = (p && p.id) ? p.id : '';
        navigator.sendBeacon(`api/heartbeat.php?id=${lID}&peer_id=${peerId}&action=leave&t=${Date.now()}`);
    });

    function leaveLesson() {
        if (confirm("Canlı dərsdən ayrılmaq istədiyinizə əminsiniz?")) {
            console.log("🚀 Dərsi tərk edirsiniz...");
            const peerId = (p && p.id) ? p.id : '';
            navigator.sendBeacon(`api/heartbeat.php?id=${lID}&peer_id=${peerId}&action=leave&t=${Date.now()}`);
            setTimeout(() => {
                window.location.href = 'live-classes.php';
            }, 500);
        }
    }

    // === WHITEBOARD LOGIC ===
    var isWhiteboardActive = false;
    var wbCanvas = null, wbCtx = null;
    var isDrawing = false, wbSnapshot = null;
    var startX = 0, startY = 0, lastX = 0, lastY = 0;
    var wbColor = '#000000', wbTool = 'pencil', wbBgType = 'plain';
    var eraserSize = 30, pencilSize = 3;
    var wbPages = [], currentPageIndex = 0;
    let undoStack = [], redoStack = [];
    const MAX_HISTORY = 30;
    var wbCall = null;
    var wbLaserSnapshot = null;
    var wbDPR = 1;

    function toggleWhiteboard() {
        if (!isWhiteboardActive) {
            if (!dataConn || !dataConn.open) { showAlert("Müəllimə qoşulmayıb!"); return; }
            dataConn.send({ type: 'whiteboard_request', sender: uName });
            showAlert("Lövhə üçün icazə istənildi...");
            document.getElementById('btnWhiteboardItem').style.background = '#f59e0b';
        } else {
            stopWhiteboard();
        }
    }
    // ... (unchanged functions) ...

    function setWBTool(t) {
        // Exit Laser Mode
        if (wbTool === 'laser' && wbLaserSnapshot) {
            wbCtx.putImageData(wbLaserSnapshot, 0, 0);
            wbLaserSnapshot = null;
        }

        wbTool = t;

        document.querySelectorAll('.wb-tool-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tool' + t.charAt(0).toUpperCase() + t.slice(1))?.classList.add('active');

        // Enter Laser Mode
        if (t === 'laser') {
            wbLaserSnapshot = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
            wbCanvas.style.cursor = 'none';
        } else {
            wbCanvas.style.cursor = 'crosshair';
        }
    }

    let wbStreamCanvas, wbStreamCtx, wbStreamInterval;

    function startActualWhiteboard() {
        isWhiteboardActive = true;
        document.getElementById('whiteboardOverlay').style.display = 'flex';
        document.getElementById('btnWhiteboardItem').style.background = '#3b82f6';
        initWBCanvas();

        // --- COMPOSITING SETUP FOR STREAM ---
        if (!wbStreamCanvas) {
            wbStreamCanvas = document.createElement('canvas');
            wbStreamCtx = wbStreamCanvas.getContext('2d');
        }
        // Match size
        wbStreamCanvas.width = wbCanvas.width;
        wbStreamCanvas.height = wbCanvas.height;

        // Start Loop
        if (wbStreamInterval) clearInterval(wbStreamInterval);
        wbStreamInterval = setInterval(() => {
            if (!wbCanvas) return;
            // 1. Fill White Background
            wbStreamCtx.fillStyle = '#ffffff';
            wbStreamCtx.fillRect(0, 0, wbStreamCanvas.width, wbStreamCanvas.height);

            // 2. Draw Grid/Lines
            if (wbBgType !== 'plain') {
                wbStreamCtx.beginPath();
                wbStreamCtx.strokeStyle = '#cbd5e1';
                wbStreamCtx.lineWidth = 1 * wbDPR;
                if (wbBgType === 'grid') {
                    const step = 30 * wbDPR;
                    for (let x = step; x < wbStreamCanvas.width; x += step) { wbStreamCtx.moveTo(x, 0); wbStreamCtx.lineTo(x, wbStreamCanvas.height); }
                    for (let y = step; y < wbStreamCanvas.height; y += step) { wbStreamCtx.moveTo(0, y); wbStreamCtx.lineTo(wbStreamCanvas.width, y); }
                } else if (wbBgType === 'lines') {
                    const step = 25 * wbDPR;
                    for (let y = step; y < wbStreamCanvas.height; y += step) { wbStreamCtx.moveTo(0, y); wbStreamCtx.lineTo(wbStreamCanvas.width, y); }
                }
                wbStreamCtx.stroke();
            }

            // 3. Draw Actual Drawings
            wbStreamCtx.drawImage(wbCanvas, 0, 0);
        }, 80); // ~12 FPS for lower latency and bandwidth usage

        const wbStream = wbStreamCanvas.captureStream(12);
        wbCall = p.call(dataConn.peer, wbStream, { metadata: { type: 'screen_share', name: uName, userId: uID } });
        showAlert("Lövhə aktivdir!");
    }

    function stopWhiteboard() {
        isWhiteboardActive = false;
        if (wbStreamInterval) clearInterval(wbStreamInterval);
        document.getElementById('whiteboardOverlay').style.display = 'none';
        document.getElementById('btnWhiteboardItem').style.background = 'rgba(255,255,255,0.1)';
        if (wbCall) { wbCall.close(); wbCall = null; }
        if (dataConn && dataConn.open) dataConn.send({ type: 'whiteboard_ended' });
        showAlert("Lövhə bağlandı.");
    }

    function initWBCanvas() {
        if (wbCanvas) return;
        wbCanvas = document.getElementById('wbCanvasInternal');
        wbCtx = wbCanvas.getContext('2d');
        const resize = () => {
            const container = wbCanvas.parentElement;
            wbDPR = window.devicePixelRatio || 1;
            let tempImg = (wbCanvas.width > 0 && wbCanvas.height > 0) ? wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height) : null;

            wbCanvas.width = container.clientWidth * wbDPR;
            wbCanvas.height = container.clientHeight * wbDPR;
            wbCanvas.style.width = container.clientWidth + 'px';
            wbCanvas.style.height = container.clientHeight + 'px';

            wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
            if (tempImg) wbCtx.putImageData(tempImg, 0, 0);
            else saveState();
            setWBBackground(wbBgType);

            // Sync Stream Canvas if exists
            if (wbStreamCanvas) {
                wbStreamCanvas.width = wbCanvas.width;
                wbStreamCanvas.height = wbCanvas.height;
            }
        };
        window.addEventListener('resize', resize);
        resize();
        if (wbPages.length === 0) { wbPages.push(null); updatePageIndicator(); }

        wbCanvas.onmousedown = (e) => {
            const mx = e.offsetX * wbDPR, my = e.offsetY * wbDPR;
            if (wbTool === 'laser') return;
            if (wbTool === 'text') { drawText(mx, my); return; }
            saveState();
            isDrawing = true;
            [startX, startY] = [mx, my];
            [lastX, lastY] = [mx, my];
            wbSnapshot = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
        };
        wbCanvas.onmousemove = (e) => {
            const mx = e.offsetX * wbDPR, my = e.offsetY * wbDPR;
            if (wbTool === 'laser') {
                if (wbLaserSnapshot) wbCtx.putImageData(wbLaserSnapshot, 0, 0);
                drawLaser(mx, my);
                return;
            }
            if (!isDrawing) return;
            if (wbTool === 'pencil' || wbTool === 'eraser') drawFreehand(mx, my);
            else if (wbTool !== 'text') {
                wbCtx.putImageData(wbSnapshot, 0, 0);
                drawShape(mx, my);
            }
        };
        wbCanvas.onmouseup = () => { isDrawing = false; wbSnapshot = null; };
        wbCanvas.onmouseout = () => {
            if (wbTool === 'laser' && wbLaserSnapshot) wbCtx.putImageData(wbLaserSnapshot, 0, 0);
        };
    }

    function saveCurrentPage() { if (wbCanvas && wbCtx) wbPages[currentPageIndex] = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height); }
    function loadPage(index) {
        if (wbPages[index]) wbCtx.putImageData(wbPages[index], 0, 0);
        else wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
        updatePageIndicator();
    }
    function addNewPage() { saveCurrentPage(); currentPageIndex = wbPages.length; wbPages.push(null); wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height); updatePageIndicator(); }
    function prevPage() { if (currentPageIndex > 0) { saveCurrentPage(); currentPageIndex--; loadPage(currentPageIndex); } }
    function nextPage() { if (currentPageIndex < wbPages.length - 1) { saveCurrentPage(); currentPageIndex++; loadPage(currentPageIndex); } }
    function deletePage() {
        if (wbPages.length <= 1) return;
        if (confirm("Silinsin?")) {
            wbPages.splice(currentPageIndex, 1);
            if (currentPageIndex >= wbPages.length) currentPageIndex = wbPages.length - 1;
            loadPage(currentPageIndex);
        }
    }
    function updatePageIndicator() { document.getElementById('pageIndicator').innerText = (currentPageIndex + 1) + '/' + wbPages.length; }
    function saveState() {
        if (undoStack.length >= MAX_HISTORY) undoStack.shift();
        undoStack.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height));
        redoStack = [];
    }
    function undo() { if (undoStack.length > 0) { redoStack.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height)); wbCtx.putImageData(undoStack.pop(), 0, 0); } }
    function redo() { if (redoStack.length > 0) { undoStack.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height)); wbCtx.putImageData(redoStack.pop(), 0, 0); } }
    function setWBBackground(type) {
        wbBgType = type;
        document.querySelectorAll('[id^="bg"]').forEach(b => b.classList.remove('active'));
        document.getElementById('bg' + type.charAt(0).toUpperCase() + type.slice(1)).classList.add('active');
        const canvasEl = document.getElementById('wbCanvasInternal');
        if (type === 'grid') { canvasEl.style.backgroundImage = 'linear-gradient(#94a3b8 1px, transparent 1px), linear-gradient(90deg, #94a3b8 1px, transparent 1px)'; canvasEl.style.backgroundSize = '30px 30px'; }
        else if (type === 'lines') { canvasEl.style.backgroundImage = 'linear-gradient(#94a3b8 1px, transparent 1px)'; canvasEl.style.backgroundSize = '100% 25px'; }
        else canvasEl.style.backgroundImage = 'none';
        canvasEl.style.backgroundColor = 'white';
    }

    function setWBColor(c, el) { wbColor = c; document.querySelectorAll('.wb_color_item').forEach(i => i.classList.remove('active')); el.classList.add('active'); }
    function changeSize(d) { if (wbTool === 'eraser') eraserSize = Math.max(10, Math.min(100, eraserSize + d)); else pencilSize = Math.max(1, Math.min(20, pencilSize + d)); document.getElementById('sizeDisplay').innerText = (wbTool === 'eraser' ? eraserSize : pencilSize) + 'px'; }
    function drawFreehand(x, y) {
        wbCtx.beginPath(); wbCtx.moveTo(lastX, lastY); wbCtx.lineTo(x, y);
        if (wbTool === 'eraser') { wbCtx.globalCompositeOperation = 'destination-out'; wbCtx.lineWidth = eraserSize * wbDPR; }
        else { wbCtx.globalCompositeOperation = 'source-over'; wbCtx.strokeStyle = wbColor; wbCtx.lineWidth = pencilSize * wbDPR; }
        wbCtx.lineCap = 'round'; wbCtx.lineJoin = 'round'; wbCtx.stroke();
        wbCtx.globalCompositeOperation = 'source-over';[lastX, lastY] = [x, y];
    }
    function drawShape(x, y) {
        wbCtx.beginPath(); wbCtx.strokeStyle = wbColor; wbCtx.lineWidth = 3 * wbDPR;
        if (wbTool === 'line') { wbCtx.moveTo(startX, startY); wbCtx.lineTo(x, y); }
        else if (wbTool === 'rect') wbCtx.strokeRect(startX, startY, x - startX, y - startY);
        else if (wbTool === 'circle') wbCtx.arc(startX, startY, Math.sqrt(Math.pow(x - startX, 2) + Math.pow(y - startY, 2)), 0, 2 * Math.PI);
        else if (wbTool === 'arrow') {
            const headlen = 15 * wbDPR, angle = Math.atan2(y - startY, x - startX);
            wbCtx.moveTo(startX, startY); wbCtx.lineTo(x, y);
            wbCtx.lineTo(x - headlen * Math.cos(angle - Math.PI / 6), y - headlen * Math.sin(angle - Math.PI / 6));
            wbCtx.moveTo(x, y); wbCtx.lineTo(x - headlen * Math.cos(angle + Math.PI / 6), y - headlen * Math.sin(angle + Math.PI / 6));
        }
        wbCtx.stroke();
    }
    function drawText(x, y) { const t = prompt("Mətn:"); if (t) { saveState(); wbCtx.font = (24 * wbDPR) + "px Inter"; wbCtx.fillStyle = wbColor; wbCtx.fillText(t, x, y); } }

    function drawLaser(x, y) {
        wbCtx.beginPath();
        wbCtx.fillStyle = 'rgba(239, 68, 68, 0.8)'; // Red with slight transparency
        wbCtx.shadowBlur = 15 * wbDPR;
        wbCtx.shadowColor = "#ef4444";
        wbCtx.arc(x, y, 6 * wbDPR, 0, Math.PI * 2);
        wbCtx.fill();
        wbCtx.shadowBlur = 0; // Reset
    }
    var placementImg = null;
    var isDraggingImage = false, isResizingImage = false;
    var imgDragStartX = 0, imgDragStartY = 0, imgStartLeft = 0, imgStartTop = 0;
    var imgStartWidth = 0, imgStartHeight = 0, imgAspectRatio = 1;

    function wbUploadImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = (e) => {
                placementImg = new Image();
                placementImg.onload = () => showImagePlacement(placementImg);
                placementImg.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
            input.value = '';
        }
    }

    function showImagePlacement(img) {
        const overlay = document.getElementById('imagePlacementOverlay');
        const container = document.getElementById('imagePlacementContainer');
        const imgEl = document.getElementById('placementImage');
        imgEl.src = img.src;
        imgAspectRatio = img.width / img.height;
        const maxW = wbCanvas.width * 0.5, maxH = wbCanvas.height * 0.5;
        let w, h;
        if (img.width / img.height > maxW / maxH) { w = maxW; h = w / imgAspectRatio; }
        else { h = maxH; w = h * imgAspectRatio; }
        const left = (wbCanvas.width - w) / 2, top = (wbCanvas.height - h) / 2;
        container.style.left = left + 'px'; container.style.top = top + 'px';
        container.style.width = w + 'px'; container.style.height = h + 'px';
        overlay.style.display = 'block';
        container.onmousedown = startImageDrag;
        document.getElementById('resizeHandle').onmousedown = startImageResize;
    }

    function startImageDrag(e) {
        if (e.target.id === 'resizeHandle') return;
        e.preventDefault();
        isDraggingImage = true;
        const container = document.getElementById('imagePlacementContainer');
        imgDragStartX = e.clientX; imgDragStartY = e.clientY;
        imgStartLeft = parseInt(container.style.left); imgStartTop = parseInt(container.style.top);
        document.onmousemove = dragImage; document.onmouseup = stopImageDrag;
    }
    function dragImage(e) {
        if (!isDraggingImage) return;
        const container = document.getElementById('imagePlacementContainer');
        container.style.left = (imgStartLeft + (e.clientX - imgDragStartX)) + 'px';
        container.style.top = (imgStartTop + (e.clientY - imgDragStartY)) + 'px';
    }
    function stopImageDrag() { isDraggingImage = false; document.onmousemove = null; document.onmouseup = null; }
    function startImageResize(e) {
        e.preventDefault(); e.stopPropagation();
        isResizingImage = true;
        const container = document.getElementById('imagePlacementContainer');
        imgDragStartX = e.clientX; imgStartWidth = parseInt(container.style.width);
        document.onmousemove = resizeImage; document.onmouseup = stopImageResize;
    }
    function resizeImage(e) {
        if (!isResizingImage) return;
        const container = document.getElementById('imagePlacementContainer');
        let newW = Math.max(50, imgStartWidth + (e.clientX - imgDragStartX));
        container.style.width = newW + 'px'; container.style.height = (newW / imgAspectRatio) + 'px';
    }
    function stopImageResize() { isResizingImage = false; document.onmousemove = null; document.onmouseup = null; }

    function confirmImagePlacement() {
        const container = document.getElementById('imagePlacementContainer');
        const x = parseInt(container.style.left), y = parseInt(container.style.top);
        const w = parseInt(container.style.width), h = parseInt(container.style.height);
        saveState();
        wbCtx.drawImage(placementImg, x, y, w, h);
        document.getElementById('imagePlacementOverlay').style.display = 'none';
        placementImg = null;
    }
    function cancelImagePlacement() { document.getElementById('imagePlacementOverlay').style.display = 'none'; placementImg = null; }
    function clearWhiteboard() { if (confirm("Təmizlənsin?")) { saveState(); wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height); } }
    function exportWhiteboard() { const link = document.createElement('a'); link.download = 'whiteboard.png'; link.href = wbCanvas.toDataURL(); link.click(); }
</script>
<!-- Pure Whiteboard Overlay -->
<div id="whiteboardOverlay">
    <!-- Floating Toolbar -->
    <div class="wb-controls-floating">
        <div class="wb-group">
            <span class="wb-group-label">NÖV</span>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 4px;">
                <button id="bgPlain" class="wb-tool-btn active" onclick="setWBBackground('plain')"
                    title="Ağ Fon">⬜</button>
                <button id="bgGrid" class="wb-tool-btn" onclick="setWBBackground('grid')" title="Dama">📏</button>
                <button id="bgLines" class="wb-tool-btn" onclick="setWBBackground('lines')" title="Xətli">📝</button>
            </div>
        </div>
        <div class="wb-group">
            <span class="wb-group-label">ADDIM</span>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                <button class="wb-tool-btn" onclick="undo()">↩️</button>
                <button class="wb-tool-btn" onclick="redo()">↪️</button>
            </div>
        </div>
        <div class="wb-group">
            <span class="wb-group-label">ALƏTLƏR</span>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                <button id="toolPencil" class="wb-tool-btn active" onclick="setWBTool('pencil')">✏️</button>
                <button id="toolEraser" class="wb-tool-btn" onclick="setWBTool('eraser')">🧽</button>
                <button id="toolText" class="wb-tool-btn" onclick="setWBTool('text')">🔤</button>
                <button id="toolLaser" class="wb-tool-btn" onclick="setWBTool('laser')">🔦</button>
                <button class="wb-tool-btn" onclick="document.getElementById('wbImgInput').click()"
                    style="grid-column: span 2;">🖼️</button>
            </div>
            <div id="sizeControl"
                style="display: flex; align-items: center; gap: 4px; justify-content: center; margin-top: 6px;">
                <button class="wb-tool-btn" onclick="changeSize(-5)" style="width:28px; height:28px;">−</button>
                <div id="sizeDisplay"
                    style="background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:6px; font-size:11px;">
                    3px
                </div>
                <button class="wb-tool-btn" onclick="changeSize(5)" style="width:28px; height:28px;">+</button>
            </div>
            <input type="file" id="wbImgInput" style="display:none" accept="image/*" onchange="wbUploadImage(this)">
        </div>
        <div class="wb-group">
            <span class="wb-group-label">FİQURLAR</span>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                <button id="toolLine" class="wb-tool-btn" onclick="setWBTool('line')">➖</button>
                <button id="toolRect" class="wb-tool-btn" onclick="setWBTool('rect')">⬜</button>
                <button id="toolCircle" class="wb-tool-btn" onclick="setWBTool('circle')">⭕</button>
                <button id="toolArrow" class="wb-tool-btn" onclick="setWBTool('arrow')">↗️</button>
            </div>
        </div>
        <div class="wb-group">
            <span class="wb-group-label">RƏNG</span>
            <div class="wb-color-grid">
                <div class="wb_color_item wb-color active" style="background: #000000;"
                    onclick="setWBColor('#000000', this)"></div>
                <div class="wb_color_item wb-color" style="background: #ef4444;" onclick="setWBColor('#ef4444', this)">
                </div>
                <div class="wb_color_item wb-color" style="background: #3b82f6;" onclick="setWBColor('#3b82f6', this)">
                </div>
                <div class="wb_color_item wb-color" style="background: #10b981;" onclick="setWBColor('#10b981', this)">
                </div>
            </div>
        </div>
        <div class="wb-group">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 4px;">
                <button class="wb-tool-btn" onclick="clearWhiteboard()">🗑️</button>
                <button class="wb-tool-btn" onclick="exportWhiteboard()">💾</button>
                <button class="wb-tool-btn" onclick="toggleWhiteboard()" style="background:#ef4444;">❌</button>
            </div>
        </div>
        <div class="wb-group" style="border-bottom: none;">
            <div style="display: flex; align-items: center; gap: 4px; justify-content: center;">
                <button class="wb-tool-btn" onclick="prevPage()" style="width:32px; height:32px;">◀</button>
                <div id="pageIndicator"
                    style="background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:6px; font-size:11px;">
                    1/1
                </div>
                <button class="wb-tool-btn" onclick="nextPage()" style="width:32px; height:32px;">▶</button>
                <button class="wb-tool-btn" onclick="addNewPage()"
                    style="width:32px; height:32px; background:rgba(16,185,129,0.2);">+</button>
            </div>
        </div>
    </div>
    <div
        style="position: absolute; top: 20px; right: 20px; background: #1e293b; color: white; padding: 10px 20px; border-radius: 12px; font-weight: 800; font-size: 11px; display: flex; align-items: center; gap: 10px; z-index: 2010;">
        <div style="width: 8px; height: 8px; background: #3b82f6; border-radius: 50%; animation: blink 1s infinite;">
        </div>
        TƏLƏBƏ LÖVHƏSİ PRO
    </div>
    <div id="imagePlacementOverlay"
        style="display: none; position: absolute; inset: 0; z-index: 3000; background: rgba(0,0,0,0.3);">
        <div id="imagePlacementContainer"
            style="position: absolute; cursor: move; border: 2px dashed #3b82f6; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <img id="placementImage" style="width: 100%; height: 100%; object-fit: contain; pointer-events: none;">
            <div id="resizeHandle"
                style="position: absolute; bottom: -8px; right: -8px; width: 16px; height: 16px; background: #3b82f6; border: 2px solid white; border-radius: 50%; cursor: se-resize;">
            </div>
        </div>
        <div
            style="position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); display: flex; gap: 15px;">
            <button onclick="confirmImagePlacement()"
                style="background: #22c55e; color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 700; cursor: pointer;">✓
                Yerləşdir</button>
            <button onclick="cancelImagePlacement()"
                style="background: #ef4444; color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 700; cursor: pointer;">✕
                Ləğv Et</button>
        </div>
    </div>
    <div style="flex: 1; position: relative; background: #ffffff; cursor: crosshair; overflow: hidden;">
        <canvas id="wbCanvasInternal" style="display: block;"></canvas>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>