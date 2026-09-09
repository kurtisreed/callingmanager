<?php
// Lets the frontend check for an existing valid session on page load
// (e.g. after a refresh) without forcing a login prompt. Unlike other
// protected endpoints, an unauthenticated request here is not an error —
// skip the auto-401 behavior and report status instead.
$skip_auto_auth = true;
require_once __DIR__ . '/auth_required.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    echo json_encode(['authenticated' => false]);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'username'      => $_SESSION['username'] ?? null,
    'display_name'  => $_SESSION['display_name'] ?? null,
    'role'          => $_SESSION['role'] ?? null,
    'csrf_token'    => csrf_token(),
]);
?>
