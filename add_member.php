<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';
require_once __DIR__ . '/InputValidator.php';

// Set JSON response header
header('Content-Type: application/json');

// Log this high-risk access for auditing
logUserActivity('add_member', ['risk_level' => 'high', 'file' => 'add_member.php']);

require_once 'db_connect.php';

try {
    // Validate and sanitize input data
    $inputData = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'birthdate' => $_POST['birthdate'] ?? ''
    ];
    
    // Define sanitization rules
    $sanitizeRules = [
        'first_name' => 'alpha',
        'last_name' => 'alpha',
        'gender' => 'string',
        'birthdate' => 'date'
    ];
    
    // Validate and sanitize
    $validation = InputValidator::validateAndSanitize(
        $inputData, 
        ValidationRules::member(), 
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
        logUserActivity('add_member_validation_failed', [
            'errors' => $validation['errors'],
            'input_data_keys' => array_keys($inputData)
        ]);
        
        exit;
    }
    
    // Use sanitized data
    $cleanData = $validation['data'];
    
    // Additional business logic validation
    $errors = [];
    
    // Check if member already exists (same name and birthdate)
    $checkSql = "SELECT COUNT(*) as count FROM members WHERE first_name = ? AND last_name = ? AND birthdate = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("sss", $cleanData['first_name'], $cleanData['last_name'], $cleanData['birthdate']);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $errors[] = "A member with this name and birthdate already exists";
    }
    
    // Validate age is reasonable (must be born after 1900 and not in future)
    $birthDate = new DateTime($cleanData['birthdate']);
    $today = new DateTime();
    $minDate = new DateTime('1900-01-01');
    
    if ($birthDate > $today) {
        $errors[] = "Birthdate cannot be in the future";
    }
    
    if ($birthDate < $minDate) {
        $errors[] = "Birthdate must be after 1900";
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Business validation failed',
            'details' => $errors
        ]);
        
        logUserActivity('add_member_business_validation_failed', [
            'errors' => $errors,
            'data' => $cleanData
        ]);
        
        exit;
    }
    
    // Insert new member with validated and sanitized data
    $sql = "INSERT INTO members (first_name, last_name, gender, birthdate) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", 
        $cleanData['first_name'], 
        $cleanData['last_name'], 
        $cleanData['gender'], 
        $cleanData['birthdate']
    );
    
    if ($stmt->execute()) {
        $newMemberId = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Member added successfully',
            'member_id' => $newMemberId
        ]);
        
        // Log successful member creation
        logUserActivity('add_member_success', [
            'member_id' => $newMemberId,
            'first_name' => $cleanData['first_name'],
            'last_name' => $cleanData['last_name']
        ]);
        
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error occurred',
            'message' => 'Failed to add member'
        ]);
        
        // Log database error (but don't expose it to client)
        error_log('Database error in add_member.php: ' . $stmt->error);
        logUserActivity('add_member_db_error', ['error' => $stmt->error]);
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
    error_log('Exception in add_member.php: ' . $e->getMessage());
    logUserActivity('add_member_exception', ['error' => $e->getMessage()]);
    
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
