<?php
// Script to create the calling_history table
require_once 'db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS calling_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    calling_id INT NOT NULL,
    approximate_period VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_by_user VARCHAR(100) DEFAULT NULL,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (calling_id) REFERENCES callings(calling_id) ON DELETE CASCADE,
    INDEX idx_member_id (member_id),
    INDEX idx_calling_id (calling_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table calling_history created successfully or already exists\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
?>