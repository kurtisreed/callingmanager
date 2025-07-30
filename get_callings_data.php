<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_callings_data', ['risk_level' => 'medium', 'file' => 'get_callings_data.php']);

require_once 'db_connect.php'; // Make sure this path is correct

$sql = "SELECT * FROM callings";
$result = $conn->query($sql);

$data = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode($data);

$conn->close();
?>
