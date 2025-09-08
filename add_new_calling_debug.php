<?php
// Debug version of add_new_calling.php with extensive error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set JSON response header
header('Content-Type: application/json');

try {
    // Step 1: Check file includes
    if (!file_exists(__DIR__ . '/auth_required.php')) {
        throw new Exception('auth_required.php file not found');
    }
    require_once __DIR__ . '/auth_required.php';
    
    if (!file_exists(__DIR__ . '/InputValidator.php')) {
        throw new Exception('InputValidator.php file not found');
    }
    require_once __DIR__ . '/InputValidator.php';
    
    if (!file_exists(__DIR__ . '/db_connect.php')) {
        throw new Exception('db_connect.php file not found');
    }
    require_once 'db_connect.php';
    
    // Step 2: Log this access
    if (function_exists('logUserActivity')) {
        logUserActivity('add_new_calling_debug', ['risk_level' => 'high', 'file' => 'add_new_calling_debug.php']);
    }
    
    // Step 3: Validate input data
    $inputData = [
        'calling_name' => $_POST['calling_name'] ?? '',
        'organization' => $_POST['organization'] ?? '',
        'grouping' => $_POST['grouping'] ?? '',
        'priority' => $_POST['priority'] ?? ''
    ];
    
    // Convert priority to integer for validation if it's a numeric string
    if (isset($inputData['priority']) && is_numeric($inputData['priority'])) {
        $inputData['priority'] = (int)$inputData['priority'];
    }
    
    // Step 4: Define sanitization rules
    $sanitizeRules = [
        'calling_name' => 'alphanumeric',
        'organization' => 'string',
        'grouping' => 'string',
        'priority' => 'integer'
    ];
    
    // Step 5: Check if classes exist
    if (!class_exists('InputValidator')) {
        throw new Exception('InputValidator class not found');
    }
    if (!class_exists('ValidationRules')) {
        throw new Exception('ValidationRules class not found');
    }
    
    // Step 6: Validate and sanitize
    $validation = InputValidator::validateAndSanitize(
        $inputData, 
        ValidationRules::calling(), 
        $sanitizeRules
    );
    
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Validation failed',
            'details' => $validation['errors'],
            'debug_info' => [
                'input_data' => $inputData,
                'validation_result' => $validation
            ]
        ]);
        exit;
    }
    
    // Use sanitized data
    $cleanData = $validation['data'];
    
    // Step 7: Additional business logic validation
    $errors = [];
    
    // Check if calling with same name already exists
    $checkSql = "SELECT COUNT(*) as count FROM callings WHERE calling_name = ?";
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $checkStmt->bind_param("s", $cleanData['calling_name']);
    if (!$checkStmt->execute()) {
        throw new Exception('Database execute failed: ' . $checkStmt->error);
    }
    
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $errors[] = "A calling with this name already exists";
    }
    
    // Validate priority is within reasonable range
    if ($cleanData['priority'] < 0 || $cleanData['priority'] > 999) {
        $errors[] = "Priority must be between 0 and 999";
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Business validation failed',
            'details' => $errors
        ]);
        exit;
    }
    
    // Step 8: Insert new calling
    $sql = "INSERT INTO callings (calling_name, organization, `grouping`, priority) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed for insert: ' . $conn->error);
    }
    
    $stmt->bind_param("sssi", 
        $cleanData['calling_name'], 
        $cleanData['organization'], 
        $cleanData['grouping'], 
        $cleanData['priority']
    );
    
    if ($stmt->execute()) {
        $newCallingId = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Calling added successfully',
            'calling_id' => $newCallingId,
            'debug_info' => [
                'clean_data' => $cleanData,
                'server_php_version' => phpversion(),
                'sql_used' => $sql
            ]
        ]);
        
        // Log successful calling creation
        if (function_exists('logUserActivity')) {
            logUserActivity('add_new_calling_debug_success', [
                'calling_id' => $newCallingId,
                'calling_name' => $cleanData['calling_name'],
                'organization' => $cleanData['organization']
            ]);
        }
        
    } else {
        throw new Exception('Database insert failed: ' . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'debug_info' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'php_version' => phpversion(),
            'post_data' => $_POST
        ]
    ]);
    
    // Also log to PHP error log
    error_log('Exception in add_new_calling_debug.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>