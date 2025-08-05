<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('apply_member_changes', ['risk_level' => 'high', 'file' => 'apply_member_changes.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['reconciliation'])) {
        throw new Exception('Invalid input data');
    }
    
    $reconciliation = $input['reconciliation'];
    
    // Start transaction
    $conn->autocommit(false);
    
    $summary = [
        'new_members_added' => 0,
        'members_updated' => 0,
        'members_removed' => 0,
        'errors' => []
    ];
    
    try {
        // Process new members
        if (!empty($reconciliation['new_members'])) {
            foreach ($reconciliation['new_members'] as $newMember) {
                $result = addNewMember($conn, $newMember);
                if ($result['success']) {
                    $summary['new_members_added']++;
                } else {
                    $summary['errors'][] = "Failed to add {$newMember['first_name']} {$newMember['last_name']}: " . $result['error'];
                }
            }
        }
        
        // Process updates
        if (!empty($reconciliation['updates'])) {
            foreach ($reconciliation['updates'] as $update) {
                $result = updateMember($conn, $update);
                if ($result['success']) {
                    $summary['members_updated']++;
                } else {
                    $summary['errors'][] = "Failed to update {$update['current']['first_name']} {$update['current']['last_name']}: " . $result['error'];
                }
            }
        }
        
        // Process removed members (mark as moved away)
        if (!empty($reconciliation['removed'])) {
            foreach ($reconciliation['removed'] as $removedMember) {
                $result = markMemberAsRemoved($conn, $removedMember);
                if ($result['success']) {
                    $summary['members_removed']++;
                } else {
                    $summary['errors'][] = "Failed to mark {$removedMember['first_name']} {$removedMember['last_name']} as removed: " . $result['error'];
                }
            }
        }
        
        // Commit transaction if no critical errors
        if (empty($summary['errors']) || count($summary['errors']) < 3) {
            // Log to member_updates_log table before committing
            logMemberUpdate($conn, $summary);
            
            $conn->commit();
            
            // Log the changes to activity log
            logMemberReconciliation($summary);
            
            echo json_encode([
                'success' => true,
                'summary' => $summary,
                'message' => 'Member reconciliation completed successfully'
            ]);
        } else {
            throw new Exception('Too many errors occurred during reconciliation');
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

function addNewMember($conn, $member) {
    try {
        $sql = "INSERT INTO members (first_name, last_name, birthdate, status, status_notes) 
                VALUES (?, ?, ?, 'active', 'Added via PDF reconciliation')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", 
            $member['first_name'], 
            $member['last_name'], 
            $member['birthdate']
        );
        
        if ($stmt->execute()) {
            return ['success' => true, 'member_id' => $conn->insert_id];
        } else {
            return ['success' => false, 'error' => $stmt->error];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateMember($conn, $update) {
    try {
        $memberId = $update['member_id'];
        $changes = $update['changes'];
        
        $setParts = [];
        $params = [];
        $types = '';
        
        foreach ($changes as $field => $change) {
            $setParts[] = "$field = ?";
            $params[] = $change['new'];
            $types .= 's'; // Assuming all are strings for simplicity
        }
        
        // Add update timestamp and note
        $setParts[] = "status_notes = CONCAT(COALESCE(status_notes, ''), '\nUpdated via PDF reconciliation on ', NOW())";
        
        $sql = "UPDATE members SET " . implode(', ', $setParts) . " WHERE member_id = ?";
        $params[] = $memberId;
        $types .= 'i';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            return ['success' => true, 'affected_rows' => $stmt->affected_rows];
        } else {
            return ['success' => false, 'error' => $stmt->error];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function markMemberAsRemoved($conn, $member) {
    try {
        $sql = "UPDATE members 
                SET status = 'moved', 
                    status_notes = CONCAT(COALESCE(status_notes, ''), '\nMarked as moved away via PDF reconciliation on ', NOW())
                WHERE member_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $member['member_id']);
        
        if ($stmt->execute()) {
            return ['success' => true, 'affected_rows' => $stmt->affected_rows];
        } else {
            return ['success' => false, 'error' => $stmt->error];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function logMemberReconciliation($summary) {
    $logMessage = sprintf(
        "Member reconciliation completed: %d new members added, %d members updated, %d members marked as removed",
        $summary['new_members_added'],
        $summary['members_updated'],
        $summary['members_removed']
    );
    
    if (!empty($summary['errors'])) {
        $logMessage .= ". Errors: " . implode('; ', $summary['errors']);
    }
    
    logUserActivity('member_reconciliation_completed', [
        'risk_level' => 'medium',
        'summary' => $summary,
        'message' => $logMessage
    ]);
}

function logMemberUpdate($conn, $summary) {
    try {
        // Create table if it doesn't exist
        $createTableSql = "CREATE TABLE IF NOT EXISTS member_updates_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            update_date DATETIME NOT NULL,
            new_members_added INT DEFAULT 0,
            members_updated INT DEFAULT 0,
            members_removed INT DEFAULT 0,
            total_changes INT DEFAULT 0,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $conn->query($createTableSql);
        
        // Calculate total changes
        $totalChanges = $summary['new_members_added'] + $summary['members_updated'] + $summary['members_removed'];
        
        // Create notes from errors if any
        $notes = '';
        if (!empty($summary['errors'])) {
            $notes = 'Errors occurred: ' . implode('; ', $summary['errors']);
        }
        
        // Insert the update log
        $sql = "INSERT INTO member_updates_log (update_date, new_members_added, members_updated, members_removed, total_changes, notes) 
                VALUES (NOW(), ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiis", 
            $summary['new_members_added'],
            $summary['members_updated'], 
            $summary['members_removed'],
            $totalChanges,
            $notes
        );
        
        $stmt->execute();
        
    } catch (Exception $e) {
        // Don't throw error for logging failure, just log it
        error_log("Failed to log member update: " . $e->getMessage());
    }
}

$conn->close();
?>