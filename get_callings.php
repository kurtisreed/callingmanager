<?php
require_once 'db_connect.php'; // Make sure this path is correct

// SQL query to fetch callings ordered by calling_name
$sql = "SELECT calling_id, calling_name FROM callings ORDER BY calling_name ASC";
$result = $conn->query($sql);

$callings = [];
while ($row = $result->fetch_assoc()) {
    $callings[] = $row;
}

header('Content-Type: application/json');
echo json_encode($callings);

$conn->close();
?>