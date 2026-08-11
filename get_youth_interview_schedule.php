<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_youth_interview_schedule', ['risk_level' => 'low', 'file' => 'get_youth_interview_schedule.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

// Defensive create, matching the pattern used elsewhere in this app
// (e.g. calling_history) so this endpoint works even if the table hasn't
// been provisioned yet.
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
    // Compute last/this/next month windows relative to today.
    $today = new DateTime();
    $windowDefs = [
        'last_month' => (clone $today)->modify('-1 month'),
        'this_month' => $today,
        'next_month' => (clone $today)->modify('+1 month'),
    ];

    $windows = [];
    foreach ($windowDefs as $key => $date) {
        $windows[$key] = [
            'month'      => (int) $date->format('n'),
            'year'       => (int) $date->format('Y'),
            'label'      => $date->format('F Y'),
            'occurrence' => $date->format('Y-m-01'),
        ];
    }

    // Active youth population — same as the Youth tab (cohort age 12-18,
    // excluding members who've moved away or are deceased).
    $sql = "SELECT member_id, CONCAT(first_name, ' ', last_name) AS member_name, birthdate,
                   (YEAR(CURDATE()) - YEAR(birthdate)) AS cohort_age
            FROM members
            WHERE birthdate IS NOT NULL
            AND status NOT IN ('moved', 'deceased')
            AND (YEAR(CURDATE()) - YEAR(birthdate)) BETWEEN 12 AND 18";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Database query failed: ' . $conn->error);
    }

    $youth = [];
    while ($row = $result->fetch_assoc()) {
        $birthMonth = (int) date('n', strtotime($row['birthdate']));
        // Semiannual interview is due 6 months from the birth month.
        $semiMonth = (($birthMonth + 6 - 1) % 12) + 1;

        $youth[] = [
            'member_id'            => (int) $row['member_id'],
            'member_name'          => $row['member_name'],
            'birthdate'            => $row['birthdate'],
            'annual_due_month'     => $birthMonth,
            // Youth turning 15 or younger this year meet a counselor for
            // the semiannual interview; 16+ meet the Bishop for both.
            'semiannual_due_month' => $semiMonth,
            'semiannual_assigned'  => ((int) $row['cohort_age']) <= 15 ? 'Bishopric Counselor' : 'Bishop',
        ];
    }

    // Build the due-list for each window.
    $dueEntries = [];
    $candidateMemberIds = [];
    foreach ($windows as $winKey => $win) {
        $dueEntries[$winKey] = [];

        foreach ($youth as $y) {
            if ($y['annual_due_month'] === $win['month']) {
                $dueEntries[$winKey][] = [
                    'member_id'      => $y['member_id'],
                    'member_name'    => $y['member_name'],
                    'birthdate'      => $y['birthdate'],
                    'interview_type' => 'annual',
                    'assigned_to'    => 'Bishop',
                ];
                $candidateMemberIds[$y['member_id']] = true;
            }
            if ($y['semiannual_due_month'] === $win['month']) {
                $dueEntries[$winKey][] = [
                    'member_id'      => $y['member_id'],
                    'member_name'    => $y['member_name'],
                    'birthdate'      => $y['birthdate'],
                    'interview_type' => 'semiannual',
                    'assigned_to'    => $y['semiannual_assigned'],
                ];
                $candidateMemberIds[$y['member_id']] = true;
            }
        }
    }

    // Fetch existing annual/semiannual logs for candidate members so we can
    // determine which due entries are already completed.
    $completedLogs = [];
    if (!empty($candidateMemberIds)) {
        $ids = array_keys($candidateMemberIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $logSql = "SELECT member_id, interview_type, interview_date, conducted_by, notes
                   FROM youth_interviews
                   WHERE member_id IN ($placeholders)
                   AND interview_type IN ('annual', 'semiannual')";
        $logStmt = $conn->prepare($logSql);
        $logStmt->bind_param($types, ...$ids);
        $logStmt->execute();
        $logResult = $logStmt->get_result();
        while ($row = $logResult->fetch_assoc()) {
            $completedLogs[$row['member_id']][] = $row;
        }
        $logStmt->close();
    }

    // Match completion within +/- 1 month of each window's due-month
    // occurrence (an interview done slightly early or late still counts).
    foreach ($dueEntries as $winKey => &$entries) {
        $occurrence = new DateTime($windows[$winKey]['occurrence']);
        $rangeStart = (clone $occurrence)->modify('-1 month');
        $rangeEnd   = (clone $occurrence)->modify('+1 month');

        foreach ($entries as &$entry) {
            $entry['completed']      = false;
            $entry['completed_date'] = null;
            $entry['completed_by']   = null;
            $entry['notes']          = null;

            if (!empty($completedLogs[$entry['member_id']])) {
                foreach ($completedLogs[$entry['member_id']] as $log) {
                    if ($log['interview_type'] !== $entry['interview_type']) continue;
                    $logDate = new DateTime($log['interview_date']);
                    if ($logDate >= $rangeStart && $logDate <= $rangeEnd) {
                        $entry['completed']      = true;
                        $entry['completed_date'] = $log['interview_date'];
                        $entry['completed_by']   = $log['conducted_by'];
                        $entry['notes']          = $log['notes'];
                        break;
                    }
                }
            }
        }
        unset($entry);
    }
    unset($entries);

    // Role-based visibility: Bishop sees all notes/who-conducted. Counselor
    // sees that a step is complete (and when) for everyone, but only sees
    // WHO conducted it and the notes for interviews a counselor conducted.
    // Leader never sees who conducted it or notes, only whether it's due
    // and whether it's done.
    $currentRole = $_SESSION['role'] ?? null;
    if ($currentRole !== 'bishop') {
        foreach ($dueEntries as &$entries) {
            foreach ($entries as &$entry) {
                $counselorMayView = $currentRole === 'counselor'
                    && in_array($entry['completed_by'], ['first_counselor', 'second_counselor'], true);
                if (!$counselorMayView) {
                    $entry['completed_by'] = null;
                    $entry['notes'] = null;
                }
            }
            unset($entry);
        }
        unset($entries);
    }

    // Needed interviews first, then alphabetical.
    foreach ($dueEntries as &$entries) {
        usort($entries, function ($a, $b) {
            if ($a['completed'] !== $b['completed']) {
                return $a['completed'] ? 1 : -1;
            }
            return strcmp($a['member_name'], $b['member_name']);
        });
    }
    unset($entries);

    echo json_encode([
        'windows' => $windows,
        'due'     => $dueEntries,
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}

$conn->close();
?>
