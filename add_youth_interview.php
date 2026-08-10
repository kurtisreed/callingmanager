<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('add_youth_interview', ['risk_level' => 'high', 'file' => 'add_youth_interview.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

$conn->query("CREATE TABLE IF NOT EXISTS youth_interviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    interview_type ENUM('annual','semiannual','calling','temple_recommend','other') NOT NULL,
    interview_date DATE NOT NULL,
    conducted_by ENUM('bishop','first_counselor','second_counselor','other') NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_member_id (member_id),
    INDEX idx_interview_type (interview_type)
)");

$data = json_decode(file_get_contents('php://input'), true);

$memberId       = $data['member_id'] ?? null;
$interviewType  = $data['interview_type'] ?? null;
$interviewDate  = $data['interview_date'] ?? null;
$conductedBy    = $data['conducted_by'] ?? null;
$notes          = $data['notes'] ?? null;

$validTypes = ['annual', 'semiannual', 'calling', 'temple_recommend', 'other'];
$validRoles = ['bishop', 'first_counselor', 'second_counselor', 'other'];

if (!$memberId || !is_numeric($memberId)) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid member']);
    exit;
}

if (!in_array($interviewType, $validTypes, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid interview type']);
    exit;
}

if (!in_array($conductedBy, $validRoles, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid conducted-by role']);
    exit;
}

if (!$interviewDate || !DateTime::createFromFormat('Y-m-d', $interviewDate)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date']);
    exit;
}

if ($notes !== null && !is_string($notes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid notes']);
    exit;
}

try {
    $memberIdInt = (int) $memberId;

    $stmt = $conn->prepare(
        "INSERT INTO youth_interviews (member_id, interview_type, interview_date, conducted_by, notes)
         VALUES (?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('issss', $memberIdInt, $interviewType, $interviewDate, $conductedBy, $notes);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save interview']);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
