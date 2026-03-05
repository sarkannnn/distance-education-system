/**
 * Distant Təhsil Sistemi - Main JavaScript
 */

// Initialize all components
function initApp() {
    // Initialize Lucide Icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Initialize components
    initSearch();
    initFilters();
    initNotifications();
    initToggleSwitches();
    initMobileSidebar();
    initTheme();
}

/**
 * Theme Toggle Functionality
 */
function initTheme() {
    const themeToggle = document.getElementById('theme-toggle');
    if (!themeToggle) return;

    const sunIcon = themeToggle.querySelector('.theme-icon-light');
    const moonIcon = themeToggle.querySelector('.theme-icon-dark');

    // Set initial icon state
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    if (currentTheme === 'dark') {
        sunIcon.style.display = 'block';
        moonIcon.style.display = 'none';
    } else {
        sunIcon.style.display = 'none';
        moonIcon.style.display = 'block';
    }

    themeToggle.addEventListener('click', () => {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const newTheme = isDark ? 'light' : 'dark';

        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);

        // Update icons
        if (newTheme === 'dark') {
            sunIcon.style.display = 'block';
            moonIcon.style.display = 'none';
        } else {
            sunIcon.style.display = 'none';
            moonIcon.style.display = 'block';
        }

        // Re-create icons if lucide is available
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
}

// Ensure the app initializes correctly
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}

/**
 * Global Search Functionality
 */
function initSearch() {
    const searchInput = document.getElementById('global-search');
    const searchResults = document.getElementById('search-results');
    if (!searchInput || !searchResults) return;

    let searchTimeout;

    searchInput.addEventListener('input', function (e) {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();

        if (query.length < 2) {
            searchResults.classList.remove('show');
            return;
        }

        searchTimeout = setTimeout(async () => {
            searchResults.innerHTML = '<div style="padding: 24px; text-align: center; color: var(--text-muted);"><i data-lucide="loader-2" class="animate-spin" style="margin: 0 auto 8px;"></i> Axtarılır...</div>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            searchResults.classList.add('show');
            await performSearch(query, searchResults);
        }, 300);
    });

    // Handle Enter key
    searchInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const firstResult = searchResults.querySelector('.search-result-item');
            if (firstResult) firstResult.click();
        }
    });

    // Close on click outside
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.remove('show');
        }
    });
}

