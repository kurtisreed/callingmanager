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

try {
    // Start transaction
    $conn->begin_transaction();
    
    $changes_made = [];
    
    // Process member releases (from member-callings table)
    if (!empty($member_releases)) {
        foreach ($member_releases as $release_id) {
            $release_id = $conn->real_escape_string($release_id);
            
            // Get calling details for logging
            $details_sql = "SELECT c.calling_name, m.first_name, m.last_name 
                           FROM current_callings cc 
                           JOIN callings c ON cc.calling_id = c.calling_id 
                           JOIN members m ON cc.member_id = m.member_id 
                           WHERE cc.id = '$release_id'";
            $details_result = $conn->query($details_sql);
            
            if ($details_result && $details_row = $details_result->fetch_assoc()) {
                $member_name = $details_row['first_name'] . ' ' . $details_row['last_name'];
                $calling_name = $details_row['calling_name'];
                
                // Update the record to set release date
                $update_sql = "UPDATE current_callings SET date_released = '$change_date' WHERE id = '$release_id'";
                if ($conn->query($update_sql)) {
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
            $details_sql = "SELECT c.calling_name, m.first_name, m.last_name 
                           FROM current_callings cc 
                           JOIN callings c ON cc.calling_id = c.calling_id 
                           JOIN members m ON cc.member_id = m.member_id 
                           WHERE cc.id = '$release_id'";
            $details_result = $conn->query($details_sql);
            
            if ($details_result && $details_row = $details_result->fetch_assoc()) {
                $member_name = $details_row['first_name'] . ' ' . $details_row['last_name'];
                $calling_name = $details_row['calling_name'];
                
                // Update the record to set release date
                $update_sql = "UPDATE current_callings SET date_released = '$change_date' WHERE id = '$release_id'";
                if ($conn->query($update_sql)) {
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