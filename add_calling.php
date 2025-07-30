<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('add_calling', ['risk_level' => 'high', 'file' => 'add_calling.php']);

require_once 'db_connect.php'; // Make sure this path is correct

$member_id = $_POST['member_id'];
$calling_id = $_POST['calling_id'];
$date_set_apart = $_POST['date_set_apart'];
$date_released = !empty($_POST['date_released']) ? $_POST['date_released'] : NULL;

// Insert new calling
$stmt = $conn->prepare("INSERT INTO current_callings (member_id, calling_id, date_set_apart, date_released) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiss", $member_id, $calling_id, $date_set_apart, $date_released);
$stmt->execute();

// Handle callings to release
if (!empty($_POST['release_callings'])) {
    $release_callings = json_decode($_POST['release_callings'], true);

    foreach ($release_callings as $release_id) {
        $release_date = date('Y-m-d');
        $stmt = $conn->prepare("UPDATE current_callings SET date_released = ? WHERE id = ? AND date_released IS NULL");
        $stmt->bind_param("si", $release_date, $release_id);
        $stmt->execute();
    }
}

$stmt->close();
$conn->close();
echo "Calling assigned and updated successfully!";
?>