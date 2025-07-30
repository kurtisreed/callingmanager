<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('delete_from_possible_callings', ['risk_level' => 'high', 'file' => 'delete_from_possible_callings.php']);

require_once 'db_connect.php'; // Make sure this path is correct

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the data from the POST request
$newCalling = $_POST['newCalling'];
$firstName = $_POST['firstName'];
$lastName = $_POST['lastName'];

// Prepare and execute the SQL delete query
$sql = "DELETE FROM possibleCallings WHERE newCalling = '$newCalling' AND `First Name` = '$firstName' AND `Last Name` = '$lastName'";

if ($conn->query($sql) === TRUE) {
    echo "Record deleted successfully";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
