<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_current_callings', ['risk_level' => 'medium', 'file' => 'get_current_callings.php']);

require_once 'db_connect.php'; // Make sure this path is correct


// SQL query to fetch current callings, grouped by organization
$sql = "SELECT c.grouping, c.calling_name, CONCAT(m.first_name, ' ', m.last_name) AS member_name, cc.date_set_apart
        FROM current_callings cc
        JOIN members m ON cc.member_id = m.member_id
        JOIN callings c ON cc.calling_id = c.calling_id
        WHERE cc.date_released IS NULL
        ORDER BY c.grouping ASC, c.calling_name ASC, cc.date_set_apart ASC";

$result = $conn->query($sql);

$callings = [];
while ($row = $result->fetch_assoc()) {
    $callings[] = $row;
}

header('Content-Type: application/json');
echo json_encode($callings);

$conn->close();
?>
