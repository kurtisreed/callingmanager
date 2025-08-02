<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_members_multiple_callings_details', ['risk_level' => 'low', 'file' => 'get_members_multiple_callings_details.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Query to get members with multiple callings
    $sql = "SELECT 
                m.member_id,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                COUNT(cc.calling_id) as calling_count,
                GROUP_CONCAT(
                    c.calling_name
                    ORDER BY cc.date_set_apart DESC 
                    SEPARATOR ', '
                ) as callings
            FROM members m
            JOIN current_callings cc ON m.member_id = cc.member_id
            JOIN callings c ON cc.calling_id = c.calling_id
            WHERE cc.date_released IS NULL
            GROUP BY m.member_id, m.first_name, m.last_name
            HAVING calling_count > 1
            ORDER BY calling_count DESC, m.last_name ASC, m.first_name ASC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $details = [];
    while ($row = $result->fetch_assoc()) {
        $details[] = [
            'member_name' => $row['member_name'],
            'calling_count' => $row['calling_count'],
            'callings' => $row['callings']
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