async function performSearch(query, container) {
    try {
        const response = await fetch(`api/search.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();

        if (data.success) {
            renderSearchResults(data.results, container);
        } else {
            container.innerHTML = `<div class="no-results">${data.message || 'Xəta baş verdi'}</div>`;
        }
    } catch (error) {
        console.error('Search error:', error);
        container.innerHTML = '<div class="no-results">Sistem xətası baş verdi</div>';
    }
}

function renderSearchResults(results, container) {
    if (results.length === 0) {
        container.innerHTML = '<div class="no-results">Nəticə tapılmadı :(</div>';
        return;
    }

    const typeLabels = {
        'course': 'Fənlər',
        'lesson': 'Dərslər',
        'archived': 'Arxiv',
        'instructor': 'Müəllimlər'
    };

    const typeIcons = {
        'course': 'book-open',
        'lesson': 'video',
        'archived': 'archive',
        'instructor': 'users'
    };

    // Group by type
    const groups = results.reduce((acc, curr) => {
        if (!acc[curr.type]) acc[curr.type] = [];
        acc[curr.type].push(curr);
        return acc;
    }, {});

    let html = '';
    for (const type in groups) {
        html += `
            <div class="search-result-group">
                <div class="search-result-group-header">${typeLabels[type] || type}</div>
                ${groups[type].map(item => `
                    <div class="search-result-item" onclick="handleSearchResultClick('${item.type}', ${item.id})">
                        <div class="search-result-icon">
                            <i data-lucide="${typeIcons[item.type] || 'link'}" style="width: 18px; height: 18px;"></i>
                        </div>
                        <div class="search-result-info">
                            <div class="search-result-title">${item.title || item.course}</div>
                            <div class="search-result-meta">${item.instructor || item.description || ''}</div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    container.innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function handleSearchResultClick(type, id) {
    const searchResults = document.getElementById('search-results');
    if (searchResults) searchResults.classList.remove('show');

    switch (type) {
        case 'course':
            window.location.href = `lessons.php?id=${id}`;
            break;
        case 'lesson':
            window.location.href = `lessons.php?id=${id}`;
            break;
        case 'archived':
            window.location.href = `archive.php?id=${id}`;
            break;
        case 'instructor':
            // Sadece aramaya odaklanmak için
            break;
    }
}

/**
 * Filter Buttons
 */
function initFilters() {
    const filterBtns = document.querySelectorAll('.filter-btn');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const filterGroup = this.closest('.filters');
            const groupBtns = filterGroup.querySelectorAll('.filter-btn');

            groupBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            // Trigger filter change
            const filterValue = this.dataset.filter;
            const filterEvent = new CustomEvent('filterChange', { detail: { value: filterValue } });
            document.dispatchEvent(filterEvent);
        });
    });
}

/**
 * Notifications
 */
function initNotifications() {
    const notificationBtn = document.getElementById('notification-btn');
    const notificationDropdown = document.querySelector('.notifications-dropdown');

    if (notificationBtn && notificationDropdown) {
        notificationBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
        });

        document.addEventListener('click', function (e) {
            if (!notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('show');
            }
        });
    }

    // Load notifications
    loadNotifications();

    // Auto-refresh notifications every 60 seconds
    setInterval(loadNotifications, 60000);
}

async function loadNotifications() {
    try {
        const response = await fetch('api/notifications.php');
        const data = await response.json();

        if (data.success && data.notifications) {
            renderNotifications(data.notifications);
            updateNotificationBadge(data.unread_count);
        }
    } catch (error) {
        console.error('Notifications error:', error);
    }
}

function renderNotifications(notifications) {
    const container = document.querySelector('.notifications-list');
    if (!container) return;

    if (notifications.length === 0) {
        container.innerHTML = '<p style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 14px;">Yeni bildiriş yoxdur.</p>';
        return;
    }

    container.innerHTML = notifications.map(n => {
        const timeStr = typeof timeAgo === 'function' ? timeAgo(n.created_at) : '';
        const isLive = n.source === 'live';
        const itemClass = (n.is_read || isLive) ? '' : 'unread';

        let title = n.title;
        if (isLive && n.course_title) {
            title = `<span style="font-size: 10px; opacity: 0.8; display: block; font-weight: 500;">[${n.course_title}]</span>${n.title}`;
        }

        let liveStyles = '';
        let titleStyle = '';
        if (isLive) {
            const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
            const typeColors = isDarkMode ? {
                'error': { border: '#ef4444', bg: 'rgba(239, 68, 68, 0.1)', text: '#fca5a5' },
                'warning': { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.1)', text: '#fcd34d' },
                'success': { border: '#10b981', bg: 'rgba(16, 185, 129, 0.1)', text: '#6ee7b7' },
                'info': { border: '#3b82f6', bg: 'rgba(59, 130, 246, 0.1)', text: '#93c5fd' }
            } : {
                'error': { border: '#ef4444', bg: '#fff5f5', text: '#991b1b' },
                'warning': { border: '#f59e0b', bg: '#fffbeb', text: '#92400e' },
                'success': { border: '#10b981', bg: '#f0fdf4', text: '#065f46' },
                'info': { border: '#3b82f6', bg: '#eff6ff', text: '#1e3a8a' }
            };
            const c = typeColors[n.type] || typeColors.info;
            liveStyles = `border-left: 3px solid ${c.border}; background: ${c.bg};`;
            titleStyle = `color: ${c.text}; font-weight: 700;`;
        }

        return `
            <div class="notification-item ${itemClass}" style="${liveStyles}">
                <div class="notification-title" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                    <span style="${titleStyle}">
                        ${isLive ? '<span class="badge badge-live" style="padding: 2px 6px; font-size: 9px; margin-right: 5px; background: currentColor; color: #fff;">CANLI</span>' : ''}
                        ${title}
                    </span>
                    <span style="font-size: 11px; opacity: 0.6; white-space: nowrap;">${timeStr}</span>
                </div>
                <div class="notification-message" style="${isLive && document.documentElement.getAttribute('data-theme') !== 'dark' ? 'color: #333;' : ''}">${n.message}</div>
            </div>
        `;
    }).join('');
}

function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (!badge) return;

    if (count > 0) {
        badge.textContent = count > 9 ? '9+' : count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}

/**
 * Toggle Switches
 */
function initToggleSwitches() {
    const toggles = document.querySelectorAll('.toggle-switch input');

    toggles.forEach(toggle => {
        toggle.addEventListener('change', function () {
            const setting = this.dataset.setting;
            const value = this.checked;

            updateSetting(setting, value);
        });
    });
}

async function updateSetting(setting, value) {
    try {
        const response = await fetch('api/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ setting, value })
        });

        const data = await response.json();

        if (data.success) {
            showToast('Parametr yeniləndi', 'success');
        }
    } catch (error) {
        console.error('Settings error:', error);
        showToast('Xəta baş verdi', 'error');
    }
}

/**
 * Mobile Sidebar
 */
function initMobileSidebar() {
    const menuBtn = document.getElementById('mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (menuBtn && sidebar && !menuBtn.dataset.listener) {
        menuBtn.dataset.listener = 'true';
        menuBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        });

        if (overlay) {
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            });
        }

        // Naviqasiya linkinə basanda mobilde sidebar-ı bağla
        const navLinks = sidebar.querySelectorAll('.nav-item');
        navLinks.forEach(link => {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 1024) {
                    sidebar.classList.remove('open');
                    if (overlay) overlay.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });
        });
    }
}

/**
 * Toast Notifications
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('show');
    }, 10);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Form Validation
 */
function validateForm(form) {
    const inputs = form.querySelectorAll('[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('error');
        } else {
            input.classList.remove('error');
        }
    });

    return isValid;
}

/**
 * CSRF Token
 */
function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

/**
 * Format Date
 */
function formatDate(dateString) {
    const months = [
        'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'İyun',
        'İyul', 'Avqust', 'Sentyabr', 'Oktyabr', 'Noyabr', 'Dekabr'
    ];

    const date = new Date(dateString);
    return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
}

/**
 * Time Ago
 */
function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'İndicə';
    if (diff < 3600) return `${Math.floor(diff / 60)} dəqiqə əvvəl`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} saat əvvəl`;
    if (diff < 604800) return `${Math.floor(diff / 86400)} gün əvvəl`;

    return formatDate(dateString);
}

/**
 * Debounce Function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * API Request Helper
 */
async function apiRequest(endpoint, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': getCSRFToken()
        }
    };

    const config = { ...defaultOptions, ...options };

    try {
        const response = await fetch(endpoint, config);
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Xəta baş verdi');
        }

        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}
