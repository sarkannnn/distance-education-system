<?php
/**
 * Arxiv Video Pleyer - Watch Lesson
 */
$currentPage = 'archive';
$pageTitle = 'Dərsi İzlə';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireLogin();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? 0;

if (!$type || !$id) {
    header('Location: archive.php');
    exit;
}

$lesson = null;
$videoUrl = '';
$title = '';
$course = '';
$instructor = '';
$date = '';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$baseDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$baseUrl = $protocol . "://" . $host . $baseDir;

try {
    if ($type === 'live') {
        // Canlı dərs yazısı — redundant metadata istifadə et
        $lesson = $db->fetch(
            "SELECT lc.* FROM live_classes lc WHERE lc.id = ?",
            [$id]
        );

        if ($lesson && $lesson['recording_path']) {
            $videoUrl = $baseUrl . '/uploads/videos/' . $lesson['recording_path'];
            $title = $lesson['title'] ?: ($lesson['subject_name'] ?? 'Canlı Dərs');
            $course = $lesson['subject_name'] ?? 'Fənn';
            $instructor = trim($lesson['instructor_name'] ?? 'Müəllim');
            $date = formatDate($lesson['start_time']);
            $duration = ($lesson['duration_minutes'] ?? 0) . ' dəqiqə';
            $description = '';

            // Increment views
            $db->query("UPDATE live_classes SET views = IFNULL(views, 0) + 1 WHERE id = ?", [$id]);
            try {
                tmis_post('/student/archive/' . $id . '/view', ['archive_id' => intval($id), 'viewed_at' => date('Y-m-d H:i:s')]);
            } catch (Exception $e) {
            }
        }
    } else {
        // Arxiv materialı — redundant metadata istifadə et (JOIN-sız)
        $lesson = $db->fetch(
            "SELECT al.* FROM archived_lessons al WHERE al.id = ?",
            [$id]
        );

        if ($lesson && $lesson['video_url']) {
            // Video URL-ni lokal yola çevir
            $rawUrl = $lesson['video_url'];
            if (str_starts_with($rawUrl, 'http')) {
                // Xarici URL — lokal faylla əvəz et
                $filename = basename(parse_url($rawUrl, PHP_URL_PATH));
                $localDir = __DIR__ . '/../teacher/uploads/archive/';
                if (file_exists($localDir . $filename)) {
                    $videoUrl = $baseUrl . '/teacher/uploads/archive/' . $filename;
                } else {
                    // Faylın adını yoxla, yoxsa raw URL istifadə et
                    $videoUrl = $baseUrl . '/teacher/uploads/archive/' . $filename;
                }
            } elseif (str_starts_with($rawUrl, 'teacher/')) {
                $videoUrl = $baseUrl . '/' . $rawUrl;
            } elseif (!str_starts_with($rawUrl, '../') && !str_starts_with($rawUrl, 'http')) {
                $videoUrl = $baseUrl . '/teacher/uploads/archive/' . $rawUrl;
            } else {
                // Remove leading ../ if present and append to base URL
                $cleanUrl = preg_replace('/^(\.\.\/)+/', '', $rawUrl);
                if (str_starts_with($rawUrl, 'http')) {
                     $videoUrl = $rawUrl;
                } else {
                     $videoUrl = $baseUrl . '/' . $cleanUrl;
                }
            }

            $title = $lesson['title'];
            $course = $lesson['subject_name'] ?? 'Ümumi';
            $instructor = trim($lesson['instructor_name'] ?? 'Müəllim');
            $date = formatDate($lesson['archived_date'] ?? $lesson['created_at']);
            $duration = $lesson['duration'] ?? '';
            $description = $lesson['description'] ?? '';

            // Increment views
            $db->query("UPDATE archived_lessons SET views = IFNULL(views, 0) + 1 WHERE id = ?", [$id]);
            try {
                tmis_post('/student/archive/' . $id . '/view', ['archive_id' => intval($id), 'viewed_at' => date('Y-m-d H:i:s')]);
            } catch (Exception $e) {
            }
        }
    }
} catch (Exception $e) {
    // Error handling
}

if (!$videoUrl) {
    header('Location: archive.php?error=not_found');
    exit;
}

require_once 'includes/header.php';
?>

<!-- Sidebar -->
<?php require_once 'includes/sidebar.php'; ?>

<div class="main-wrapper">
    <?php require_once 'includes/topnav.php'; ?>

    <main class="main-content">
        <div class="content-container">
            <!-- Back Button -->
            <div style="margin-bottom: 24px;">
                <a href="archive.php" class="btn btn-secondary"
                    style="display: inline-flex; align-items: center; gap: 8px; background: rgba(0,0,0,0.05); color: var(--text-primary); border: none; padding: 10px 20px; border-radius: 12px; font-weight: 600;">
                    <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
                    Arxivə Qayıt
                </a>
            </div>

            <!-- Video Player Card -->
            <div
                style="background: var(--card-bg); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
                <!-- Player Area -->
                <div style="aspect-ratio: 16/9; background: #000; position: relative;">
                    <video id="player" controls playsinline src="<?php echo e($videoUrl); ?>" style="width: 100%; height: 100%;">
                        Brauzeriniz video pleyeri dəstəkləmir.
                    </video>
                </div>

                <!-- Info Area -->
                <div style="padding: 30px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 300px;">
                            <h1
                                style="font-size: 24px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">
                                <?php echo e($title); ?>
                            </h1>
                            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                <div
                                    style="display: flex; align-items: center; gap: 6px; color: var(--primary); font-weight: 700; font-size: 14px;">
                                    <i data-lucide="book-open" style="width: 16px; height: 16px;"></i>
                                    <?php echo e($course); ?>
                                </div>
                                <div
                                    style="display: flex; align-items: center; gap: 6px; color: var(--text-muted); font-size: 14px; font-weight: 500;">
                                    <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                                    <?php echo e($date); ?>
                                </div>
                                <?php if (!empty($duration)): ?>
                                    <div
                                        style="display: flex; align-items: center; gap: 6px; color: var(--text-muted); font-size: 14px; font-weight: 500;">
                                        <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                                        <?php echo e($duration); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($description)): ?>
                                <p style="margin-top: 12px; color: var(--text-muted); font-size: 14px; line-height: 1.6;">
                                    <?php echo e($description); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; gap: 12px;">
                            <a href="<?php echo e($videoUrl); ?>" download class="btn btn-secondary"
                                style="display: flex; align-items: center; gap: 8px; border-radius: 12px; padding: 12px 24px;">
                                <i data-lucide="download"></i>
                                Yüklə
                            </a>
                        </div>
                    </div>

                    <div
                        style="margin-top: 30px; padding-top: 30px; border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 20px;">
                        <div
                            style="width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 18px;">
                            <?php echo mb_substr($instructor, 0, 1); ?>
                        </div>
                        <div>
                            <p
                                style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">
                                Təlimatçı</p>
                            <p style="font-size: 16px; font-weight: 700; color: var(--text-primary);">
                                <?php echo e($instructor); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') lucide.createIcons();

    // Simple view tracking persistence
    const player = document.getElementById('player');
    let tracked = false;

    player.addEventListener('play', function () {
        if (!tracked) {
            console.log('Video started playing');
            tracked = true;
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>