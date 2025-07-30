<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_considered_callings', ['risk_level' => 'medium', 'file' => 'get_considered_callings.php']);

require_once 'db_connect.php'; // Make sure this path is correct

// Get the member_id from the request
$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;

// Initialize an empty response array
$response = [];

if ($member_id > 0) {
    // Query the database for possible callings related to the member_id
    $query = "
        SELECT pc.status, c.calling_name, pc.date_updated
        FROM possible_callings AS pc
        JOIN callings AS c ON pc.calling_id = c.calling_id
        WHERE pc.member_id = ?
        ORDER BY pc.date_updated DESC
    ";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();

        // Fetch the data and populate the response array
        while ($row = $result->fetch_assoc()) {
            $response[] = [
                'status' => $row['status'],
                'calling_name' => $row['calling_name'],
                'date_updated' => $row['date_updated']
            ];
        }

        $stmt->close();
    }
}

// Set the header to JSON and echo the response
header('Content-Type: application/json');
echo json_encode($response);
?>
