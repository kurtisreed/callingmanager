<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_calling_order', ['risk_level' => 'low', 'file' => 'get_calling_order.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Check if calling_order table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'calling_order'");
    
    if ($tableExists->num_rows === 0) {
        // Table doesn't exist, return empty order
        echo json_encode([
            'success' => true,
            'order' => [],
            'message' => 'No saved order found - using default order'
        ]);
        exit;
    }
    
    // Fetch saved order
    $sql = "SELECT grouping, order_position FROM calling_order ORDER BY order_position ASC";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $order = [];
    while ($row = $result->fetch_assoc()) {
        $order[] = [
            'grouping' => $row['grouping'],
            'order_position' => intval($row['order_position'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'count' => count($order)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'order' => []
    ]);
}

$conn->close();
?>