<?php
require_once __DIR__ . '/auth_required.php';
logUserActivity('get_release_processes', ['risk_level' => 'low', 'file' => 'get_release_processes.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    $sql = "SELECT
                rp.id,
                rp.status,
                rp.added_date,
                rp.member_id,
                rp.calling_id,
                CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                c.calling_name
            FROM release_process rp
            JOIN members m ON rp.member_id = m.member_id
            JOIN callings c ON rp.calling_id = c.calling_id
            WHERE rp.status = 'pending'
            ORDER BY rp.added_date DESC";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception('Database query failed: ' . $conn->error);
    }

    $releases = [];
    while ($row = $result->fetch_assoc()) {
        $releases[] = [
            'id'          => (int) $row['id'],
            'member_id'   => (int) $row['member_id'],
            'calling_id'  => (int) $row['calling_id'],
            'member_name' => $row['member_name'],
            'calling_name'=> $row['calling_name'],
            'status'      => $row['status'],
            'added_date'  => $row['added_date'],
        ];
    }

    echo json_encode($releases);

} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}

$conn->close();
?>
