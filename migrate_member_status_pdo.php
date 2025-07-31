<?php
/**
 * Database Migration: Add Member Status System (PDO Version)
 * Run this script once to add status functionality to members table
 */

// Load configuration
require_once __DIR__ . '/config.php';

echo "Member Status Migration (PDO Version)\n";
echo "=====================================\n\n";

try {
    // Create PDO connection
    $host = Config::get('DB_HOST', 'localhost');
    $dbname = Config::get('DB_NAME');
    $username = Config::get('DB_USERNAME');
    $password = Config::get('DB_PASSWORD');
    
    if (!$host || !$dbname || !$username || !$password) {
        throw new Exception("Missing required database configuration. Please check your .env file.");
    }
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Start transaction
    $pdo->beginTransaction();
    
    echo "1. Checking if status column already exists...\n";
    
    // Check if status column already exists
    $checkStmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'status'");
    if ($checkStmt->rowCount() > 0) {
        echo "   ✓ Status column already exists. Skipping migration.\n";
        $pdo->rollback();
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
    
    if (!$pdo->exec($statusSql)) {
        throw new Exception("Failed to add status column");
    }
    echo "   ✓ Status column added successfully\n";
    
    echo "3. Adding status index for performance...\n";
    
    // Add index
    $indexSql = "ALTER TABLE members ADD INDEX idx_member_status (status)";
    if (!$pdo->exec($indexSql)) {
        throw new Exception("Failed to add status index");
    }
    echo "   ✓ Status index added successfully\n";
    
    echo "4. Adding status_updated timestamp...\n";
    
    // Add status_updated column
    $timestampSql = "ALTER TABLE members 
                     ADD COLUMN status_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    if (!$pdo->exec($timestampSql)) {
        throw new Exception("Failed to add status_updated column");
    }
    echo "   ✓ Status timestamp column added successfully\n";
    
    echo "5. Adding status_notes field...\n";
    
    // Add status_notes column
    $notesSql = "ALTER TABLE members ADD COLUMN status_notes TEXT NULL";
    if (!$pdo->exec($notesSql)) {
        throw new Exception("Failed to add status_notes column");
    }
    echo "   ✓ Status notes column added successfully\n";
    
    echo "6. Setting default status for existing members...\n";
    
    // Update existing members to have 'active' status
    $updateStmt = $pdo->prepare("UPDATE members SET status = 'active' WHERE status IS NULL OR status = ''");
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update existing member statuses");
    }
    
    $affectedRows = $updateStmt->rowCount();
    echo "   ✓ Updated $affectedRows existing members to 'active' status\n";
    
    // Commit transaction
    $pdo->commit();
    
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
    $result = $pdo->query("DESCRIBE members");
    while ($row = $result->fetch()) {
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
    if (isset($pdo)) {
        $pdo->rollback();
    }
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Database changes have been rolled back.\n";
    exit(1);
}

echo "\nNext Steps:\n";
echo "1. Update your application code to use the new status field\n";
echo "2. Add status selection to member forms\n";
echo "3. Update member lists to show status indicators\n";
echo "4. Consider filtering inactive/moved members from calling assignments\n";
?>