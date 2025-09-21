<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'vibely_db');

// Social Login Configuration
define('GOOGLE_CLIENT_ID', 'your_google_client_id_here');
define('GOOGLE_CLIENT_SECRET', 'your_google_client_secret_here');
define('GOOGLE_REDIRECT_URI', 'http://localhost/vibely/social_login.php?provider=google');

define('FACEBOOK_APP_ID', 'your_facebook_app_id_here');
define('FACEBOOK_APP_SECRET', 'your_facebook_app_secret_here');
define('FACEBOOK_REDIRECT_URI', 'http://localhost/vibely/social_login.php?provider=facebook');

// Application Configuration
define('APP_URL', 'http://localhost/vibely');
define('APP_NAME', 'Vibely');
define('SUPPORT_EMAIL', 'support@vibely.com');

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Start session with security settings
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_strict_mode' => true
]);

// Security function to prevent XSS
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_token']);
}

// Generate CSRF token
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Get current user info
function getCurrentUser() {
    if (isLoggedIn()) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    return null;
}

// Send email function
function sendEmail($to, $subject, $message) {
    $headers = "From: " . SUPPORT_EMAIL . "\r\n";
    $headers .= "Reply-To: " . SUPPORT_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Generate random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Check if user has role in community
function hasCommunityRole($community_id, $user_id, $role) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $community_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $member = $result->fetch_assoc();
        return $member['role'] === $role;
    }
    return false;
}

// Rate limiting function
function checkRateLimit($key, $limit = 5, $timeout = 60) {
    $current_time = time();
    $rate_key = "rate_limit_{$key}";
    
    if (!isset($_SESSION[$rate_key])) {
        $_SESSION[$rate_key] = [
            'count' => 1,
            'time' => $current_time
        ];
        return true;
    }
    
    $rate_data = $_SESSION[$rate_key];
    
    if ($current_time - $rate_data['time'] > $timeout) {
        $_SESSION[$rate_key] = [
            'count' => 1,
            'time' => $current_time
        ];
        return true;
    }
    
    if ($rate_data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION[$rate_key]['count']++;
    return true;
}
?>
