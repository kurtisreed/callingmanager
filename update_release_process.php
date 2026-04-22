<?php
require_once __DIR__ . '/auth_required.php';
logUserActivity('update_release_process', ['risk_level' => 'high', 'file' => 'update_release_process.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$id   = $data['id']   ?? null;
$step = $data['step'] ?? null;
$date = $data['date'] ?? null;

if (!$id || !$step || !$date) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$validSteps = ['approved', 'leader_notified', 'interviewed', 'announced', 'lcr'];
if (!in_array($step, $validSteps)) {
    echo json_encode(['success' => false, 'message' => 'Invalid step']);
    exit;
}

if (!DateTime::createFromFormat('Y-m-d', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

$dateFieldMap = [
    'approved'        => 'approved_date',
    'leader_notified' => 'leader_notified_date',
    'interviewed'     => 'interviewed_date',
    'announced'       => 'announced_date',
    'lcr'             => 'lcr_date',
];

$dateField = $dateFieldMap[$step];

try {
    $stmt = $conn->prepare("UPDATE release_process SET $dateField = ? WHERE id = ?");
    $stmt->bind_param('si', $date, $id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('No release process found with that ID');
    }

    echo json_encode(['success' => true]);
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
