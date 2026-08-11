<?php
// Harden and start the session, and load CSRF helpers
require_once __DIR__ . '/session_bootstrap.php';

// Load configuration and dependencies
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/RateLimiter.php';

// Get configuration values
$max_attempts = (int)Config::get('RATE_LIMIT_MAX_ATTEMPTS', 5);
$window_minutes = (int)Config::get('RATE_LIMIT_WINDOW_MINUTES', 15);
$lockout_minutes = (int)Config::get('RATE_LIMIT_LOCKOUT_MINUTES', 30);

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

// Check if credentials were submitted
if (!isset($_POST['username']) || !isset($_POST['password'])) {
    echo json_encode(['success' => false, 'error' => 'Username and password required']);
    exit;
}

$entered_username = $_POST['username'];
$entered_password = $_POST['password'];

// Basic input validation
if (empty($entered_username) || !is_string($entered_username) ||
    empty($entered_password) || !is_string($entered_password)) {
    // Record failed attempt for empty/invalid input
    $rateLimiter->recordAttempt($client_ip, $user_agent, false);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    exit;
}

// Look up the account. Always run password_verify() against something (a
// dummy hash if no matching account) so a nonexistent username takes the
// same amount of time as a wrong password — avoids leaking which usernames
// exist via a timing side channel.
$DUMMY_HASH = '$2y$10$IbvrGTLHSLQJeH9DaF4RO.R7zY6Dieg5HlowyohUNGK47h105Sy7m';
$user = null;

$stmt = $conn->prepare("SELECT user_id, username, display_name, password_hash, role, status FROM users WHERE username = ?");
$stmt->bind_param('s', $entered_username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
}
$stmt->close();

$hashToCheck = ($user && $user['status'] === 'active') ? $user['password_hash'] : $DUMMY_HASH;
$passwordValid = password_verify($entered_password, $hashToCheck);

if ($user && $user['status'] === 'active' && $passwordValid) {
    // SUCCESS: Reset rate limiting and set session
    $rateLimiter->resetAttempts($client_ip);

    // Prevent session fixation: issue a fresh session ID on privilege change
    session_regenerate_id(true);

    $_SESSION['authenticated'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['user_ip'] = $client_ip;
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['display_name'] = $user['display_name'];
    $_SESSION['role'] = $user['role'];

    // Record login time (best-effort, doesn't affect the login outcome)
    $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $update_stmt->bind_param('i', $user['user_id']);
    $update_stmt->execute();
    $update_stmt->close();

    // Issue a fresh CSRF token for this authenticated session and hand it to
    // the client so it can attach it to subsequent state-changing requests.
    $csrf_token = csrf_rotate();

    error_log("Successful authentication for user '{$user['username']}' from IP: $client_ip");
    echo json_encode([
        'success' => true,
        'csrf_token' => $csrf_token,
        'display_name' => $user['display_name'],
        'role' => $user['role'],
    ]);

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
        error_log("Failed authentication attempt for username '$entered_username' from IP: $client_ip ($attempts_left attempts remaining)");
        echo json_encode([
            'success' => false,
            'attempts_remaining' => $attempts_left,
            'message' => $attempts_left === 1 ?
                "Invalid username or password. 1 attempt remaining before lockout." :
                "Invalid username or password. $attempts_left attempts remaining."
        ]);
    }
}

// Close database connection
$conn->close();
?>