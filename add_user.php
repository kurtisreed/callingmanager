<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Only the Bishop can manage user accounts
requireRole(['bishop']);

logUserActivity('add_user', ['risk_level' => 'high', 'file' => 'add_user.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

$username     = trim($data['username'] ?? '');
$display_name = trim($data['display_name'] ?? '');
$password     = $data['password'] ?? '';
$role         = $data['role'] ?? '';

$validRoles = ['bishop', 'counselor', 'leader'];

if ($username === '' || $display_name === '') {
    echo json_encode(['success' => false, 'message' => 'Username and display name are required']);
    exit;
}

if (strlen($username) > 50) {
    echo json_encode(['success' => false, 'message' => 'Username must be 50 characters or fewer']);
    exit;
}

if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Username may only contain letters, numbers, and . _ -']);
    exit;
}

if (!in_array($role, $validRoles, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

if (!is_string($password) || strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}

try {
    // Check for an existing username first so we can give a clear message
    // rather than a generic duplicate-key database error.
    $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $check->bind_param('s', $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $check->close();
        echo json_encode(['success' => false, 'message' => 'That username is already taken']);
        exit;
    }
    $check->close();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO users (username, display_name, password_hash, role, status)
         VALUES (?, ?, ?, ?, 'active')"
    );
    $stmt->bind_param('ssss', $username, $display_name, $passwordHash, $role);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'user_id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create account']);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
