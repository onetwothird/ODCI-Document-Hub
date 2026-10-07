<?php include 'assets/script/users-script.php' ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <!-- Component Stylesheets -->
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/users.css?v=<?= time() ?>">

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body class="superadmin-users-page">
    <!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->
        <?php include 'components/navbar.html'; ?>

        <!-- Main Content -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>User Management</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Users</a></li>
                    </ul>
                </div>
                <div class="right">
                    <button class="btn btn-secondary" type="button" title="Refresh" onclick="window.location.reload()">
                        <i class='bx bx-refresh'></i>
                        <span>Refresh</span>
                    </button>
                    <button onclick="openModal('createUserModal')" class="btn btn-primary">
                        <i class='bx bx-user-plus'></i>
                        <span>Add User</span>
                    </button>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class='bx bx-check-circle'></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class='bx bx-error-circle'></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters">
                <div class="filter-tabs">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['filter' => 'all', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        All (<?php echo $counts['all']; ?>)
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['filter' => 'pending', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                        Pending (<?php echo $counts['pending']; ?>)
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['filter' => 'approved', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                        Approved (<?php echo $counts['approved']; ?>)
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['filter' => 'restricted', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $filter === 'restricted' ? 'active' : ''; ?>">
                        Restricted (<?php echo $counts['restricted']; ?>)
                    </a>
                </div>
                
                <div class="filter-group">
                    <label for="department-filter">Department:</label>
                    <select id="department-filter" onchange="filterUsers()">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="role-filter">Role:</label>
                    <select id="role-filter" onchange="filterUsers()">
                        <option value="">All Roles</option>
                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="super_admin" <?php echo $role_filter === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                    </select>
                </div>
            </div>

            <!-- User Grid with Profile Picture Support -->
            <div class="user-grid">
                <?php foreach ($users as $user): ?>
                    <div class="user-card">
                        <div class="user-header">
                            <div class="user-avatar">
                                <?php 
                                $first_letter = strtoupper(substr($user['name'], 0, 1));
                                $last_letter = strtoupper(substr($user['surname'], 0, 1));
                                $initials = $first_letter . $last_letter;
                                $has_profile_image = !empty($user['profile_image_url']);
                                ?>
                                
                                <?php if ($has_profile_image): ?>
                                    <img src="<?= htmlspecialchars($user['profile_image_url']) ?>" 
                                         alt="<?= htmlspecialchars($user['name'] . ' ' . $user['surname']) ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="fallback-avatar" 
                                         style="display: none; width: 100%; height: 100%; 
                                                background: linear-gradient(135deg, var(--cvsu-green-600), var(--cvsu-green-800)); 
                                                color: white; font-weight: bold; 
                                                align-items: center; justify-content: center; 
                                                border-radius: 50%;">
                                        <?= $initials ?>
                                    </div>
                                <?php else: ?>
                                    <div class="letter-avatar" 
                                         style="width: 100%; height: 100%; 
                                                background: linear-gradient(135deg, var(--cvsu-green-600), var(--cvsu-green-800)); 
                                                color: white; font-weight: bold; 
                                                display: flex; align-items: center; justify-content: center; 
                                                border-radius: 50%;">
                                        <?= $initials ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="user-info">
                                <h3><?php echo htmlspecialchars($user['name'] . ' ' . ($user['mi'] ? $user['mi'] . '. ' : '') . $user['surname']); ?></h3>
                                <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                            </div>
                        </div>
                        
                        <div class="user-details">
                            <div>
                                <span>Email:</span>
                                <span><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                            <div>
                                <span>Employee ID:</span>
                                <span><?php echo htmlspecialchars($user['employee_id'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <span>Department:</span>
                                <span><?php echo htmlspecialchars($user['department_code'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <span>Position:</span>
                                <span><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <span>Role:</span>
                                <span style="text-transform: capitalize;"><?php echo htmlspecialchars($user['role']); ?></span>
                            </div>
                            <div>
                                <span>Status:</span>
                                <span class="user-status <?php 
                                    if (!$user['is_approved']) echo 'status-pending';
                                    elseif ($user['is_restricted']) echo 'status-restricted';
                                    else echo 'status-approved';
                                ?>">
                                    <?php 
                                    if (!$user['is_approved']) echo 'Pending';
                                    elseif ($user['is_restricted']) echo 'Restricted';
                                    else echo 'Approved';
                                    ?>
                                </span>
                            </div>
                            <div>
                                <span>Joined:</span>
                                <span><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                            </div>
                            <?php if ($user['last_login']): ?>
                                <div>
                                    <span>Last Login:</span>
                                    <span><?php echo date('M j, Y', strtotime($user['last_login'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="user-actions">
                            <button onclick="editUser(<?php echo $user['id']; ?>)" class="btn btn-secondary">
                                <i class='bx bx-edit'></i> Edit
                            </button>
                            
                            <?php if (!$user['is_approved']): ?>
                                <button onclick="approveUser(<?php echo $user['id']; ?>)" class="btn btn-primary">
                                    <i class='bx bx-check'></i> Approve
                                </button>
                            <?php endif; ?>
                            
                            <button onclick="resetPassword(<?php echo $user['id']; ?>)" class="btn btn-warning">
                                <i class='bx bx-key'></i> Reset
                            </button>
                            
                            <?php if ($user['role'] !== 'super_admin'): ?>
                                <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name'] . ' ' . $user['surname']); ?>')" class="btn btn-danger">
                                    <i class='bx bx-trash'></i> Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class='bx bx-user' style="font-size: 64px; margin-bottom: 20px; display: block;"></i>
                    <h3>No users found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <!-- Create User Modal -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button type="button" class="close" onclick="closeModal('createUserModal')">&times;</button>
            </div>
            <form method="POST" action="?action=create">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required>
                    <small style="color: #666;">Must be at least 8 characters with uppercase, lowercase, and number</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">First Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="mi">M.I.</label>
                        <input type="text" id="mi" name="mi" maxlength="5">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="surname">Last Name *</label>
                    <input type="text" id="surname" name="surname" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="employee_id">Employee ID</label>
                        <input type="text" id="employee_id" name="employee_id">
                    </div>
                    <div class="form-group">
                        <label for="position">Position</label>
                        <input type="text" id="position" name="position">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select id="role" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3"></textarea>
                </div>
                
                <div class="modal-actions" style="justify-content: flex-end; margin-top: 30px;">
                    <button type="button" onclick="closeModal('createUserModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button type="button" class="close" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <form method="POST" action="?action=update" id="editUserForm">
                <input type="hidden" id="edit_user_id" name="user_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_name">First Name *</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_mi">M.I.</label>
                        <input type="text" id="edit_mi" name="mi" maxlength="5">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_surname">Last Name *</label>
                    <input type="text" id="edit_surname" name="surname" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_employee_id">Employee ID</label>
                        <input type="text" id="edit_employee_id" name="employee_id">
                    </div>
                    <div class="form-group">
                        <label for="edit_position">Position</label>
                        <input type="text" id="edit_position" name="position">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_department_id">Department</label>
                        <select id="edit_department_id" name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_role">Role *</label>
                        <select id="edit_role" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_phone">Phone</label>
                        <input type="tel" id="edit_phone" name="phone">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_address">Address</label>
                    <textarea id="edit_address" name="address" rows="3"></textarea>
                </div>
                
                <div style="display: flex; gap: 15px; margin: 20px 0;">
                    <div class="form-group checkbox">
                        <input type="checkbox" id="edit_is_approved" name="is_approved">
                        <label for="edit_is_approved">Account Approved</label>
                    </div>
                    <div class="form-group checkbox">
                        <input type="checkbox" id="edit_is_restricted" name="is_restricted">
                        <label for="edit_is_restricted">Account Restricted</label>
                    </div>
                </div>
                
                <div class="modal-actions" style="justify-content: flex-end; margin-top: 30px;">
                    <button type="button" onclick="closeModal('editUserModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reset Password</h3>
                <button type="button" class="close" onclick="closeModal('resetPasswordModal')">&times;</button>
            </div>
            <form method="POST" action="?action=reset_password">
                <input type="hidden" id="reset_user_id" name="user_id">
                
                <div class="form-group">
                    <label for="new_password">New Password *</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <small style="color: #666;">Must be at least 8 characters with uppercase, lowercase, and number</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="modal-actions" style="justify-content: flex-end; margin-top: 30px;">
                    <button type="button" onclick="closeModal('resetPasswordModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/users.js?v=<?= time() ?>"></script>

    <!-- Page-specific initialization -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set active sidebar item and page title for users
            if (window.AppUtils) {
                window.AppUtils.setActiveSidebarItem('users.php');
                window.AppUtils.updateNavbarTitle('User Management');
            }

            // Initialize search for users if needed
            const navbarSearchForm = document.getElementById('navbarSearchForm');
            if (navbarSearchForm) {
                navbarSearchForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const searchInput = document.getElementById('navbarSearchInput');
                    if (searchInput && searchInput.value.trim()) {
                        // Redirect to users page with search parameter
                        window.location.href = `users.php?search=${encodeURIComponent(searchInput.value.trim())}`;
                    }
                });
            }
        });
    </script>
</body>
</html>
