<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_calling_history', ['risk_level' => 'medium', 'file' => 'get_calling_history.php']);

require_once 'db_connect.php';

try {
    $member_id = $_GET['member_id'];

    // Create table if it doesn't exist
    $createTableSql = "CREATE TABLE IF NOT EXISTS calling_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        calling_id INT NOT NULL,
        approximate_period VARCHAR(100) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        FOREIGN KEY (calling_id) REFERENCES callings(calling_id) ON DELETE CASCADE,
        INDEX idx_member_id (member_id),
        INDEX idx_calling_id (calling_id)
    )";
    
    $conn->query($createTableSql);

    // SQL query to fetch calling history, ordering by id (most recent first)
    $sql = "SELECT c.calling_id, c.calling_name, ch.approximate_period, ch.notes, ch.id
            FROM calling_history ch
            JOIN callings c ON ch.calling_id = c.calling_id
            WHERE ch.member_id = ?
            ORDER BY ch.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($history);

    $stmt->close();
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
} finally {
    $conn->close();
}
?>