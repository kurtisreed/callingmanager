<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('fetch_calling_data', ['risk_level' => 'medium', 'file' => 'fetch_calling_data.php']);

require_once 'db_connect.php'; // Make sure this path is correct

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the calling_id from the request (from data-calling-id)
$callingId = $_GET['calling_id'];

// Query the database to retrieve member details for the selected calling using calling_id
$sql = "
    SELECT 
        members.member_id AS `Member ID`,
        members.first_name AS `First Name`,
        members.last_name AS `Last Name`,
        current_callings.date_set_apart AS `Date Set Apart`
    FROM current_callings
    JOIN members ON current_callings.member_id = members.member_id
    WHERE current_callings.calling_id = ?
    AND current_callings.date_released IS NULL"; // Only fetch active callings

// Use prepared statements to avoid SQL injection
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $callingId); // Bind the calling_id as an integer
$stmt->execute();
$result = $stmt->get_result();

$data = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Store the data, including the member_id, in the response
        $data[] = $row;
    }
}

// Return the data as JSON
echo json_encode($data);

$stmt->close();
$conn->close();
?>
