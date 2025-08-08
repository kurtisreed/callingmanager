<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this high-risk access for auditing
logUserActivity('make_calling_changes', ['risk_level' => 'high', 'file' => 'make_calling_changes.php']);

header('Content-Type: text/plain');
require_once 'db_connect.php';

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo "Invalid input data.";
    exit;
}

$member_id = $data['member_id'] ?? null;
$calling_id = $data['calling_id'] ?? null;
$change_date = $data['change_date'] ?? null;
$member_releases = $data['member_releases'] ?? [];
$calling_releases = $data['calling_releases'] ?? [];
$update_possible_callings = $data['update_possible_callings'] ?? false;
$remove_other_candidates = $data['remove_other_candidates'] ?? false;

if (!$change_date) {
    echo "Change date is required.";
    exit;
}

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $change_date)) {
    echo "Invalid date format.";
    exit;
}

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

try {
    // Start transaction
    $conn->begin_transaction();
    
    $changes_made = [];
    
    // Process member releases (from member-callings table)
    if (!empty($member_releases)) {
        foreach ($member_releases as $release_id) {
            $release_id = $conn->real_escape_string($release_id);
            
            // Get calling details for logging
            $details_sql = "SELECT cc.member_id, cc.calling_id, cc.date_set_apart, c.calling_name, m.first_name, m.last_name 
                           FROM current_callings cc 
                           JOIN callings c ON cc.calling_id = c.calling_id 
                           JOIN members m ON cc.member_id = m.member_id 
                           WHERE cc.id = '$release_id'";
            $details_result = $conn->query($details_sql);
            
            if ($details_result && $details_row = $details_result->fetch_assoc()) {
                $member_name = $details_row['first_name'] . ' ' . $details_row['last_name'];
                $calling_name = $details_row['calling_name'];
                $calling_member_id = $details_row['member_id'];
                $calling_calling_id = $details_row['calling_id'];
                $date_set_apart = $details_row['date_set_apart'];
                
                // Update the record to set release date
                $update_sql = "UPDATE current_callings SET date_released = '$change_date' WHERE id = '$release_id'";
                if ($conn->query($update_sql)) {
                    // Add to calling history
                    addToCallingHistory($conn, $calling_member_id, $calling_calling_id, $date_set_apart, $change_date);
                    
                    $changes_made[] = "$member_name released from $calling_name";
                } else {
                    throw new Exception("Failed to release $member_name from $calling_name");
                }
            }
        }
    }
    
    // Process calling releases (from calling-members table - same as current_callings)
    if (!empty($calling_releases)) {
        foreach ($calling_releases as $release_id) {
            $release_id = $conn->real_escape_string($release_id);
            
            // Get member details for logging
            $details_sql = "SELECT cc.member_id, cc.calling_id, cc.date_set_apart, c.calling_name, m.first_name, m.last_name 
                           FROM current_callings cc 
                           JOIN callings c ON cc.calling_id = c.calling_id 
                           JOIN members m ON cc.member_id = m.member_id 
                           WHERE cc.id = '$release_id'";
            $details_result = $conn->query($details_sql);
            
            if ($details_result && $details_row = $details_result->fetch_assoc()) {
                $member_name = $details_row['first_name'] . ' ' . $details_row['last_name'];
                $calling_name = $details_row['calling_name'];
                $calling_member_id = $details_row['member_id'];
                $calling_calling_id = $details_row['calling_id'];
                $date_set_apart = $details_row['date_set_apart'];
                
                // Update the record to set release date
                $update_sql = "UPDATE current_callings SET date_released = '$change_date' WHERE id = '$release_id'";
                if ($conn->query($update_sql)) {
                    // Add to calling history
                    addToCallingHistory($conn, $calling_member_id, $calling_calling_id, $date_set_apart, $change_date);
                    
                    $changes_made[] = "$member_name released from $calling_name";
                } else {
                    throw new Exception("Failed to release $member_name from $calling_name");
                }
            }
        }
    }
    
    // Process new assignment if both member and calling are selected
    if ($member_id && $calling_id) {
        $member_id = $conn->real_escape_string($member_id);
        $calling_id = $conn->real_escape_string($calling_id);
        
        // Get member and calling names for logging
        $member_sql = "SELECT first_name, last_name FROM members WHERE member_id = '$member_id'";
        $member_result = $conn->query($member_sql);
        
        $calling_sql = "SELECT calling_name FROM callings WHERE calling_id = '$calling_id'";
        $calling_result = $conn->query($calling_sql);
        
        if ($member_result && $calling_result) {
            $member_row = $member_result->fetch_assoc();
            $calling_row = $calling_result->fetch_assoc();
            
            $member_name = $member_row['first_name'] . ' ' . $member_row['last_name'];
            $calling_name = $calling_row['calling_name'];
            
            // Insert new calling assignment
            $insert_sql = "INSERT INTO current_callings (member_id, calling_id, date_set_apart) 
                          VALUES ('$member_id', '$calling_id', '$change_date')";
            
            if ($conn->query($insert_sql)) {
                $changes_made[] = "$member_name assigned to $calling_name";
            } else {
                throw new Exception("Failed to assign $member_name to $calling_name");
            }
        }
    }
    
    // Handle possible_callings updates if new assignment was made
    if ($update_possible_callings && $member_id && $calling_id) {
        // Update the assigned member's status to 'assigned'
        $update_assigned_sql = "UPDATE possible_callings 
                               SET status = 'assigned' 
                               WHERE member_id = '$member_id' AND calling_id = '$calling_id' AND status = 'considered'";
        
        if ($conn->query($update_assigned_sql)) {
            $affected_rows = $conn->affected_rows;
            if ($affected_rows > 0) {
                $changes_made[] = "Updated possible calling status to assigned";
            }
        }
        
        // If checkbox was checked, dismiss other candidates
        if ($remove_other_candidates) {
            $dismiss_others_sql = "UPDATE possible_callings 
                                  SET status = 'dismissed' 
                                  WHERE calling_id = '$calling_id' AND member_id != '$member_id' AND status = 'considered'";
            
            if ($conn->query($dismiss_others_sql)) {
                $dismissed_count = $conn->affected_rows;
                if ($dismissed_count > 0) {
                    $changes_made[] = "Dismissed $dismissed_count other candidate(s) from consideration";
                }
            } else {
                throw new Exception("Failed to dismiss other candidates");
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    if (empty($changes_made)) {
        echo "No changes were made.";
    } else {
        echo "Changes completed successfully: " . implode(", ", $changes_made) . ".";
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "Error making changes: " . $e->getMessage();
}

$conn->close();
?>