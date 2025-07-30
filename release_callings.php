<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('release_callings', ['risk_level' => 'high', 'file' => 'release_callings.php']);

require_once 'db_connect.php'; // Make sure this path is correct

// Check if the required POST variables are set and not empty
if (empty($_POST['release_callings']) || empty($_POST['release_date'])) {
    die("Error: Missing required data for release.");
}

$release_callings = json_decode($_POST['release_callings'], true);
$release_date_str = $_POST['release_date'];

// Validate the incoming date to ensure it's in a valid format (e.g., YYYY-MM-DD)
// This is a good security and data integrity practice.
$date_obj = date_create($release_date_str);
if (!$date_obj) {
    http_response_code(400); // Bad Request
    die("Error: Invalid date format provided.");
}
// If valid, format it to the standard Y-m-d for the database.
$release_date = date_format($date_obj, 'Y-m-d');


// Ensure there are callings to release
if ($release_callings && count($release_callings) > 0) {

    // IMPORTANT: The table name in your database might be different. 
    // I'm using `calling_history` based on our previous discussions.
    // If your table is named `current_callings`, use that instead.
    $sql = "UPDATE current_callings SET date_released = ? WHERE id = ? AND date_released IS NULL";
    
    // Prepare the statement once, outside the loop for efficiency
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }

    $released_count = 0;
    foreach ($release_callings as $id) {
        // Sanitize the ID to ensure it's an integer
        $calling_id = (int)$id;

        // Bind the parameters for each ID *inside* the loop
        $stmt->bind_param("si", $release_date, $calling_id);
        
        // Execute the statement
        if ($stmt->execute()) {
            $released_count += $stmt->affected_rows;
        }
    }

    $stmt->close();
    $conn->close();

    echo "$released_count calling(s) released successfully as of $release_date.";

} else {
    echo "No valid callings were selected for release.";
}
?>