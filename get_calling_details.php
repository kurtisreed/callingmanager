<?php
// Set JSON content type header
header('Content-Type: application/json');

try {
    // Require authentication for this endpoint
    require_once __DIR__ . '/auth_required.php';

    // Log this access for auditing (wrapped in try-catch for shared hosting compatibility)
    try {
        logUserActivity('get_calling_details', ['risk_level' => 'medium', 'file' => 'get_calling_details.php']);
    } catch (Exception $e) {
        // Silently continue if logging fails on shared hosting
        error_log("Logging failed: " . $e->getMessage());
    }

    require_once 'db_connect.php';

    // Validate input
    if (!isset($_GET['calling_id']) || !is_numeric($_GET['calling_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid calling ID']);
        exit;
    }

    $callingId = (int)$_GET['calling_id'];
    $sql = "SELECT calling_id, calling_name, organization, grouping, priority FROM callings WHERE calling_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $callingId);
    
    if (!$stmt->execute()) {
        throw new Exception('Database execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $calling = $result->fetch_assoc();
    
    if (!$calling) {
        http_response_code(404);
        echo json_encode(['error' => 'Calling not found']);
    } else {
        echo json_encode($calling);
    }
    
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    error_log("get_calling_details.php error: " . $e->getMessage());
}
?>
