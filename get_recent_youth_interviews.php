<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_recent_youth_interviews', ['risk_level' => 'low', 'file' => 'get_recent_youth_interviews.php']);

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

try {
    $sql = "SELECT
                yi.member_id,
                CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                yi.interview_type,
                yi.interview_date,
                yi.conducted_by,
                yi.notes
            FROM youth_interviews yi
            JOIN members m ON yi.member_id = m.member_id
            WHERE yi.interview_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
            ORDER BY yi.interview_date DESC, yi.created_at DESC";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Database query failed: ' . $conn->error);
    }

    // Role-based visibility: same rule as the per-person history endpoint.
    // Everyone sees who conducted each interview. Notes are more
    // restricted: Bishop sees all notes (including counselors'); Counselor
    // sees notes only for interviews a counselor conducted; Leader never
    // sees notes.
    $currentRole = $_SESSION['role'] ?? null;

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $conductedBy = $row['conducted_by'];
        $notes = $row['notes'];

        if ($currentRole !== 'bishop') {
            $counselorMayViewNotes = $currentRole === 'counselor'
                && in_array($conductedBy, ['first_counselor', 'second_counselor'], true);
            if (!$counselorMayViewNotes) {
                $notes = null;
            }
        }

        $history[] = [
            'member_id'      => (int) $row['member_id'],
            'member_name'    => $row['member_name'],
            'interview_type' => $row['interview_type'],
            'interview_date' => $row['interview_date'],
            'conducted_by'   => $conductedBy,
            'notes'          => $notes,
        ];
    }

    echo json_encode($history);

} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}

$conn->close();
?>
