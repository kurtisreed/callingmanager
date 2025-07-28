<?php
require_once 'db_connect.php'; // Make sure this path is correct

$memberId = $_GET['member_id'];
$sql = "SELECT member_id, first_name, last_name, gender, birthdate FROM members WHERE member_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $memberId);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();

echo json_encode($member);
$conn->close();
?>
