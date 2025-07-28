<?php
// Include your database connection file
require_once 'db_connection.php'; // Make sure this path is correct

header('Content-Type: application/json');

try {
    // === QUERY 1: Get all calling IDs that are currently 'considered' ===
    // This creates a simple, fast lookup set.
    $considered_sql = "SELECT DISTINCT calling_id FROM possible_callings WHERE status = 'considered'";
    $considered_stmt = $pdo->query($considered_sql);
    
    // PDO::FETCH_COLUMN gives us a simple array like [5, 12, 23]
    // array_flip makes it a hash map like [5 => 0, 12 => 1, 23 => 2] for O(1) lookups
    $considered_ids = array_flip($considered_stmt->fetchAll(PDO::FETCH_COLUMN));

    // === QUERY 2: Get all callings and their currently assigned members ===
    $sql = "
        SELECT
            c.calling_id, c.calling_name, c.grouping, c.priority, c.organization,
            m.member_id, m.first_name, m.last_name,
            cc.date_set_apart
        FROM
            callings c
        LEFT JOIN
            current_callings cc ON c.calling_id = cc.calling_id AND cc.date_released IS NULL
        LEFT JOIN
            members m ON cc.member_id = m.member_id
        ORDER BY
            c.priority, c.calling_name, m.last_name;
    ";

    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $callings_data = [];
    foreach ($results as $row) {
        $calling_id = $row['calling_id'];

        // If we haven't seen this calling yet, add its details
        if (!isset($callings_data[$calling_id])) {
            $callings_data[$calling_id] = [
                'details' => [
                    'calling_name' => $row['calling_name'],
                    'grouping' => $row['grouping'],
                    'priority' => (int)$row['priority'],
                    'organization' => $row['organization'],
                    // ** NEW: Check if this calling_id exists in our lookup set **
                    'is_considered' => isset($considered_ids[$calling_id]) 
                ],
                'members' => []
            ];
        }

        // If there is a member in this calling, add them to the members array
        if ($row['member_id']) {
            $callings_data[$calling_id]['members'][] = [
                'member_id' => $row['member_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'date_set_apart' => $row['date_set_apart']
            ];
        }
    }

    echo json_encode($callings_data);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>