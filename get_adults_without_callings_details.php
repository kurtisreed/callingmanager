<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_adults_without_callings_details', ['risk_level' => 'low', 'file' => 'get_adults_without_callings_details.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Query to get active adults without callings
    $sql = "SELECT 
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                TIMESTAMPDIFF(YEAR, m.birthdate, CURDATE()) as age,
                m.status,
                m.birthdate
            FROM members m
            WHERE m.status = 'active'
            AND TIMESTAMPDIFF(YEAR, m.birthdate, CURDATE()) >= 18
            AND m.member_id NOT IN (
                SELECT DISTINCT cc.member_id 
                FROM current_callings cc 
                WHERE cc.date_released IS NULL
            )
            ORDER BY m.last_name ASC, m.first_name ASC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $details = [];
    while ($row = $result->fetch_assoc()) {
        $details[] = [
            'member_name' => $row['member_name'],
            'age' => $row['age'],
            'status' => ucfirst($row['status'])
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