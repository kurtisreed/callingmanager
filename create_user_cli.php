<?php
/**
 * Calling Manager - User Account Setup
 *
 * Creates the `users` table (if needed) and creates or updates one user
 * account. Run this once to create the first Bishop account before the
 * username/password login is usable, and again any time you need to
 * create an account outside the in-app "Manage Users" screen.
 *
 * CLI-ONLY: run with `php create_user_cli.php`.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This setup script can only be run from the command line.');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connect.php';

echo "Calling Manager - User Account Setup\n";
echo "=====================================\n\n";

$conn->query("CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('bishop', 'counselor', 'leader') NOT NULL DEFAULT 'leader',
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
)");

echo "users table ready.\n\n";

function cm_prompt($label) {
    echo $label;
    return trim(fgets(STDIN));
}

function cm_prompt_password($label) {
    echo $label;
    // Best-effort: hide input on unix-like terminals. Falls back to
    // visible input if stty isn't available.
    system('stty -echo 2>/dev/null');
    $password = trim(fgets(STDIN));
    system('stty echo 2>/dev/null');
    echo "\n";
    return $password;
}

$username = cm_prompt("Username: ");
$displayName = cm_prompt('Display name (e.g. "Bishop Reed"): ');
$password = cm_prompt_password("Password (min 8 characters): ");
$roleInput = trim(strtolower(cm_prompt("Role [bishop/counselor/leader] (default: bishop): ")));
$role = in_array($roleInput, ['bishop', 'counselor', 'leader'], true) ? $roleInput : 'bishop';

if (empty($username) || empty($displayName) || empty($password)) {
    echo "\nUsername, display name, and password are all required. Aborting.\n";
    exit(1);
}

if (strlen($password) < 8) {
    echo "\nPassword must be at least 8 characters. Aborting.\n";
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO users (username, display_name, password_hash, role, status)
     VALUES (?, ?, ?, ?, 'active')
     ON DUPLICATE KEY UPDATE
        display_name = VALUES(display_name),
        password_hash = VALUES(password_hash),
        role = VALUES(role),
        status = 'active'"
);
$stmt->bind_param('ssss', $username, $displayName, $passwordHash, $role);

if ($stmt->execute()) {
    echo "\nAccount '$username' ($role) created/updated successfully.\n";
} else {
    echo "\nFailed to create account: " . $stmt->error . "\n";
    exit(1);
}

$stmt->close();
$conn->close();
?>
