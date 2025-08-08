<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('save_calling_history', ['risk_level' => 'medium', 'file' => 'save_calling_history.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['member_id']) || !isset($input['history_entries'])) {
        throw new Exception('Invalid input data');
    }
    
    $memberId = $input['member_id'];
    $historyEntries = $input['history_entries'];
    
    if (empty($historyEntries)) {
        throw new Exception('No history entries to save');
    }
    
    // Validate member exists
    $memberCheck = $conn->prepare("SELECT member_id FROM members WHERE member_id = ?");
    $memberCheck->bind_param("i", $memberId);
    $memberCheck->execute();
    if ($memberCheck->get_result()->num_rows === 0) {
        throw new Exception('Member not found');
    }
    
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
    
    // Start transaction
    $conn->autocommit(false);
    
    $savedCount = 0;
    $errors = [];
    
    try {
        // Prepare insert statement
        $stmt = $conn->prepare("INSERT INTO calling_history (member_id, calling_id, approximate_period, notes) VALUES (?, ?, ?, ?)");
        
        foreach ($historyEntries as $entry) {
            $callingId = $entry['callingId'];
            $period = !empty($entry['period']) ? $entry['period'] : null;
            $notes = !empty($entry['notes']) ? $entry['notes'] : null;
            
            // Validate calling exists
            $callingCheck = $conn->prepare("SELECT calling_id FROM callings WHERE calling_id = ?");
            $callingCheck->bind_param("i", $callingId);
            $callingCheck->execute();
            if ($callingCheck->get_result()->num_rows === 0) {
                $errors[] = "Calling ID {$callingId} not found";
                continue;
            }
            
            // Check for duplicate entry
            $duplicateCheck = $conn->prepare("SELECT id FROM calling_history WHERE member_id = ? AND calling_id = ?");
            $duplicateCheck->bind_param("ii", $memberId, $callingId);
            $duplicateCheck->execute();
            if ($duplicateCheck->get_result()->num_rows > 0) {
                $errors[] = "Duplicate entry for calling ID {$callingId}";
                continue;
            }
            
            // Insert the history entry
            $stmt->bind_param("iiss", $memberId, $callingId, $period, $notes);
            
            if ($stmt->execute()) {
                $savedCount++;
            } else {
                $errors[] = "Failed to save calling ID {$callingId}: " . $stmt->error;
            }
        }
        
        // Commit if we saved at least one entry
        if ($savedCount > 0) {
            $conn->commit();
            
            $message = "Successfully saved {$savedCount} calling history entries.";
            if (!empty($errors)) {
                $message .= " Errors: " . implode('; ', $errors);
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'saved_count' => $savedCount,
                'errors' => $errors
            ]);
        } else {
            throw new Exception('No entries could be saved. Errors: ' . implode('; ', $errors));
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
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>