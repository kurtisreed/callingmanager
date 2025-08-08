<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('release_callings', ['risk_level' => 'high', 'file' => 'release_callings.php']);

require_once 'db_connect.php'; // Make sure this path is correct

// Function to add calling to history when member is released
function addToCallingHistory($conn, $member_id, $calling_id, $date_set_apart, $date_released) {
    try {
        // Create table if it doesn't exist
        $createTableSql = "CREATE TABLE IF NOT EXISTS calling_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_id INT NOT NULL,
            calling_id INT NOT NULL,
            approximate_period VARCHAR(100) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
            FOREIGN KEY (calling_id) REFERENCES callings(calling_id) ON DELETE CASCADE,
            INDEX idx_member_id (member_id),
            INDEX idx_calling_id (calling_id)
        )";
        
        $conn->query($createTableSql);
        
        // Format the period based on available dates
        $period = null;
        if ($date_set_apart && $date_released) {
            $setApart = new DateTime($date_set_apart);
            $released = new DateTime($date_released);
            $period = $setApart->format('M j, Y') . ' - ' . $released->format('M j, Y');
        } elseif ($date_set_apart) {
            $setApart = new DateTime($date_set_apart);
            $period = 'From ' . $setApart->format('M j, Y');
        } elseif ($date_released) {
            $released = new DateTime($date_released);
            $period = 'Until ' . $released->format('M j, Y');
        }
        
        $notes = "Automatically added when released from calling";
        
        // Check for duplicate entry
        $duplicateCheck = $conn->prepare("SELECT id FROM calling_history WHERE member_id = ? AND calling_id = ?");
        $duplicateCheck->bind_param("ii", $member_id, $calling_id);
        $duplicateCheck->execute();
        if ($duplicateCheck->get_result()->num_rows > 0) {
            return; // Don't add duplicate
        }
        
        // Insert the history entry
        $stmt = $conn->prepare("INSERT INTO calling_history (member_id, calling_id, approximate_period, notes) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $member_id, $calling_id, $period, $notes);
        $stmt->execute();
        
    } catch (Exception $e) {
        // Log error but don't stop the release process
        error_log("Failed to add calling history: " . $e->getMessage());
    }
}

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
        $calling_record_id = (int)$id;

        // Get calling details before releasing
        $details_sql = "SELECT member_id, calling_id, date_set_apart FROM current_callings WHERE id = ? AND date_released IS NULL";
        $details_stmt = $conn->prepare($details_sql);
        $details_stmt->bind_param("i", $calling_record_id);
        $details_stmt->execute();
        $details_result = $details_stmt->get_result();
        
        if ($details_row = $details_result->fetch_assoc()) {
            $member_id = $details_row['member_id'];
            $calling_id = $details_row['calling_id'];
            $date_set_apart = $details_row['date_set_apart'];
            
            // Bind the parameters for the release
            $stmt->bind_param("si", $release_date, $calling_record_id);
            
            // Execute the release statement
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                // Add to calling history
                addToCallingHistory($conn, $member_id, $calling_id, $date_set_apart, $release_date);
                $released_count += $stmt->affected_rows;
            }
        }
    }

    $stmt->close();
    $conn->close();

    echo "$released_count calling(s) released successfully as of $release_date.";

} else {
    echo "No valid callings were selected for release.";
}
?>