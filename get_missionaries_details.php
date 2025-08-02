<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_missionaries_details', ['risk_level' => 'low', 'file' => 'get_missionaries_details.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Query to get missionaries (members with Full-Time Missionaries callings)
    $sql = "SELECT 
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                c.calling_name,
                cc.date_set_apart
            FROM members m
            JOIN current_callings cc ON m.member_id = cc.member_id
            JOIN callings c ON cc.calling_id = c.calling_id
            WHERE cc.date_released IS NULL
            AND c.organization = 'Full-Time Missionaries'
            ORDER BY cc.date_set_apart DESC, m.last_name ASC, m.first_name ASC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $details = [];
    while ($row = $result->fetch_assoc()) {
        $details[] = [
            'member_name' => $row['member_name'],
            'calling_name' => $row['calling_name'],
            'date_set_apart' => $row['date_set_apart']
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