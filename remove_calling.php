<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('remove_calling', ['risk_level' => 'high', 'file' => 'remove_calling.php']);

require_once 'db_connect.php'; // Make sure this path is correct

// Check if member_id is sent
if (!isset($_POST['calling_id'])) {
    echo json_encode(["success" => false, "message" => "No calling ID provided."]);
    exit;
}

$calling_id = $_POST['calling_id'];

// SQL query to delete the calling
$sql = "DELETE FROM callings WHERE calling_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $calling_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Calling removed successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Error removing Calling."]);
}

// Close the statement and connection
$stmt->close();
$conn->close();
?>
