<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('update_calling_considering', ['risk_level' => 'medium', 'file' => 'update_calling_considering.php']);

// Include database connection
require_once 'db_connect.php';
require_once 'InputValidator.php';

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

// Check required fields exist
if (!isset($input['calling_id']) || !isset($input['considering'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: calling_id and considering']);
    exit;
}

// Validate input
$validator = new InputValidator();
$validator->rule('calling_id', InputValidator::REQUIRED)
          ->rule('calling_id', InputValidator::INTEGER)
          ->rule('considering', InputValidator::BOOLEAN);

if (!$validator->validate($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Validation failed', 'details' => $validator->getErrors()]);
    exit;
}

$calling_id = (int)$input['calling_id'];
$considering = (bool)$input['considering'];

// Update the calling's considering flag
$stmt = $conn->prepare("UPDATE callings SET considering = ? WHERE calling_id = ?");
$stmt->bind_param("ii", $considering, $calling_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Considering flag updated successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Calling not found']);
    }
} else {
    http_response_code(500);
    error_log('Database error in update_calling_considering.php: ' . $conn->error);
    echo json_encode(['error' => 'Database update failed']);
}

$stmt->close();
$conn->close();
?>