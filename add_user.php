<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Only the Bishop can manage user accounts
requireRole(['bishop']);

logUserActivity('add_user', ['risk_level' => 'high', 'file' => 'add_user.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

$username  = trim($data['username'] ?? '');
$member_id = isset($data['member_id']) ? (int) $data['member_id'] : 0;
$password  = $data['password'] ?? '';
$role      = $data['role'] ?? '';

$validRoles = ['bishop', 'counselor', 'leader'];

if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'Username is required']);
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

if ($member_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'A member must be selected for this account']);
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

    // Validate the member: must exist, not be moved/deceased, and not
    // already be linked to a different account.
    $memberCheck = $conn->prepare(
        "SELECT m.first_name, m.last_name, u.user_id AS linked_user_id
         FROM members m
         LEFT JOIN users u ON u.member_id = m.member_id
         WHERE m.member_id = ? AND m.status NOT IN ('moved', 'deceased')"
    );
    $memberCheck->bind_param('i', $member_id);
    $memberCheck->execute();
    $memberResult = $memberCheck->get_result();

    if ($memberResult->num_rows === 0) {
        $memberCheck->close();
        echo json_encode(['success' => false, 'message' => 'Selected member not found']);
        exit;
    }

    $memberRow = $memberResult->fetch_assoc();
    $memberCheck->close();

    if ($memberRow['linked_user_id'] !== null) {
        echo json_encode(['success' => false, 'message' => 'That member already has an account']);
        exit;
    }

    $displayName = $memberRow['first_name'] . ' ' . $memberRow['last_name'];
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO users (username, member_id, display_name, password_hash, role, status)
         VALUES (?, ?, ?, ?, ?, 'active')"
    );
    $stmt->bind_param('sisss', $username, $member_id, $displayName, $passwordHash, $role);

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
