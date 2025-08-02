<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_vacant_callings_details', ['risk_level' => 'low', 'file' => 'get_vacant_callings_details.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Query to get vacant callings (callings without current assignments)
    $sql = "SELECT 
                c.calling_id,
                c.calling_name,
                c.organization,
                c.grouping,
                c.priority
            FROM callings c
            WHERE c.calling_id NOT IN (
                SELECT DISTINCT cc.calling_id 
                FROM current_callings cc 
                WHERE cc.date_released IS NULL
            )
            ORDER BY c.organization ASC, c.priority ASC, c.calling_name ASC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $details = [];
    while ($row = $result->fetch_assoc()) {
        $details[] = [
            'calling_id' => $row['calling_id'],
            'calling_name' => $row['calling_name'],
            'organization' => $row['organization'],
            'grouping' => $row['grouping'] ?: 'N/A'
        ];
    }
    
    echo json_encode($details);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>