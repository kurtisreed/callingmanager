<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('update_overview_notes', ['risk_level' => 'medium', 'file' => 'update_overview_notes.php']);

// Include database connection
require_once 'db_connect.php';
require_once 'InputValidator.php';
require_once 'OutputSanitizer.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get the raw POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// Check required fields exist - at least one field should be present
if (!isset($input['callings_notes']) && !isset($input['people_notes'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required field: callings_notes or people_notes']);
    exit;
}

// Validate input
$validator = new InputValidator();
if (isset($input['callings_notes'])) {
    $validator->rule('callings_notes', InputValidator::STRING);
}
if (isset($input['people_notes'])) {
    $validator->rule('people_notes', InputValidator::STRING);
}

if (!$validator->validate($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Validation failed', 'details' => $validator->getErrors()]);
    exit;
}

// Determine which fields to update
$updateFields = [];
$params = [];
$types = '';

if (isset($input['callings_notes'])) {
    $updateFields[] = 'callings_notes = ?';
    $params[] = $input['callings_notes'];
    $types .= 's';
}

if (isset($input['people_notes'])) {
    $updateFields[] = 'people_notes = ?';
    $params[] = $input['people_notes'];
    $types .= 's';
}

// Build the SQL query dynamically based on which fields are being updated
$sql = "UPDATE overview_notes SET " . implode(', ', $updateFields) . " WHERE id = 1";

$stmt = $conn->prepare($sql);

// Bind parameters dynamically
if (count($params) === 1) {
    $stmt->bind_param($types, $params[0]);
} elseif (count($params) === 2) {
    $stmt->bind_param($types, $params[0], $params[1]);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Notes saved successfully']);
} else {
    http_response_code(500);
    error_log('Database error in update_overview_notes.php: ' . $conn->error);
    echo json_encode(['error' => 'Database update failed']);
}

$stmt->close();
$conn->close();
?>
