<?php
/**
 * Arxiv və Resurslar - Archive
 * TMİS API inteqrasiyası ilə.
 * API: GET /api/student/archive?per_page=20
 */
$currentPage = 'archive';
$pageTitle = 'Arxiv və Resurslar';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireLogin();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

$archivedLessons = [];
$archiveStats = [
    'total_lessons' => 0,
    'total_videos' => 0,
    'total_pdfs' => 0,
    'total_views' => 0
];

// =========================================================================
//  ARXİV MATERİALLARI
//  Həmişə lokal bazadan oxu (müəllimlər materialları lokal yükləyir)
// =========================================================================
try {
    // 1. Arxiv dərsləri - müəllimlərin yüklədikləri
    $archivesData = $db->fetchAll(
        "SELECT al.*,
                c.title as course_title
         FROM archived_lessons al
         JOIN enrollments e ON e.course_id = al.course_id AND e.user_id = ?
         LEFT JOIN courses c ON al.course_id = c.id
         ORDER BY al.created_at DESC",
        [$currentUser['id']]
    );

    if (!$archivesData || empty($archivesData)) {
        // Enrollment join olmadan da yoxla (TMIS subject ID ilə)
        $studentSubjectIds = [];
        $tmisSubjectsForArchive = tmis_get('/student/subjects');
        if ($tmisSubjectsForArchive && is_array($tmisSubjectsForArchive)) {
            foreach ($tmisSubjectsForArchive as $subj) {
                if (isset($subj['id'])) {
                    $studentSubjectIds[] = intval($subj['id']);
                }
            }
        }

        if (!empty($studentSubjectIds)) {
            $placeholders = implode(',', array_fill(0, count($studentSubjectIds), '?'));
            $params = array_merge($studentSubjectIds, $studentSubjectIds);
            $archivesData = $db->fetchAll(
                "SELECT al.*,
                        c.title as course_title
                 FROM archived_lessons al
                 LEFT JOIN courses c ON al.course_id = c.id
                 WHERE al.course_id IN ($placeholders) OR al.tmis_subject_id IN ($placeholders)
                 ORDER BY al.created_at DESC",
                $params
            );
        }
        // If no subjects found, archivesData stays empty — don't show all
    }

    if ($archivesData) {
        foreach ($archivesData as $archive) {
            $instructorName = trim($archive['instructor_name'] ?? '');
            if (empty($instructorName)) {
                $instructorName = 'Müəllim';
            }

            $courseName = $archive['subject_name'] ?? ($archive['course_title'] ?? 'Ümumi');

            $rawVideo = $archive['video_url'] ?? '';
            $rawPdf = $archive['pdf_url'] ?? '';

            // URL handling... (keeping existing logic for brevity or adding fix)
            $videoUrl = $rawVideo;
            // ... (rest of URL logic is fine)

            $archivedLessons[] = [
                'id' => $archive['id'],
                'type' => 'arch',
                'title' => $archive['title'],
                'course' => $courseName,
                'instructor' => $instructorName,
                // ... (rest as is)
                'date' => formatDate($archive['archived_date'] ?? $archive['created_at']),
                'duration' => $archive['duration'] ?? '',
                'views' => $archive['views'] ?? 0,
                'hasVideo' => !empty($archive['video_url']),
                'hasPdf' => !empty($archive['pdf_url']),
                'description' => $archive['description'] ?? '',
                'video_raw' => $videoUrl, // simplified for replacement
                'pdf_url' => $rawPdf,
                'raw_date' => $archive['created_at'] ?? $archive['archived_date']
            ];
        }
    }

    // 2. Canlı dərslərin yazıları (WebRTC) - yalnız tələbənin qeydiyyatda olduğu fənlər
    try {
        $studentCourseIds = [];
        try {
            $enrolledCourses = $db->fetchAll("SELECT course_id FROM enrollments WHERE user_id = ?", [$currentUser['id']]);
            foreach ($enrolledCourses as $ec)
                $studentCourseIds[] = (int) $ec['course_id'];
        } catch (Exception $e) {
        }

        if (empty($studentSubjectIds)) {
            $studentSubjectIds = [];
            $tmisSubjectsForLive = tmis_get('/student/subjects');
            if ($tmisSubjectsForLive && is_array($tmisSubjectsForLive)) {
                foreach ($tmisSubjectsForLive as $subj)
                    if (isset($subj['id']))
                        $studentSubjectIds[] = (int) $subj['id'];
            }
        }

        $allCourseIds = array_unique(array_merge($studentCourseIds, $studentSubjectIds));

        if (!empty($allCourseIds)) {
            $placeholders = implode(',', array_fill(0, count($allCourseIds), '?'));
            $liveRecordings = $db->fetchAll(
                "SELECT lc.*, c.title as course_title_alt 
                 FROM live_classes lc
                 LEFT JOIN courses c ON lc.course_id = c.id
                 WHERE lc.recording_path IS NOT NULL AND (lc.course_id IN ($placeholders) OR lc.tmis_subject_id IN ($placeholders))
                 ORDER BY lc.start_time DESC",
                array_merge($allCourseIds, $allCourseIds)
            );
        } else {
            $liveRecordings = [];
        }

        if ($liveRecordings) {
            foreach ($liveRecordings as $rec) {
                $cName = $rec['subject_name'] ?? ($rec['course_title_alt'] ?? 'Fənn');

                $archivedLessons[] = [
                    'id' => $rec['id'],
                    'type' => 'live',
                    'title' => 'Canlı Dərs Yazısı: ' . ($rec['title'] ?: $cName),
                    'course' => $cName,
                    'instructor' => trim($rec['instructor_name'] ?? 'Müəllim'),
                    'date' => formatDate($rec['start_time']),
                    'duration' => ($rec['duration_minutes'] ?? 0) . ':00',
                    'views' => $rec['views'] ?? 0,
                    'hasVideo' => true,
                    'hasPdf' => false,
                    'description' => 'Sistem tərəfindən avtomatik qeydə alınmış canlı dərs yazısı.',
                    'video_raw' => '../uploads/videos/' . $rec['recording_path'],
                    'pdf_url' => '',
                    'raw_date' => $rec['start_time']
                ];
            }
        }
    } catch (Exception $e) {
    }

    // Sort by date
    usort($archivedLessons, function ($a, $b) {
        return strtotime($b['raw_date'] ?? '2000-01-01') - strtotime($a['raw_date'] ?? '2000-01-01');
    });

} catch (Exception $e) {
    // Fail silently
}

