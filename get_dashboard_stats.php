<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_dashboard_stats', ['risk_level' => 'low', 'file' => 'get_dashboard_stats.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    $stats = [];
    
    // 1. Callings under consideration (unique callings with status 'considered')
    $sql = "SELECT COUNT(DISTINCT pc.calling_id) as count 
            FROM possible_callings pc 
            WHERE pc.status = 'considered'";
    $result = $conn->query($sql);
    $stats['callings_under_consideration'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // 2. Members with Callings (have at least one current calling)
    $sql = "SELECT COUNT(DISTINCT cc.member_id) as count 
            FROM current_callings cc 
            WHERE cc.date_released IS NULL";
    $result = $conn->query($sql);
    $stats['members_with_callings'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // 3. Adults without Callings (active members 18+ without current callings)
    $sql = "SELECT COUNT(m.member_id) as count 
            FROM members m 
            WHERE m.status = 'active' 
            AND TIMESTAMPDIFF(YEAR, m.birthdate, CURDATE()) >= 18
            AND m.member_id NOT IN (
                SELECT DISTINCT cc.member_id 
                FROM current_callings cc 
                WHERE cc.date_released IS NULL
            )";
    $result = $conn->query($sql);
    $stats['adults_without_callings'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // 4. Members with Stake Callings
    $sql = "SELECT COUNT(DISTINCT cc.member_id) as count 
            FROM current_callings cc 
            JOIN callings c ON cc.calling_id = c.calling_id 
            WHERE cc.date_released IS NULL 
            AND c.organization = 'Stake'";
    $result = $conn->query($sql);
    $stats['members_with_stake_callings'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // 5. Members with more than 1 calling
    $sql = "SELECT COUNT(*) as count 
            FROM (
                SELECT cc.member_id, COUNT(*) as calling_count 
                FROM current_callings cc 
                WHERE cc.date_released IS NULL 
                GROUP BY cc.member_id 
                HAVING calling_count > 1
            ) as multiple_callings";
    $result = $conn->query($sql);
    $stats['members_with_multiple_callings'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // 6. Vacant Callings (callings without current assignments)
    $sql = "SELECT COUNT(c.calling_id) as count 
            FROM callings c 
            WHERE c.calling_id NOT IN (
                SELECT DISTINCT cc.calling_id 
                FROM current_callings cc 
                WHERE cc.date_released IS NULL
            )";
    $result = $conn->query($sql);
    $stats['vacant_callings'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // 7. Missionaries (members with callings in Full-Time Missionaries organization)
    $sql = "SELECT COUNT(DISTINCT cc.member_id) as count 
            FROM current_callings cc 
            JOIN callings c ON cc.calling_id = c.calling_id 
            WHERE cc.date_released IS NULL 
            AND c.organization = 'Full-Time Missionaries'";
    $result = $conn->query($sql);
    $stats['missionaries'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    echo json_encode($stats);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>