<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('save_calling_order', ['risk_level' => 'medium', 'file' => 'save_calling_order.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['order']) || !is_array($data['order'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

try {
    // Create calling_order table if it doesn't exist
    $createTableSql = "CREATE TABLE IF NOT EXISTS calling_order (
        id INT AUTO_INCREMENT PRIMARY KEY,
        `grouping` VARCHAR(255) NOT NULL UNIQUE,
        order_position INT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_order_position (order_position),
        INDEX idx_grouping (`grouping`)
    )";
    
    if (!$conn->query($createTableSql)) {
        throw new Exception("Failed to create calling_order table: " . $conn->error);
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Update or insert the order for each grouping
    $stmt = $conn->prepare("INSERT INTO calling_order (`grouping`, order_position) VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE order_position = VALUES(order_position)");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    foreach ($data['order'] as $item) {
        if (!isset($item['grouping']) || !isset($item['order'])) {
            throw new Exception("Invalid order item format");
        }
        
        $grouping = trim($item['grouping']);
        $order = intval($item['order']);
        
        if (empty($grouping) || $order < 1) {
            throw new Exception("Invalid grouping or order value");
        }
        
        $stmt->bind_param("si", $grouping, $order);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save order for grouping '{$grouping}': " . $stmt->error);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Calling order saved successfully',
        'count' => count($data['order'])
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction ?? false) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>