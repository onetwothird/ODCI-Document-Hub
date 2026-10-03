// Dashboard JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all dashboard functionality
    initSidebar();
    initNavbar();
    initAnimations();
    initInteractiveElements();
});

function initSidebar() {
    const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');

    allSideMenu.forEach(item => {
        const li = item.parentElement;

        item.addEventListener('click', function () {
            allSideMenu.forEach(i => {
                i.parentElement.classList.remove('active');
            })
            li.classList.add('active');
        })
    });

}

// Navbar functionality
function initNavbar() {
    // Search functionality
    const searchButton = document.querySelector('#content nav form .form-input button');
    const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
    const searchForm = document.querySelector('#content nav form');

    if (searchButton && searchButtonIcon && searchForm) {
        searchButton.addEventListener('click', function (e) {
            if (window.innerWidth < 576) {
                e.preventDefault();
                searchForm.classList.toggle('show');
                if (searchForm.classList.contains('show')) {
                    searchButtonIcon.classList.replace('bx-search', 'bx-x');
                } else {
                    searchButtonIcon.classList.replace('bx-x', 'bx-search');
                }
            }
        });

        // Handle responsive search
        if (window.innerWidth > 576) {
            searchButtonIcon.classList.replace('bx-x', 'bx-search');
            searchForm.classList.remove('show');
        }

        window.addEventListener('resize', function () {
            if (window.innerWidth > 576) {
                searchButtonIcon.classList.replace('bx-x', 'bx-search');
                searchForm.classList.remove('show');
            }
        });
    }

    // Dark mode toggle
    const switchMode = document.getElementById('switch-mode');

    if (switchMode) {
        switchMode.addEventListener('change', function () {
            if (this.checked) {
                document.body.classList.add('dark');
            } else {
                document.body.classList.remove('dark');
            }
        });
    }
}

// Animation functionality
function initAnimations() {
    // Animate statistics cards on load
    const statCards = document.querySelectorAll('.box-info li');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Add click animations to action items
    const actionItems = document.querySelectorAll('.action-item');
    actionItems.forEach(item => {
        item.addEventListener('click', function(e) {
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
}

// Interactive elements functionality
function initInteractiveElements() {
    // Refresh button functionality
    const refreshBtn = document.querySelector('.card-actions i[title="Refresh"]');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            this.style.transform = 'rotate(360deg)';
            this.style.transition = 'transform 0.6s ease';
            setTimeout(() => {
                this.style.transform = '';
                // Here you could add actual refresh functionality
                console.log('Refreshing dashboard data...');
            }, 600);
        });
    }

    // Add hover effects for table rows
    const tableRows = document.querySelectorAll('.files-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(4px)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });

    // Profile tooltip functionality
    const profileElement = document.querySelector('.profile');
    if (profileElement) {
        profileElement.addEventListener('mouseenter', function() {
            // Could add custom tooltip functionality here if needed
        });
    }

    // Card actions functionality
    const cardActions = document.querySelectorAll('.card-actions i');
    cardActions.forEach(action => {
        action.addEventListener('click', function() {
            const title = this.getAttribute('title');
            switch(title) {
                case 'Search Files':
                    handleSearchFiles();
                    break;
                case 'Filter Files':
                    handleFilterFiles();
                    break;
                case 'View Analytics':
                    handleViewAnalytics();
                    break;
                case 'System Information':
                    handleSystemInfo();
                    break;
                case 'Edit Profile':
                    handleEditProfile();
                    break;
                default:
                    console.log('Action clicked:', title);
            }
        });
    });
}

// Handler functions for card actions
function handleSearchFiles() {
    console.log('Opening file search...');
    // Implement file search functionality
}

function handleFilterFiles() {
    console.log('Opening file filters...');
    // Implement file filtering functionality
}

function handleViewAnalytics() {
    console.log('Opening analytics view...');
    // Implement analytics view functionality
}

function handleSystemInfo() {
    console.log('Opening system information...');
    // Implement system info display
}

function handleEditProfile() {
    console.log('Opening profile editor...');
    // Implement profile editing functionality
    window.location.href = 'settings.php';
}

// Utility functions
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Style the notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 24px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 1000;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        background: ${type === 'success' ? '#059669' : type === 'error' ? '#dc2626' : '#3b82f6'};
    `;
    
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Hide notification
    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 1800);
}

// Export functions for external use if needed
window.dashboardUtils = {
    showNotification,
    refreshDashboard: function() {
        location.reload();
    },
    toggleSidebar: function() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.toggle('hide');
        }
    }
};