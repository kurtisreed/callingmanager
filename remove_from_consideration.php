<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('remove_from_consideration', ['risk_level' => 'high', 'file' => 'remove_from_consideration.php']);

require_once 'db_connect.php'; // Make sure this path is correct

// Get the possible_callings_id from the POST request
$possible_callings_id = (int) ($_POST['possible_callings_id'] ?? 0);

if ($possible_callings_id <= 0) {
    echo "Error updating status: invalid candidate.";
    exit;
}

// Look up which calling this candidate belongs to, so the considering flag
// can be reconciled below without trusting the client to say which one.
$lookup = $conn->prepare("SELECT calling_id FROM possible_callings WHERE id = ?");
$lookup->bind_param("i", $possible_callings_id);
$lookup->execute();
$lookup->bind_result($calling_id);
$found = $lookup->fetch();
$lookup->close();

if (!$found) {
    echo "Error updating status: candidate not found.";
    exit;
}

// Update query to set the status to 'dismissed'
$sql = "
    UPDATE possible_callings
    SET status = 'dismissed', date_updated = NOW()
    WHERE id = ?
";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $possible_callings_id);

if ($stmt->execute()) {
    // The calling's "considering" flag is switched on when a candidate is
    // added; switch it off again once its last candidate is removed, so the
    // calling doesn't keep showing as being considered with nobody on it.
    $clear = $conn->prepare("
        UPDATE callings
        SET considering = 0
        WHERE calling_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM possible_callings
            WHERE calling_id = ? AND status = 'considered'
        )
    ");
    $clear->bind_param("ii", $calling_id, $calling_id);
    $clear->execute();
    $clear->close();

    echo "Candidate status updated to dismissed.";
} else {
    echo "Error updating status: " . $conn->error;
}

// Close connections
$stmt->close();
$conn->close();
?>
