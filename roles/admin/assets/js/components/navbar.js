// Navbar Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Search form toggle
    const searchForm = document.querySelector('#content nav form');
    const searchInput = document.querySelector('#content nav form .form-input input');
    const searchBtn = document.querySelector('#content nav form .form-input button');
    
    if (searchBtn && searchInput) {
        searchBtn.addEventListener('click', function(e) {
            if (window.innerWidth <= 576) {
                e.preventDefault();
                searchForm.classList.toggle('show');
                if (searchForm.classList.contains('show')) {
                    searchInput.focus();
                }
            }
        });
    }
    
    // Profile dropdown
    const profileBtn = document.querySelector('#content nav .profile-btn');
    const profileDropdown = document.querySelector('.profile-dropdown');
    
    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', function(e) {
            if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        });
    }
    
    // Dark mode toggle
    const switchMode = document.getElementById('switch-mode');
    if (switchMode) {
        // Load saved preference
        const isDark = localStorage.getItem('darkMode') === 'true';
        if (isDark) {
            document.body.classList.add('dark');
            switchMode.checked = true;
        }
        
        switchMode.addEventListener('change', function() {
            if (this.checked) {
                document.body.classList.add('dark');
                localStorage.setItem('darkMode', 'true');
            } else {
                document.body.classList.remove('dark');
                localStorage.setItem('darkMode', 'false');
            }
        });
    }
    
    // Notification bell
    const notificationBell = document.querySelector('#content nav .notification');
    if (notificationBell) {
        notificationBell.addEventListener('click', function() {
            // Toggle notification panel (to be implemented)
            console.log('Notifications clicked');
        });
    }
    
    // Active nav link highlighting
    const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
    const navLinks = document.querySelectorAll('#content nav .nav-link');
    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });
});

// Dark mode initialization (run immediately to prevent flash)
(function() {
    const isDark = localStorage.getItem('darkMode') === 'true';
    if (isDark) {
        document.body.classList.add('dark');
    }
})();

window.Navbar = {
    showSearch: function() {
        const form = document.querySelector('#content nav form');
        if (form) form.classList.add('show');
    },
    hideSearch: function() {
        const form = document.querySelector('#content nav form');
        if (form) form.classList.remove('show');
    }
};