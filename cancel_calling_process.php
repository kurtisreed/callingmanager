<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('cancel_calling_process', ['risk_level' => 'high', 'file' => 'cancel_calling_process.php']);

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

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Process ID is required']);
    exit;
}

try {
    // Get process details for logging before deletion
    $getProcessSql = "SELECT 
                        cp.id,
                        CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                        c.calling_name,
                        cp.status,
                        cp.proposed_date
                      FROM calling_process cp
                      JOIN members m ON cp.member_id = m.member_id
                      JOIN callings c ON cp.calling_id = c.calling_id
                      WHERE cp.id = ?";
    
    $stmt = $conn->prepare($getProcessSql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Calling process not found']);
        exit;
    }
    
    $process = $result->fetch_assoc();
    
    // Delete the calling process
    $deleteSql = "DELETE FROM calling_process WHERE id = ?";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to cancel calling process: " . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No calling process found with that ID']);
    } else {
        // Log the cancellation for audit trail
        logUserActivity('calling_process_canceled', [
            'member_name' => $process['member_name'],
            'calling_name' => $process['calling_name'],
            'status_when_canceled' => $process['status'],
            'proposed_date' => $process['proposed_date']
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => "Calling process for {$process['member_name']} → {$process['calling_name']} has been canceled"
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>