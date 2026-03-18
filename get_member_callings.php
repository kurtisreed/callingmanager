<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_member_callings', ['risk_level' => 'medium', 'file' => 'get_member_callings.php']);

require_once 'db_connect.php'; // Make sure this path is correct

$member_id = $_GET['member_id'];

// SQL query to fetch callings, ordering active callings first
$sql = "SELECT c.calling_id, c.calling_name, c.leader AS leader_calling, cc.date_set_apart, cc.date_released, cc.id,
        (SELECT CONCAT(lm.first_name, ' ', lm.last_name)
         FROM current_callings lcc
         JOIN callings lc ON lcc.calling_id = lc.calling_id
         JOIN members lm ON lcc.member_id = lm.member_id
         WHERE lc.calling_name = c.leader
         AND lcc.date_released IS NULL
         LIMIT 1) AS leader_name
        FROM current_callings cc
        JOIN callings c ON cc.calling_id = c.calling_id
        WHERE cc.member_id = ?
        ORDER BY cc.date_released IS NULL DESC, cc.date_set_apart ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

$callings = [];
while ($row = $result->fetch_assoc()) {
    $callings[] = $row;
}

header('Content-Type: application/json');
echo json_encode($callings);

$stmt->close();
$conn->close();
?>