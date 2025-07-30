<?php
// Load configuration
require_once __DIR__ . '/config.php';

// Database connection details from environment variables
$servername = Config::get('DB_HOST', 'localhost');
$username   = Config::get('DB_USERNAME');
$password   = Config::get('DB_PASSWORD');
$dbname     = Config::get('DB_NAME');

// Validate required environment variables
if (empty($username) || empty($password) || empty($dbname)) {
    http_response_code(500);
    error_log('Database configuration incomplete: Missing required environment variables');
    echo json_encode(['error' => 'Database configuration error']);
    exit;
}

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    error_log('Database connection failed: ' . $conn->connect_error);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
