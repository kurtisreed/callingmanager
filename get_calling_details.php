<?php
require_once 'db_connect.php'; // Make sure this path is correct

$callingId = $_GET['calling_id'];
$sql = "SELECT calling_id, calling_name, organization, grouping, priority FROM callings WHERE calling_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $callingId);
$stmt->execute();
$result = $stmt->get_result();
$calling = $result->fetch_assoc();

echo json_encode($calling);
$conn->close();
?>
