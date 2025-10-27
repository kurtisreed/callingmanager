<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('update_calling_process', ['risk_level' => 'high', 'file' => 'update_calling_process.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$id = $data['id'] ?? null;
$status = $data['status'] ?? null;
$date = $data['date'] ?? null;
$preserve_status = $data['preserve_status'] ?? false;
$actual_status = $data['actual_status'] ?? null;

if (!$id || !$status || !$date) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate status
$validStatuses = ['approved', 'interviewed', 'sustained', 'set_apart'];
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// If preserving status, also validate the actual status
if ($preserve_status && $actual_status && !in_array($actual_status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid actual status']);
    exit;
}

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Note: 'completed' status is no longer used - completion happens through Assign/Release tab
    // All statuses now just update the process record
    
    // Update the process status and corresponding date field
    $dateField = '';
    switch ($status) {
        case 'approved':
            $dateField = 'approved_date';
            break;
        case 'interviewed':
            $dateField = 'interviewed_date';
            break;
        case 'sustained':
            $dateField = 'sustained_date';
            break;
        case 'set_apart':
            $dateField = 'set_apart_date';
            break;
        default:
            throw new Exception("Invalid status provided");
    }
    
    // Determine which status to use for the update
    $statusToUpdate = $preserve_status && $actual_status ? $actual_status : $status;

    $updateSql = "UPDATE calling_process SET status = ?, $dateField = ? WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("ssi", $statusToUpdate, $date, $id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to update calling process: " . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("No calling process found with that ID");
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Calling process updated successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>