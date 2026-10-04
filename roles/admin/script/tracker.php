<?php
    require_once __DIR__ . '/../../../includes/config.php';
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }

    $currentUser = getCurrentUser($pdo);
    if (!$currentUser || !$currentUser['is_approved']) {
        header('Location: logout.php');
        exit();
    }

    if ($currentUser['role'] !== 'admin') {
        header('Location: ../admin/dashboard.php');
        exit();
    }

    $admin_department_id = null;
    $admin_department_name = 'Unknown Department';

    try {
        if (isset($currentUser['department_id']) && !empty($currentUser['department_id'])) {
            $admin_department_id = $currentUser['department_id'];
        }

        if (!$admin_department_id) {
            $stmt = $pdo->prepare("
                SELECT u.department_id, d.department_name, d.department_code
                FROM users u 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE u.id = ? AND u.role = 'admin'
            ");
            $stmt->execute([$currentUser['id']]);
            $user_dept = $stmt->fetch();
            
            if ($user_dept && $user_dept['department_id']) {
                $admin_department_id = $user_dept['department_id'];
                $admin_department_name = $user_dept['department_name'] ?? 'Department ' . $admin_department_id;
            }
        } else {
            $stmt = $pdo->prepare("SELECT department_name, department_code FROM departments WHERE id = ?");
            $stmt->execute([$admin_department_id]);
            $dept = $stmt->fetch();
            if ($dept) {
                $admin_department_name = $dept['department_name'];
            }
        }

        if (!$admin_department_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
            $stmt->execute();
            $admin_count = $stmt->fetch()['admin_count'];

            if ($admin_count == 1) {
                $admin_department_name = 'System Administrator (All Departments)';
            } else {
                die("
                    <div style='text-align: center; padding: 50px; font-family: Arial, sans-serif;'>
                        <h2 style='color: #dc3545;'>Department Assignment Required</h2>
                        <p>Your admin account is not assigned to any department.</p>
                        <p>Please contact the system administrator to assign your account to a department.</p>
                        <a href='logout.php' style='color: #007bff; text-decoration: none;'>← Logout and Contact Admin</a>
                    </div>
                ");
            }
        }
        
    } catch (Exception $e) {
        error_log("Department ID Error for Admin " . $currentUser['id'] . ": " . $e->getMessage());
        die("
            <div style='text-align: center; padding: 50px; font-family: Arial, sans-serif;'>
                <h2 style='color: #dc3545;'>System Error</h2>
                <p>Unable to determine department assignment. Please contact system administrator.</p>
                <p><small>Error ID: " . uniqid() . "</small></p>
                <a href='logout.php' style='color: #007bff; text-decoration: none;'>← Logout</a>
            </div>
        ");
    }

    // Check if parent_id column exists in departments table
    $hasParentId = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM departments LIKE 'parent_id'");
        $hasParentId = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error checking parent_id column: " . $e->getMessage());
        $hasParentId = false;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_file_details') {
        header('Content-Type: application/json');
        
        try {
            $faculty_id = (int)($_GET['faculty_id'] ?? 0);
            $doc_type = $_GET['document_type'] ?? '';
            $semester = $_GET['semester'] ?? '';
            $academic_year = (int)($_GET['academic_year'] ?? date('Y'));

            // The period filter is sent as "1st Semester AY 2026-2027"; the
            // department upload stores the year as "2026-2027" and the semester
            // as the files.semester enum, so reduce both here.
            $parsed = odci_period_parse($semester);
            if ($parsed['start_year'] > 0) {
                $academic_year = $parsed['start_year'];
            }
            $folderSemester = $parsed['start_year'] > 0
                ? $parsed['semester']
                : odci_semester_column($semester);

            error_log("get_file_details: faculty_id=$faculty_id, doc_type=$doc_type, academic_year=$academic_year, semester=$folderSemester");

            $faculty_check_query = "
                SELECT u.id, u.department_id, d.department_name
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.id = ? AND u.role = 'user'
            ";

            $stmt = $pdo->prepare($faculty_check_query);
            $stmt->execute([$faculty_id]);
            $faculty_dept = $stmt->fetch();
            
            if (!$faculty_dept) {
                throw new Exception('Faculty member not found');
            }

            $can_view = false;
            if (!$admin_department_id) {
                $can_view = true;
            } else {
                if ($faculty_dept['department_id'] == $admin_department_id) {
                    $can_view = true;
                }
            }
            
            if (!$can_view) {
                throw new Exception('Access denied: Faculty not in your department');
            }
            
            // Map the tracker document type back to its folder category
            $docTypeToCategory = odci_document_type_to_category();
            $folderCategory = $docTypeToCategory[$doc_type] ?? $doc_type;

            // Query files table joined with folders (new folders.php system)
            $stmt = $pdo->prepare("
                SELECT 
                    f.id, f.file_name, f.original_name, f.file_path, f.file_size,
                    f.uploaded_at, f.description, f.mime_type, f.file_extension,
                    COALESCE(f.download_count, 0) AS download_count,
                    fo.category as file_type,
                    CONCAT(u.name, ' ', u.surname) as uploader_name,
                    u.employee_id,
                    f.academic_year,
                    f.semester
                FROM files f
                INNER JOIN folders fo ON f.folder_id = fo.id
                INNER JOIN users u ON f.uploaded_by = u.id
                WHERE f.uploaded_by = ? 
                AND fo.category = ?
                AND f.semester = ?
                AND " . odci_academic_year_sql('f.academic_year') . "
                AND f.is_deleted = 0
                AND fo.is_deleted = 0
                ORDER BY f.uploaded_at DESC
            ");
            
            $stmt->execute([$faculty_id, $folderCategory, $folderSemester, $academic_year]);
            $files = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'files' => $files,
                'period' => $academic_year . '-' . ($academic_year + 1),
                'semester' => $folderSemester,
                'document_type' => $doc_type
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Get selected period - now dynamic based on available data
    $selectedSemester = $_GET['semester'] ?? '';
    $selectedYear = null;
    $normalizedSemester = '';

    // If no semester is selected, get the latest available period.
    // The source of truth is the same `files` + `folders` pair that the
    // submission matrix reads, so periods created by roles/user/folders.php
    // uploads are always selectable here.
    if (empty($selectedSemester)) {
        $latest_period_query = "
            SELECT DISTINCT
                " . odci_academic_year_expr('f.academic_year') . " AS start_year,
                f.semester AS semester,
                COUNT(f.id) AS file_count
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            INNER JOIN users u ON f.uploaded_by = u.id
            WHERE u.role = 'user' AND u.is_approved = 1
              AND f.is_deleted = 0 AND fo.is_deleted = 0
              AND fo.category IS NOT NULL
        ";

        $latest_params = [];
        if ($admin_department_id) {
            $latest_period_query .= " AND u.department_id = ?";
            $latest_params[] = $admin_department_id;
        }

        $latest_period_query .= "
            GROUP BY start_year, f.semester
            ORDER BY start_year DESC,
            CASE f.semester WHEN 'first' THEN 1 WHEN 'second' THEN 2 ELSE 3 END DESC
            LIMIT 1
        ";

        $stmt = $pdo->prepare($latest_period_query);
        $stmt->execute($latest_params);
        $latest_period = $stmt->fetch();

        if ($latest_period && (int)$latest_period['start_year'] > 0) {
            $selectedYear = (int)$latest_period['start_year'];
            $normalizedSemester = odci_semester_ordinal($latest_period['semester']);
            $selectedSemester = odci_period_key($selectedYear, $latest_period['semester']);
        } else {
            // Fallback to current year if no data
            $selectedYear = date('Y');
            $normalizedSemester = '2nd Semester';
            $selectedSemester = odci_period_key($selectedYear, 'second');
        }
    } else {
        // Parse selected semester
        $parsed = odci_period_parse($selectedSemester);
        if ($parsed['start_year'] > 0) {
            $normalizedSemester = odci_semester_ordinal($parsed['semester']);
            $selectedYear = $parsed['start_year'];
        }
    }

    $department_filter = $_GET['department'] ?? '';
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? ''; 
    
    // Get document types dynamically from document_requirements table based on selected period
    // This matches what folders.php uses for uploads
    $document_types = [];
    if ($selectedYear && $normalizedSemester) {
        $req_query = "
            SELECT DISTINCT document_type 
            FROM document_requirements 
            WHERE academic_year = ? AND semester = ? AND is_required = 1
            ORDER BY document_type
        ";
        $stmt = $pdo->prepare($req_query);
        $stmt->execute([$selectedYear, $normalizedSemester]);
        $document_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Fallback to the canonical list if no requirements found for the period
    if (empty($document_types)) {
        $document_types = odci_default_document_types();
    }

    $faculty_conditions = ["u.role = 'user'", "u.is_approved = 1"];
    $faculty_params = [];

    if ($admin_department_id) {
        if (!empty($department_filter)) {
            // Check if the filtered department is allowed for this admin
            if ($hasParentId) {
                $stmt = $pdo->prepare("
                    SELECT id FROM departments 
                    WHERE (id = ? OR parent_id = ?) AND id = ? AND is_active = 1
                ");
                $stmt->execute([$admin_department_id, $admin_department_id, $department_filter]);
                $allowed_dept = $stmt->fetch();
                
                if ($allowed_dept) {
                    $faculty_conditions[] = "u.department_id = ?";
                    $faculty_params[] = $department_filter;
                } else {
                    // Restrict to admin's department and sub-departments
                    $faculty_conditions[] = "(u.department_id = ? OR d.parent_id = ?)";
                    $faculty_params[] = $admin_department_id;
                    $faculty_params[] = $admin_department_id;
                }
            } else {
                $stmt = $pdo->prepare("
                    SELECT id FROM departments 
                    WHERE id = ? AND id = ? AND is_active = 1
                ");
                $stmt->execute([$admin_department_id, $department_filter]);
                $allowed_dept = $stmt->fetch();
                
                if ($allowed_dept) {
                    $faculty_conditions[] = "u.department_id = ?";
                    $faculty_params[] = $department_filter;
                } else {
                    $faculty_conditions[] = "u.department_id = ?";
                    $faculty_params[] = $admin_department_id;
                }
            }
        } else {
            // Show admin's department and sub-departments
            if ($hasParentId) {
                $faculty_conditions[] = "(u.department_id = ? OR d.parent_id = ?)";
                $faculty_params[] = $admin_department_id;
                $faculty_params[] = $admin_department_id;
            } else {
                $faculty_conditions[] = "u.department_id = ?";
                $faculty_params[] = $admin_department_id;
            }
        }
    } else {
        if (!empty($department_filter)) {
            $faculty_conditions[] = "u.department_id = ?";
            $faculty_params[] = $department_filter;
        }
    }

    if (!empty($search)) {
        $faculty_conditions[] = "(CONCAT(u.name, ' ', u.surname) LIKE ? OR u.employee_id LIKE ? OR u.email LIKE ?)";
        $search_term = '%' . $search . '%';
        $faculty_params[] = $search_term;
        $faculty_params[] = $search_term;
        $faculty_params[] = $search_term;
    }

    function getProfileImagePath($profileImage) {
        if (empty($profileImage)) {
            return null;
        }
        
        // Clean the path - remove leading slashes and normalize
        $cleanPath = ltrim($profileImage, '/');
        
        // Define possible paths to check
        $possiblePaths = [
            '../../' . $cleanPath, // Direct path as stored
            '../../uploads/profile_images/' . basename($cleanPath), // In profile_images folder
            '../../uploads/profiles/' . basename($cleanPath), // Alternative profiles folder
            '../../' . str_replace('uploads/', 'uploads/', $cleanPath) // Ensure uploads prefix
        ];
        
        // Check each possible path
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path . '?v=' . filemtime($path); // Add cache busting
            }
        }
        
        return null;
    }

    // Update the faculty query to ensure proper profile image handling
    $faculty_query = "
        SELECT u.id, u.name, u.mi, u.surname, u.employee_id, u.email, u.position, 
            u.profile_image,
            d.department_name, d.department_code, d.id as dept_id
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE " . implode(' AND ', $faculty_conditions) . "
        ORDER BY d.department_name, u.surname, u.name
    ";

    $stmt = $pdo->prepare($faculty_query);
    $stmt->execute($faculty_params);
    $faculty_raw = $stmt->fetchAll();

    // Process faculty data to include proper profile image paths
    $faculty = [];
    foreach ($faculty_raw as $staff) {
        $staff['profile_image_url'] = getProfileImagePath($staff['profile_image']);
        $faculty[] = $staff;
    }

    // Enhanced file submissions query using files table joined with folders (new folders.php system)
    $file_submissions = [];
    if (!empty($faculty) && $selectedYear && $normalizedSemester) {
        $faculty_ids = array_column($faculty, 'id');
        $placeholders = implode(',', array_fill(0, count($faculty_ids), '?'));
        
        // Convert normalized semester to folder semester format
        // normalizedSemester: '1st Semester' -> 'first', '2nd Semester' -> 'second'
        $folderSemester = odci_semester_column($normalizedSemester);
        
        // Query files table joined with folders for folders.php uploads
        // Map folder categories to document_types
        $file_query = "
            SELECT 
                f.uploaded_by as faculty_id,
                fo.category as folder_category,
                COUNT(f.id) as file_count,
                MAX(f.uploaded_at) as latest_upload,
                MIN(f.uploaded_at) as first_upload,
                SUM(f.file_size) as total_size,
                f.academic_year,
                f.semester
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            WHERE f.uploaded_by IN ($placeholders)
            AND " . odci_academic_year_sql('f.academic_year') . "
            AND f.semester = ?
            AND f.is_deleted = 0
            AND fo.is_deleted = 0
            AND fo.category IS NOT NULL
            GROUP BY f.uploaded_by, fo.category, f.academic_year, f.semester
        ";
        
        $stmt = $pdo->prepare($file_query);
        
        // Combine parameters: faculty_ids first, then year and semester (folder format: 'first'/'second')
        $params = array_merge($faculty_ids, [$selectedYear, $folderSemester]);
        $stmt->execute($params);
        
        // Build a lookup map: folder category -> document_type
        $categoryToDocType = odci_category_to_document_type();
        
        while ($row = $stmt->fetch()) {
            // Map folder category to document_type
            $matchedType = $categoryToDocType[$row['folder_category']] ?? $row['folder_category'];
            $file_submissions[$row['faculty_id']][$matchedType] = [
                'file_count' => $row['file_count'] ?: 0,
                'latest_upload' => $row['latest_upload'],
                'first_upload' => $row['first_upload'],
                'total_size' => $row['total_size'] ?: 0,
                'status' => $row['file_count'] > 0 ? 'submitted' : 'pending',
                'academic_year' => $row['academic_year'],
                'semester' => $row['semester']
            ];
        }
    }

    // Apply status filter
    if (!empty($status_filter)) {
        $filtered_faculty = [];
        foreach ($faculty as $f) {
            $submitted_count = 0;
            foreach ($document_types as $dt) {
                if (isset($file_submissions[$f['id']][$dt])) {
                    $submitted_count++;
                }
            }
            
            $completion_percentage = count($document_types) > 0 ? ($submitted_count / count($document_types)) * 100 : 0;
            
            $include = false;
            switch ($status_filter) {
                case 'complete':
                    $include = $completion_percentage == 100;
                    break;
                case 'partial':
                    $include = $completion_percentage > 0 && $completion_percentage < 100;
                    break;
                case 'none':
                    $include = $completion_percentage == 0;
                    break;
                default:
                    $include = true;
            }
            
            if ($include) {
                $filtered_faculty[] = $f;
            }
        }
        $faculty = $filtered_faculty;
    }

    // Calculate statistics
    $total_faculty = count($faculty);
    $total_possible = $total_faculty * count($document_types);
    $submitted_count = 0;
    $complete_faculty = 0;

    foreach ($faculty as $f) {
        $faculty_submitted = 0;
        foreach ($document_types as $dt) {
            if (isset($file_submissions[$f['id']][$dt])) {
                $submitted_count++;
                $faculty_submitted++;
            }
        }
        if ($faculty_submitted == count($document_types)) {
            $complete_faculty++;
        }
    }

    $completion_rate = $total_possible > 0 ? round(($submitted_count / $total_possible) * 100, 1) : 0;
    $faculty_completion_rate = $total_faculty > 0 ? round(($complete_faculty / $total_faculty) * 100, 1) : 0;

    // Get departments for filter - handle parent_id conditionally
    $dept_conditions = ["is_active = 1"];
    $dept_params = [];

    if ($admin_department_id) {
        if ($hasParentId) {
            $dept_conditions[] = "(id = ? OR parent_id = ?)";
            $dept_params[] = $admin_department_id;
            $dept_params[] = $admin_department_id;
        } else {
            $dept_conditions[] = "id = ?";
            $dept_params[] = $admin_department_id;
        }
    }

    $dept_query = "SELECT * FROM departments WHERE " . implode(' AND ', $dept_conditions) . " ORDER BY department_name";
    $stmt = $pdo->prepare($dept_query);
    $stmt->execute($dept_params);
    $departments = $stmt->fetchAll();

    function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return '1 byte';
        } else {
            return '0 bytes';
        }
    }

?>