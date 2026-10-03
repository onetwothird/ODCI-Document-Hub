// ======================================
// UNIVERSAL SCRIPT - WORKS ON ALL PAGES
// ======================================

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeComponents();
});

// ======================================
// COMPONENT INITIALIZATION
// ======================================

function initializeComponents() {
    initializeSidebar();
    initializeNavbar();
    initializeTooltips();
    initializeResponsive();
    initializeTheme();
}

// ======================================
// SIDEBAR FUNCTIONALITY
// ======================================

function initializeSidebar() {
    // Handle sidebar menu active states
    const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');
    
    allSideMenu.forEach(item => {
        const li = item.parentElement;

        item.addEventListener('click', function (e) {
            // Don't prevent default - let navigation happen
            allSideMenu.forEach(i => {
                i.parentElement.classList.remove('active');
            });
            li.classList.add('active');
        });
    });

    // Toggle sidebar visibility
    const menuBar = document.querySelector('#content nav .toggle-sidebar');
    const sidebar = document.getElementById('sidebar');

    if (menuBar && sidebar) {
        menuBar.addEventListener('click', function () {
            sidebar.classList.toggle('expanded');
        });
    }

    // Set active sidebar item based on current page
    setActiveSidebarItemFromURL();
}

// ======================================
// NAVBAR FUNCTIONALITY
// ======================================

function initializeNavbar() {
    // Handle navbar search functionality
    const searchButton = document.querySelector('#content nav form .form-input button');
    const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
    const searchForm = document.querySelector('#content nav form');

    if (searchButton && searchButtonIcon && searchForm) {
        searchButton.addEventListener('click', function (e) {
            if(window.innerWidth < 576) {
                e.preventDefault();
                searchForm.classList.toggle('show');
                if(searchForm.classList.contains('show')) {
                    searchButtonIcon.classList.replace('bx-search', 'bx-x');
                } else {
                    searchButtonIcon.classList.replace('bx-x', 'bx-search');
                }
            }
        });
    }
}

// ======================================
// TOOLTIP FUNCTIONALITY
// ======================================

function initializeTooltips() {
    function updateTooltipPosition(menuItem, tooltip) {
        const rect = menuItem.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        // Position tooltip to the right of the menu item
        const left = rect.right + 10;
        const top = rect.top + (rect.height / 2) - (tooltipRect.height / 2);
        
        // Ensure tooltip doesn't go off screen
        const maxTop = window.innerHeight - tooltipRect.height - 10;
        const adjustedTop = Math.max(10, Math.min(top, maxTop));
        
        tooltip.style.left = left + 'px';
        tooltip.style.top = adjustedTop + 'px';
    }
    
    // Setup tooltip positioning for all sidebar menu items
    const sidebarMenuItems = document.querySelectorAll('#sidebar .side-menu li');
    
    sidebarMenuItems.forEach(menuItem => {
        const tooltip = menuItem.querySelector('.tooltip');
        if (tooltip) {
            // Show tooltip on mouse enter
            menuItem.addEventListener('mouseenter', function() {
                const sidebar = document.getElementById('sidebar');
                if (sidebar && !sidebar.classList.contains('expanded')) {
                    updateTooltipPosition(menuItem, tooltip);
                    tooltip.classList.add('show');
                }
            });
            
            // Hide tooltip on mouse leave
            menuItem.addEventListener('mouseleave', function() {
                tooltip.classList.remove('show');
            });
        }
    });
    
    // Update tooltip positions on window resize
    window.addEventListener('resize', function() {
        const visibleTooltips = document.querySelectorAll('#sidebar .tooltip.show');
        visibleTooltips.forEach(tooltip => {
            const menuItem = tooltip.closest('li');
            if (menuItem) {
                updateTooltipPosition(menuItem, tooltip);
            }
        });
    });
}

// ======================================
// THEME FUNCTIONALITY
// ======================================

function initializeTheme() {
    const switchModeToggle = document.getElementById('switch-mode');

    if (switchModeToggle) {
        // Load saved theme
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark');
            switchModeToggle.checked = true;
        }

        // Handle theme toggle
        switchModeToggle.addEventListener('change', function () {
            if(this.checked) {
                document.body.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.body.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            }
        });
    }
}

// ======================================
// RESPONSIVE FUNCTIONALITY
// ======================================

function initializeResponsive() {
    const sidebar = document.getElementById('sidebar');
    const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
    const searchForm = document.querySelector('#content nav form');
    
    // Initial setup - sidebar starts collapsed
    if (sidebar) {
        sidebar.classList.remove('expanded');
    }
    
    // Handle window resize events
    window.addEventListener('resize', function () {
        if(window.innerWidth > 576 && searchButtonIcon && searchForm) {
            searchButtonIcon.classList.replace('bx-x', 'bx-search');
            searchForm.classList.remove('show');
        }

        // Auto-collapse sidebar on mobile
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('expanded')) {
            sidebar.classList.remove('expanded');
        }
    });
}

