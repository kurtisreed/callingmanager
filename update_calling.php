<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('update_calling', ['risk_level' => 'high', 'file' => 'update_calling.php']);

header('Content-Type: application/json');
require_once 'db_connect.php'; // Make sure this path is correct

// Decode JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Extract and validate `member-id`
$callingId = $data['calling-id'] ?? null;
if (!$callingId) {
    echo json_encode(['success' => false, 'message' => 'Calling ID missing']);
    exit;
}

// Map the input data to the correct column names for `callings` table
$columnsToUpdate = [
    'calling-name' => 'calling_name',
    'organization' => 'organization',
    'grouping' => '`grouping`',
    'priority' => 'priority'
];

// Construct the update query with sanitized values
$updates = [];
foreach ($columnsToUpdate as $inputKey => $dbColumn) {
    if (isset($data[$inputKey])) {
        $updates[] = "$dbColumn = '" . $conn->real_escape_string($data[$inputKey]) . "'";
    }
}

if (empty($updates)) {
    echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
    exit;
}

$updateQuery = implode(', ', $updates);
$sql = "UPDATE callings SET $updateQuery WHERE calling_id = '" . $conn->real_escape_string($callingId) . "'";

// Execute the update query
if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}

$conn->close();
?>