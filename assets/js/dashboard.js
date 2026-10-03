// Auto-refresh dashboard data every 5 minutes
        setInterval(function() {
            // implement AJAX refresh here
            console.log('Dashboard refresh check...');
        }, 300000);

        // Add click handlers for stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.style.cursor = 'pointer';
            card.addEventListener('click', function() {
                const text = this.querySelector('.stat-info p').textContent.toLowerCase();
                if (text.includes('users')) window.location.href = 'users.php';
                else if (text.includes('files')) window.location.href = 'files.php';
                else if (text.includes('folders')) window.location.href = 'folders.php';
                else if (text.includes('departments')) window.location.href = 'departments.php';
                else if (text.includes('announcements')) window.location.href = 'announcements.php';
            });
        });

        // Dark mode toggle
        const switchMode = document.getElementById('switch-mode');

        switchMode.addEventListener('change', function () {
            if(this.checked) {
                document.body.classList.add('dark');
            } else {
                document.body.classList.remove('dark');
            }
        })