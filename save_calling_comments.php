<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('save_calling_comments', ['risk_level' => 'high', 'file' => 'save_calling_comments.php']);

header('Content-Type: application/json');
require_once 'db_connect.php'; // Make sure this path is correct

// Check if calling_id and comments are provided in the POST request
if (isset($_POST['calling_id']) && isset($_POST['comments'])) {
    // Get the calling_id and comments from the POST data
    $calling_id = $_POST['calling_id'];
    $comments = $_POST['comments'];

    // Prepare the SQL statement to update the comments
    $stmt = $conn->prepare("UPDATE callings SET comments = ? WHERE calling_id = ?");
    $stmt->bind_param("si", $comments, $calling_id); // "s" denotes a string, "i" denotes an integer

    // Execute the statement
    if ($stmt->execute()) {
        echo "Comments updated successfully.";
    } else {
        echo "Error updating comments: " . $stmt->error;
    }

    // Close the statement
    $stmt->close();
} else {
    echo "calling_id or comments not provided.";
}

// Close the connection
$conn->close();
?>