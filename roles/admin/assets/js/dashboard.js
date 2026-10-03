
        // Enhanced Dashboard JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize animations
            initializeAnimations();
            
            // Initialize interactive elements
            initializeInteractiveElements();
            
            // Start real-time updates
            startRealTimeUpdates();
            
            // Initialize tooltips
            initializeTooltips();
        });

        function initializeAnimations() {
            // Animate progress bars on load
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach((bar, index) => {
                setTimeout(() => {
                    const width = bar.style.width;
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = width;
                    }, 100);
                }, index * 200);
            });

            // Animate stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
            });
        }

        function initializeInteractiveElements() {
            // Add hover effects to action items
            const actionItems = document.querySelectorAll('.action-item');
            actionItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(8px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });

            // Add click effects to buttons
            const buttons = document.querySelectorAll('.btn-download, .btn-secondary');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    // Create ripple effect
                    const ripple = document.createElement('span');
                    const rect = button.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255,255,255,0.3);
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                    `;
                    
                    button.style.position = 'relative';
                    button.style.overflow = 'hidden';
                    button.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Add refresh functionality
            const refreshBtn = document.querySelector('.btn-secondary');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    const icon = this.querySelector('i');
                    icon.style.animation = 'spin 1s linear';
                    
                    // Simulate data refresh
                    setTimeout(() => {
                        icon.style.animation = '';
                        showNotification('Data refreshed successfully!', 'success');
                    }, 1000);
                });
            }
        }

        function startRealTimeUpdates() {
            // Simulate real-time updates for demo purposes
            setInterval(() => {
                updateActiveUsers();
                updateServerStatus();
            }, 30000); // Update every 30 seconds
        }

        function updateActiveUsers() {
            const activeUsersElement = document.querySelector('.stat-card:nth-child(4) .stat-info h3');
            if (activeUsersElement) {
                const currentValue = parseInt(activeUsersElement.textContent.replace(',', ''));
                const newValue = currentValue + Math.floor(Math.random() * 20 - 10);
                activeUsersElement.textContent = newValue.toLocaleString();
            }
        }

        function updateServerStatus() {
            const serverStatusElement = document.querySelector('.status-item.healthy .status-value');
            if (serverStatusElement) {
                const uptime = (99.8 + Math.random() * 0.2).toFixed(1);
                serverStatusElement.textContent = uptime + '%';
            }
        }

        function initializeTooltips() {
            const tooltipElements = document.querySelectorAll('[title]');
            tooltipElements.forEach(element => {
                element.addEventListener('mouseenter', function(e) {
                    showTooltip(e.target, e.target.getAttribute('title'));
                });
                
                element.addEventListener('mouseleave', function() {
                    hideTooltip();
                });
            });
        }

        function showTooltip(element, text) {
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = text;
            tooltip.style.cssText = `
                position: absolute;
                background: var(--dark);
                color: white;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 12px;
                z-index: 10000;
                pointer-events: none;
                opacity: 0;
                transition: opacity 0.3s ease;
            `;
            
            document.body.appendChild(tooltip);
            
            const rect = element.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.bottom + 5 + 'px';
            
            setTimeout(() => {
                tooltip.style.opacity = '1';
            }, 10);
        }

        function hideTooltip() {
            const tooltip = document.querySelector('.custom-tooltip');
            if (tooltip) {
                tooltip.remove();
            }
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--${type === 'success' ? 'success' : 'info'});
                color: white;
                padding: 16px 20px;
                border-radius: 8px;
                font-weight: 500;
                z-index: 10000;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 10);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 1800);
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }
            
            .custom-tooltip::after {
                content: '';
                position: absolute;
                top: -5px;
                left: 50%;
                transform: translateX(-50%);
                width: 0;
                height: 0;
                border-left: 5px solid transparent;
                border-right: 5px solid transparent;
                border-bottom: 5px solid var(--dark);
            }
        `;
        document.head.appendChild(style);
