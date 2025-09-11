<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start session and check authentication
session_start();

try {
    // Check if db_connect.php exists
    if (!file_exists('db_connect.php')) {
        echo json_encode(['success' => false, 'error' => 'db_connect.php not found', 'debug' => true]);
        exit;
    }
    
    require_once 'db_connect.php';
    
    // Check if connection exists
    if (!isset($conn)) {
        echo json_encode(['success' => false, 'error' => 'Database connection not established', 'debug' => true]);
        exit;
    }
    
    // Simple authentication check
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        echo json_encode(['success' => false, 'error' => 'Authentication failed', 'session_data' => $_SESSION, 'debug' => true]);
        exit;
    }
    
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Method not allowed: ' . $_SERVER['REQUEST_METHOD'], 'debug' => true]);
        exit;
    }
    
    // Debug POST data
    $post_data = $_POST;
    
    // Get input data
    $calling_name = trim($_POST['calling_name'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $grouping = trim($_POST['grouping'] ?? '');
    $priority = $_POST['priority'] ?? '';
    
    // Simple validation
    if (empty($calling_name)) {
        echo json_encode(['success' => false, 'error' => 'Calling name is required', 'post_data' => $post_data, 'debug' => true]);
        exit;
    }
    
    if (empty($organization)) {
        echo json_encode(['success' => false, 'error' => 'Organization is required', 'post_data' => $post_data, 'debug' => true]);
        exit;
    }
    
    if (empty($grouping)) {
        echo json_encode(['success' => false, 'error' => 'Grouping is required', 'post_data' => $post_data, 'debug' => true]);
        exit;
    }
    
    if ($priority === '') {
        echo json_encode(['success' => false, 'error' => 'Priority is required', 'post_data' => $post_data, 'debug' => true]);
        exit;
    }
    
    // Validate priority is a number
    $priority_num = (int)$priority;
    if ($priority_num < 0 || $priority_num > 999) {
        echo json_encode(['success' => false, 'error' => 'Priority must be between 0 and 999', 'priority_received' => $priority, 'priority_num' => $priority_num, 'debug' => true]);
        exit;
    }
    
    // Test database connection
    $test_query = "SELECT 1 as test";
    $test_result = $conn->query($test_query);
    if (!$test_result) {
        echo json_encode(['success' => false, 'error' => 'Database test query failed: ' . $conn->error, 'debug' => true]);
        exit;
    }
    
    // Check if calling name already exists
    $check_sql = "SELECT COUNT(*) as count FROM callings WHERE calling_name = ?";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        echo json_encode(['success' => false, 'error' => 'Database prepare failed: ' . $conn->error, 'debug' => true]);
        exit;
    }
    
    $check_stmt->bind_param("s", $calling_name);
    if (!$check_stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Check query execute failed: ' . $check_stmt->error, 'debug' => true]);
        exit;
    }
    
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'A calling with this name already exists', 'debug' => true]);
        exit;
    }
    
    // Test the insert statement preparation (include comments field)
    $insert_sql = "INSERT INTO callings (calling_name, organization, `grouping`, priority, comments) VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    if (!$insert_stmt) {
        echo json_encode(['success' => false, 'error' => 'Insert prepare failed: ' . $conn->error, 'sql' => $insert_sql, 'debug' => true]);
        exit;
    }
    
    $comments = ''; // Empty comments field
    $bind_result = $insert_stmt->bind_param("sssis", $calling_name, $organization, $grouping, $priority_num, $comments);
    if (!$bind_result) {
        echo json_encode(['success' => false, 'error' => 'Bind param failed: ' . $insert_stmt->error, 'debug' => true]);
        exit;
    }
    
    if ($insert_stmt->execute()) {
        $calling_id = $conn->insert_id;
        echo json_encode([
            'success' => true, 
            'message' => 'Calling created successfully',
            'calling_id' => $calling_id,
            'data_inserted' => [
                'calling_name' => $calling_name,
                'organization' => $organization,
                'grouping' => $grouping,
                'priority' => $priority_num
            ],
            'debug' => true
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Execute failed: ' . $insert_stmt->error, 'debug' => true]);
    }
    
    $insert_stmt->close();
    $check_stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Exception: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'php_version' => phpversion(),
        'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'debug' => true
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'PHP Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'debug' => true
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>