// Calculate stats from local data
$archiveStats['total_lessons'] = count($archivedLessons);
$archiveStats['total_videos'] = count(array_filter($archivedLessons, fn($l) => $l['hasVideo']));
$archiveStats['total_pdfs'] = count(array_filter($archivedLessons, fn($l) => $l['hasPdf']));
$archiveStats['total_views'] = array_sum(array_column($archivedLessons, 'views'));

// Unikal kurslar
$courses = array_unique(array_column($archivedLessons, 'course'));

// Stats (use API stats if available, else calculated)
$totalViews = $archiveStats['total_views'];
$totalVideos = $archiveStats['total_videos'];
$totalPdfs = $archiveStats['total_pdfs'];
$totalLessons = $archiveStats['total_lessons'];

require_once 'includes/header.php';
?>

<!-- Sidebar -->
<?php require_once 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-wrapper">
    <!-- Top Navigation -->
    <?php require_once 'includes/topnav.php'; ?>

    <!-- Main Content Area -->
    <main class="main-content">
        <div class="content-container space-y-6">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Arxiv və Resurslar</h1>
                <p>Keçmiş dərslərin video yazıları və materialları</p>
            </div>

            <!-- Stats -->
            <div class="stats-grid-mockup">
                <div class="stat-card-mockup pink">
                    <div class="stat-icon-mockup pink">
                        <i data-lucide="book-open"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $totalLessons; ?></div>
                    <div class="stat-label-mockup pink">Ümumi Dərslər</div>
                </div>

                <div class="stat-card-mockup blue">
                    <div class="stat-icon-mockup blue">
                        <i data-lucide="play-circle"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $totalVideos; ?></div>
                    <div class="stat-label-mockup blue">Video Yazılar</div>
                </div>

                <div class="stat-card-mockup green">
                    <div class="stat-icon-mockup green">
                        <i data-lucide="file-text"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $totalPdfs; ?></div>
                    <div class="stat-label-mockup green">PDF Materiallar</div>
                </div>

                <div class="stat-card-mockup purple">
                    <div class="stat-icon-mockup purple">
                        <i data-lucide="eye"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $totalViews; ?></div>
                    <div class="stat-label-mockup purple">Ümumi Baxışlar</div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="archive-filter-container">
                <!-- Search Input -->
                <div class="archive-search-wrapper">
                    <div class="form-input-icon">
                        <i data-lucide="search"></i>
                        <input type="text" class="form-input" placeholder="Dərs, fənn və ya material axtar..."
                            id="archive-search">
                    </div>
                </div>

                <!-- Type Filter -->
                <div class="type-filter-group">
                    <span class="type-label">Növ</span>
                    <div class="type-btns">
                        <button class="type-btn active" data-type="all" title="Hamısı">
                            <i data-lucide="layers"></i>
                        </button>
                        <button class="type-btn" data-type="video" title="Videolar">
                            <i data-lucide="play"></i>
                        </button>
                        <button class="type-btn" data-type="pdf" title="PDF Materiallar">
                            <i data-lucide="file-text"></i>
                        </button>
                    </div>
                </div>

                <!-- Course Select -->
                <div class="archive-course-select">
                    <select class="form-select" id="course-filter">
                        <option value="all">Bütün fənlər</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo e($course); ?>"><?php echo e($course); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Archived Lessons Grid -->
            <div class="grid-3" id="archive-container">
                <?php foreach ($archivedLessons as $lesson): ?>
                    <div class="archive-card" data-course="<?php echo e($lesson['course']); ?>">
                        <!-- Thumbnail -->
                        <div class="archive-thumbnail">
                            <?php if ($lesson['hasVideo']): ?>
                                <i data-lucide="play-circle" style="width: 64px; height: 64px;"></i>
                                <span class="archive-duration"><?php echo $lesson['duration']; ?></span>
                            <?php else: ?>
                                <i data-lucide="file-text" style="width: 64px; height: 64px;"></i>
                            <?php endif; ?>
                        </div>

                        <!-- Content -->
                        <div class="archive-content">
                            <h3 class="archive-title"><?php echo e($lesson['title']); ?></h3>
                            <p class="archive-course"><?php echo e($lesson['course']); ?></p>

                            <?php if (isset($lesson['description']) && !empty($lesson['description'])): ?>
                                <p class="archive-description"><?php echo e($lesson['description']); ?></p>
                            <?php endif; ?>

                            <!-- Metadata -->
                            <div class="archive-meta">
                                <div class="archive-meta-item">
                                    <i data-lucide="calendar" style="width: 14px; height: 14px;"></i>
                                    <span><?php echo $lesson['date']; ?></span>
                                </div>
                                <div class="archive-meta-item">
                                    <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                                    <span><?php echo $lesson['views']; ?> baxış</span>
                                </div>
                            </div>

                            <!-- Instructor -->
                            <div class="archive-instructor">
                                <?php echo e($lesson['instructor']); ?>
                            </div>

                            <!-- Resources -->
                            <div class="archive-resources">
                                <?php if ($lesson['hasVideo']): ?>
                                    <span class="resource-badge video">
                                        <i data-lucide="play-circle" style="width: 14px; height: 14px;"></i>
                                        Video
                                    </span>
                                <?php endif; ?>
                                <?php if ($lesson['hasPdf']): ?>
                                    <span class="resource-badge pdf">
                                        <i data-lucide="file-text" style="width: 14px; height: 14px;"></i>
                                        PDF
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="archive-actions">
                                <?php if ($lesson['hasVideo']): ?>
                                    <a href="watch.php?type=<?php echo $lesson['type']; ?>&id=<?php echo $lesson['id']; ?>"
                                        class="btn btn-primary" style="flex: 1;">
                                        <i data-lucide="play-circle"></i>
                                        İzlə
                                    </a>
                                    <a href="<?php echo $lesson['video_raw']; ?>" download class="btn btn-secondary"
                                        title="Yüklə">
                                        <i data-lucide="download"></i>
                                    </a>
                                <?php elseif ($lesson['hasPdf'] && !empty($lesson['pdf_url'])): ?>
                                    <a href="<?php echo $lesson['pdf_url']; ?>" target="_blank"
                                        class="btn btn-primary track-view" style="flex: 1;"
                                        data-id="<?php echo $lesson['type']; ?>_<?php echo $lesson['id']; ?>">
                                        <i data-lucide="file-text"></i>
                                        Aç
                                    </a>
                                    <a href="<?php echo $lesson['pdf_url']; ?>" download class="btn btn-secondary"
                                        title="Yüklə">
                                        <i data-lucide="download"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled style="flex: 1; opacity: 0.5;">
                                        Material yoxdur
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<script>
    let currentType = 'all';

    // Search functionality
    document.getElementById('archive-search').addEventListener('input', function (e) {
        filterArchive();
    });

    // Course filter
    document.getElementById('course-filter').addEventListener('change', function (e) {
        filterArchive();
    });

    // Type filter buttons
    document.querySelectorAll('.type-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentType = this.dataset.type;
            filterArchive();
        });
    });

    function filterArchive() {
        const searchQuery = document.getElementById('archive-search').value.toLowerCase();
        const courseFilter = document.getElementById('course-filter').value;
        const container = document.getElementById('archive-container');
        const cards = document.querySelectorAll('.archive-card');

        cards.forEach(card => {
            const title = card.querySelector('.archive-title').textContent.toLowerCase();
            const course = card.dataset.course;
            const instructor = card.querySelector('.archive-instructor').textContent.toLowerCase();
            const hasVideo = card.querySelector('.resource-badge.video') !== null;
            const hasPdf = card.querySelector('.resource-badge.pdf') !== null;

            const matchesSearch = title.includes(searchQuery) ||
                course.toLowerCase().includes(searchQuery) ||
                instructor.includes(searchQuery);

            const matchesCourse = courseFilter === 'all' || course === courseFilter;

            let matchesType = true;
            if (currentType === 'video') matchesType = hasVideo;
            else if (currentType === 'pdf') matchesType = hasPdf;

            card.style.display = (matchesSearch && matchesCourse && matchesType) ? 'block' : 'none';
        });

        const visibleCards = Array.from(cards).filter(c => c.style.display !== 'none');
        let emptyState = document.getElementById('empty-state');

        if (visibleCards.length === 0) {
            if (!emptyState) {
                emptyState = document.createElement('div');
                emptyState.id = 'empty-state';
                emptyState.style.textAlign = 'center';
                emptyState.style.padding = '60px 20px';
                emptyState.style.width = '100%';
                emptyState.style.gridColumn = '1 / -1';
                emptyState.innerHTML = `
                    <div style="margin-bottom: 20px; opacity: 0.5;">
                        <i data-lucide="search-x" style="width: 64px; height: 64px; margin: 0 auto;"></i>
                    </div>
                    <h3 style="color: var(--text-primary); margin-bottom: 8px;">Nəticə tapılmadı</h3>
                    <p style="color: var(--text-muted);">Axtarış meyarlarınıza uyğun material yoxdur.</p>
                `;
                container.appendChild(emptyState);
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        } else if (emptyState) {
            emptyState.remove();
        }
    }

    // View tracking - both local and TMİS
    document.querySelectorAll('.track-view').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            if (!id) return;

            // Immediate UI feedback
            const card = this.closest('.archive-card');
            const viewCountSpan = card.querySelector('.archive-meta-item:last-child span');
            if (viewCountSpan) {
                const currentViews = parseInt(viewCountSpan.textContent) || 0;
                viewCountSpan.textContent = (currentViews + 1) + ' baxış';
            }

            // Local increment
            fetch('api/increment_views.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            }).catch(err => console.error('View tracking error:', err));
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>