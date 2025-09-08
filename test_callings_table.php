<?php
// Test script to check callings table structure
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    require_once 'db_connect.php';
    
    // Test 1: Check if table exists and get structure
    $sql = "DESCRIBE callings";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Failed to describe table: ' . $conn->error);
    }
    
    $tableStructure = [];
    while ($row = $result->fetch_assoc()) {
        $tableStructure[] = $row;
    }
    
    // Test 2: Try a simple SELECT to see if the table is accessible
    $sql = "SELECT COUNT(*) as count FROM callings";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Failed to count records: ' . $conn->error);
    }
    $count = $result->fetch_assoc()['count'];
    
    // Test 3: Try to insert a test record (but rollback)
    $conn->autocommit(false);
    
    $sql = "INSERT INTO callings (calling_name, organization, `grouping`, priority) VALUES ('TEST_CALLING', 'Primary', 'Primary Presidency', 999)";
    $testResult = $conn->query($sql);
    
    if ($testResult) {
        $insertId = $conn->insert_id;
        // Rollback the test insert
        $conn->rollback();
        $insertSuccess = true;
        $insertError = null;
    } else {
        $insertSuccess = false;
        $insertError = $conn->error;
        $conn->rollback();
    }
    
    $conn->autocommit(true);
    
    echo json_encode([
        'success' => true,
        'table_structure' => $tableStructure,
        'record_count' => $count,
        'insert_test' => [
            'success' => $insertSuccess,
            'error' => $insertError
        ],
        'php_version' => phpversion(),
        'mysql_version' => $conn->server_info
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>