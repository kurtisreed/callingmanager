<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Only the Bishop can manage user accounts
requireRole(['bishop']);

logUserActivity('update_user', ['risk_level' => 'high', 'file' => 'update_user.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

$user_id      = isset($data['user_id']) ? (int) $data['user_id'] : 0;
$role         = $data['role'] ?? null;
$status       = $data['status'] ?? null;
$new_password = $data['new_password'] ?? null;
$member_id    = array_key_exists('member_id', $data) ? (int) $data['member_id'] : null;

$validRoles = ['bishop', 'counselor', 'leader'];
$validStatuses = ['active', 'disabled'];

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

// Prevent the logged-in Bishop from locking themselves out by disabling
// their own account or changing their own role away from bishop. Use the
// CLI tool (create_user_cli.php) for that instead.
$isSelf = $user_id === (int) ($_SESSION['user_id'] ?? 0);
if ($isSelf) {
    if ($status !== null && $status !== 'active') {
        echo json_encode(['success' => false, 'message' => 'You cannot disable your own account.']);
        exit;
    }
    if ($role !== null && $role !== 'bishop') {
        echo json_encode(['success' => false, 'message' => 'You cannot change your own role away from Bishop.']);
        exit;
    }
}

$setParts = [];
$params = [];
$types = '';

if ($role !== null) {
    if (!in_array($role, $validRoles, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid role']);
        exit;
    }
    $setParts[] = 'role = ?';
    $params[] = $role;
    $types .= 's';
}

if ($status !== null) {
    if (!in_array($status, $validStatuses, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    $setParts[] = 'status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($new_password !== null) {
    if (!is_string($new_password) || strlen($new_password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        exit;
    }
    $setParts[] = 'password_hash = ?';
    $params[] = password_hash($new_password, PASSWORD_DEFAULT);
    $types .= 's';
}

try {
    if ($member_id !== null) {
        if ($member_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid member']);
            exit;
        }

        // Must exist, not be moved/deceased, and not already be linked to
        // a DIFFERENT account (self-relink is fine, e.g. re-saving unchanged).
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

        if ($memberRow['linked_user_id'] !== null && (int) $memberRow['linked_user_id'] !== $user_id) {
            echo json_encode(['success' => false, 'message' => 'That member already has an account']);
            exit;
        }

        $setParts[] = 'member_id = ?';
        $params[] = $member_id;
        $types .= 'i';

        $setParts[] = 'display_name = ?';
        $params[] = $memberRow['first_name'] . ' ' . $memberRow['last_name'];
        $types .= 's';
    }

    if (empty($setParts)) {
        echo json_encode(['success' => false, 'message' => 'No changes provided']);
        exit;
    }

    $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE user_id = ?";
    $params[] = $user_id;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'affected_rows' => $stmt->affected_rows]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
