<?php
/**
 * Course Details Page
 */
$currentPage = 'courses';
$pageTitle = 'Dərs Detalları';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireInstructor();

$currentUser = $auth->getCurrentUser();
$courseId = $_GET['id'] ?? 0;

if (!$courseId) {
    header('Location: courses.php');
    exit;
}

// ============================================================
// TMİS Token
// ============================================================
$tmisToken = TmisApi::getToken();
$course = null;
$students = [];
$archives = [];

if ($tmisToken) {
    try {
        // Əvvəlcə subjects-listdən əsas dataları al
        $listResult = TmisApi::getSubjectsList($tmisToken);
        if ($listResult['success'] && isset($listResult['data'])) {
            foreach ($listResult['data'] as $cs) {
                if ($cs['id'] == $courseId) {
                    $course = [
                        'id' => $cs['id'],
                        'tmis_subject_id' => $cs['id'],
                        'title' => trim($cs['subject_name'] ?? 'Fənn'),
                        'subject_code' => $cs['subject_code'] ?? '',
                        'category_name' => $cs['sector_name'] ?? '',
                        'specialization_name' => $cs['profession_name'] ?? '',
                        'faculty_name' => $cs['faculty_name'] ?? '',
                        'department_name' => $cs['department_name'] ?? '',
                        'group_name' => $cs['class_name'] ?? '*',
                        'course_level' => $cs['course'] ?? 1,
                        'lecture_count' => $cs['subject_lecture_time'] ?? 0,
                        'seminar_count' => $cs['subject_seminar_time'] ?? 0,
                        'lab_count' => $cs['subject_lab_time'] ?? 0,
                        'total_lessons' => $cs['subject_time'] ?? 0,
                        'initial_students' => 0,
                        'weekly_days' => '',
                        'start_time' => '',
                        'status' => 'active',
                        'created_at' => date('Y-m-d H:i:s'),
                        'lecture_done' => 0,
                        'seminar_done' => 0,
                        'lab_done' => 0
                    ];
                    break;
                }
            }
        }

        // Əlavə olaraq subject details API-ni çağır (tələbələr və arxivlər üçün)
        $detailsResult = TmisApi::getSubjectDetails($tmisToken, (int) $courseId);

        if ($detailsResult['success'] && isset($detailsResult['data'])) {
            $tmisData = $detailsResult['data'];

            // Tələbə siyahısı
            if (isset($tmisData['students']) && is_array($tmisData['students'])) {
                foreach ($tmisData['students'] as $s) {
                    $students[] = [
                        'first_name' => $s['first_name'] ?? ($s['name'] ?? ''),
                        'last_name' => $s['last_name'] ?? ($s['surname'] ?? ''),
                        'father_name' => $s['father_name'] ?? '',
                        'email' => $s['email'] ?? '',
                        'student_id' => $s['student_id'] ?? ($s['id'] ?? ''),
                        'enrolled_at' => $s['enrolled_at'] ?? date('Y-m-d')
                    ];
                }
                $course['initial_students'] = count($students);
            }

            // Lokal fallback əgər tələbə yoxdursa
            if (empty($students)) {
                $count = (int) ($tmisData['total_students'] ?? ($tmisData['student_count'] ?? ($tmisData['students_count'] ?? 0)));
                if ($count === 0) {
                    $localStudents = $db->fetch("SELECT COUNT(*) as count FROM enrollments WHERE course_id = ?", [$courseId]);
                    if ($localStudents) {
                        $count = (int) $localStudents['count'];
                    }
                }
                $course['initial_students'] = $count;
            }

            // Arxiv materialları
            if (isset($tmisData['archive']) && is_array($tmisData['archive'])) {
                foreach ($tmisData['archive'] as $a) {
                    $itemTitle = !empty($a['title']) ? $a['title'] : (!empty($course['title']) ? $course['title'] : 'Material');
                    $itemDate = !empty($a['date']) ? $a['date'] : (!empty($a['created_at']) ? $a['created_at'] : date('Y-m-d H:i:s'));

                    $archives[] = [
                        'title' => $itemTitle,
                        'created_at' => $itemDate,
                        'video_url' => $a['video_url'] ?? null,
                        'pdf_url' => $a['pdf_url'] ?? null
                    ];
                }
            }
        }

    } catch (Exception $e) {
        error_log('TMİS Subject Details xətası: ' . $e->getMessage());
    }
}

