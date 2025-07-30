<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('remove_member', ['risk_level' => 'high', 'file' => 'remove_member.php']);

require_once 'db_connect.php'; // Make sure this path is correct

// Check if member_id is sent
if (!isset($_POST['member_id'])) {
    echo json_encode(["success" => false, "message" => "No member ID provided."]);
    exit;
}

$member_id = $_POST['member_id'];

// SQL query to delete the member
$sql = "DELETE FROM members WHERE member_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $member_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Member removed successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Error removing member."]);
}

// Close the statement and connection
$stmt->close();
$conn->close();
?>
