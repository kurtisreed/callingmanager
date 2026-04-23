<?php
require_once __DIR__ . '/auth_required.php';
logUserActivity('get_calling_history', ['risk_level' => 'medium', 'file' => 'get_calling_history.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    $member_id = (int) $_GET['member_id'];

    // Ensure calling_history table exists (used for legacy manual entries)
    $conn->query("CREATE TABLE IF NOT EXISTS calling_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        calling_id INT NOT NULL,
        approximate_period VARCHAR(100) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_member_id (member_id),
        INDEX idx_calling_id (calling_id)
    )");

    // Union released current_callings (real dates) with legacy calling_history entries.
    // Subquery wrapper keeps ORDER BY compatible with MySQL 5.7+.
    $sql = "
        SELECT calling_name, approximate_period, notes
        FROM (
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

            UNION ALL

            SELECT
                c.calling_name,
                ch.approximate_period,
                ch.notes,
                ch.added_date AS sort_date
            FROM calling_history ch
            JOIN callings c ON ch.calling_id = c.calling_id
            WHERE ch.member_id = ?
        ) AS combined
        ORDER BY sort_date DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('ii', $member_id, $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'calling_name'       => $row['calling_name'],
            'approximate_period' => $row['approximate_period'],
            'notes'              => $row['notes'],
        ];
    }

    $stmt->close();
    echo json_encode($history);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>
