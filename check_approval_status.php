<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('check_approval_status', ['risk_level' => 'low', 'file' => 'check_approval_status.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

// Get the calling ID from POST data
$calling_id = $_POST['calling_id'] ?? null;

if (!$calling_id) {
    echo json_encode(['error' => 'Calling ID is required']);
    exit;
}

try {
    // Query to check which candidates for this calling are approved in calling_process
    $sql = "SELECT 
                pc.member_id,
                pc.id as possible_callings_id,
                cp.id as process_id,
                cp.status as process_status,
                cp.approved_date
            FROM possible_callings pc
            LEFT JOIN calling_process cp ON pc.member_id = cp.member_id AND pc.calling_id = cp.calling_id
            WHERE pc.calling_id = ? AND pc.status = 'considered'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $calling_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $approved_candidates = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['process_id'] && !empty($row['process_status'])) {
            $approved_candidates[] = [
                'member_id' => $row['member_id'],
                'possible_callings_id' => $row['possible_callings_id'],
                'approved_date' => $row['approved_date']
            ];
        }
    }
    
    echo json_encode($approved_candidates);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>