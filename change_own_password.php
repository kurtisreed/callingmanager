<?php
// Require authentication for this endpoint. No requireRole() restriction —
// any logged-in user (Bishop, Counselor, or Leader) can change their own
// password.
require_once __DIR__ . '/auth_required.php';

logUserActivity('change_own_password', ['risk_level' => 'medium', 'file' => 'change_own_password.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

$currentPassword = $data['current_password'] ?? '';
$newPassword     = $data['new_password'] ?? '';

// Always act on the logged-in session's own account — never a client-
// supplied user_id, so this endpoint can never be used to change someone
// else's password.
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if (!is_string($currentPassword) || $currentPassword === '') {
    echo json_encode(['success' => false, 'message' => 'Current password is required']);
    exit;
}

if (!is_string($newPassword) || strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    if (!password_verify($currentPassword, $row['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $update->bind_param('si', $newHash, $userId);

    if ($update->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password']);
    }
    $update->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
