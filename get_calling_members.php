<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_calling_members', ['risk_level' => 'medium', 'file' => 'get_calling_members.php']);

require_once 'db_connect.php'; // Make sure this path is correct

$calling_id = $_GET['calling_id'];

// SQL query to fetch members associated with the selected calling, ordering active members first
$sql = "SELECT m.member_id, CONCAT(m.first_name, ' ', m.last_name) AS member_name, cc.date_set_apart, cc.date_released, cc.id
        FROM current_callings cc
        JOIN members m ON cc.member_id = m.member_id
        WHERE cc.calling_id = ?
        ORDER BY cc.date_released IS NULL DESC, cc.date_set_apart ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $calling_id);
$stmt->execute();
$result = $stmt->get_result();

$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

header('Content-Type: application/json');
echo json_encode($members);

$stmt->close();
$conn->close();
?>
