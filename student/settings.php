<?php
/**
 * Parametrlər - Settings
 */
$currentPage = 'settings';
$pageTitle = 'Parametrlər';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireLogin();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

$message = '';
$error = '';

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';



    if ($action === 'update_notifications') {
        $db->update('users', [
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'push_notifications' => isset($_POST['push_notifications']) ? 1 : 0,
            'lesson_reminders' => isset($_POST['lesson_reminders']) ? 1 : 0
        ], 'id = :id', ['id' => $currentUser['id']]);

        $message = 'Bildiriş parametrləri yeniləndi';
        $currentUser = $auth->getCurrentUser();
    }



    if ($action === 'update_preferences') {
        $db->update('users', [
            'language' => $_POST['language'],
            'timezone' => $_POST['timezone']
        ], 'id = :id', ['id' => $currentUser['id']]);

        $message = 'Dil və region parametrləri yeniləndi';
        $currentUser = $auth->getCurrentUser();
    }
}



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
                <h1>Parametrlər</h1>
                <p>Hesab, təhlükəsizlik və tətbiq parametrləri</p>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div id="status-message"
                    style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); padding: 16px; border-radius: 8px;">
                    <?php echo e($message); ?>
                </div>
                <script>setTimeout(() => { document.getElementById('status-message')?.remove(); }, 3000);</script>
            <?php endif; ?>

            <?php if ($error): ?>
                <div id="error-message"
                    style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--error); color: var(--error); padding: 16px; border-radius: 8px;">
                    <?php echo e($error); ?>
                </div>
                <script>setTimeout(() => { document.getElementById('error-message')?.remove(); }, 5000);</script>
            <?php endif; ?>



            <!-- Notification Settings -->
            <form method="POST" class="card p-0 overflow-hidden" style="border-radius: 24px;">
                <input type="hidden" name="action" value="update_notifications">

                <div class="card-header flex items-center gap-4"
                    style="padding: 24px 32px; border-bottom: 1px solid var(--border-color); background: rgba(139, 92, 246, 0.02);">
                    <div
                        style="background: #8b5cf6; color: white; padding: 12px; border-radius: 14px; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);">
                        <i data-lucide="bell" style="width: 24px; height: 24px;"></i>
                    </div>
                    <div>
                        <h2 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin: 0;">Bildiriş
                            Parametrləri</h2>
                        <p style="font-size: 13px; color: var(--text-muted); margin: 0;">Sistem bildirişlərini necə
                            almaq istədiyinizi seçin</p>
                    </div>
                </div>

                <div style="padding: 16px;">
                    <!-- Email Settings -->
                    <div class="flex items-center justify-between p-6 rounded-2xl transition-all hover:bg-gray-100/50">
                        <div class="flex items-center gap-4">
                            <div
                                style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="mail" style="color: #3b82f6; width: 22px;"></i>
                            </div>
                            <div>
                                <h4
                                    style="font-weight: 700; font-size: 15px; color: var(--text-primary); margin-bottom: 2px;">
                                    Email Bildirişləri</h4>
                                <p style="color: var(--text-muted); font-size: 13px;">Girişlər və əsas fəaliyyətlər
                                    haqqında email al</p>
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_notifications" <?php echo ($currentUser['email_notifications'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <!-- Push Settings -->
                    <div class="flex items-center justify-between p-6 rounded-2xl transition-all hover:bg-gray-100/50"
                        style="border-top: 1px solid var(--border-color);">
                        <div class="flex items-center gap-4">
                            <div
                                style="width: 48px; height: 48px; border-radius: 12px; background: rgba(14, 89, 149, 0.1); display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="monitor" style="color: var(--primary); width: 22px;"></i>
                            </div>
                            <div>
                                <h4
                                    style="font-weight: 700; font-size: 15px; color: var(--text-primary); margin-bottom: 2px;">
                                    Brauzer Bildirişləri</h4>
                                <p style="color: var(--text-muted); font-size: 13px;">Sistem daxilində push bildirişləri
                                    al</p>
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="push_notifications" <?php echo ($currentUser['push_notifications'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <!-- Lesson Reminders -->
                    <div class="flex items-center justify-between p-6 rounded-2xl transition-all hover:bg-gray-100/50"
                        style="border-top: 1px solid var(--border-color);">
                        <div class="flex items-center gap-4">
                            <div
                                style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="clock" style="color: #10b981; width: 22px;"></i>
                            </div>
                            <div>
                                <h4
                                    style="font-weight: 700; font-size: 15px; color: var(--text-primary); margin-bottom: 2px;">
                                    Dərs Xatırlatmaları</h4>
                                <p style="color: var(--text-muted); font-size: 13px;">Dərs başlamazdan 15 dəqiqə əvvəl
                                    bildiriş al</p>
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="lesson_reminders" <?php echo ($currentUser['lesson_reminders'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                </div>

                <div
                    style="padding: 24px 32px; background: rgba(139, 92, 246, 0.02); border-top: 1px solid var(--border-color);">
                    <button type="submit" class="btn btn-primary"
                        style="padding: 14px 32px; border-radius: 14px; font-weight: 700; display: flex; align-items: center; gap: 10px; box-shadow: 0 8px 24px -6px rgba(139, 92, 246, 0.4); max-width: 200px; background: #8b5cf6; border-color: #8b5cf6;">
                        <i data-lucide="save" style="width: 20px; height: 20px;"></i>
                        Yadda saxla
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>