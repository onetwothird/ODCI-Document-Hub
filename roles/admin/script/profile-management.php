<?php
// script/profile-management.php
require_once '../../includes/config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Handle different actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'upload_profile_image':
        handleProfileImageUpload();
        break;
    case 'update_profile':
        handleProfileUpdate();
        break;
    case 'get_profile_data':
        handleGetProfileData();
        break;
    case 'delete_profile_image':
        handleDeleteProfileImage();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleProfileImageUpload() {
    global $pdo, $currentUser;
    
    if (!isset($_FILES['profile_image'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        return;
    }

    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }

    $uploadResult = uploadProfileImage($_FILES['profile_image'], $currentUser['id']);
    
    if ($uploadResult['success']) {
        try {
            // Delete old profile image if exists
            if (!empty($currentUser['profile_image'])) {
                deleteProfileImage($currentUser['profile_image']);
            }

            // Update database with new image path
            $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $stmt->execute([$uploadResult['path'], $currentUser['id']]);

            // Log activity
            logActivity($pdo, $currentUser['id'], 'update_profile_image', 'user', $currentUser['id'], 'Updated profile image');

            // Get the full URL for response
            $imageUrl = getProfileImageUrl($uploadResult['path']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Profile image updated successfully',
                'image_url' => $imageUrl,
                'image_path' => $uploadResult['path']
            ]);
        } catch (Exception $e) {
            error_log("Error updating profile image in database: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        }
    } else {
        echo json_encode($uploadResult);
    }
}

function handleProfileUpdate() {
    global $pdo, $currentUser;
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }

    $updateFields = [];
    $params = [];
    
    // List of updateable fields
    $allowedFields = ['name', 'mi', 'surname', 'phone', 'position', 'employee_id'];
    
    foreach ($allowedFields as $field) {
        if (isset($_POST[$field])) {
            $value = sanitizeInput($_POST[$field]);
            $updateFields[] = "$field = ?";
            $params[] = $value;
        }
    }

    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }

    try {
        $params[] = $currentUser['id']; // Add user ID for WHERE clause
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Log activity
        logActivity($pdo, $currentUser['id'], 'update_profile', 'user', $currentUser['id'], 'Updated profile information');

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
    } catch (Exception $e) {
        error_log("Error updating profile: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

function handleGetProfileData() {
    global $currentUser;
    
    // Remove sensitive information
    unset($currentUser['password']);
    
    $profileData = [
        'id' => $currentUser['id'],
        'username' => $currentUser['username'],
        'email' => $currentUser['email'],
        'name' => $currentUser['name'],
        'mi' => $currentUser['mi'],
        'surname' => $currentUser['surname'],
        'employee_id' => $currentUser['employee_id'],
        'position' => $currentUser['position'],
        'phone' => $currentUser['phone'],
        'profile_image' => $currentUser['profile_image'],
        'profile_image_url' => getProfileImageUrl($currentUser['profile_image']),
        'department_name' => $currentUser['department_name'],
        'department_code' => $currentUser['department_code'],
        'last_login' => $currentUser['last_login'],
        'created_at' => $currentUser['created_at']
    ];

    echo json_encode([
        'success' => true,
        'data' => $profileData
    ]);
}

function handleDeleteProfileImage() {
    global $pdo, $currentUser;
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }

    try {
        // Delete physical file
        if (!empty($currentUser['profile_image'])) {
            deleteProfileImage($currentUser['profile_image']);
        }

        // Update database
        $stmt = $pdo->prepare("UPDATE users SET profile_image = NULL WHERE id = ?");
        $stmt->execute([$currentUser['id']]);

        // Log activity
        logActivity($pdo, $currentUser['id'], 'delete_profile_image', 'user', $currentUser['id'], 'Deleted profile image');

        echo json_encode([
            'success' => true,
            'message' => 'Profile image deleted successfully',
            'default_image_url' => getProfileImageUrl('')
        ]);
    } catch (Exception $e) {
        error_log("Error deleting profile image: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}
?>