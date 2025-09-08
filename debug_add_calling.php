<?php
// Debug script for add_new_calling.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Debug Add New Calling ===\n";

// Test 1: Check if required files exist
echo "1. Checking required files:\n";
$required_files = [
    'auth_required.php',
    'InputValidator.php', 
    'db_connect.php'
];

foreach ($required_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✓ $file exists\n";
    } else {
        echo "✗ $file MISSING\n";
    }
}

// Test 2: Try to include each file
echo "\n2. Testing file includes:\n";
try {
    require_once __DIR__ . '/auth_required.php';
    echo "✓ auth_required.php loaded successfully\n";
} catch (Exception $e) {
    echo "✗ auth_required.php error: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/InputValidator.php';
    echo "✓ InputValidator.php loaded successfully\n";
} catch (Exception $e) {
    echo "✗ InputValidator.php error: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/db_connect.php';
    echo "✓ db_connect.php loaded successfully\n";
} catch (Exception $e) {
    echo "✗ db_connect.php error: " . $e->getMessage() . "\n";
}

// Test 3: Check if classes exist
echo "\n3. Testing class availability:\n";
if (class_exists('InputValidator')) {
    echo "✓ InputValidator class exists\n";
} else {
    echo "✗ InputValidator class NOT FOUND\n";
}

if (class_exists('ValidationRules')) {
    echo "✓ ValidationRules class exists\n";
} else {
    echo "✗ ValidationRules class NOT FOUND\n";
}

// Test 4: Test basic validation
echo "\n4. Testing basic validation:\n";
try {
    $testData = [
        'calling_name' => 'Test Calling',
        'organization' => 'Primary',
        'grouping' => 'Primary Presidency', 
        'priority' => 0
    ];
    
    $rules = ValidationRules::calling();
    $sanitizeRules = [
        'calling_name' => 'alphanumeric',
        'organization' => 'string',
        'grouping' => 'string',
        'priority' => 'integer'
    ];
    
    $validation = InputValidator::validateAndSanitize($testData, $rules, $sanitizeRules);
    
    if ($validation['valid']) {
        echo "✓ Basic validation test passed\n";
    } else {
        echo "✗ Basic validation test failed: " . json_encode($validation['errors']) . "\n";
    }
} catch (Exception $e) {
    echo "✗ Validation test error: " . $e->getMessage() . "\n";
}

// Test 5: Check POST data simulation
echo "\n5. Testing POST data simulation:\n";
try {
    $_POST = [
        'calling_name' => 'Test Calling',
        'organization' => 'Primary',
        'grouping' => 'Primary Presidency',
        'priority' => '0'
    ];
    
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
    
    echo "✓ POST data processing works\n";
    echo "Priority value: " . var_export($inputData['priority'], true) . "\n";
    echo "Priority type: " . gettype($inputData['priority']) . "\n";
    
} catch (Exception $e) {
    echo "✗ POST data processing error: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
?>