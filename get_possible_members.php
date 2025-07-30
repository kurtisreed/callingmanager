<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_possible_members', ['risk_level' => 'medium', 'file' => 'get_possible_members.php']);

require_once 'db_connect.php'; // Make sure this path is correct

// Get the calling_id from the request
$calling_id = isset($_GET['calling_id']) ? intval($_GET['calling_id']) : 0;

// Initialize an empty response array
$response = [];

if ($calling_id > 0) {
    // Query the database for members related to the calling_id
    // Order by date_updated in descending order
    $query = "
        SELECT pc.status, CONCAT(m.first_name, ' ', m.last_name) AS member_name, pc.date_updated
        FROM possible_callings AS pc
        JOIN members AS m ON pc.member_id = m.member_id
        WHERE pc.calling_id = ?
        ORDER BY pc.date_updated DESC
    ";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $calling_id);
        $stmt->execute();
        $result = $stmt->get_result();

        // Fetch the data and populate the response array
        while ($row = $result->fetch_assoc()) {
            $response[] = [
                'status' => $row['status'],
                'member_name' => $row['member_name'],
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
