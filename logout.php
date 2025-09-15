<?php
header('Content-Type: application/json');

// Start session
session_start();

try {
    // Destroy the session
    session_destroy();
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Logout successful'
    ]);
    
} catch (Exception $e) {
    // Even if there's an error, return success for security
    // (We don't want to prevent logout even if session cleanup fails)
    echo json_encode([
        'success' => true,
        'message' => 'Logout completed'
    ]);
}
?>