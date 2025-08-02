<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_callings_under_consideration_details', ['risk_level' => 'low', 'file' => 'get_callings_under_consideration_details.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Query to get callings under consideration with candidate counts
    $sql = "SELECT 
                c.calling_id,
                c.calling_name,
                c.organization,
                c.grouping,
                COUNT(pc.member_id) as candidate_count
            FROM callings c
            JOIN possible_callings pc ON c.calling_id = pc.calling_id
            WHERE pc.status = 'considered'
            GROUP BY c.calling_id, c.calling_name, c.organization, c.grouping
            ORDER BY c.organization ASC, c.calling_name ASC";
    
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
            'grouping' => $row['grouping'],
            'candidate_count' => $row['candidate_count']
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