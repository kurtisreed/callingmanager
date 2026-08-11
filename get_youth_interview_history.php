<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_youth_interview_history', ['risk_level' => 'low', 'file' => 'get_youth_interview_history.php']);

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

$memberId = isset($_GET['member_id']) ? (int) $_GET['member_id'] : 0;

if ($memberId <= 0) {
    echo json_encode(['error' => true, 'message' => 'Invalid member_id']);
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT interview_type, interview_date, conducted_by, notes
         FROM youth_interviews
         WHERE member_id = ?
         ORDER BY interview_date DESC"
    );
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('i', $memberId);
    $stmt->execute();
    $result = $stmt->get_result();

    // Role-based visibility: everyone sees the type/date/conductor of every
    // interview. Notes are more restricted: Bishop sees all notes (including
    // counselors'). Counselors see notes only for interviews conducted by a
    // counselor (not the Bishop's). Leaders never see notes.
    $currentRole = $_SESSION['role'] ?? null;

    $history = [];
    while ($row = $result->fetch_assoc()) {
        if ($currentRole !== 'bishop') {
            $counselorMayViewNotes = $currentRole === 'counselor'
                && in_array($row['conducted_by'], ['first_counselor', 'second_counselor'], true);
            if (!$counselorMayViewNotes) {
                $row['notes'] = null;
            }
        }
        $history[] = $row;
    }
    $stmt->close();

    echo json_encode($history);

} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}

$conn->close();
?>
