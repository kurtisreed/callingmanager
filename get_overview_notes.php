<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_overview_notes', ['risk_level' => 'low', 'file' => 'get_overview_notes.php']);

require_once 'db_connect.php';

// SQL query to fetch the overview notes
$sql = "SELECT callings_notes, people_notes FROM overview_notes WHERE id = 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode([
        'callings_notes' => $row['callings_notes'],
        'people_notes' => $row['people_notes']
    ]);
} else {
    // If no row exists, return empty notes
    header('Content-Type: application/json');
    echo json_encode([
        'callings_notes' => '',
        'people_notes' => ''
    ]);
}

$conn->close();
?>
