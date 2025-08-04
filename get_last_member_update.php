<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_last_member_update', ['risk_level' => 'low', 'file' => 'get_last_member_update.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Check if we have a member_updates_log table, if not create it
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
    
    // Get the most recent member update
    $sql = "SELECT update_date, new_members_added, members_updated, members_removed, total_changes, notes
            FROM member_updates_log 
            ORDER BY update_date DESC 
            LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $lastUpdate = $result->fetch_assoc();
        
        // Calculate time since last update
        $updateTime = new DateTime($lastUpdate['update_date']);
        $now = new DateTime();
        $interval = $now->diff($updateTime);
        
        // Format time difference
        if ($interval->days > 0) {
            $timeSince = $interval->days . ' day' . ($interval->days > 1 ? 's' : '') . ' ago';
        } elseif ($interval->h > 0) {
            $timeSince = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
        } elseif ($interval->i > 0) {
            $timeSince = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
        } else {
            $timeSince = 'Just now';
        }
        
        echo json_encode([
            'success' => true,
            'has_update' => true,
            'last_update' => [
                'date' => $lastUpdate['update_date'],
                'formatted_date' => $updateTime->format('M j, Y g:i A'),
                'time_since' => $timeSince,
                'new_members_added' => (int)$lastUpdate['new_members_added'],
                'members_updated' => (int)$lastUpdate['members_updated'],
                'members_removed' => (int)$lastUpdate['members_removed'],
                'total_changes' => (int)$lastUpdate['total_changes'],
                'notes' => $lastUpdate['notes'],
                'is_recent' => $interval->days <= 7 // Consider recent if within 7 days
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'has_update' => false,
            'message' => 'No member updates have been performed yet'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>