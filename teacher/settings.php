<?php
/**
 * Teacher Settings - Parametrlər
 */
$currentPage = 'settings';
$pageTitle = 'Parametrlər';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireInstructor();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// Fetch the latest user data from DB to get notification preferences
// Auth::getCurrentUser() only returns session data which might be incomplete
$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$currentUser['id']]);

// Update currentUser with database values for notification preferences
if ($user) {
    $currentUser['email_notifications'] = $user['email_notifications'] ?? 0;
    $currentUser['push_notifications'] = $user['push_notifications'] ?? 0;
    $currentUser['sms_notifications'] = $user['sms_notifications'] ?? 0;
    $currentUser['lesson_reminders'] = $user['lesson_reminders'] ?? 0;
    $currentUser['assignment_deadlines'] = $user['assignment_deadlines'] ?? 0;
    $currentUser['grade_updates'] = $user['grade_updates'] ?? 0;
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
                <p>Bildiriş və tətbiq parametrləri</p>
            </div>

            <div id="settings-alerts"></div>

            <!-- Notification Settings -->
            <div class="card p-0 overflow-hidden" style="border-radius: 24px;">
                <div class="card-header flex items-center gap-4"
                    style="padding: 24px 32px; border-bottom: 1px solid var(--border-color); background: rgba(14, 89, 149, 0.02);">
                    <div
                        style="background: var(--primary); color: white; padding: 12px; border-radius: 14px; box-shadow: 0 4px 12px rgba(14, 89, 149, 0.2);">
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
                    <div class="flex items-center justify-between p-6 rounded-2xl transition-all hover:bg-gray-100/50"
                        style="background: transparent;">
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
                            <input type="checkbox" class="setting-toggle" data-setting="email_notifications" <?php echo ($currentUser['email_notifications'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <!-- Push Settings -->
                    <div class="flex items-center justify-between p-6 rounded-2xl transition-all hover:bg-gray-100/50"
                        style="background: transparent; border-top: 1px solid var(--border-color);">
                        <div class="flex items-center gap-4">
                            <div
                                style="width: 48px; height: 48px; border-radius: 12px; background: rgba(167, 139, 250, 0.1); display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="bell-ring" style="color: #8b5cf6; width: 22px;"></i>
                            </div>
                            <div>
                                <h4
                                    style="font-weight: 700; font-size: 15px; color: var(--text-primary); margin-bottom: 2px;">
                                    Push Bildirişləri</h4>
                                <p style="color: var(--text-muted); font-size: 13px;">Brauzer və mobil tətbiq üzərindən
                                    anında bildiriş al</p>
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" class="setting-toggle" data-setting="push_notifications" <?php echo ($currentUser['push_notifications'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <!-- SMS Settings -->
                    <div class="flex items-center justify-between p-6 rounded-2xl transition-all hover:bg-gray-100/50"
                        style="background: transparent; border-top: 1px solid var(--border-color);">
                        <div class="flex items-center gap-4">
                            <div
                                style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="message-square" style="color: #f59e0b; width: 22px;"></i>
                            </div>
                            <div>
                                <h4
                                    style="font-weight: 700; font-size: 15px; color: var(--text-primary); margin-bottom: 2px;">
                                    SMS Bildirişləri</h4>
                                <p style="color: var(--text-muted); font-size: 13px;">Təcili hallarda mobil nömrənizə
                                    SMS bildirişləri al</p>
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" class="setting-toggle" data-setting="sms_notifications" <?php echo ($currentUser['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <!-- Lesson Reminders -->
                    <div class="flex items-center justify-between p-6 rounded-2xl transition-all hover:bg-gray-100/50"
                        style="background: transparent; border-top: 1px solid var(--border-color);">
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
                            <input type="checkbox" class="setting-toggle" data-setting="lesson_reminders" <?php echo ($currentUser['lesson_reminders'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <!-- Assignment Deadlines -->
                    <div class="flex items-center justify-between p-6 rounded-2xl transition-all hover:bg-gray-100/50"
                        style="background: transparent; border-top: 1px solid var(--border-color);">
                        <div class="flex items-center gap-4">
                            <div
                                style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.1); display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="alert-circle" style="color: #ef4444; width: 22px;"></i>
                            </div>
                            <div>
                                <h4
                                    style="font-weight: 700; font-size: 15px; color: var(--text-primary); margin-bottom: 2px;">
                                    Tapşırıq Vaxtları</h4>
                                <p style="color: var(--text-muted); font-size: 13px;">Tapşırıqların bitmə vaxtına az
                                    qalmış xəbərdarlıq al</p>
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" class="setting-toggle" data-setting="assignment_deadlines" <?php echo ($currentUser['assignment_deadlines'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <!-- Grade Updates -->
                    <div class="flex items-center justify-between p-6 rounded-2xl transition-all hover:bg-gray-100/50"
                        style="background: transparent; border-top: 1px solid var(--border-color);">
                        <div class="flex items-center gap-4">
                            <div
                                style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="award" style="color: #3b82f6; width: 22px;"></i>
                            </div>
                            <div>
                                <h4
                                    style="font-weight: 700; font-size: 15px; color: var(--text-primary); margin-bottom: 2px;">
                                    Qiymətləndirmə Yenilikləri</h4>
                                <p style="color: var(--text-muted); font-size: 13px;">Tələbələr tapşırıq göndərdikdə və
                                    ya qiymət dəyişdikdə bildiriş al</p>
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" class="setting-toggle" data-setting="grade_updates" <?php echo ($currentUser['grade_updates'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                </div>
            </div>

            <div
                style="padding: 24px 32px; background: rgba(14, 89, 149, 0.02); border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-primary" onclick="saveAllNotifications(this)"
                    style="padding: 14px 32px; border-radius: 14px; font-weight: 700; display: flex; align-items: center; gap: 10px; box-shadow: 0 8px 24px -6px rgba(14, 89, 149, 0.4);">
                    <i data-lucide="save" style="width: 20px; height: 20px;"></i>
                    Dəyişiklikləri Yadda Saxla
                </button>
            </div>
        </div>


</div>
</main>
</div>

<script>
    async function saveAllNotifications(btn) {
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="animate-spin" style="width: 18px; height: 18px;"></i> Gözləyin...';
        lucide.createIcons();

        const data = {
            action: 'update_notifications',
            email_notifications: document.querySelector('[data-setting="email_notifications"]').checked ? 1 : 0,
            push_notifications: document.querySelector('[data-setting="push_notifications"]').checked ? 1 : 0,
            sms_notifications: document.querySelector('[data-setting="sms_notifications"]').checked ? 1 : 0,
            lesson_reminders: document.querySelector('[data-setting="lesson_reminders"]').checked ? 1 : 0,
            assignment_deadlines: document.querySelector('[data-setting="assignment_deadlines"]').checked ? 1 : 0,
            grade_updates: document.querySelector('[data-setting="grade_updates"]').checked ? 1 : 0
        };

        try {
            const response = await fetch('api/settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                showToast(result.message, 'success');
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            showToast('Xəta baş verdi', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
            lucide.createIcons();
        }
    }


</script>

<?php require_once 'includes/footer.php'; ?>