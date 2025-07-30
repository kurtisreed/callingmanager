<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';
require_once 'db_connect.php';

// Log this access for auditing
logUserActivity('get_members', ['action' => 'fetch_member_list']);

// SQL query to fetch members ordered by last name
$sql = "SELECT member_id, first_name, last_name FROM members ORDER BY last_name ASC";
$result = $conn->query($sql);

$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

header('Content-Type: application/json');
echo json_encode($members);

$conn->close();
?>