<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';
require_once __DIR__ . '/InputValidator.php';

// Set JSON response header
header('Content-Type: application/json');

// Log this high-risk access for auditing
logUserActivity('add_new_calling', ['risk_level' => 'high', 'file' => 'add_new_calling.php']);

require_once 'db_connect.php';

try {
    // Validate and sanitize input data
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
    
    // Define sanitization rules
    $sanitizeRules = [
        'calling_name' => 'alphanumeric',
        'organization' => 'string',
        'grouping' => 'string',
        'priority' => 'integer'
    ];
    
    // Validate and sanitize
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
            'details' => $validation['errors']
        ]);
        
        // Log validation failure
        logUserActivity('add_new_calling_validation_failed', [
            'errors' => $validation['errors'],
            'input_data_keys' => array_keys($inputData)
        ]);
        
        exit;
    }
    
    // Use sanitized data
    $cleanData = $validation['data'];
    
    // Additional business logic validation
    $errors = [];
    
    // Check if calling with same name already exists
    $checkSql = "SELECT COUNT(*) as count FROM callings WHERE calling_name = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $cleanData['calling_name']);
    $checkStmt->execute();
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
        
        logUserActivity('add_new_calling_business_validation_failed', [
            'errors' => $errors,
            'data' => $cleanData
        ]);
        
        exit;
    }
    
    // Insert new calling with validated and sanitized data
    $sql = "INSERT INTO callings (calling_name, organization, grouping, priority) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
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
            'calling_id' => $newCallingId
        ]);
        
        // Log successful calling creation
        logUserActivity('add_new_calling_success', [
            'calling_id' => $newCallingId,
            'calling_name' => $cleanData['calling_name'],
            'organization' => $cleanData['organization']
        ]);
        
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error occurred',
            'message' => 'Failed to add calling'
        ]);
        
        // Log database error (but don't expose it to client)
        error_log('Database error in add_new_calling.php: ' . $stmt->error);
        logUserActivity('add_new_calling_db_error', ['error' => $stmt->error]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => 'An unexpected error occurred'
    ]);
    
    // Log the exception
    error_log('Exception in add_new_calling.php: ' . $e->getMessage());
    logUserActivity('add_new_calling_exception', ['error' => $e->getMessage()]);
    
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
