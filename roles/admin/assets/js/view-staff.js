// Enhanced Faculty Management JavaScript
        let selectedFaculty = new Set();
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeFilters();
            initializeProgressAnimations();
            initializeBulkActions();
            setupKeyboardShortcuts();
            
            // Debug: Log all image sources for troubleshooting
            document.querySelectorAll('.faculty-avatar').forEach(img => {
                console.log('Image source:', img.src, 'Loaded:', img.complete && img.naturalHeight !== 0);
            });
        });

        function initializeFilters() {
            const facultySearch = document.getElementById('facultySearch');
            const completionFilter = document.getElementById('completionFilter');
            const activityFilter = document.getElementById('activityFilter');
            const verificationFilter = document.getElementById('verificationFilter');
            
            [facultySearch, completionFilter, activityFilter, verificationFilter].forEach(element => {
                if (element) {
                    element.addEventListener('input', filterFaculty);
                    element.addEventListener('change', filterFaculty);
                }
            });
        }

        function filterFaculty() {
            const searchTerm = document.getElementById('facultySearch')?.value.toLowerCase() || '';
            const completionValue = document.getElementById('completionFilter')?.value || '';
            const activityValue = document.getElementById('activityFilter')?.value || '';
            const verificationValue = document.getElementById('verificationFilter')?.value || '';
            
            const facultyCards = document.querySelectorAll('.faculty-card');
            let visibleCount = 0;
            
            facultyCards.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                const position = card.getAttribute('data-position') || '';
                const employeeId = card.getAttribute('data-employee-id') || '';
                const completion = card.getAttribute('data-completion') || '';
                const activity = card.getAttribute('data-activity') || '';
                const verification = card.getAttribute('data-verification') || '';
                
                let showCard = true;
                
                // Search filter
                if (searchTerm && !name.includes(searchTerm) && 
                    !position.includes(searchTerm) && !employeeId.includes(searchTerm)) {
                    showCard = false;
                }
                
                // Completion filter
                if (completionValue && completion !== completionValue) {
                    showCard = false;
                }
                
                // Activity filter
                if (activityValue && activity !== activityValue) {
                    showCard = false;
                }

                // Verification filter
                if (verificationValue && verification !== verificationValue) {
                    showCard = false;
                }
                
                card.style.display = showCard ? 'flex' : 'none';
                if (showCard) visibleCount++;
            });
            
            updateNoResultsMessage(visibleCount);
        }

        function updateNoResultsMessage(visibleCount) {
            let noResultsMsg = document.querySelector('.no-results');
            
            if (visibleCount === 0 && document.querySelectorAll('.faculty-card').length > 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.className = 'no-results';
                    noResultsMsg.innerHTML = `
                        <i class='bx bx-search'></i>
                        <h3>No Results Found</h3>
                        <p>Try adjusting your search criteria or filters to find faculty members.</p>
                    `;
                    document.getElementById('facultyList').appendChild(noResultsMsg);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        }

        function initializeProgressAnimations() {
            const progressBars = document.querySelectorAll('.progress-fill');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const bar = entry.target;
                        const width = bar.style.width;
                        bar.style.width = '0%';
                        setTimeout(() => {
                            bar.style.width = width;
                        }, 100);
                        observer.unobserve(bar);
                    }
                });
            });

            progressBars.forEach(bar => observer.observe(bar));
        }

        function initializeBulkActions() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const facultyCheckboxes = document.querySelectorAll('.faculty-checkbox');
            
            selectAllCheckbox?.addEventListener('change', function() {
                facultyCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    updateSelectedFaculty(checkbox);
                });
            });

            facultyCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectedFaculty(this);
                    updateSelectAllState();
                });
            });
        }

        function updateSelectedFaculty(checkbox) {
            const facultyId = checkbox.value;
            if (checkbox.checked) {
                selectedFaculty.add(facultyId);
            } else {
                selectedFaculty.delete(facultyId);
            }
            
            document.getElementById('selectedCount').textContent = selectedFaculty.size;
            document.getElementById('bulkActions').classList.toggle('active', selectedFaculty.size > 0);
        }

        function updateSelectAllState() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const facultyCheckboxes = document.querySelectorAll('.faculty-checkbox:not([style*="display: none"])');
            const checkedBoxes = document.querySelectorAll('.faculty-checkbox:checked:not([style*="display: none"])');
            
            if (facultyCheckboxes.length === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (checkedBoxes.length === facultyCheckboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else if (checkedBoxes.length > 0) {
                selectAllCheckbox.indeterminate = true;
                selectAllCheckbox.checked = false;
            } else {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            }
        }

        // View toggle functionality
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const view = this.getAttribute('data-view');
                const facultyList = document.getElementById('facultyList');
                
                if (view === 'compact') {
                    facultyList.classList.add('compact-view');
                } else {
                    facultyList.classList.remove('compact-view');
                }
                
                localStorage.setItem('facultyViewMode', view);
            });
        });

        // Message Modal Functions
        function openMessageModal(facultyId, facultyName) {
            document.getElementById('recipientId').value = facultyId;
            document.getElementById('recipientName').value = facultyName;
            document.getElementById('messageModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMessageModal() {
            document.getElementById('messageModal').classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('messageForm').reset();
        }

        // Handle message form submission
        document.getElementById('messageForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'send_message');
            
            try {
                const response = await fetch('script/messaging-system.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Message sent successfully!', 'success');
                    closeMessageModal();
                } else {
                    showNotification(result.message || 'Failed to send message', 'error');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                showNotification('Failed to send message. Please try again.', 'error');
            }
        });

        // Bulk message function
        async function bulkSendMessage() {
            if (selectedFaculty.size === 0) {
                showNotification('Please select faculty members first', 'error');
                return;
            }

            const subject = prompt('Enter message subject (optional):') || '';
            const message = prompt('Enter your message:');
            
            if (!message) return;

            const formData = new FormData();
            formData.append('action', 'send_bulk_message');
            formData.append('recipient_ids', JSON.stringify([...selectedFaculty]));
            formData.append('subject', subject);
            formData.append('message', message);
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

            try {
                const response = await fetch('script/messaging-system.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification(`Message sent to ${result.success_count} faculty members`, 'success');
                    clearSelection();
                } else {
                    showNotification(result.message || 'Failed to send bulk message', 'error');
                }
            } catch (error) {
                console.error('Error sending bulk message:', error);
                showNotification('Failed to send bulk message', 'error');
            }
        }

        // Show detailed stats
        async function showDetailedStats(facultyId) {
            try {
                const formData = new FormData();
                formData.append('action', 'get_faculty_stats');
                formData.append('faculty_id', facultyId);
                formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

                const response = await fetch('script/faculty-staff.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    displayStatsModal(result.data);
                } else {
                    showNotification('Failed to load statistics', 'error');
                }
            } catch (error) {
                console.error('Error loading stats:', error);
                showNotification('Failed to load statistics', 'error');
            }
        }

        function displayStatsModal(stats) {
            // Create and show stats modal (implementation depends on your modal system)
            alert(`Faculty Statistics:\nCompletion Rate: ${stats.completion_rate}%\nSubmissions: ${stats.total_submitted}/${stats.total_required}\nOverdue: ${stats.overdue_count}\nStreak: ${stats.submission_streak} months`);
        }

        // Export selected faculty
        function exportSelected() {
            if (selectedFaculty.size === 0) {
                showNotification('Please select faculty members first', 'error');
                return;
            }

            const selectedData = [...selectedFaculty].map(id => {
                const card = document.querySelector(`[data-faculty-id="${id}"]`);
                return {
                    name: card.querySelector('.faculty-name').textContent.trim(),
                    position: card.querySelector('.faculty-position').textContent.trim(),
                    email: card.querySelector('.meta-item i.bx-envelope').parentElement.textContent.trim(),
                    completion: card.querySelector('.progress-percentage').textContent.trim()
                };
            });

            downloadCSV(selectedData, 'selected_faculty.csv');
        }

        function downloadCSV(data, filename) {
            const csv = 'Name,Position,Email,Completion\n' + 
                       data.map(row => `"${row.name}","${row.position}","${row.email}","${row.completion}"`).join('\n');
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', filename);
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        function clearSelection() {
            selectedFaculty.clear();
            document.querySelectorAll('.faculty-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectedCount').textContent = '0';
            document.getElementById('bulkActions').classList.remove('active');
            document.getElementById('selectAll').checked = false;
            document.getElementById('selectAll').indeterminate = false;
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => notification.classList.add('show'), 100);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 1800);
        }

        // Keyboard shortcuts
        function setupKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    switch(e.key) {
                        case 'f':
                            e.preventDefault();
                            document.getElementById('facultySearch')?.focus();
                            break;
                        case 'a':
                            e.preventDefault();
                            document.getElementById('selectAll')?.click();
                            break;
                        case 'e':
                            e.preventDefault();
                            if (selectedFaculty.size > 0) exportSelected();
                            break;
                    }
                }
                
                if (e.key === 'Escape') {
                    if (document.getElementById('messageModal').classList.contains('active')) {
                        closeMessageModal();
                    }
                    clearSelection();
                }
            });
        }

        // Close modals when clicking outside
        document.getElementById('messageModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeMessageModal();
            }
        });

        // Auto-refresh functionality (optional)
        function startAutoRefresh() {
            setInterval(() => {
                // Refresh data without full page reload
                // Implementation would depend on your requirements
            }, 300000); // 5 minutes
        }

        // Load saved view mode
        const savedViewMode = localStorage.getItem('facultyViewMode');
        if (savedViewMode === 'compact') {
            document.querySelector('[data-view="compact"]')?.click();
        }