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

        // Filter functions
        function filterUsers() {
            const department = document.getElementById('department-filter') ? document.getElementById('department-filter').value : '';
            const role = document.getElementById('role-filter') ? document.getElementById('role-filter').value : '';
            
            const params = new URLSearchParams(window.location.search);
            
            if (department) {
                params.set('department', department);
            } else {
                params.delete('department');
            }
            
            if (role) {
                params.set('role', role);
            } else {
                params.delete('role');
            }
            
            params.set('page', '1');
            window.location.search = params.toString();
        }

        // Edit user function
        function editUser(userId) {
            // Fetch user data via AJAX
            fetch(`get_user.php?id=${userId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch user data');
                    }
                    return response.json();
                })
                .then(user => {
                    if (user.error) {
                        alert(user.error);
                        return;
                    }
                    document.getElementById('edit_user_id').value = user.id;
                    document.getElementById('edit_name').value = user.name || '';
                    document.getElementById('edit_mi').value = user.mi || '';
                    document.getElementById('edit_surname').value = user.surname || '';
                    document.getElementById('edit_employee_id').value = user.employee_id || '';
                    document.getElementById('edit_position').value = user.position || '';
                    document.getElementById('edit_department_id').value = user.department_id || '';
                    document.getElementById('edit_role').value = user.role || '';
                    document.getElementById('edit_phone').value = user.phone || '';
                    document.getElementById('edit_address').value = user.address || '';
                    document.getElementById('edit_is_approved').checked = user.is_approved == 1;
                    document.getElementById('edit_is_restricted').checked = user.is_restricted == 1;
                    
                    openModal('editUserModal');
                })
                .catch(error => {
                    console.error('Error fetching user data:', error);
                    alert('Error loading user data');
                });
        }

        // Approve user function
        function approveUser(userId) {
            if (confirm('Are you sure you want to approve this user?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '?action=approve';
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                
                form.appendChild(userIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Reset password function
        function resetPassword(userId) {
            document.getElementById('reset_user_id').value = userId;
            openModal('resetPasswordModal');
        }

        // Delete user function
        function deleteUser(userId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?action=delete';
            
            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'user_id';
            userIdInput.value = userId;
            
            form.appendChild(userIdInput);
            document.body.appendChild(form);
            form.submit();
        }

        function confirmDeleteUser(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
            openModal('deleteUserModal');
        }

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirm = this.value;
            
            if (password !== confirm) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 1800);