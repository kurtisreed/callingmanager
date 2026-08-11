<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Only the Bishop can view the activity log
requireRole(['bishop']);

logUserActivity('get_activity_log', ['risk_level' => 'medium', 'file' => 'get_activity_log.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

$conn->query("CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    username VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL,
    action VARCHAR(100) NOT NULL,
    file VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    details TEXT DEFAULT NULL,
    INDEX idx_created_at (created_at),
    INDEX idx_username (username),
    INDEX idx_action (action)
)");

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
$limit = max(1, min(500, $limit));
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

$where = [];
$params = [];
$types = '';

if (!empty($_GET['username'])) {
    $where[] = 'username = ?';
    $params[] = $_GET['username'];
    $types .= 's';
}

if (!empty($_GET['action'])) {
    $where[] = 'action LIKE ?';
    $params[] = '%' . $_GET['action'] . '%';
    $types .= 's';
}

if (!empty($_GET['start_date'])) {
    $where[] = 'created_at >= ?';
    $params[] = $_GET['start_date'] . ' 00:00:00';
    $types .= 's';
}

if (!empty($_GET['end_date'])) {
    $where[] = 'created_at <= ?';
    $params[] = $_GET['end_date'] . ' 23:59:59';
    $types .= 's';
}

$whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

try {
    $sql = "SELECT id, created_at, username, role, action, file, ip_address, details
            FROM activity_log
            $whereClause
            ORDER BY created_at DESC, id DESC
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $bindParams = $params;
    $bindParams[] = $limit;
    $bindParams[] = $offset;
    $bindTypes = $types . 'ii';
    $stmt->bind_param($bindTypes, ...$bindParams);
    $stmt->execute();
    $result = $stmt->get_result();

    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $entries[] = [
            'id'         => (int) $row['id'],
            'created_at' => $row['created_at'],
            'username'   => $row['username'],
            'role'       => $row['role'],
            'action'     => $row['action'],
            'file'       => $row['file'],
            'ip_address' => $row['ip_address'],
            'details'    => $row['details'],
        ];
    }
    $stmt->close();

    echo json_encode($entries);

} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}

$conn->close();
?>
