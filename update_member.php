<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('update_member', ['risk_level' => 'high', 'file' => 'update_member.php']);

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
    'birthdate' => 'birthdate',
    'member-status' => 'status',
    'status-notes' => 'status_notes'
];

// Build a parameterized update. Column names come from the whitelist above
// (never from user input); all values are bound as prepared-statement params.
$updates = [];
$params  = [];
$types   = '';
foreach ($columnsToUpdate as $inputKey => $dbColumn) {
    if (isset($data[$inputKey])) {
        $updates[] = "$dbColumn = ?";
        $params[]  = $data[$inputKey];
        $types    .= 's';
    }
}

if (empty($updates)) {
    echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
    exit;
}

$sql = "UPDATE members SET " . implode(', ', $updates) . " WHERE member_id = ?";
$params[] = $memberId;
$types   .= 'i';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('update_member prepare failed: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    error_log('update_member execute failed: ' . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}

$stmt->close();
$conn->close();
?>