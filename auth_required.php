<?php
/**
 * Authentication Middleware
 * Include this file at the top of any PHP file that requires authentication
 * 
 * Usage: require_once __DIR__ . '/auth_required.php';
 */

// Harden and start the session, and load CSRF helpers
require_once __DIR__ . '/session_bootstrap.php';

// Load configuration
require_once __DIR__ . '/config.php';

/**
 * Check if user is authenticated
 * 
 * @return bool True if authenticated, false otherwise
 */
function isAuthenticated() {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        return false;
    }
    
    // Check if session has expired
    $session_timeout = (int)Config::get('SESSION_TIMEOUT_MINUTES', 60); // Default 1 hour
    $login_time = $_SESSION['login_time'] ?? 0;
    
    if (time() - $login_time > ($session_timeout * 60)) {
        // Session expired
        destroySession();
        return false;
    }
    
    // Check if IP changed (potential session hijacking)
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $session_ip = $_SESSION['user_ip'] ?? '';
    
    if ($current_ip !== $session_ip) {
        error_log("Session IP mismatch detected. Session IP: $session_ip, Current IP: $current_ip");
        destroySession();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Destroy session and clean up
 */
function destroySession() {
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Require authentication or terminate with error
 * 
 * @param string $error_type Type of error response ('json', 'html', 'redirect')
 * @param string $redirect_url URL to redirect to if not authenticated
 */
function requireAuth($error_type = 'json', $redirect_url = 'index.html') {
    if (!isAuthenticated()) {
        // Log unauthorized access attempt
        $requested_file = basename($_SERVER['PHP_SELF']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        error_log("Unauthorized access attempt to $requested_file from IP: $ip");
        
        switch ($error_type) {
            case 'html':
                http_response_code(401);
                echo '<!DOCTYPE html>
                <html>
                <head><title>Unauthorized</title></head>
                <body>
                    <h1>401 - Unauthorized</h1>
                    <p>You must be logged in to access this resource.</p>
                    <a href="' . htmlspecialchars($redirect_url) . '">Return to Login</a>
                </body>
                </html>';
                break;
                
            case 'redirect':
                header('Location: ' . $redirect_url);
                break;
                
            case 'json':
            default:
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication required',
                    'code' => 401
                ]);
                break;
        }
        
        exit;
    }
}

/**
 * Get current user information
 *
 * @return array User session information
 */
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }

    return [
        'authenticated' => true,
        'login_time' => $_SESSION['login_time'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'ip_address' => $_SESSION['user_ip'] ?? null,
        'session_id' => session_id(),
        'time_remaining' => getSessionTimeRemaining(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'display_name' => $_SESSION['display_name'] ?? null,
        'role' => $_SESSION['role'] ?? null,
    ];
}

/**
 * Require the current user to hold one of the given roles, terminating
 * with a 403 JSON response otherwise. Call requireAuth() (or rely on the
 * auto-executed check below) before this — it assumes the user is already
 * authenticated.
 *
 * @param array $allowedRoles e.g. ['bishop'] or ['bishop', 'counselor']
 */
function requireRole(array $allowedRoles) {
    $role = $_SESSION['role'] ?? null;

    if (!in_array($role, $allowedRoles, true)) {
        $requested_file = basename($_SERVER['PHP_SELF']);
        $username = $_SESSION['username'] ?? 'unknown';
        error_log("Forbidden: user '$username' (role: " . ($role ?? 'none') . ") attempted $requested_file, requires one of: " . implode(', ', $allowedRoles));

        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Forbidden',
            'message' => 'You do not have permission to access this resource.',
            'code' => 403
        ]);
        exit;
    }
}

/**
 * Get remaining session time in minutes
 * 
 * @return int Minutes remaining before session expires
 */
function getSessionTimeRemaining() {
    if (!isAuthenticated()) {
        return 0;
    }
    
    $session_timeout = (int)Config::get('SESSION_TIMEOUT_MINUTES', 60);
    $login_time = $_SESSION['login_time'] ?? 0;
    $elapsed_minutes = (time() - $login_time) / 60;
    
    return max(0, $session_timeout - $elapsed_minutes);
}

/**
 * Log user activity for auditing
 * 
 * @param string $action Description of the action performed
 * @param array $details Additional details about the action
 */
function logUserActivity($action, $details = []) {
    if (!isAuthenticated()) {
        return;
    }
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip_address' => $_SESSION['user_ip'] ?? 'unknown',
        'session_id' => session_id(),
        'username' => $_SESSION['username'] ?? 'unknown',
        'role' => $_SESSION['role'] ?? 'unknown',
        'action' => $action,
        'file' => basename($_SERVER['PHP_SELF']),
        'details' => $details
    ];
    
    error_log('User Activity: ' . json_encode($log_entry));
}

// Auto-execute authentication check for most files
// Can be overridden by setting $skip_auto_auth = true before including this file
if (!isset($skip_auto_auth) || !$skip_auto_auth) {
    // Determine response type based on file type and request
    $error_type = 'json'; // Default for API endpoints
    
    // Check if this looks like a page request (might want HTML response)
    if (isset($_SERVER['HTTP_ACCEPT']) && 
        strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false) {
        $error_type = 'html';
    }
    
    requireAuth($error_type);

    // Enforce CSRF protection on all state-changing requests to protected
    // endpoints (safe methods like GET are exempt).
    csrf_require();
}

?>