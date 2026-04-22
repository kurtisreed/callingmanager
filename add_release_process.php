<?php
require_once __DIR__ . '/auth_required.php';
logUserActivity('add_release_process', ['risk_level' => 'high', 'file' => 'add_release_process.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['member_id'], $input['calling_id'], $input['record_ids']) || !is_array($input['record_ids'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    $member_id  = (int) $input['member_id'];
    $calling_id = (int) $input['calling_id'];
    $record_ids = array_map('intval', $input['record_ids']);

    if ($member_id <= 0 || $calling_id <= 0 || empty($record_ids)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input values.']);
        exit;
    }

    // Duplicate check: find any record_ids already pending
    $placeholders = implode(',', array_fill(0, count($record_ids), '?'));
    $types = str_repeat('i', count($record_ids));
    $check_sql = "SELECT current_calling_record_id FROM release_process
                  WHERE current_calling_record_id IN ($placeholders) AND status = 'pending'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param($types, ...$record_ids);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    $existing = [];
    while ($row = $check_result->fetch_assoc()) {
        $existing[] = (int) $row['current_calling_record_id'];
    }
    $check_stmt->close();

    // Insert only non-duplicates, auto-setting approved_date to today
    $today = date('Y-m-d');
    $insert_sql = "INSERT INTO release_process (current_calling_record_id, member_id, calling_id, approved_date) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);

    $inserted = 0;
    foreach ($record_ids as $record_id) {
        if (in_array($record_id, $existing)) {
            continue; // skip duplicate
        }
        $insert_stmt->bind_param('iiis', $record_id, $member_id, $calling_id, $today);
        if ($insert_stmt->execute()) {
            $inserted++;
        }
    }
    $insert_stmt->close();

    echo json_encode(['success' => true, 'inserted' => $inserted]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
