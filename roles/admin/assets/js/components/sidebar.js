// Sidebar Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    const menuBtn = document.querySelector('.bx-menu');
    const drawerBack = document.querySelector('.cvsu-drawer-back');
    
    // Toggle sidebar on menu button click
    if (menuBtn) {
        menuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('expanded');
            if (content) {
                // Content adjustment handled by CSS
            }
        });
    }
    
    // Close sidebar on drawer back button click
    if (drawerBack) {
        drawerBack.addEventListener('click', function() {
            sidebar.classList.remove('expanded');
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 576) {
            sidebar.classList.remove('expanded');
        }
    });
    
    // Tooltip functionality for collapsed sidebar
    const sidebarItems = document.querySelectorAll('#sidebar .side-menu li');
    
    sidebarItems.forEach(item => {
        const tooltip = item.querySelector('.tooltip');
        if (!tooltip) return;
        
        item.addEventListener('mouseenter', function() {
            if (!sidebar.classList.contains('expanded') && window.innerWidth > 576) {
                tooltip.classList.add('show');
            }
        });
        
        item.addEventListener('mouseleave', function() {
            tooltip.classList.remove('show');
        });
    });
    
    // Set active menu item based on current page
    const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
    const activeLink = document.querySelector(`#sidebar a[href="${currentPage}"]`);
    if (activeLink) {
        activeLink.closest('li').classList.add('active');
    }
});

// Export for use in other scripts
window.Sidebar = {
    toggle: function() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.toggle('expanded');
        }
    },
    close: function() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.remove('expanded');
        }
    }
};