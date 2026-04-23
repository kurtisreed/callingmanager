<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_calling_history', ['risk_level' => 'medium', 'file' => 'get_calling_history.php']);

require_once 'db_connect.php';

try {
    $member_id = (int) $_GET['member_id'];

    // Union both sources — no deduplication, a member may hold the same calling multiple times
    // Primary: released current_callings rows (real dates)
    // Secondary: legacy calling_history entries (manually added, approximate periods)
    $sql = "
        (
            SELECT
                c.calling_name,
                CASE
                    WHEN cc.date_set_apart IS NOT NULL AND cc.date_released IS NOT NULL
                        THEN CONCAT(DATE_FORMAT(cc.date_set_apart, '%b %e, %Y'), ' - ', DATE_FORMAT(cc.date_released, '%b %e, %Y'))
                    WHEN cc.date_set_apart IS NOT NULL
                        THEN CONCAT('From ', DATE_FORMAT(cc.date_set_apart, '%b %e, %Y'))
                    ELSE CONCAT('Until ', DATE_FORMAT(cc.date_released, '%b %e, %Y'))
                END AS approximate_period,
                NULL AS notes,
                cc.date_released AS sort_date
            FROM current_callings cc
            JOIN callings c ON cc.calling_id = c.calling_id
            WHERE cc.member_id = ? AND cc.date_released IS NOT NULL
        )
        UNION ALL
        (
            SELECT
                c.calling_name,
                ch.approximate_period,
                ch.notes,
                ch.added_date AS sort_date
            FROM calling_history ch
            JOIN callings c ON ch.calling_id = c.calling_id
            WHERE ch.member_id = ?
        )
        ORDER BY sort_date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $member_id, $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'calling_name'      => $row['calling_name'],
            'approximate_period'=> $row['approximate_period'],
            'notes'             => $row['notes'],
        ];
    }

    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode($history);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
} finally {
    $conn->close();
}
?>
