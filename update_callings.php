<?php
header('Content-Type: application/json');
require_once 'db_connect.php'; // Make sure this path is correct
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'];
$updatedData = $data['updatedData'];

$updates = [];
foreach ($updatedData as $column => $value) {
    $updates[] = "$column = '$value'";
}
$updateQuery = implode(', ', $updates);

$sql = "UPDATE callings SET $updateQuery WHERE calling_id = '$id'";

if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}

$conn->close();
?>
