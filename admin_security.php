<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log admin access
logUserActivity('admin_admin_security', ['risk_level' => 'admin', 'file' => 'admin_security.php']);

/**
 * Security Administration Panel
 * Monitor authentication attempts and rate limiting
 */

// Start session and check authentication
session_start();

// Load dependencies
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/RateLimiter.php';

// Simple authentication check (in production, implement proper admin authentication)
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    die('Unauthorized access. Please log in first.');
}

// Initialize rate limiter
try {
    $max_attempts = (int)Config::get('RATE_LIMIT_MAX_ATTEMPTS', 5);
    $window_minutes = (int)Config::get('RATE_LIMIT_WINDOW_MINUTES', 15);
    $lockout_minutes = (int)Config::get('RATE_LIMIT_LOCKOUT_MINUTES', 30);
    
    $rateLimiter = new RateLimiter($conn, $max_attempts, $window_minutes, $lockout_minutes);
} catch (Exception $e) {
    die('System error: ' . htmlspecialchars($e->getMessage()));
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'stats':
            $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
            echo json_encode($rateLimiter->getStats($hours));
            break;
            
        case 'blocked_ips':
            echo json_encode($rateLimiter->getBlockedIPs());
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Administration - Calling Manager</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background-color: #f5f5f5; 
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        }
        h1, h2 { 
            color: #333; 
            border-bottom: 2px solid #4CAF50; 
            padding-bottom: 10px; 
        }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin: 20px 0; 
        }
        .stat-card { 
            background: #f9f9f9; 
            padding: 20px; 
            border-radius: 8px; 
            text-align: center; 
            border-left: 4px solid #4CAF50; 
        }
        .stat-number { 
            font-size: 2em; 
            font-weight: bold; 
            color: #4CAF50; 
        }
        .stat-label { 
            color: #666; 
            margin-top: 5px; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        th { 
            background-color: #4CAF50; 
            color: white; 
        }
        .danger { 
            color: #d32f2f; 
            font-weight: bold; 
        }
        .warning { 
            color: #ef6c00; 
            font-weight: bold; 
        }
        .success { 
            color: #2e7d32; 
            font-weight: bold; 
        }
        .back-link { 
            display: inline-block; 
            background: #333; 
            color: white; 
            padding: 10px 20px; 
            text-decoration: none; 
            border-radius: 4px; 
            margin-bottom: 20px; 
        }
        .back-link:hover { 
            background: #555; 
        }
        .refresh-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 10px 5px;
        }
        .refresh-btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.html" class="back-link">← Back to Application</a>
        
        <h1>Security Administration Panel</h1>
        
        <div class="stats-section">
            <h2>Authentication Statistics (Last 24 Hours)</h2>
            <button class="refresh-btn" onclick="refreshStats()">Refresh Stats</button>
            <button class="refresh-btn" onclick="refreshStats(1)">Last Hour</button>
            <button class="refresh-btn" onclick="refreshStats(168)">Last Week</button>
            
            <div class="stats-grid" id="stats-grid">
                <div class="stat-card">
                    <div class="stat-number" id="total-attempts">-</div>
                    <div class="stat-label">Total Attempts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number success" id="successful-attempts">-</div>
                    <div class="stat-label">Successful Logins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number danger" id="failed-attempts">-</div>
                    <div class="stat-label">Failed Attempts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="unique-ips">-</div>
                    <div class="stat-label">Unique IP Addresses</div>
                </div>
            </div>
        </div>
        
        <div class="blocked-section">
            <h2>Currently Blocked IP Addresses</h2>
            <button class="refresh-btn" onclick="refreshBlocked()">Refresh Blocked IPs</button>
            
            <table id="blocked-table">
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Failed Attempts</th>
                        <th>First Attempt</th>
                        <th>Last Attempt</th>
                        <th>Lockout Expires</th>
                        <th>Time Remaining</th>
                    </tr>
                </thead>
                <tbody id="blocked-tbody">
                    <tr>
                        <td colspan="6" style="text-align: center; color: #666;">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="config-section">
            <h2>Current Rate Limiting Configuration</h2>
            <ul>
                <li><strong>Max Attempts:</strong> <?php echo htmlspecialchars($max_attempts); ?></li>
                <li><strong>Time Window:</strong> <?php echo htmlspecialchars($window_minutes); ?> minutes</li>
                <li><strong>Lockout Duration:</strong> <?php echo htmlspecialchars($lockout_minutes); ?> minutes</li>
            </ul>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        let autoRefreshInterval;
        
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(() => {
                refreshStats();
                refreshBlocked();
            }, 30000);
        }
        
        function refreshStats(hours = 24) {
            fetch(`?action=stats&hours=${hours}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('total-attempts').textContent = data.total_attempts || 0;
                    document.getElementById('successful-attempts').textContent = data.successful_attempts || 0;
                    document.getElementById('failed-attempts').textContent = data.failed_attempts || 0;
                    document.getElementById('unique-ips').textContent = data.unique_ips || 0;
                })
                .catch(error => console.error('Error loading stats:', error));
        }
        
        function refreshBlocked() {
            fetch('?action=blocked_ips')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('blocked-tbody');
                    
                    if (!data || data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #2e7d32;">No currently blocked IPs</td></tr>';
                        return;
                    }
                    
                    tbody.innerHTML = data.map(ip => `
                        <tr>
                            <td class="danger">${ip.ip_address}</td>
                            <td>${ip.failed_count}</td>
                            <td>${ip.first_attempt}</td>
                            <td>${ip.last_attempt}</td>
                            <td>${ip.lockout_expires}</td>
                            <td>${Math.ceil(ip.remaining_seconds / 60)} minutes</td>
                        </tr>
                    `).join('');
                })
                .catch(error => console.error('Error loading blocked IPs:', error));
        }
        
        // Initial load
        document.addEventListener('DOMContentLoaded', function() {
            refreshStats();
            refreshBlocked();
            startAutoRefresh();
        });
        
        // Stop auto-refresh when page is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(autoRefreshInterval);
            } else {
                startAutoRefresh();
            }
        });
    </script>
</body>
</html>