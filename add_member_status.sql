-- Add status column to members table
-- Run this SQL script on your database

ALTER TABLE members 
ADD COLUMN status ENUM(
    'active',
    'inactive', 
    'moved',
    'no_calling',
    'deceased',
    'unknown'
) NOT NULL DEFAULT 'active';

-- Add an index for better query performance
ALTER TABLE members 
ADD INDEX idx_member_status (status);

-- Add a status_updated timestamp to track when status was last changed
ALTER TABLE members 
ADD COLUMN status_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Optional: Add a status_notes field for additional context
ALTER TABLE members 
ADD COLUMN status_notes TEXT NULL;

-- Update any existing members without explicit status (they become 'active')
UPDATE members SET status = 'active' WHERE status IS NULL;

-- Show the updated table structure
DESCRIBE members;