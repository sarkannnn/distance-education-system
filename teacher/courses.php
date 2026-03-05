<?php
/**
 * Teacher Courses - Fənlərim
 */
$currentPage = 'live';
$pageTitle = 'Fənlərim';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireInstructor();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// Müəllimin tədris etdiyi kursları verilənlər bazasından çək
$courses = [];

// Müəllimin instructor_id-sini tap (əvvəlcə user_id, sonra email ilə)
$instructor = $db->fetch(
    "SELECT id FROM instructors WHERE user_id = ?",
    [$currentUser['id']]
);

// Əgər user_id ilə tapılmadısa, email ilə axtar
if (!$instructor) {
    $instructor = $db->fetch(
        "SELECT id FROM instructors WHERE email = ?",
        [$currentUser['email']]
    );
}

// ============================================================
// TMİS API-dən fənn siyahısını çək (əsas mənbə)
// ============================================================
$tmisToken = TmisApi::getToken();
$tmisCoursesLoaded = false;

if ($tmisToken) {
    try {
        $courseStatsResult = TmisApi::getSubjectsList($tmisToken);

        if ($courseStatsResult['success'] && isset($courseStatsResult['data']) && is_array($courseStatsResult['data']) && count($courseStatsResult['data']) > 0) {
            $tmisCoursesLoaded = true;

            foreach ($courseStatsResult['data'] as $cs) {
                // `courseStatsResult` -dan gələn datalar (GET subjects-list formatında)
                $courseId = $cs['id'] ?? 0;
                $subjectName = $cs['subject_name'] ?? 'Fənn';
                $subjectCode = $cs['subject_code'] ?? '';
                $courseLevel = $cs['course'] ?? 1;
                $subjectTime = $cs['subject_time'] ?? 0;
                $facultyName = $cs['faculty_name'] ?? '';
                $professionName = $cs['profession_name'] ?? '';
                $sectorName = $cs['sector_name'] ?? '';

                $lectureCount = $cs['subject_lecture_time'] ?? 0;
                $seminarCount = $cs['subject_seminar_time'] ?? 0;
                $labCount = $cs['subject_lab_time'] ?? 0;
                $lectureDone = 0;
                $seminarDone = 0;
                $labDone = 0;
                $progress = 0;
                $studentCount = 0;

                // TMİS subject details API-dən real tələbə sayını və proqresi çək
                try {
                    $subjectDetailResult = TmisApi::getSubjectDetails($tmisToken, (int) $courseId);
                    if ($subjectDetailResult['success'] && isset($subjectDetailResult['data'])) {
                        $detail = $subjectDetailResult['data'];
                        $studentCount = $detail['total_students'] ?? ($detail['student_count'] ?? ($detail['students_count'] ?? 0));

                        // Əlavə: Əgər 'students' massivi gəlirsə, onu da sayaq
                        if ($studentCount == 0 && isset($detail['students']) && is_array($detail['students'])) {
                            $studentCount = count($detail['students']);
                        }

                        // Lokal fallback
                        if ($studentCount == 0) {
                            $localStudents = $db->fetch("SELECT COUNT(*) as count FROM enrollments WHERE course_id = ?", [$courseId]);
                            if ($localStudents) {
                                $studentCount = (int) $localStudents['count'];
                            }
                        }

                        // Proqres məlumatları
                        $lectureDone = $detail['completed_lectures'] ?? ($detail['lecture_done'] ?? 0);
                        $seminarDone = $detail['completed_seminars'] ?? ($detail['seminar_done'] ?? 0);
                        $labDone = $detail['completed_labs'] ?? ($detail['lab_done'] ?? 0);

                        // Əgər lokal bazada da dərs sayı varsa, onu da istifadə et
                        if ($lectureDone == 0 || $seminarDone == 0) {
                            $localCounts = $db->fetch(
                                "SELECT 
                                    SUM(CASE WHEN lesson_type='lecture' AND status IN ('ended','completed') THEN 1 ELSE 0 END) as lec_done,
                                    SUM(CASE WHEN lesson_type='seminar' AND status IN ('ended','completed') THEN 1 ELSE 0 END) as sem_done,
                                    SUM(CASE WHEN lesson_type='laboratory' AND status IN ('ended','completed') THEN 1 ELSE 0 END) as lab_done
                                 FROM live_classes WHERE course_id = ?",
                                [$courseId]
                            );
                            if ($localCounts) {
                                $lectureDone = max($lectureDone, (int) ($localCounts['lec_done'] ?? 0));
                                $seminarDone = max($seminarDone, (int) ($localCounts['sem_done'] ?? 0));
                                $labDone = max($labDone, (int) ($localCounts['lab_done'] ?? 0));
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Xəta olsa default 0 qalır
                }

                // Proqresi hesabla
                $totalForProgress = $lectureCount + $seminarCount + $labCount;
                $totalDone = $lectureDone + $seminarDone + $labDone;
                if ($totalForProgress > 0) {
                    $progress = round(($totalDone / $totalForProgress) * 100);
                }

                // Status
                $statusLabel = 'Aktiv';
                $statusRaw = 'active';
                $isDue = false;

                $courses[] = [
                    'id' => $courseId,
                    'tmis_subject_id' => $courseId,
                    'title' => trim($subjectName),
                    'subject_code' => $subjectCode,
                    'lesson_type' => 'Mühazirə',
                    'description' => $facultyName,
                    'students' => $studentCount,
                    'initial_students' => $studentCount,
                    'weekly_days' => '',
                    'start_time' => '',
                    'category_id' => '',
                    'specialization_id' => '',
                    'course_level' => $courseLevel,
                    'total_lessons' => $subjectTime,
                    'lecture_count' => $lectureCount,
                    'seminar_count' => $seminarCount,
                    'lab_count' => $labCount,
                    'lesson_count' => 0,
                    'progress' => $progress,
                    'lecture_done' => $lectureDone,
                    'seminar_done' => $seminarDone,
                    'lab_done' => $labDone,
                    'status' => $statusLabel,
                    'status_raw' => $statusRaw,
                    'category' => $sectorName,
                    'specialization' => $professionName,
                    'is_due' => $isDue,
                    'attendance' => 0
                ];
            }
        }
    } catch (Exception $e) {
        error_log('TMİS Courses xətası: ' . $e->getMessage());
    }
}

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

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
        <div class="content-container">
            <!-- Page Header -->
            <div class="page-header flex justify-between items-center mb-6">
                <div>
                    <h1>Canlı Dərslər</h1>
                    <p>Tədris etdiyiniz bütün fənlər</p>
                </div>
                <!-- Course creation disabled as it syncs from TMIS -->
                <?php /*
<button class="btn btn-primary" onclick="openAddCourseModal()">
<i data-lucide="plus"></i>
Yeni Fənn
</button>
*/ ?>
            </div>

            <!-- Tab Navigation -->
            <style>
                .nav-tabs {
                    display: flex;
                    border-bottom: 2px solid #e5e7eb;
                    margin-bottom: 24px;
                    gap: 32px;
                }

                .nav-tab {
                    padding: 12px 0;
                    font-weight: 500;
                    color: var(--text-muted);
                    border-bottom: 2px solid transparent;
                    margin-bottom: -2px;
                    cursor: pointer;
                    text-decoration: none;
                    transition: all 0.3s;
                    font-size: 16px;
                }

                .nav-tab:hover {
                    color: var(--primary);
                }

                .nav-tab.active {
                    color: var(--primary);
                    border-bottom-color: var(--primary);
                }
            </style>

            <div class="nav-tabs">
                <a href="live-lessons.php" class="nav-tab">Canlı Cədvəl</a>
                <a href="courses.php" class="nav-tab active">Fənlərim</a>
            </div>

            <?php if (empty($courses)): ?>
                <div class="card p-8 text-center">
                    <i data-lucide="book-open"
                        style="width: 64px; height: 64px; color: var(--text-muted); margin: 0 auto 16px;"></i>
                    <h3 style="margin-bottom: 8px;">Hələ heç bir dərs yoxdur</h3>
                    <p class="text-muted">Yeni dərs əlavə etmək üçün yuxarıdakı düyməni istifadə edin.</p>
                </div>
            <?php else: ?>
                <div class="grid-3">
                    <?php foreach ($courses as $course): ?>
                        <div class="card p-0 overflow-hidden cursor-pointer">
                            <div
                                style="height: 160px; background: linear-gradient(135deg, var(--primary-dark), var(--primary)); position: relative;">
                                <!-- Edit/Delete disabled as managed by TMIS -->
                                <?php /*
                                                                                                  <div class="absolute top-3 left-3 flex gap-2">
                                                                                                      <button
                                                                                                          class="icon-btn bg-white/20 hover:bg-white/40 text-white p-2 rounded-lg backdrop-blur-sm transition-all"
                                                                                                          onclick="event.stopPropagation(); openEditCourseModal(<?php echo htmlspecialchars(json_encode($course)); ?>)"
                                                                                                          title="Redaktə et">
                                                                                                          <i data-lucide="edit-3" style="width: 16px; height: 16px;"></i>
                                                                                                      </button>
                                                                                                      <button
                                                                                                          class="icon-btn bg-white/20 hover:bg-white/40 text-white p-2 rounded-lg backdrop-blur-sm transition-all"
                                                                                                          onclick="event.stopPropagation(); deleteCourse(<?php echo $course['id']; ?>, '<?php echo addslashes($course['title']); ?>')"
                                                                                                          title="Sil">
                                                                                                          <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                                                                                      </button>
                                                                                                  </div>
                                                                                                  */ ?>
                                <div class="absolute top-3 right-3">
                                    <span
                                        class="badge <?php echo $course['status'] === 'Aktiv' ? 'badge-success' : ($course['status'] === 'Tamamlandı' ? 'badge-purple' : 'badge-warning'); ?>">
                                        <?php echo $course['status']; ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-center h-full opacity-20">
                                    <i data-lucide="book-open" style="width: 64px; height: 64px; color: var(--text-white);"></i>
                                </div>
                            </div>

                            <div class="p-5">
                                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 12px;">
                                    <?php echo e($course['title']); ?>
                                </h3>

                                <div class="space-y-2 mb-4">
                                    <div class="flex items-center gap-2 text-muted" style="font-size: 14px;">
                                        <i data-lucide="users" style="width: 16px; height: 16px;"></i>
                                        <span>
                                            <?php echo $course['students']; ?> tələbə
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 text-muted" style="font-size: 14px;">
                                        <i data-lucide="book-open" style="width: 16px; height: 16px;"></i>
                                        <span>
                                            <?php
                                            $parts = [];
                                            if ($course['lecture_count'] > 0)
                                                $parts[] = $course['lecture_count'] . " mühazirə";
                                            if ($course['seminar_count'] > 0)
                                                $parts[] = $course['seminar_count'] . " seminar";
                                            if (!empty($course['lab_count']) && $course['lab_count'] > 0)
                                                $parts[] = $course['lab_count'] . " lab";
                                            if (empty($parts))
                                                $parts[] = "0 dərs";
                                            echo implode(", ", $parts);
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($isAdmin && !empty($course['instructor_name'])): ?>
                                        <div class="flex items-center gap-2"
                                            style="font-size: 14px; color: var(--primary); font-weight: 600;">
                                            <i data-lucide="user-check" style="width: 16px; height: 16px;"></i>
                                            <span>
                                                <?php echo e($course['instructor_name']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($course['specialization'])): ?>
                                        <div class="flex items-center gap-2 text-muted" style="font-size: 14px;">
                                            <i data-lucide="graduation-cap" style="width: 16px; height: 16px;"></i>
                                            <span>
                                                <?php echo e($course['specialization']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($course['weekly_days'])): ?>
                                        <div class="flex items-center gap-2 text-muted" style="font-size: 14px;">
                                            <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                                            <span>
                                                <?php echo e($course['weekly_days']); ?> / <?php echo $course['start_time']; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex items-center gap-2 text-muted" style="font-size: 14px;">
                                        <i data-lucide="layers" style="width: 16px; height: 16px;"></i>
                                        <span>Kurs: <?php echo $course['course_level']; ?></span>
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <a href="course-details.php?id=<?php echo $course['id']; ?>"
                                        class="btn btn-secondary flex-1" style="font-size: 13px; height: 40px;">
                                        Detallar
                                    </a>
                                    <button class="btn btn-success flex-1"
                                        style="font-size: 13px; height: 40px; background: #22c55e; border: none;"
                                        onclick="event.stopPropagation(); openStartLiveModal(<?php echo (int) $course['id']; ?>, '<?php echo htmlspecialchars(addslashes($course['title']), ENT_QUOTES); ?>', <?php echo (int) $course['lecture_count']; ?>, <?php echo (int) $course['seminar_count']; ?>, <?php echo (int) $course['lecture_done']; ?>, <?php echo (int) $course['seminar_done']; ?>, <?php echo (int) $course['lab_count']; ?>, <?php echo (int) $course['lab_done']; ?>, '<?php echo addslashes($course['description'] ?? ''); ?>', '<?php echo addslashes($course['specialization'] ?? ''); ?>', '<?php echo $course['course_level'] ?? 1; ?>')">
                                        <i data-lucide="video" style="width: 14px; height: 14px;"></i>
                                        Canlı dərs yarat
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Add Course Modal -->
<div id="addCourseModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>Yeni Fənn Əlavə Et</h2>
            <button class="modal-close" onclick="closeAddCourseModal()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="addCourseForm" method="POST" action="api/add_course.php">
            <div class="modal-body">
                <div class="form-group">
                    <label for="course_title">Fənn Adı *</label>
                    <input type="text" id="course_title" name="title" class="form-input" required
                        placeholder="Məsələn: Riyazi Analiz">
                </div>

                <div class="form-group">
                    <label for="course_description">Təsvir</label>
                    <textarea id="course_description" name="description" class="form-input" rows="4"
                        placeholder="Dərs haqqında qısa məlumat"></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="course_category">Kateqoriya *</label>
                        <select id="course_category" name="category_id" class="form-input" required>
                            <option value="">Seçin</option>
                            <?php
                            $categories = $db->fetchAll("SELECT * FROM categories ORDER BY name");
                            foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="course_specialization">İxtisas *</label>
                        <select id="course_specialization" name="specialization_id" class="form-input" required>
                            <option value="">Seçin</option>
                            <?php
                            $specializations = $db->fetchAll("SELECT id, name, default_student_count FROM specializations ORDER BY name");
                            foreach ($specializations as $spec): ?>
                                <option value="<?php echo $spec['id']; ?>"
                                    data-count="<?php echo $spec['default_student_count']; ?>">
                                    <?php echo e($spec['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="lecture_count">Mühazirə Sayı</label>
                        <input type="number" id="lecture_count" name="lecture_count" class="form-input" min="0"
                            value="16" placeholder="16">
                        <small style="color: var(--text-muted);">Planlaşdırılan mühazirə sayı</small>
                    </div>
                    <div class="form-group">
                        <label for="seminar_count">Seminar Sayı</label>
                        <input type="number" id="seminar_count" name="seminar_count" class="form-input" min="0"
                            value="16" placeholder="16">
                        <small style="color: var(--text-muted);">Planlaşdırılan seminar sayı</small>
                    </div>
                </div>

                <div class="grid-3">
                    <div class="form-group">
                        <label for="initial_students">Tələbə Sayı (Avtomatik)</label>
                        <input type="number" id="initial_students" name="initial_students" class="form-input" min="0"
                            value="0" readonly style="background: var(--gray-50); cursor: not-allowed;">
                        <small class="text-muted">İxtisasa görə təyin edilir</small>
                    </div>
                    <div class="form-group">
                        <label for="course_level">Kurs *</label>
                        <select id="course_level" name="course_level" class="form-input" required>
                            <option value="1">1-ci kurs</option>
                            <option value="2">2-ci kurs</option>
                            <option value="3">3-ci kurs</option>
                            <option value="4">4-ci kurs</option>
                            <option value="5">5-ci kurs</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="start_time">Dərs Saatı *</label>
                        <input type="time" id="start_time" name="start_time" class="form-input" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Dərs Günləri *</label>
                    <div class="weekday-selector flex flex-wrap gap-2 mt-2">
                        <?php
                        $weekdays = ['Bazar ertəsi', 'Çərşənbə axşamı', 'Çərşənbə', 'Cümə axşamı', 'Cümə', 'Şənbə', 'Bazar'];
                        foreach ($weekdays as $day): ?>
                            <label class="day-chip">
                                <input type="checkbox" name="weekly_days[]" value="<?php echo $day; ?>">
                                <span><?php echo $day; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="course_status">Status *</label>
                    <select id="course_status" name="status" class="form-input" required>
                        <option value="active">Aktiv</option>
                        <option value="draft">Qaralama</option>
                        <option value="inactive">Qeyri-aktiv</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddCourseModal()">Ləğv et</button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="plus"></i>
                    Dərs Əlavə Et
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        backdrop-filter: blur(4px);
    }

    .modal-content {
        background: var(--bg-primary);
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 24px;
        border-bottom: 1px solid var(--gray-200);
    }

    .modal-header h2 {
        font-size: 20px;
        font-weight: 600;
        margin: 0;
    }

    .modal-close {
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px;
        border-radius: 8px;
        transition: background 0.2s;
    }

    .modal-close:hover {
        background: var(--gray-100);
    }

    .modal-body {
        padding: 24px;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 24px;
        border-top: 1px solid var(--gray-200);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        font-size: 14px;
        color: var(--text-primary);
    }

    .form-input {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--gray-300);
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    textarea.form-input {
        resize: vertical;
        font-family: inherit;
    }

    .day-chip {
        cursor: pointer;
    }

    .day-chip input {
        display: none;
    }

    .day-chip span {
        display: inline-block;
        padding: 8px 16px;
        background: var(--gray-100);
        border: 1px solid var(--gray-200);
        border-radius: 99px;
        font-size: 13px;
        transition: all 0.2s;
        color: var(--text-muted);
    }

    .day-chip input:checked+span {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    .weekday-selector {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
</style>

<script>
    function openAddCourseModal() {
        document.getElementById('addCourseModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        // Reinitialize lucide icons for modal
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    function closeAddCourseModal() {
        document.getElementById('addCourseModal').style.display = 'none';
        document.body.style.overflow = 'auto';
        document.getElementById('addCourseForm').reset();
    }

    // Close modal on outside click
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('addCourseModal');
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    closeAddCourseModal();
                }
            });
        }

        // Handle form submission
        const form = document.getElementById('addCourseForm');
        if (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                const formData = new FormData(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i data-lucide="loader" class="animate-spin"></i> Əlavə edilir...';

                try {
                    const response = await fetch('api/add_course.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Show success message
                        alert('Dərs uğurla əlavə edildi!');
                        // Reload page to show new course
                        window.location.reload();
                    } else {
                        alert('Xəta: ' + (result.message || 'Dərs əlavə edilə bilmədi'));
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                } catch (error) {
                    alert('Xəta baş verdi: ' + error.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        }

        // Handle Edit form submission
        const editForm = document.getElementById('editCourseForm');
        if (editForm) {
            editForm.addEventListener('submit', async function (e) {
                e.preventDefault();

                const formData = new FormData(editForm);
                const submitBtn = editForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i data-lucide="loader" class="animate-spin"></i> Yenilənir...';

                try {
                    const response = await fetch('api/edit_course.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('Dərs uğurla yeniləndi!');
                        window.location.reload();
                    } else {
                        alert('Xəta: ' + result.message);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                } catch (error) {
                    alert('Xəta baş verdi: ' + error.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        }
        // Handle Specialization change to auto-update student count
        const specSelect = document.getElementById('course_specialization');
        const studentInput = document.getElementById('initial_students');
        if (specSelect && studentInput) {
            specSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                const count = selectedOption.dataset.count || 0;
                studentInput.value = count;
            });
        }

        const editSpecSelect = document.getElementById('edit_course_specialization');
        const editStudentInput = document.getElementById('edit_initial_students');
        if (editSpecSelect && editStudentInput) {
            editSpecSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                const count = selectedOption.dataset.count || 0;
                editStudentInput.value = count;
            });
        }
    });

    function openEditCourseModal(course) {
        document.getElementById('edit_course_id').value = course.id;
        document.getElementById('edit_course_title').value = course.title;
        document.getElementById('edit_course_description').value = course.description;
        document.getElementById('edit_course_category').value = course.category_id || '';
        document.getElementById('edit_course_specialization').value = course.specialization_id || '';
        document.getElementById('edit_course_level').value = course.course_level || 1;
        document.getElementById('edit_lecture_count').value = course.lecture_count || 16;
        document.getElementById('edit_seminar_count').value = course.seminar_count || 16;
        document.getElementById('edit_initial_students').value = course.initial_students || 0;

        // Reset and check weekdays
        const editWeekdayCheckboxes = document.querySelectorAll('#editCourseForm input[name="weekly_days[]"]');
        editWeekdayCheckboxes.forEach(cb => cb.checked = false);
        if (course.weekly_days) {
            const days = course.weekly_days.split(', ');
            editWeekdayCheckboxes.forEach(cb => {
                if (days.includes(cb.value)) cb.checked = true;
            });
        }
        document.getElementById('edit_start_time').value = course.start_time || '';

        // Map status text to potential values
        let statusValue = course.status_raw || 'active';

        document.getElementById('edit_course_status').value = statusValue;

        document.getElementById('editCourseModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    function closeEditCourseModal() {
        document.getElementById('editCourseModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    async function deleteCourse(id, title) {
        if (confirm(`"${title}" dərsini silmək istədiyinizə əminsiniz? Bu əməliyyat geri qaytarıla bilməz.`)) {
            try {
                const formData = new FormData();
                formData.append('course_id', id);

                const response = await fetch('api/delete_course.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('Dərs uğurla silindi!');
                    window.location.reload();
                } else {
                    alert('Xəta: ' + result.message);
                }
            } catch (error) {
                alert('Xəta baş verdi: ' + error.message);
            }
        }
    }

    const hasZoomLink = <?php echo !empty($currentUser['zoom_link']) ? 'true' : 'false'; ?>;


    // Close modal with Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (typeof closeAddCourseModal === 'function') closeAddCourseModal();
            if (typeof closeEditCourseModal === 'function') closeEditCourseModal();
            if (typeof closeStartLiveModal === 'function') closeStartLiveModal();
        }
    });
</script>

<!-- Edit Course Modal -->
<div id="editCourseModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>Dərs Redaktə Et</h2>
            <button class="modal-close" onclick="closeEditCourseModal()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="editCourseForm" method="POST">
            <input type="hidden" id="edit_course_id" name="course_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_course_title">Fənn Adı *</label>
                    <input type="text" id="edit_course_title" name="title" class="form-input" required>
                </div>

                <div class="form-group">
                    <label for="edit_course_description">Təsvir</label>
                    <textarea id="edit_course_description" name="description" class="form-input" rows="4"></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="edit_course_category">Kateqoriya *</label>
                        <select id="edit_course_category" name="category_id" class="form-input" required>
                            <option value="">Seçin</option>
                            <?php
                            foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_course_specialization">İxtisas *</label>
                        <select id="edit_course_specialization" name="specialization_id" class="form-input" required>
                            <option value="">Seçin</option>
                            <?php
                            foreach ($specializations as $spec): ?>
                                <option value="<?php echo $spec['id']; ?>"
                                    data-count="<?php echo $spec['default_student_count']; ?>">
                                    <?php echo e($spec['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="edit_lecture_count">Mühazirə Sayı</label>
                        <input type="number" id="edit_lecture_count" name="lecture_count" class="form-input" min="0">
                        <small style="color: var(--text-muted);">Planlaşdırılan mühazirə sayı</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_seminar_count">Seminar Sayı</label>
                        <input type="number" id="edit_seminar_count" name="seminar_count" class="form-input" min="0">
                        <small style="color: var(--text-muted);">Planlaşdırılan seminar sayı</small>
                    </div>
                </div>

                <div class="grid-3">
                    <div class="form-group">
                        <label for="edit_initial_students">Tələbə Sayı (Avtomatik)</label>
                        <input type="number" id="edit_initial_students" name="initial_students" class="form-input"
                            min="0" readonly style="background: var(--gray-50); cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label for="edit_course_level">Kurs *</label>
                        <select id="edit_course_level" name="course_level" class="form-input" required>
                            <option value="1">1-ci kurs</option>
                            <option value="2">2-ci kurs</option>
                            <option value="3">3-ci kurs</option>
                            <option value="4">4-ci kurs</option>
                            <option value="5">5-ci kurs</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_start_time">Dərs Saatı *</label>
                        <input type="time" id="edit_start_time" name="start_time" class="form-input" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Dərs Günləri *</label>
                    <div class="weekday-selector flex flex-wrap gap-2 mt-2">
                        <?php
                        $weekdays = ['Bazar ertəsi', 'Çərşənbə axşamı', 'Çərşənbə', 'Cümə axşamı', 'Cümə', 'Şənbə', 'Bazar'];
                        foreach ($weekdays as $day): ?>
                            <label class="day-chip">
                                <input type="checkbox" name="weekly_days[]" value="<?php echo $day; ?>">
                                <span><?php echo $day; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit_course_status">Status *</label>
                    <select id="edit_course_status" name="status" class="form-input" required>
                        <option value="active">Aktiv</option>
                        <option value="draft">Qaralama</option>
                        <option value="inactive">Qeyri-aktiv</option>
                        <option value="completed">Tamamlandı</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditCourseModal()">Ləğv et</button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save"></i>
                    Yadda Saxla
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/modal_start_live.php'; ?>
<?php require_once 'includes/footer.php'; ?>