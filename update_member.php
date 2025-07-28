<?php
header('Content-Type: application/json');
require_once 'db_connect.php'; // Make sure this path is correct

// Decode JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Extract and validate `member-id`
$memberId = $data['member-id'] ?? null;
if (!$memberId) {
    echo json_encode(['success' => false, 'message' => 'Member ID missing']);
    exit;
}

// Map the input data to the correct column names for `members` table
$columnsToUpdate = [
    'first-name' => 'first_name',
    'last-name' => 'last_name',
    'gender' => 'gender',
    'birthdate' => 'birthdate'
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
$sql = "UPDATE members SET $updateQuery WHERE member_id = '" . $conn->real_escape_string($memberId) . "'";

// Execute the update query
if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}

$conn->close();
?>