// ======================================
// UTILITY FUNCTIONS
// ======================================

// Function to set active sidebar menu item based on current URL
function setActiveSidebarItemFromURL() {
    const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
    const allSideMenuItems = document.querySelectorAll('#sidebar .side-menu.top li');
    
    allSideMenuItems.forEach(item => {
        const link = item.querySelector('a');
        if (link) {
            const href = link.getAttribute('href');
            if (href && (href === currentPage || href.includes(currentPage))) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        }
    });
}

// Function to set active sidebar menu item programmatically
function setActiveSidebarItem(pageName) {
    const allSideMenuItems = document.querySelectorAll('#sidebar .side-menu.top li');
    
    allSideMenuItems.forEach(item => {
        const link = item.querySelector('a');
        if (link && link.getAttribute('href') && link.getAttribute('href').includes(pageName)) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

// Function to update page title in navbar
function updateNavbarTitle(title) {
    const pageTitle = document.getElementById('pageTitle');
    if (pageTitle) {
        pageTitle.textContent = title;
    }
}

// Function to update notification count
function updateNotificationCount(count) {
    const notificationCount = document.getElementById('notificationCount');
    if (notificationCount) {
        notificationCount.textContent = count;
        notificationCount.style.display = count > 0 ? 'flex' : 'none';
    }
}

// Function to update user avatar
function updateUserAvatar(avatarUrl) {
    const userAvatar = document.getElementById('userAvatar');
    if (userAvatar && avatarUrl) {
        userAvatar.src = avatarUrl;
    }
}

// Function to show notifications/alerts
function showAlert(message, type = 'info', duration = 5000) {
    // Remove existing alerts
    const existingAlert = document.querySelector('.app-alert');
    if (existingAlert) {
        existingAlert.remove();
    }

    // Create new alert
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} app-alert`;
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        min-width: 300px;
        max-width: 500px;
        animation: slideInRight 0.3s ease;
    `;

    const icons = {
        success: 'bx-check-circle',
        error: 'bx-error-circle',
        warning: 'bx-error-circle',
        info: 'bx-info-circle'
    };

    alert.innerHTML = `
        <i class='bx ${icons[type] || icons.info}'></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; font-size: 18px; cursor: pointer;">&times;</button>
    `;

    document.body.appendChild(alert);

    // Auto remove after duration
    if (duration > 0) {
        setTimeout(() => {
            if (alert.parentElement) {
                alert.style.animation = 'slideOutRight 0.3s ease forwards';
                setTimeout(() => alert.remove(), 300);
            }
        }, duration);
    }
}

// Function to initialize user data
function initializeUserData() {
    // This can be called by individual pages to load user-specific data
    const userAvatar = document.getElementById('userAvatar');
    const composerAvatar = document.getElementById('composerAvatar');
    const composerName = document.getElementById('composerName');
    
    // Try to get user data from a global variable or make an API call
    if (window.currentUser) {
        if (userAvatar && window.currentUser.profile_image) {
            userAvatar.src = window.currentUser.profile_image;
        }
        if (composerAvatar && window.currentUser.profile_image) {
            composerAvatar.src = window.currentUser.profile_image;
        }
        if (composerName && window.currentUser.full_name) {
            composerName.textContent = window.currentUser.full_name;
        }
    }
}

// Function to handle common form submissions
function handleFormSubmission(form, successCallback, errorCallback) {
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : '';
        
        try {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processing...';
            }

            const formData = new FormData(form);
            const response = await fetch(form.action || window.location.href, {
                method: form.method || 'POST',
                body: formData
            });

            if (response.ok) {
                const result = await response.text();
                if (successCallback) {
                    successCallback(result);
                } else {
                    showAlert('Operation completed successfully!', 'success');
                }
            } else {
                throw new Error(`HTTP ${response.status}`);
            }
        } catch (error) {
            if (errorCallback) {
                errorCallback(error);
            } else {
                showAlert('An error occurred. Please try again.', 'error');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    });
}

// ======================================
// CSS ANIMATIONS
// ======================================

// Add CSS animations for alerts
const alertStyles = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    .app-alert {
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
`;

// Add styles to document
const styleSheet = document.createElement('style');
styleSheet.textContent = alertStyles;
document.head.appendChild(styleSheet);

// ======================================
// EXPORT FUNCTIONS FOR OTHER MODULES
// ======================================

// Make utility functions available globally for other scripts
window.AppUtils = {
    setActiveSidebarItem: setActiveSidebarItem,
    updateNavbarTitle: updateNavbarTitle,
    updateNotificationCount: updateNotificationCount,
    updateUserAvatar: updateUserAvatar,
    showAlert: showAlert,
    initializeUserData: initializeUserData,
    handleFormSubmission: handleFormSubmission
};

// Also make individual functions available
window.setActiveSidebarItem = setActiveSidebarItem;
window.updateNavbarTitle = updateNavbarTitle;
window.updateNotificationCount = updateNotificationCount;
window.updateUserAvatar = updateUserAvatar;
window.showAlert = showAlert;