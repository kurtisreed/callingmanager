<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_calling_candidates', ['risk_level' => 'medium', 'file' => 'get_calling_candidates.php']);

require_once 'db_connect.php';

$calling_id = $_GET['calling_id'] ?? null;

if (!$calling_id) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// SQL query to fetch members being considered for this calling
// Excludes the member who is currently being assigned (status = 'considered' only)
$sql = "SELECT m.member_id, CONCAT(m.first_name, ' ', m.last_name) AS member_name
        FROM possible_callings pc
        JOIN members m ON pc.member_id = m.member_id
        WHERE pc.calling_id = ? AND pc.status = 'considered'
        ORDER BY m.last_name, m.first_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $calling_id);
$stmt->execute();
$result = $stmt->get_result();

$candidates = [];
while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
}

header('Content-Type: application/json');
echo json_encode($candidates);

$stmt->close();
$conn->close();
?>
