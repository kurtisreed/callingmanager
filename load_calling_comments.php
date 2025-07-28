<?php
require_once 'db_connect.php'; // Make sure this path is correct

// Check if calling_id is provided in the POST request
if (isset($_POST['calling_id'])) {
    // Get the calling_id from the POST data
    $calling_id = $_POST['calling_id'];

    // Prepare the SQL statement
    $stmt = $conn->prepare("SELECT comments FROM callings WHERE calling_id = ?");
    $stmt->bind_param("i", $calling_id); // "i" denotes an integer type

    // Execute the statement
    $stmt->execute();

    // Bind the result to a variable
    $stmt->bind_result($comments);

    // Fetch the result
    if ($stmt->fetch()) {
        // Echo the comments if found
        echo $comments;
    } else {
        // Return an error message if no data found
        echo "No comments found for the provided calling_id.";
    }

    // Close the statement
    $stmt->close();
} else {
    echo "No calling_id provided.";
}

// Close the connection
$conn->close();
?>