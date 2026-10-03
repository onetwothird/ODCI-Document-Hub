<?php

require_once dirname(__DIR__, 2) . '/includes/config.php';

// Google OAuth Configuration - Load from environment variables
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost/ODCI/login/script/google_callback.php');

class GoogleOAuth {
    
    public static function getAuthUrl() {
        $clientId = GOOGLE_CLIENT_ID;
        if (empty($clientId)) {
            return '';
        }
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'scope' => 'openid email profile',
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
    }
    
    public static function getAccessToken($code) {
        $clientId = GOOGLE_CLIENT_ID;
        $clientSecret = GOOGLE_CLIENT_SECRET;
        if (empty($clientId) || empty($clientSecret)) {
            return false;
        }
        $data = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'grant_type' => 'authorization_code',
            'code' => $code
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            error_log("cURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("HTTP Error $httpCode: " . $response);
            return false;
        }
        
        return json_decode($response, true);
    }
    
    public static function getUserInfo($accessToken) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $accessToken);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            error_log("cURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("HTTP Error $httpCode: " . $response);
            return false;
        }
        
        return json_decode($response, true);
    }
    
    public static function handleGoogleUser($pdo, $userInfo) {
        try {
            // Validate required user info
            if (!isset($userInfo['email']) || !isset($userInfo['name'])) {
                return ['success' => false, 'message' => 'Incomplete user information from Google.'];
            }
            
            // Check if user exists by email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$userInfo['email']]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                // User exists, check if approved and log them in
                if ($existingUser['is_approved']) {
                    self::loginUser($pdo, $existingUser);
                    return ['success' => true, 'message' => 'Welcome back!'];
                } else {
                    return ['success' => false, 'message' => 'Your account is pending approval. Please contact the administrator.'];
                }
            } else {
                // New user, create account
                $nameParts = self::parseFullName($userInfo['name']);
                $firstName = $nameParts['firstName'];
                $lastName = $nameParts['lastName'];
                
                // Generate unique username
                $username = self::generateUniqueUsername($pdo, $firstName, $lastName);
                
                // Create new user account
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        username, email, password, name, surname, 
                        is_approved, email_verified, created_at, role
                    ) VALUES (?, ?, ?, ?, ?, 0, 1, NOW(), 'user')
                ");
                
                // Generate a secure temporary password (user won't use it)
                $tempPassword = password_hash(generateToken(32), PASSWORD_DEFAULT);
                
                $stmt->execute([
                    $username,
                    $userInfo['email'],
                    $tempPassword,
                    $firstName,
                    $lastName
                ]);
                
                $userId = $pdo->lastInsertId();
                
                // Log registration activity
                logActivity($pdo, $userId, 'register_google', 'user', $userId, 'User registered via Google OAuth');
                
                return [
                    'success' => true, 
                    'message' => 'Account created successfully! Please wait for admin approval.',
                    'redirect' => '../../login.php?success=' . urlencode('Account created! Please wait for approval.')
                ];
            }
            
        } catch (Exception $e) {
            error_log("Google OAuth handleUser error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again later.'];
        }
    }
    
    private static function parseFullName($fullName) {
        $nameParts = explode(' ', trim($fullName));
        $firstName = $nameParts[0] ?? '';
        $lastName = '';
        
        if (count($nameParts) > 1) {
            $lastName = end($nameParts);
        }
        
        return [
            'firstName' => $firstName,
            'lastName' => $lastName
        ];
    }
    
    private static function generateUniqueUsername($pdo, $firstName, $lastName) {
        // Create base username from first and last name
        $baseUsername = strtolower(
            preg_replace('/[^a-zA-Z0-9]/', '', $firstName . $lastName)
        );
        
        // Ensure minimum length
        if (strlen($baseUsername) < 3) {
            $baseUsername = 'user' . $baseUsername;
        }
        
        $username = $baseUsername;
        $counter = 1;
        
        while (true) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if (!$stmt->fetch()) {
                break;
            }
            
            $username = $baseUsername . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    private static function loginUser($pdo, $user) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'] . ' ' . ($user['mi'] ? $user['mi'] . '. ' : '') . $user['surname'];
        $_SESSION['department_id'] = $user['department_id'];
        
        // Update last login timestamp
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Log successful login
        logActivity($pdo, $user['id'], 'login_google', 'user', $user['id'], 'User logged in via Google OAuth');
    }
    
    public static function isConfigured() {
        return GOOGLE_CLIENT_ID !== 'your-google-client-id.googleusercontent.com' &&
               GOOGLE_CLIENT_SECRET !== 'your-google-client-secret' &&
               !empty(GOOGLE_CLIENT_ID) &&
               !empty(GOOGLE_CLIENT_SECRET);
    }
}
?>