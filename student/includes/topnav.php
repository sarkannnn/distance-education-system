<?php
/**
 * Top Navigation Template
 */
?>

<header class="top-header">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Menyunu aç">
        <i data-lucide="menu"></i>
    </button>

    <div class="search-container">
        <i data-lucide="search"></i>
        <input type="text" placeholder="Dərs, material və ya təlimatçı axtar..." class="search-input" id="global-search"
            autocomplete="off">
        <div class="search-results-dropdown" id="search-results"></div>
    </div>

    <div class="header-actions">
        <button class="header-btn theme-toggle-btn" id="theme-toggle" title="Mövzunu dəyiş">
            <i data-lucide="moon" class="theme-icon-dark"></i>
            <i data-lucide="sun" class="theme-icon-light" style="display: none;"></i>
        </button>

        <button class="header-btn notification-btn" id="notification-btn">

            <i data-lucide="bell"></i>
            <span class="notification-badge"></span>
        </button>

        <!-- Notifications Dropdown -->
        <div class="notifications-dropdown">
            <div class="notification-header">Bildirişlər</div>
            <div class="notifications-list">
                <p style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 14px;">Yüklənir...</p>
            </div>
        </div>

        <a href="logout.php?action=exit" class="header-btn" id="exit-distant-btn"
            style="width: auto; padding: 0 16px; border-radius: 12px; font-size: 14px; font-weight: 500; text-decoration: none; display: flex; align-items: center; color: var(--text-primary);">
            Distant təhsildən çıx
        </a>
        <div class="user-info">
            <div class="user-avatar">
                <?php if (isset($currentUser['avatar']) && $currentUser['avatar']): ?>
                    <img src="<?php echo e($currentUser['avatar']); ?>" alt="User Avatar">
                <?php else: ?>
                    <span>
                        <?php echo isset($currentUser['first_name']) ? e($currentUser['first_name'][0]) : 'U'; ?>
                    </span>
                <?php endif; ?>
            </div>
            <span class="user-name">
                <?php echo !empty($currentUser['name']) ? e($currentUser['name']) : 'Tələbə'; ?>
            </span>
        </div>
    </div>
</header>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>