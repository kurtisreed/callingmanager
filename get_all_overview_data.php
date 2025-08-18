<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_all_overview_data', ['risk_level' => 'medium', 'file' => 'get_all_overview_data.php']);

// Include your database connection file
require_once 'db_connect.php'; // Use standard MySQLi connection

header('Content-Type: application/json');

// === QUERY 1: Get all calling IDs that are currently 'considered' ===
// This creates a simple, fast lookup set.
$considered_sql = "SELECT DISTINCT calling_id FROM possible_callings WHERE status = 'considered'";
$considered_result = $conn->query($considered_sql);

if (!$considered_result) {
    http_response_code(500);
    error_log('Database error in get_all_overview_data.php (Query 1): ' . $conn->error);
    echo json_encode(['error' => 'Database query failed']);
    exit;
}

// Build lookup array for considered calling IDs - equivalent to array_flip(fetchAll(PDO::FETCH_COLUMN))
$considered_ids = [];
while ($row = $considered_result->fetch_row()) {
    $considered_ids[$row[0]] = true; // Use calling_id as key for O(1) lookups
}

// === QUERY 1.5: Get all calling IDs that have approved calling processes ===
$approved_sql = "SELECT DISTINCT calling_id FROM calling_process WHERE status IN ('approved', 'interviewed', 'sustained', 'set_apart')";
$approved_result = $conn->query($approved_sql);

if (!$approved_result) {
    http_response_code(500);
    error_log('Database error in get_all_overview_data.php (Query 1.5): ' . $conn->error);
    echo json_encode(['error' => 'Database query failed']);
    exit;
}

// Build lookup array for approved calling IDs
$approved_ids = [];
while ($row = $approved_result->fetch_row()) {
    $approved_ids[$row[0]] = true; // Use calling_id as key for O(1) lookups
}

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

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    error_log('Database error in get_all_overview_data.php (Query 2): ' . $conn->error);
    echo json_encode(['error' => 'Database query failed']);
    exit;
}

$callings_data = [];
while ($row = $result->fetch_assoc()) {
    $calling_id = $row['calling_id'];

    // If we haven't seen this calling yet, add its details
    if (!isset($callings_data[$calling_id])) {
        $callings_data[$calling_id] = [
            'details' => [
                'calling_name' => $row['calling_name'],
                'grouping' => $row['grouping'],
                'priority' => (int)$row['priority'],
                'organization' => $row['organization'],
                // Check if this calling_id exists in our lookup set
                'is_considered' => isset($considered_ids[$calling_id]),
                'is_approved' => isset($approved_ids[$calling_id])
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

// Close the connection
$conn->close();
?>