if (!$course) {
    header('Location: courses.php');
    exit;
}

require_once 'includes/header.php';
?>

<!-- Sidebar -->
<?php require_once 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-wrapper">
    <!-- Top Navigation -->
    <?php require_once 'includes/topnav.php'; ?>

    <main class="main-content">
        <div class="content-container space-y-6">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm text-muted mb-4">
                <a href="courses" class="hover:text-primary transition-colors">Dərslərim</a>
                <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i>
                <span class="text-primary font-medium">
                    <?php echo e($course['title']); ?>
                </span>
            </div>

            <!-- Header Section -->
            <div class="card p-6">
                <div class="flex flex-col gap-5">
                    <!-- Title Row -->
                    <div class="flex items-center justify-between gap-3" style="flex-wrap: wrap;">
                        <div class="flex items-center gap-3">
                            <h1 style="font-size: 24px; font-weight: 700; color: var(--text-primary);">
                                <?php echo e($course['title']); ?>
                            </h1>
                            <span
                                class="badge badge-<?php echo $course['status'] === 'active' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($course['status']); ?>
                            </span>
                        </div>
                        <button class="btn btn-success"
                            style="background: #22c55e; border: none; padding: 10px 20px; border-radius: 12px; font-weight: 600;"
                            onclick="openStartLiveModal(<?php echo (int) $course['id']; ?>, '<?php echo htmlspecialchars(addslashes($course['title']), ENT_QUOTES); ?>', <?php echo (int) $course['lecture_count']; ?>, <?php echo (int) $course['seminar_count']; ?>, <?php echo (int) $course['lecture_done']; ?>, <?php echo (int) $course['seminar_done']; ?>, <?php echo (int) $course['lab_count']; ?>, <?php echo (int) $course['lab_done']; ?>)">
                            <i data-lucide="video" style="width: 16px; height: 16px; margin-right: 8px;"></i>
                            Canlı dərs yarat
                        </button>
                    </div>

                    <?php if (!empty($course['description'])): ?>
                        <p style="color: var(--text-muted); font-size: 14px; line-height: 1.6;">
                            <?php echo e($course['description']); ?>
                        </p>
                    <?php endif; ?>

                    <!-- TMIS Additional Info -->
                    <?php if (isset($course['faculty_name']) && $course['faculty_name']): ?>
                        <div
                            style="font-size: 14px; color: var(--text-muted); line-height: 1.8; background: var(--gray-50); padding: 16px 20px; border-radius: 12px; border: 1px solid var(--gray-200);">
                            <?php if (!empty($course['faculty_name'])): ?>
                                <div><span style="font-weight: 600; color: var(--text-primary);">Fakültə:</span>
                                    <?php echo e($course['faculty_name']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($course['department_name'])): ?>
                                <div><span style="font-weight: 600; color: var(--text-primary);">Kafedra:</span>
                                    <?php echo e($course['department_name']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($course['specialization_name'])): ?>
                                <div><span style="font-weight: 600; color: var(--text-primary);">İxtisas:</span>
                                    <?php echo e($course['specialization_name']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($course['group_name'])): ?>
                                <div><span style="font-weight: 600; color: var(--text-primary);">Qrup:</span>
                                    <?php echo e($course['group_name']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Stats Grid -->
                    <div
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-top: 8px;">
                        <!-- İxtisas -->
                        <div
                            style="background: var(--gray-50); border-radius: 12px; padding: 16px; border-left: 4px solid #8b5cf6;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div
                                    style="width: 40px; height: 40px; border-radius: 10px; background: rgba(139, 92, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i data-lucide="graduation-cap"
                                        style="width: 20px; height: 20px; color: #8b5cf6;"></i>
                                </div>
                                <div>
                                    <p
                                        style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">
                                        İxtisas</p>
                                    <p style="font-size: 14px; font-weight: 600; color: var(--text-primary);">
                                        <?php echo e(!empty($course['specialization_name']) ? $course['specialization_name'] : (!empty($course['group_name']) ? $course['group_name'] : 'Təyin edilməyib')); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Kurs -->
                        <div
                            style="background: var(--gray-50); border-radius: 12px; padding: 16px; border-left: 4px solid #3b82f6;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div
                                    style="width: 40px; height: 40px; border-radius: 10px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i data-lucide="layers" style="width: 20px; height: 20px; color: #3b82f6;"></i>
                                </div>
                                <div>
                                    <p
                                        style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">
                                        Kurs</p>
                                    <p style="font-size: 14px; font-weight: 600; color: var(--text-primary);">
                                        <?php echo $course['course_level']; ?>-cü kurs
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Mühazirə -->
                        <div
                            style="background: var(--gray-50); border-radius: 12px; padding: 16px; border-left: 4px solid #10b981;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div
                                    style="width: 40px; height: 40px; border-radius: 10px; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i data-lucide="book-open" style="width: 20px; height: 20px; color: #10b981;"></i>
                                </div>
                                <div>
                                    <p
                                        style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">
                                        Mühazirə</p>
                                    <p style="font-size: 14px; font-weight: 600; color: var(--text-primary);">
                                        <?php echo $course['lecture_count']; ?> dərs
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Seminar -->
                        <div
                            style="background: var(--gray-50); border-radius: 12px; padding: 16px; border-left: 4px solid #f59e0b;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div
                                    style="width: 40px; height: 40px; border-radius: 10px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i data-lucide="users" style="width: 20px; height: 20px; color: #f59e0b;"></i>
                                </div>
                                <div>
                                    <p
                                        style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">
                                        Seminar</p>
                                    <p style="font-size: 14px; font-weight: 600; color: var(--text-primary);">
                                        <?php echo $course['seminar_count']; ?> dərs
                                    </p>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($course['lab_count']) && $course['lab_count'] > 0): ?>
                            <!-- Laboratoriya -->
                            <div
                                style="background: var(--gray-50); border-radius: 12px; padding: 16px; border-left: 4px solid #ec4899;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div
                                        style="width: 40px; height: 40px; border-radius: 10px; background: rgba(236, 72, 153, 0.1); display: flex; align-items: center; justify-content: center;">
                                        <i data-lucide="flask-conical"
                                            style="width: 20px; height: 20px; color: #ec4899;"></i>
                                    </div>
                                    <div>
                                        <p
                                            style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">
                                            Laboratoriya</p>
                                        <p style="font-size: 14px; font-weight: 600; color: var(--text-primary);">
                                            <?php echo $course['lab_count']; ?> dərs
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Tələbə -->
                        <div
                            style="background: var(--gray-50); border-radius: 12px; padding: 16px; border-left: 4px solid #0e5995;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div
                                    style="width: 40px; height: 40px; border-radius: 10px; background: rgba(14, 89, 149, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i data-lucide="user-check" style="width: 20px; height: 20px; color: #0e5995;"></i>
                                </div>
                                <div>
                                    <p
                                        style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">
                                        Tələbə</p>
                                    <p style="font-size: 14px; font-weight: 600; color: var(--text-primary);">
                                        <?php echo max(count($students), intval($course['initial_students'])); ?> nəfər
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule Info -->
                    <div
                        style="display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: linear-gradient(135deg, rgba(14, 89, 149, 0.05), rgba(14, 89, 149, 0.1)); border-radius: 12px; margin-top: 4px;">
                        <i data-lucide="calendar-clock" style="width: 22px; height: 22px; color: var(--primary);"></i>
                        <div>
                            <span style="font-size: 13px; color: var(--text-muted);">Cədvəl:</span>
                            <span
                                style="font-size: 14px; font-weight: 600; color: var(--primary); margin-left: 8px;"><?php echo e($course['weekly_days']); ?></span>
                            <span style="font-size: 14px; color: var(--text-muted); margin: 0 8px;">•</span>
                            <span
                                style="font-size: 14px; font-weight: 600; color: var(--primary);"><?php echo $course['start_time']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top: 24px;">
                <!-- Students List -->
                <div class="card p-0 overflow-hidden">
                    <div
                        class="p-5 border-b border-gray-100 flex justify-between items-center bg-white sticky top-0 z-10">
                        <div class="flex items-center gap-3">
                            <div
                                style="width: 36px; height: 36px; background: rgba(14, 89, 149, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="users" style="color: #0e5995; width: 18px;"></i>
                            </div>
                            <div>
                                <h2 class="font-bold text-gray-900 text-sm md:text-base">Tələbə Siyahısı</h2>
                                <p class="text-xs text-muted">Kursda qeydiyyatda olan tələbələr</p>
                            </div>
                        </div>
                        <span
                            class="bg-blue-50 text-blue-600 px-3 py-1 rounded-full text-xs font-bold border border-blue-100 shadow-sm">
                            <?php echo count($students); ?> Tələbə
                        </span>
                    </div>

                    <?php if (empty($students)): ?>
                        <div class="text-center py-12 px-6">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i data-lucide="user-x" class="text-gray-300" style="width: 32px; height: 32px;"></i>
                            </div>
                            <h3 class="text-gray-900 font-medium mb-1">Tələbə tapılmadı</h3>
                            <p class="text-muted text-sm">Bu kursa hələ heç bir tələbə qeydiyyatdan keçməyib.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="w-full text-left table" style="border-collapse: separate; border-spacing: 0;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th
                                            style="padding: 12px 24px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; border-top: 1px solid #e2e8f0;">
                                            #</th>
                                        <th
                                            style="padding: 12px 24px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; border-top: 1px solid #e2e8f0;">
                                            Tələbə Adı</th>
                                        <th
                                            style="padding: 12px 24px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; border-top: 1px solid #e2e8f0;">
                                            E-poçt Ünvanı</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1;
                                    foreach ($students as $student): ?>
                                        <tr style="transition: all 0.2s;" onmouseover="this.style.background='#f1f5f9'"
                                            onmouseout="this.style.background='transparent'">
                                            <td
                                                style="padding: 14px 24px; font-size: 13px; color: #94a3b8; border-bottom: 1px solid #f1f5f9;">
                                                <?php echo $i++; ?>
                                            </td>
                                            <td style="padding: 14px 24px; border-bottom: 1px solid #f1f5f9;">
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <div
                                                        style="width: 32px; height: 32px; border-radius: 8px; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px;">
                                                        <?php echo mb_substr($student['first_name'] ?? '', 0, 1) . mb_substr($student['last_name'] ?? '', 0, 1); ?>
                                                    </div>
                                                    <span style="font-size: 14px; font-weight: 600; color: #1e293b;">
                                                        <?php echo e(($student['last_name'] ?? '') . ' ' . ($student['first_name'] ?? '') . (!empty($student['father_name']) ? ' ' . $student['father_name'] : '')); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td
                                                style="padding: 14px 24px; font-size: 14px; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                                                <?php echo e($student['email'] ?? ''); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
</div>
</div>
</main>
</div>

<?php require_once 'includes/modal_start_live.php'; ?>
<?php require_once 'includes/footer.php'; ?>