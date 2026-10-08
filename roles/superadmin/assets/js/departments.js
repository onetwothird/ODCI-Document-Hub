// Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Edit department function
        function editDepartment(deptId) {
            // Fetch department data via AJAX
            fetch(`get_department.php?id=${deptId}`)
                .then(response => response.json())
                .then(dept => {
                    document.getElementById('edit_department_id').value = dept.id;
                    document.getElementById('edit_department_code').value = dept.department_code;
                    document.getElementById('edit_department_name').value = dept.department_name;
                    document.getElementById('edit_description').value = dept.description || '';
                    document.getElementById('edit_head_of_department').value = dept.head_of_department || '';
                    document.getElementById('edit_contact_email').value = dept.contact_email || '';
                    document.getElementById('edit_contact_phone').value = dept.contact_phone || '';
                    document.getElementById('edit_is_active').checked = dept.is_active == 1;
                    
                    openModal('editDeptModal');
                })
                .catch(error => {
                    console.error('Error fetching department data:', error);
                    alert('Error loading department data');
                });
        }

        // Toggle department status
        function toggleStatus(deptId) {
            if (confirm('Are you sure you want to change the department status?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '?action=toggle_status';
                
                const deptIdInput = document.createElement('input');
                deptIdInput.type = 'hidden';
                deptIdInput.name = 'department_id';
                deptIdInput.value = deptId;
                
                form.appendChild(deptIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Delete department function
        function deleteDepartment(deptId, deptName) {
            if (confirm(`Are you sure you want to delete "${deptName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '?action=delete';
                
                const deptIdInput = document.createElement('input');
                deptIdInput.type = 'hidden';
                deptIdInput.name = 'department_id';
                deptIdInput.value = deptId;
                
                form.appendChild(deptIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-convert department code to uppercase
        document.getElementById('department_code').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });

        document.getElementById('edit_department_code').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });

        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 1800);