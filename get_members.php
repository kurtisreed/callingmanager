<?php
require_once 'db_connect.php'; // Make sure this path is correct

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