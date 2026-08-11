<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Only the Bishop can manage user accounts
requireRole(['bishop']);

logUserActivity('get_available_members_for_user', ['risk_level' => 'low', 'file' => 'get_available_members_for_user.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

// When editing an existing account, pass its user_id so that account's
// currently-linked member still appears in the list (otherwise a member
// already linked to THIS account would incorrectly look "taken").
$currentUserId = isset($_GET['current_user_id']) ? (int) $_GET['current_user_id'] : 0;

try {
    $sql = "SELECT m.member_id, CONCAT(m.first_name, ' ', m.last_name) AS member_name
            FROM members m
            LEFT JOIN users u ON u.member_id = m.member_id
            WHERE m.status NOT IN ('moved', 'deceased')
            AND (u.user_id IS NULL OR u.user_id = ?)
            ORDER BY m.last_name ASC, m.first_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();

    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = [
            'member_id'   => (int) $row['member_id'],
            'member_name' => $row['member_name'],
        ];
    }

    echo json_encode($members);

} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}

$conn->close();
?>
