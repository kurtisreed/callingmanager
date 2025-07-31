<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_calling_processes', ['risk_level' => 'low', 'file' => 'get_calling_processes.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Query to get all calling processes with member and calling names
    $sql = "SELECT 
                cp.id,
                cp.member_id,
                cp.calling_id,
                cp.proposed_date,
                cp.status,
                cp.approved_date,
                cp.interviewed_date,
                cp.sustained_date,
                cp.set_apart_date,
                cp.notes,
                cp.proposed_by,
                cp.updated_at,
                CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                c.calling_name
            FROM calling_process cp
            JOIN members m ON cp.member_id = m.member_id
            JOIN callings c ON cp.calling_id = c.calling_id
            ORDER BY cp.proposed_date DESC, cp.updated_at DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $processes = [];
    while ($row = $result->fetch_assoc()) {
        $processes[] = [
            'id' => $row['id'],
            'member_id' => $row['member_id'],
            'calling_id' => $row['calling_id'],
            'member_name' => $row['member_name'],
            'calling_name' => $row['calling_name'],
            'proposed_date' => $row['proposed_date'],
            'status' => $row['status'],
            'approved_date' => $row['approved_date'],
            'interviewed_date' => $row['interviewed_date'],
            'sustained_date' => $row['sustained_date'],
            'set_apart_date' => $row['set_apart_date'],
            'notes' => $row['notes'],
            'proposed_by' => $row['proposed_by'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    echo json_encode($processes);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>