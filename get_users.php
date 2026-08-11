<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Only the Bishop can manage user accounts
requireRole(['bishop']);

logUserActivity('get_users', ['risk_level' => 'medium', 'file' => 'get_users.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    $sql = "SELECT user_id, member_id, username, display_name, role, status, created_at, last_login
            FROM users
            ORDER BY display_name ASC";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Database query failed: ' . $conn->error);
    }

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'user_id'      => (int) $row['user_id'],
            'member_id'    => $row['member_id'] !== null ? (int) $row['member_id'] : null,
            'username'     => $row['username'],
            'display_name' => $row['display_name'],
            'role'         => $row['role'],
            'status'       => $row['status'],
            'created_at'   => $row['created_at'],
            'last_login'   => $row['last_login'],
        ];
    }

    echo json_encode($users);

} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}

$conn->close();
?>
