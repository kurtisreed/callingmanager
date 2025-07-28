<?php
require_once 'db_connect.php'; // Make sure this path is correct

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberId = intval($_POST['member_id']);
    $callingId = intval($_POST['calling_id']);
    
    // Prepare and execute the SQL query to insert a new possible calling
    $sql = "INSERT INTO possible_callings (member_id, calling_id, status, date_added) 
            VALUES (?, ?, 'considered', NOW())";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo "Error preparing statement: " . $conn->error;
        exit;
    }

    $stmt->bind_param("ii", $memberId, $callingId);

    if ($stmt->execute()) {
        echo "Member added to possible callings successfully.";
    } else {
        echo "Error adding member: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Invalid request method.";
}

$conn->close();
?>