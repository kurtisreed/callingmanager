<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_calling_details', ['risk_level' => 'medium', 'file' => 'get_calling_details.php']);

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
