<?php
/**
 * Sidebar Template for Teacher Portal
 */

$menuItems = [
    ['id' => 'dashboard', 'label' => 'İdarəetmə Paneli', 'icon' => 'home', 'url' => './'],
    ['id' => 'live', 'label' => 'Canlı Dərslər', 'icon' => 'video', 'url' => 'live-lessons.php'],
    ['id' => 'plan', 'label' => 'Arxiv və Resurslar', 'icon' => 'folder-archive', 'url' => 'plan.php'],
    ['id' => 'analytics', 'label' => 'Analitika', 'icon' => 'bar-chart-3', 'url' => 'analytics.php'],
    ['id' => 'settings', 'label' => 'Parametrlər', 'icon' => 'settings', 'url' => 'settings.php'],
];

$currentPage = $currentPage ?? 'dashboard';
?>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-container">
            <img src="assets/img/nsu_logo.png" alt="NSU Logo">
        </div>
        <div class="logo-details">
            <div class="uni-name">Naxçıvan Dövlət Universiteti</div>
            <div class="system-name">Müəllim Distant Təhsil Sistemi</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menuItems as $item): ?>
            <a href="<?php echo $item['url']; ?>"
                class="nav-item <?php echo $currentPage === $item['id'] ? 'active' : ''; ?>">
                <?php if ($currentPage === $item['id']): ?>
                    <div class="nav-indicator"></div>
                <?php endif; ?>
                <i data-lucide="<?php echo $item['icon']; ?>"></i>
                <span>
                    <?php echo $item['label']; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>