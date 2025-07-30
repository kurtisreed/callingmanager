<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('add_member', ['risk_level' => 'high', 'file' => 'add_member.php']);

require_once 'db_connect.php'; // Make sure this path is correct

$firstName = $_POST['first_name'];
$lastName = $_POST['last_name'];
$gender = $_POST['gender'];
$birthdate = $_POST['birthdate'];

// SQL to insert new member
$sql = "INSERT INTO members (first_name, last_name, gender, birthdate) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $firstName, $lastName, $gender, $birthdate);

if ($stmt->execute()) {
    echo "Member added successfully.";
} else {
    echo "Error adding member: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
