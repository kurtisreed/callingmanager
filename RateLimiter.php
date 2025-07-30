<?php
/**
 * Rate Limiter Class
 * Prevents brute force attacks by limiting authentication attempts
 */

class RateLimiter {
    private $conn;
    private $max_attempts;
    private $window_minutes;
    private $lockout_minutes;
    
    /**
     * Initialize rate limiter
     * 
     * @param mysqli $connection Database connection
     * @param int $max_attempts Maximum attempts allowed in time window
     * @param int $window_minutes Time window in minutes for counting attempts
     * @param int $lockout_minutes How long to lock out after max attempts reached
     */
    public function __construct($connection, $max_attempts = 5, $window_minutes = 15, $lockout_minutes = 30) {
        $this->conn = $connection;
        $this->max_attempts = $max_attempts;
        $this->window_minutes = $window_minutes;
        $this->lockout_minutes = $lockout_minutes;
        
        $this->createTableIfNotExists();
    }
    
    /**
     * Create the login_attempts table if it doesn't exist
     */
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success BOOLEAN DEFAULT FALSE,
            INDEX idx_ip_time (ip_address, attempt_time),
            INDEX idx_attempt_time (attempt_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$this->conn->query($sql)) {
            error_log("Failed to create login_attempts table: " . $this->conn->error);
            throw new Exception("Database setup failed");
        }
    }
    
    /**
     * Check if IP address is currently rate limited
     * 
     * @param string $ip_address IP address to check
     * @return array ['blocked' => bool, 'remaining_lockout' => int, 'attempts_left' => int]
     */
    public function checkRateLimit($ip_address) {
        $this->cleanupOldAttempts();
        
        // Check for recent lockout (failed attempts within lockout period)
        $lockout_time = date('Y-m-d H:i:s', strtotime("-{$this->lockout_minutes} minutes"));
        
        $lockout_sql = "SELECT COUNT(*) as failed_count, MAX(attempt_time) as last_attempt 
                       FROM login_attempts 
                       WHERE ip_address = ? 
                       AND attempt_time > ? 
                       AND success = FALSE";
        
        $stmt = $this->conn->prepare($lockout_sql);
        $stmt->bind_param("ss", $ip_address, $lockout_time);
        $stmt->execute();
        $lockout_result = $stmt->get_result()->fetch_assoc();
        
        // If we have max failed attempts within lockout period, check if still locked
        if ($lockout_result['failed_count'] >= $this->max_attempts) {
            $last_attempt_time = strtotime($lockout_result['last_attempt']);
            $lockout_expires = $last_attempt_time + ($this->lockout_minutes * 60);
            $current_time = time();
            
            if ($current_time < $lockout_expires) {
                return [
                    'blocked' => true,
                    'remaining_lockout' => $lockout_expires - $current_time,
                    'attempts_left' => 0
                ];
            }
        }
        
        // Check attempts within the current window
        $window_time = date('Y-m-d H:i:s', strtotime("-{$this->window_minutes} minutes"));
        
        $window_sql = "SELECT COUNT(*) as attempt_count 
                      FROM login_attempts 
                      WHERE ip_address = ? 
                      AND attempt_time > ? 
                      AND success = FALSE";
        
        $stmt = $this->conn->prepare($window_sql);
        $stmt->bind_param("ss", $ip_address, $window_time);
        $stmt->execute();
        $window_result = $stmt->get_result()->fetch_assoc();
        
        $attempts_made = $window_result['attempt_count'];
        $attempts_left = max(0, $this->max_attempts - $attempts_made);
        
        return [
            'blocked' => $attempts_left <= 0,
            'remaining_lockout' => 0,
            'attempts_left' => $attempts_left
        ];
    }
    
    /**
     * Record a login attempt
     * 
     * @param string $ip_address IP address making the attempt
     * @param string $user_agent User agent string
     * @param bool $success Whether the login was successful
     * @return bool Success of recording the attempt
     */
    public function recordAttempt($ip_address, $user_agent, $success = false) {
        $sql = "INSERT INTO login_attempts (ip_address, user_agent, success) VALUES (?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssi", $ip_address, $user_agent, $success);
        
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("Failed to record login attempt: " . $this->conn->error);
        }
        
        return $result;
    }
    
    /**
     * Reset attempts for an IP (useful after successful login)
     * 
     * @param string $ip_address IP address to reset
     * @return bool Success of reset
     */
    public function resetAttempts($ip_address) {
        // We don't delete records (for auditing), but mark a successful login
        return $this->recordAttempt($ip_address, $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', true);
    }
    
    /**
     * Clean up old login attempts (older than lockout period)
     * This helps keep the table size manageable
     */
    private function cleanupOldAttempts() {
        // Keep records for 7 days for auditing purposes
        $cleanup_time = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        $sql = "DELETE FROM login_attempts WHERE attempt_time < ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $cleanup_time);
        $stmt->execute();
    }
    
    /**
     * Get statistics about login attempts for monitoring
     * 
     * @param int $hours How many hours back to analyze
     * @return array Statistics array
     */
    public function getStats($hours = 24) {
        $since_time = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $sql = "SELECT 
                    COUNT(*) as total_attempts,
                    SUM(CASE WHEN success = TRUE THEN 1 ELSE 0 END) as successful_attempts,
                    SUM(CASE WHEN success = FALSE THEN 1 ELSE 0 END) as failed_attempts,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM login_attempts 
                WHERE attempt_time > ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $since_time);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get list of currently blocked IPs
     * 
     * @return array List of blocked IP addresses with details
     */
    public function getBlockedIPs() {
        $lockout_time = date('Y-m-d H:i:s', strtotime("-{$this->lockout_minutes} minutes"));
        
        $sql = "SELECT 
                    ip_address, 
                    COUNT(*) as failed_count,
                    MAX(attempt_time) as last_attempt,
                    MIN(attempt_time) as first_attempt
                FROM login_attempts 
                WHERE attempt_time > ? 
                AND success = FALSE
                GROUP BY ip_address 
                HAVING failed_count >= ?
                ORDER BY last_attempt DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $lockout_time, $this->max_attempts);
        $stmt->execute();
        
        $blocked_ips = [];
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $last_attempt_time = strtotime($row['last_attempt']);
            $lockout_expires = $last_attempt_time + ($this->lockout_minutes * 60);
            
            // Only include if still within lockout period
            if (time() < $lockout_expires) {
                $row['lockout_expires'] = date('Y-m-d H:i:s', $lockout_expires);
                $row['remaining_seconds'] = $lockout_expires - time();
                $blocked_ips[] = $row;
            }
        }
        
        return $blocked_ips;
    }
}
?>