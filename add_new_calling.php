<?php
require_once 'db_connect.php'; // Make sure this path is correct

$calling_name = $_POST['calling_name'];
$organization = $_POST['organization'];
$grouping = $_POST['grouping'];
$priority = $_POST['priority'];

// SQL to insert new calling
$sql = "INSERT INTO callings (calling_name, organization, grouping, priority) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $calling_name, $organization, $grouping, $priority);

if ($stmt->execute()) {
    echo "Calling added successfully.";
} else {
    echo "Error adding calling: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
