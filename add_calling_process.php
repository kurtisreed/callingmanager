<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('add_calling_process', ['risk_level' => 'high', 'file' => 'add_calling_process.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$member_id = $data['member_id'] ?? null;
$calling_id = $data['calling_id'] ?? null;
$proposed_by = $data['proposed_by'] ?? 'System';
$notes = $data['notes'] ?? '';

if (!$member_id || !$calling_id) {
    echo json_encode(['success' => false, 'message' => 'Member ID and Calling ID are required']);
    exit;
}

try {
    // Check if this member/calling combination already exists in process
    $checkSql = "SELECT id FROM calling_process WHERE member_id = ? AND calling_id = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("ii", $member_id, $calling_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'This member/calling combination is already in process']);
        exit;
    }
    
    // Check if member already has this calling active
    $checkActiveSql = "SELECT id FROM current_callings WHERE member_id = ? AND calling_id = ? AND date_released IS NULL";
    $stmt = $conn->prepare($checkActiveSql);
    $stmt->bind_param("ii", $member_id, $calling_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'This member already has this calling active']);
        exit;
    }
    
    // Get member and calling names for response
    $memberSql = "SELECT first_name, last_name FROM members WHERE member_id = ?";
    $stmt = $conn->prepare($memberSql);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $memberResult = $stmt->get_result();
    
    $callingSql = "SELECT calling_name FROM callings WHERE calling_id = ?";
    $stmt = $conn->prepare($callingSql);
    $stmt->bind_param("i", $calling_id);
    $stmt->execute();
    $callingResult = $stmt->get_result();
    
    if ($memberResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        exit;
    }
    
    if ($callingResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Calling not found']);
        exit;
    }
    
    $member = $memberResult->fetch_assoc();
    $calling = $callingResult->fetch_assoc();
    $memberName = $member['first_name'] . ' ' . $member['last_name'];
    $callingName = $calling['calling_name'];
    
    // Insert new calling process
    $today = date('Y-m-d');
    $insertSql = "INSERT INTO calling_process (
                    member_id, 
                    calling_id, 
                    proposed_date, 
                    status, 
                    approved_date, 
                    notes, 
                    proposed_by
                  ) VALUES (?, ?, ?, 'approved', ?, ?, ?)";
    
    $stmt = $conn->prepare($insertSql);
    $stmt->bind_param("iissss", $member_id, $calling_id, $today, $today, $notes, $proposed_by);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to add calling to process: " . $stmt->error);
    }
    
    $processId = $conn->insert_id;
    
    // Log the addition for audit trail
    logUserActivity('calling_process_added', [
        'process_id' => $processId,
        'member_name' => $memberName,
        'calling_name' => $callingName,
        'proposed_by' => $proposed_by
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => "$memberName has been added to the calling process for $callingName",
        'process_id' => $processId
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>