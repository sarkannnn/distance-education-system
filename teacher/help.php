<?php
/**
 * Yardım Mərkəzi
 */
$currentPage = 'help';
$pageTitle = 'Yardım Mərkəzi';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireInstructor();
$currentUser = $auth->getCurrentUser();

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
        <div class="content-container space-y-8">
            <div class="text-center space-y-4 py-8">
                <div
                    class="inline-flex items-center justify-center w-20 h-20 bg-primary bg-opacity-10 rounded-full mb-4">
                    <i data-lucide="help-circle" style="width: 40px; height: 40px; color: var(--primary);"></i>
                </div>
                <h1 style="font-size: 28px; font-weight: 700; color: var(--text-primary);">Yardım Mərkəzi</h1>
                <p style="color: var(--text-muted); font-size: 16px;">Sistemdən istifadə zamanı yaranan suallarınız üçün
                    köməkçi təlimatlar</p>
            </div>

            <style>
                .help-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 24px;
                }

                .help-card {
                    background: var(--bg-white);
                    border-radius: 16px;
                    padding: 24px;
                    border: 1px solid var(--border-color);
                    transition: all 0.3s ease;
                }

                .help-card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
                }

                .help-icon-box {
                    width: 48px;
                    height: 48px;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 20px;
                }
            </style>

            <div class="help-grid">
                <!-- Canlı Studio Təlimatı -->
                <div class="help-card" style="border-top: 4px solid var(--primary);">
                    <div class="help-icon-box" style="background: rgba(59, 130, 246, 0.1);">
                        <i data-lucide="video" style="color: #3b82f6;"></i>
                    </div>
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 12px; color: var(--text-primary);">
                        Canlı Studio İstifadəsı</h3>
                    <p style="color: var(--text-muted); font-size: 14px; line-height: 1.6;">
                        Canlı dərsi başlatmazdan əvvəl kameranızın və mikrofonunuzun icazəsini verdiyinizdən əmin olun.
                        Keyfiyyətli yayın üçün stabil internet bağlantısı tövsiyə edilir.
                    </p>
                </div>

                <!-- Arxiv və Resurslar -->
                <div class="help-card" style="border-top: 4px solid #10b981;">
                    <div class="help-icon-box" style="background: rgba(16, 185, 129, 0.1);">
                        <i data-lucide="folder-open" style="color: #10b981;"></i>
                    </div>
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 12px; color: var(--text-primary);">
                        Resursların İdarə Edilməsi</h3>
                    <p style="color: var(--text-muted); font-size: 14px; line-height: 1.6;">
                        Dərs materiallarını (PDF, Video) "Arxiv və Resurslar" bölməsindən əlavə edə bilərsiniz. Hər fənn
                        üzrə materiallar tələbələrə anında görünür.
                    </p>
                </div>

                <!-- Texniki Dəstək -->
                <div class="help-card" style="border-top: 4px solid #f59e0b;">
                    <div class="help-icon-box" style="background: rgba(245, 158, 11, 0.1);">
                        <i data-lucide="headphones" style="color: #f59e0b;"></i>
                    </div>
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 12px; color: var(--text-primary);">
                        Texniki Dəstək</h3>
                    <p style="color: var(--text-muted); font-size: 14px; line-height: 1.6;">
                        Hər hansı texniki problem yaşasanız, sistem administratoru ilə birbaşa əlaqə saxlaya bilərsiniz:
                        <br>
                        <strong style="color: var(--primary);">distant@ndu.edu.az</strong>
                    </p>
                </div>

                <!-- Tələbə Statistikası -->
                <div class="help-card" style="border-top: 4px solid #8b5cf6;">
                    <div class="help-icon-box" style="background: rgba(139, 92, 246, 0.1);">
                        <i data-lucide="bar-chart-3" style="color: #8b5cf6;"></i>
                    </div>
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 12px; color: var(--text-primary);">
                        Statistika Paneli</h3>
                    <p style="color: var(--text-muted); font-size: 14px; line-height: 1.6;">
                        Dashboard üzərindən ümumi tələbə sayınızı, tədris saatlarınızı və fəaliyyət tarixçənizi izləyə
                        bilərsiniz.
                    </p>
                </div>
            </div>

            <div
                style="background: var(--bg-white); border: 1px solid var(--border-color); border-radius: 20px; padding: 40px; margin-top: 48px; text-align: center;">
                <h2 style="font-size: 22px; font-weight: 700; margin-bottom: 12px; color: var(--text-primary);">Başqa
                    sualınız var?</h2>
                <p style="color: var(--text-muted); margin-bottom: 24px;">Distant Təhsil Mərkəzinin texniki dəstək
                    komandası sizə kömək etməyə hazırdır.</p>
                <a href="mailto:distant@ndu.edu.az" class="btn btn-primary"
                    style="padding: 12px 32px; border-radius: 12px; font-weight: 600;">
                    Sorğu Göndər
                </a>
            </div>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>