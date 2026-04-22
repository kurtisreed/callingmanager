<?php
require_once __DIR__ . '/auth_required.php';
logUserActivity('remove_release_process', ['risk_level' => 'high', 'file' => 'remove_release_process.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id']) || (int) $input['id'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or missing id.']);
        exit;
    }

    $id = (int) $input['id'];

    $stmt = $conn->prepare("DELETE FROM release_process WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
    } else {
        echo json_encode(['success' => true]);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
