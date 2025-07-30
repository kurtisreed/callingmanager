<?php
session_start();

// Load configuration and dependencies
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/RateLimiter.php';

// Get configuration values
$correct_pin = Config::get('AUTH_PIN');
$max_attempts = (int)Config::get('RATE_LIMIT_MAX_ATTEMPTS', 5);
$window_minutes = (int)Config::get('RATE_LIMIT_WINDOW_MINUTES', 15);
$lockout_minutes = (int)Config::get('RATE_LIMIT_LOCKOUT_MINUTES', 30);

// Validate PIN configuration
if (empty($correct_pin)) {
    error_log('Authentication configuration error: AUTH_PIN not set');
    echo json_encode(['success' => false, 'error' => 'Configuration error']);
    exit;
}

// Get client information
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// Initialize rate limiter
try {
    $rateLimiter = new RateLimiter($conn, $max_attempts, $window_minutes, $lockout_minutes);
} catch (Exception $e) {
    error_log('Rate limiter initialization failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'System error']);
    exit;
}

// Check rate limiting BEFORE processing the request
$rateStatus = $rateLimiter->checkRateLimit($client_ip);

if ($rateStatus['blocked']) {
    $minutes_remaining = ceil($rateStatus['remaining_lockout'] / 60);
    
    error_log("Rate limited authentication attempt from IP: $client_ip (locked for $minutes_remaining minutes)");
    
    echo json_encode([
        'success' => false, 
        'error' => 'Too many failed attempts', 
        'locked_until' => $minutes_remaining,
        'message' => "Account temporarily locked. Try again in $minutes_remaining minute(s)."
    ]);
    exit;
}

// Check if the PIN was submitted
if (!isset($_POST['pin'])) {
    echo json_encode(['success' => false, 'error' => 'PIN required']);
    exit;
}

$entered_pin = $_POST['pin'];

// Basic input validation
if (empty($entered_pin) || !is_string($entered_pin)) {
    // Record failed attempt for empty/invalid input
    $rateLimiter->recordAttempt($client_ip, $user_agent, false);
    echo json_encode(['success' => false]);
    exit;
}

// Check if the PIN is correct
if ($entered_pin === $correct_pin) {
    // SUCCESS: Reset rate limiting and set session
    $rateLimiter->resetAttempts($client_ip);
    
    $_SESSION['authenticated'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['user_ip'] = $client_ip;
    
    error_log("Successful authentication from IP: $client_ip");
    echo json_encode(['success' => true]);
    
} else {
    // FAILURE: Record failed attempt
    $rateLimiter->recordAttempt($client_ip, $user_agent, false);
    
    // Check if this failure triggers a lockout
    $newRateStatus = $rateLimiter->checkRateLimit($client_ip);
    
    if ($newRateStatus['attempts_left'] <= 0) {
        error_log("IP $client_ip has been rate limited after failed authentication");
        echo json_encode([
            'success' => false, 
            'error' => 'Too many failed attempts',
            'message' => "Too many failed attempts. Account locked for $lockout_minutes minutes."
        ]);
    } else {
        $attempts_left = $newRateStatus['attempts_left'];
        error_log("Failed authentication attempt from IP: $client_ip ($attempts_left attempts remaining)");
        echo json_encode([
            'success' => false,
            'attempts_remaining' => $attempts_left,
            'message' => $attempts_left === 1 ? 
                "Invalid PIN. 1 attempt remaining before lockout." : 
                "Invalid PIN. $attempts_left attempts remaining."
        ]);
    }
}

// Close database connection
$conn->close();
?>