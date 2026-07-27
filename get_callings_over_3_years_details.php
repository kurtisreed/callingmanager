<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_callings_over_3_years_details', ['risk_level' => 'low', 'file' => 'get_callings_over_3_years_details.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Query to get current calling assignments held 3+ years
    $sql = "SELECT
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                c.calling_name,
                cc.date_set_apart,
                TIMESTAMPDIFF(YEAR, cc.date_set_apart, CURDATE()) as years_in_calling
            FROM current_callings cc
            JOIN members m ON cc.member_id = m.member_id
            JOIN callings c ON cc.calling_id = c.calling_id
            WHERE cc.date_released IS NULL
            AND cc.date_set_apart IS NOT NULL
            AND cc.date_set_apart <= DATE_SUB(CURDATE(), INTERVAL 3 YEAR)
            ORDER BY cc.date_set_apart ASC";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    $details = [];
    while ($row = $result->fetch_assoc()) {
        $details[] = [
            'member_name' => $row['member_name'],
            'calling_name' => $row['calling_name'],
            'date_set_apart' => $row['date_set_apart'],
            'years_in_calling' => $row['years_in_calling']
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
