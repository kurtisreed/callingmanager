<?php
/**
 * Database Migration: Add Member Status System
 * Run this script once to add status functionality to members table
 */

// Load configuration and database
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connect.php';

echo "Member Status Migration\n";
echo "======================\n\n";

try {
    // Start transaction
    $conn->begin_transaction();
    
    echo "1. Checking if status column already exists...\n";
    
    // Check if status column already exists
    $checkResult = $conn->query("SHOW COLUMNS FROM members LIKE 'status'");
    if ($checkResult->num_rows > 0) {
        echo "   ✓ Status column already exists. Skipping migration.\n";
        $conn->rollback();
        exit;
    }
    
    echo "2. Adding status column to members table...\n";
    
    // Add status column
    $statusSql = "ALTER TABLE members 
                  ADD COLUMN status ENUM(
                      'active',
                      'inactive', 
                      'moved',
                      'no_calling',
                      'deceased',
                      'unknown'
                  ) NOT NULL DEFAULT 'active'";
    
    if (!$conn->query($statusSql)) {
        throw new Exception("Failed to add status column: " . $conn->error);
    }
    echo "   ✓ Status column added successfully\n";
    
    echo "3. Adding status index for performance...\n";
    
    // Add index
    $indexSql = "ALTER TABLE members ADD INDEX idx_member_status (status)";
    if (!$conn->query($indexSql)) {
        throw new Exception("Failed to add status index: " . $conn->error);
    }
    echo "   ✓ Status index added successfully\n";
    
    echo "4. Adding status_updated timestamp...\n";
    
    // Add status_updated column
    $timestampSql = "ALTER TABLE members 
                     ADD COLUMN status_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    if (!$conn->query($timestampSql)) {
        throw new Exception("Failed to add status_updated column: " . $conn->error);
    }
    echo "   ✓ Status timestamp column added successfully\n";
    
    echo "5. Adding status_notes field...\n";
    
    // Add status_notes column
    $notesSql = "ALTER TABLE members ADD COLUMN status_notes TEXT NULL";
    if (!$conn->query($notesSql)) {
        throw new Exception("Failed to add status_notes column: " . $conn->error);
    }
    echo "   ✓ Status notes column added successfully\n";
    
    echo "6. Setting default status for existing members...\n";
    
    // Update existing members to have 'active' status
    $updateSql = "UPDATE members SET status = 'active' WHERE status IS NULL OR status = ''";
    if (!$conn->query($updateSql)) {
        throw new Exception("Failed to update existing member statuses: " . $conn->error);
    }
    
    $affectedRows = $conn->affected_rows;
    echo "   ✓ Updated $affectedRows existing members to 'active' status\n";
    
    // Commit transaction
    $conn->commit();
    
    echo "\n✅ Member status migration completed successfully!\n\n";
    
    echo "New Status Options:\n";
    echo "- active (default): Current active ward member\n";
    echo "- inactive: Member but not currently participating\n";
    echo "- moved: Moved away from ward boundaries\n";
    echo "- no_calling: Active member currently without a calling\n";
    echo "- deceased: Passed away (for historical records)\n";
    echo "- unknown: Status needs to be determined\n\n";
    
    // Show updated table structure
    echo "Updated Members Table Structure:\n";
    echo "================================\n";
    $result = $conn->query("DESCRIBE members");
    while ($row = $result->fetch_assoc()) {
        printf("%-20s %-20s %-10s %-10s %-20s %-10s\n", 
               $row['Field'], 
               $row['Type'], 
               $row['Null'], 
               $row['Key'], 
               $row['Default'], 
               $row['Extra']);
    }
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Database changes have been rolled back.\n";
    exit(1);
    
} finally {
    $conn->close();
}

echo "\nNext Steps:\n";
echo "1. Update your application code to use the new status field\n";
echo "2. Add status selection to member forms\n";
echo "3. Update member lists to show status indicators\n";
echo "4. Consider filtering inactive/moved members from calling assignments\n";
?>