<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';
require_once __DIR__ . '/OutputSanitizer.php';
require_once 'db_connect.php';

// Log this access for auditing
logUserActivity('get_members', ['action' => 'fetch_member_list']);

try {
    // SQL query to fetch members ordered by last name
    $sql = "SELECT member_id, first_name, last_name FROM members ORDER BY last_name ASC";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Database query failed: ' . $conn->error);
    }
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        // Sanitize output data to prevent XSS
        $members[] = [
            'member_id' => (int) $row['member_id'], // Ensure integer
            'first_name' => OutputSanitizer::html($row['first_name']),
            'last_name' => OutputSanitizer::html($row['last_name'])
        ];
    }
    
    header('Content-Type: application/json');
    echo OutputSanitizer::json($members);
    
    // Log successful data retrieval
    logUserActivity('get_members_success', [
        'member_count' => count($members)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Failed to retrieve members',
        'message' => 'An error occurred while fetching member data'
    ]);
    
    // Log the error
    error_log('Error in get_members.php: ' . $e->getMessage());
    logUserActivity('get_members_error', ['error' => $e->getMessage()]);
    
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>