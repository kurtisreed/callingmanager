<?php
require_once 'db_connect.php'; // Make sure this path is correct

$member_id = $_GET['member_id'];

// SQL query to fetch callings, ordering active callings first
$sql = "SELECT c.calling_id, c.calling_name, cc.date_set_apart, cc.date_released, cc.id
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