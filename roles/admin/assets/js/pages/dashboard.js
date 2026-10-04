// Dashboard Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Animate stat cards on scroll
    const statCards = document.querySelectorAll('.box-info li, .stat-card, .status-item');
    
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                entry.target.style.animationDelay = `${index * 0.1}s`;
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    statCards.forEach(card => observer.observe(card));
    
    // Animate progress bars
    const progressBars = document.querySelectorAll('.progress-fill');
    const progressObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const fill = entry.target;
                const width = fill.style.width || fill.getAttribute('data-width') || '0%';
                fill.style.width = '0%';
                setTimeout(() => {
                    fill.style.width = width;
                }, 200);
                progressObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    progressBars.forEach(bar => progressObserver.observe(bar));
    
    // Card and table row hover is owned entirely by CSS (shadow and tint only, no
// movement). An inline transform here would override that policy and make the
// cards jump under the cursor, so the handlers are intentionally absent.
    
    // Action item hover effects
    const actionItems = document.querySelectorAll('.action-item');
    actionItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            const arrow = this.querySelector('.action-arrow, i.bx-chevron-right');
            if (arrow) {
                arrow.style.transform = 'translateX(4px)';
            }
        });
        item.addEventListener('mouseleave', function() {
            const arrow = this.querySelector('.action-arrow, i.bx-chevron-right');
            if (arrow) {
                arrow.style.transform = 'translateX(0)';
            }
        });
    });
    
    // Refresh button functionality
    const refreshBtn = document.querySelector('.btn-icon[title="Refresh"]');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            this.style.animation = 'spin 1s linear';
            setTimeout(() => {
                this.style.animation = '';
                // In a real app, you'd fetch fresh data here
                showNotification('Data refreshed', 'success');
            }, 1000);
        });
    }
    
    // Filter button functionality
    const filterBtns = document.querySelectorAll('.btn-icon[title="Filter"]');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            showNotification('Filter panel coming soon', 'info');
        });
    });
    
    // Export button functionality
    const exportBtns = document.querySelectorAll('.btn-icon[title="Export"]');
    exportBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            showNotification('Export functionality coming soon', 'info');
        });
    });
    
    // View toggle (grid/list)
    const gridView = document.getElementById('grid-view');
    const listView = document.getElementById('list-view');
    const gridContainer = document.getElementById('grid-container');
    const listContainer = document.getElementById('list-container');
    
    if (gridView && listView) {
        gridView.addEventListener('change', function() {
            if (this.checked) {
                gridContainer.style.display = 'block';
                listContainer.style.display = 'none';
            }
        });
        listView.addEventListener('change', function() {
            if (this.checked) {
                gridContainer.style.display = 'none';
                listContainer.style.display = 'block';
            }
        });
    }
    
    // Dropdown menus
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
    
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const menu = this.nextElementSibling;
            document.querySelectorAll('.dropdown-menu').forEach(m => {
                if (m !== menu) m.classList.remove('show');
            });
            menu.classList.toggle('show');
        });
    });
});

// Notification system
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = `notification ${type} show`;
    notification.innerHTML = `
        <i class="bx ${type === 'success' ? 'bx-check-circle' : type === 'error' ? 'bx-error-circle' : type === 'warning' ? 'bx-error' : 'bx-info-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Table sorting
function sortTable(table, column, asc = true) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const dir = asc ? 1 : -1;
    
    rows.sort((a, b) => {
        const aText = a.cells[column].textContent.trim();
        const bText = b.cells[column].textContent.trim();
        
        // Try numeric comparison
        const aNum = parseFloat(aText);
        const bNum = parseFloat(bText);
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return (aNum - bNum) * dir;
        }
        
        return aText.localeCompare(bText) * dir;
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Initialize sortable tables
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.enhanced-table th, .files-table th').forEach((th, index) => {
        th.style.cursor = 'pointer';
        th.addEventListener('click', function() {
            const table = this.closest('table');
            const currentAsc = this.dataset.sort === 'asc';
            this.dataset.sort = currentAsc ? 'desc' : 'asc';
            
            // Reset other headers
            table.querySelectorAll('th').forEach(h => {
                if (h !== this) delete h.dataset.sort;
            });
            
            sortTable(table, index, !currentAsc);
        });
    });
});

window.Dashboard = {
    showNotification,
    sortTable
};