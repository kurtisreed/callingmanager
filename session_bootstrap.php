<?php
/**
 * Session bootstrap + CSRF helpers.
 *
 * Include this before any session use. Safe to include multiple times.
 * Responsibilities:
 *   - Harden the session cookie (HttpOnly, SameSite, Secure-when-HTTPS)
 *   - Start the session
 *   - Provide CSRF token generation, validation, and enforcement helpers
 */

if (!defined('CM_SESSION_BOOTSTRAPPED')) {
    define('CM_SESSION_BOOTSTRAPPED', true);

    if (session_status() === PHP_SESSION_NONE) {
        // Detect HTTPS directly or via a reverse proxy so the Secure flag is
        // set in production but not on plain-HTTP local development.
        $isHttps =
            (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443)
            || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,      // not readable from JavaScript
            'samesite' => 'Lax',     // blocks cross-site POST cookie sending
        ]);

        session_start();
    }
}

/**
 * Return the current CSRF token, creating one if it does not exist yet.
 *
 * @return string
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Issue a brand-new CSRF token, replacing any existing one. Call on login.
 *
 * @return string
 */
function csrf_rotate() {
    unset($_SESSION['csrf_token']);
    return csrf_token();
}

/**
 * Constant-time comparison of a supplied token against the session token.
 *
 * @param mixed $token
 * @return bool
 */
function csrf_validate($token) {
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Enforce CSRF protection on state-changing requests.
 *
 * Safe methods (GET/HEAD/OPTIONS) are exempt. For all others the request must
 * carry a valid token in the X-CSRF-Token header. Exits with 403 on failure.
 */
function csrf_require() {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!csrf_validate($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'error'   => 'Forbidden',
            'message' => 'Invalid or missing CSRF token. Please reload and log in again.',
            'code'    => 403,
        ]);
        exit;
    }
}
