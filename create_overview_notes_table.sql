-- Create table for storing calling overview notes
CREATE TABLE IF NOT EXISTS overview_notes (
    id INT PRIMARY KEY DEFAULT 1,
    callings_notes TEXT,
    people_notes TEXT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert initial empty row
INSERT INTO overview_notes (id, callings_notes, people_notes) VALUES (1, '', '')
ON DUPLICATE KEY UPDATE id=1;
