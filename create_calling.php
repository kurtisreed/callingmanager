<?php
header('Content-Type: application/json');

// Start session and check authentication
session_start();
require_once 'db_connect.php';

// Simple authentication check
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get input data
    $calling_name = trim($_POST['calling_name'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $grouping = trim($_POST['grouping'] ?? '');
    $priority = $_POST['priority'] ?? '';

    // Simple validation
    if (empty($calling_name)) {
        echo json_encode(['success' => false, 'error' => 'Calling name is required']);
        exit;
    }

    if (empty($organization)) {
        echo json_encode(['success' => false, 'error' => 'Organization is required']);
        exit;
    }

    if (empty($grouping)) {
        echo json_encode(['success' => false, 'error' => 'Grouping is required']);
        exit;
    }

    if ($priority === '') {
        echo json_encode(['success' => false, 'error' => 'Priority is required']);
        exit;
    }

    // Validate priority is a number
    $priority_num = (int)$priority;
    if ($priority_num < 0 || $priority_num > 999) {
        echo json_encode(['success' => false, 'error' => 'Priority must be between 0 and 999']);
        exit;
    }

    // Check if calling name already exists
    $check_sql = "SELECT COUNT(*) as count FROM callings WHERE calling_name = ?";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $check_stmt->bind_param("s", $calling_name);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'A calling with this name already exists']);
        exit;
    }

    // Insert new calling (include comments field with empty value)
    $insert_sql = "INSERT INTO callings (calling_name, organization, `grouping`, priority, comments) VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    if (!$insert_stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $comments = ''; // Empty comments field
    $insert_stmt->bind_param("sssis", $calling_name, $organization, $grouping, $priority_num, $comments);
    
    if ($insert_stmt->execute()) {
        $calling_id = $conn->insert_id;
        echo json_encode([
            'success' => true, 
            'message' => 'Calling created successfully',
            'calling_id' => $calling_id
        ]);
    } else {
        throw new Exception('Failed to insert calling: ' . $insert_stmt->error);
    }

    $insert_stmt->close();
    $check_stmt->close();

} catch (Exception $e) {
    error_log('Error in create_calling.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>