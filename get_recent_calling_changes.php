<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_recent_calling_changes', ['risk_level' => 'low', 'file' => 'get_recent_calling_changes.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Get the time period from query parameter (default to 1 month)
    $period = $_GET['period'] ?? '1';
    
    // Validate period parameter
    $validPeriods = ['1', '3', '6', '12'];
    if (!in_array($period, $validPeriods)) {
        $period = '1';
    }
    
    // Build the SQL query to get calling changes within the specified period
    $sql = "
        SELECT 
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            c.calling_name,
            c.organization,
            cc.date_set_apart,
            cc.date_released,
            CASE 
                WHEN cc.date_released IS NULL THEN 'Assigned'
                ELSE 'Released'
            END as change_type,
            COALESCE(cc.date_released, cc.date_set_apart) as change_date
        FROM current_callings cc
        INNER JOIN members m ON cc.member_id = m.member_id
        INNER JOIN callings c ON cc.calling_id = c.calling_id
        WHERE (
            (cc.date_set_apart >= DATE_SUB(CURDATE(), INTERVAL ? MONTH) AND cc.date_set_apart IS NOT NULL)
            OR 
            (cc.date_released >= DATE_SUB(CURDATE(), INTERVAL ? MONTH) AND cc.date_released IS NOT NULL)
        )
        ORDER BY COALESCE(cc.date_released, cc.date_set_apart) DESC, m.last_name ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $period, $period);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $changes = [];
    while ($row = $result->fetch_assoc()) {
        $changes[] = [
            'member_name' => $row['member_name'],
            'calling_name' => $row['calling_name'],
            'organization' => $row['organization'],
            'change_type' => $row['change_type'],
            'change_date' => $row['change_date'],
            'date_set_apart' => $row['date_set_apart'],
            'date_released' => $row['date_released']
        ];
    }
    
    echo json_encode($changes);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>