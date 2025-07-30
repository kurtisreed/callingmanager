<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('remove_from_consideration', ['risk_level' => 'high', 'file' => 'remove_from_consideration.php']);

require_once 'db_connect.php'; // Make sure this path is correct

// Get the possible_callings_id from the POST request
$possible_callings_id = $_POST['possible_callings_id'];

// Update query to set the status to 'dismissed'
$sql = "
    UPDATE possible_callings 
    SET status = 'dismissed', date_updated = NOW() 
    WHERE id = ?
";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $possible_callings_id);

if ($stmt->execute()) {
    echo "Candidate status updated to dismissed.";
} else {
    echo "Error updating status: " . $conn->error;
}

// Close connections
$stmt->close();
$conn->close();
?>