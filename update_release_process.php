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
    $conn->begin_transaction();

    // Update the step date on the release process record
    $stmt = $conn->prepare("UPDATE release_process SET $dateField = ? WHERE id = ?");
    $stmt->bind_param('si', $date, $id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('No release process found with that ID');
    }
    $stmt->close();

    // When Announced: officially release the calling in current_callings
    if ($step === 'announced') {
        $release_stmt = $conn->prepare(
            "UPDATE current_callings cc
             JOIN release_process rp ON cc.id = rp.current_calling_record_id
             SET cc.date_released = ?
             WHERE rp.id = ? AND cc.date_released IS NULL"
        );
        $release_stmt->bind_param('si', $date, $id);
        $release_stmt->execute();
        $release_stmt->close();
    }

    // If all 5 steps are now complete, mark the record as released
    $check_stmt = $conn->prepare(
        "SELECT id FROM release_process
         WHERE id = ?
           AND approved_date IS NOT NULL
           AND leader_notified_date IS NOT NULL
           AND interviewed_date IS NOT NULL
           AND announced_date IS NOT NULL
           AND lcr_date IS NOT NULL"
    );
    $check_stmt->bind_param('i', $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        $done_stmt = $conn->prepare("UPDATE release_process SET status = 'released' WHERE id = ?");
        $done_stmt->bind_param('i', $id);
        $done_stmt->execute();
        $done_stmt->close();
    }
    $check_stmt->close();

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
