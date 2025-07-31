<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_member_details', ['risk_level' => 'medium', 'file' => 'get_member_details.php']);

require_once 'db_connect.php'; // Make sure this path is correct

$memberId = $_GET['member_id'];
$sql = "SELECT member_id, first_name, last_name, gender, birthdate, status, status_notes FROM members WHERE member_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $memberId);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();

echo json_encode($member);
$conn->close();
?>
