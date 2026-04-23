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
    // and write to calling_history (same as release_callings.php does)
    if ($step === 'announced') {
        // Fetch the current_callings record details before updating
        $details_stmt = $conn->prepare(
            "SELECT cc.id AS cc_id, cc.member_id, cc.calling_id, cc.date_set_apart
             FROM current_callings cc
             JOIN release_process rp ON cc.id = rp.current_calling_record_id
             WHERE rp.id = ? AND cc.date_released IS NULL"
        );
        $details_stmt->bind_param('i', $id);
        $details_stmt->execute();
        $details_result = $details_stmt->get_result();
        $details = $details_result->fetch_assoc();
        $details_stmt->close();

        if ($details) {
            // Set date_released on current_callings
            $release_stmt = $conn->prepare(
                "UPDATE current_callings SET date_released = ? WHERE id = ?"
            );
            $release_stmt->bind_param('si', $date, $details['cc_id']);
            $release_stmt->execute();
            $release_stmt->close();

            // Build the approximate_period string
            $period = null;
            if ($details['date_set_apart'] && $date) {
                $setApart = new DateTime($details['date_set_apart']);
                $released = new DateTime($date);
                $period = $setApart->format('M j, Y') . ' - ' . $released->format('M j, Y');
            } elseif ($details['date_set_apart']) {
                $period = 'From ' . (new DateTime($details['date_set_apart']))->format('M j, Y');
            } elseif ($date) {
                $period = 'Until ' . (new DateTime($date))->format('M j, Y');
            }

            // Insert into calling_history (skip if duplicate member+calling combo)
            $dup_stmt = $conn->prepare(
                "SELECT id FROM calling_history WHERE member_id = ? AND calling_id = ?"
            );
            $dup_stmt->bind_param('ii', $details['member_id'], $details['calling_id']);
            $dup_stmt->execute();
            $dup_result = $dup_stmt->get_result();
            $isDuplicate = $dup_result->num_rows > 0;
            $dup_stmt->close();

            if (!$isDuplicate) {
                $hist_stmt = $conn->prepare(
                    "INSERT INTO calling_history (member_id, calling_id, approximate_period, notes)
                     VALUES (?, ?, ?, NULL)"
                );
                $hist_stmt->bind_param('iis', $details['member_id'], $details['calling_id'], $period);
                $hist_stmt->execute();
                $hist_stmt->close();
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
