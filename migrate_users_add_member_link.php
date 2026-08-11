<?php
/**
 * Database Migration: Link User Accounts to Member Records
 * Run this script once to add a member_id link to the users table for
 * installs where `users` was already created (e.g. via setup_first_user.php
 * or create_user_cli.php before this migration existed).
 *
 * Fresh installs don't need this — create_user_cli.php and
 * setup_first_user.php already create the users table with member_id
 * included from the start.
 *
 * CLI-ONLY: run with `php migrate_users_add_member_link.php`.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This migration script can only be run from the command line.');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connect.php';

echo "User-to-Member Link Migration\n";
echo "==============================\n\n";

try {
    echo "1. Checking if member_id column already exists...\n";
    $checkResult = $conn->query("SHOW COLUMNS FROM users LIKE 'member_id'");
    if ($checkResult && $checkResult->num_rows > 0) {
        echo "   Already present. Nothing to do.\n";
        exit;
    }

    echo "2. Adding member_id column...\n";
    if (!$conn->query("ALTER TABLE users ADD COLUMN member_id INT NULL AFTER user_id")) {
        throw new Exception("Failed to add member_id column: " . $conn->error);
    }
    echo "   Column added.\n";

    echo "3. Adding unique index (one account per member)...\n";
    if (!$conn->query("ALTER TABLE users ADD UNIQUE KEY uniq_users_member_id (member_id)")) {
        throw new Exception("Failed to add unique index: " . $conn->error);
    }
    echo "   Index added.\n";

    echo "4. Adding foreign key to members (unlinks automatically if a member record is ever deleted)...\n";
    if (!$conn->query("ALTER TABLE users ADD CONSTRAINT fk_users_member_id FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE SET NULL")) {
        throw new Exception("Failed to add foreign key: " . $conn->error);
    }
    echo "   Foreign key added.\n";

    echo "\nMigration completed successfully.\n";
    echo "Existing accounts are left with member_id = NULL (unlinked). New\n";
    echo "accounts created from now on will require a linked member record.\n";

} catch (Exception $e) {
    echo "\nMigration failed: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    $conn->close();
}
?>
