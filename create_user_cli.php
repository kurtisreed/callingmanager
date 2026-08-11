<?php
/**
 * Ward Manager - User Account Setup
 *
 * Creates the `users` table (if needed) and creates or updates one user
 * account, linked to a real member record. Run this once to create the
 * first Bishop account before the username/password login is usable, and
 * again any time you need to create an account outside the in-app
 * "Manage Users" screen.
 *
 * CLI-ONLY: run with `php create_user_cli.php`.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This setup script can only be run from the command line.');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connect.php';

echo "Ward Manager - User Account Setup\n";
echo "=====================================\n\n";

$conn->query("CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('bishop', 'counselor', 'leader') NOT NULL DEFAULT 'leader',
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    UNIQUE KEY uniq_users_member_id (member_id),
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE SET NULL
)");

// Self-heal: if the users table already existed from before member_id was
// introduced, add it now rather than requiring a separate migration step.
$hasMemberId = $conn->query("SHOW COLUMNS FROM users LIKE 'member_id'");
if ($hasMemberId && $hasMemberId->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN member_id INT NULL AFTER user_id");
    $conn->query("ALTER TABLE users ADD UNIQUE KEY uniq_users_member_id (member_id)");
    $conn->query("ALTER TABLE users ADD CONSTRAINT fk_users_member_id FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE SET NULL");
}

echo "users table ready.\n\n";

function cm_prompt($label) {
    echo $label;
    return trim(fgets(STDIN));
}

function cm_prompt_password($label) {
    echo $label;
    // Best-effort: hide input on unix-like terminals. Falls back to
    // visible input if stty isn't available.
    system('stty -echo 2>/dev/null');
    $password = trim(fgets(STDIN));
    system('stty echo 2>/dev/null');
    echo "\n";
    return $password;
}

// Search for and select the member this account belongs to. Excludes
// members already linked to another account, and members who've moved
// away or are deceased.
function cm_search_member($conn) {
    while (true) {
        $term = cm_prompt("\nSearch for the member this account belongs to (part of first or last name): ");
        if ($term === '') {
            echo "Please enter a search term.\n";
            continue;
        }

        $like = '%' . $term . '%';
        $stmt = $conn->prepare(
            "SELECT m.member_id, m.first_name, m.last_name
             FROM members m
             LEFT JOIN users u ON u.member_id = m.member_id
             WHERE (m.first_name LIKE ? OR m.last_name LIKE ?)
             AND m.status NOT IN ('moved', 'deceased')
             AND u.user_id IS NULL
             ORDER BY m.last_name, m.first_name
             LIMIT 20"
        );
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();

        $matches = [];
        while ($row = $result->fetch_assoc()) {
            $matches[] = $row;
        }
        $stmt->close();

        if (empty($matches)) {
            echo "No matching, unlinked members found. Try a different search.\n";
            continue;
        }

        echo "\nMatches:\n";
        foreach ($matches as $i => $m) {
            printf("  [%d] %s %s\n", $i + 1, $m['first_name'], $m['last_name']);
        }

        $choice = cm_prompt("\nEnter the number of the correct member (or press Enter to search again): ");
        if ($choice === '') {
            continue;
        }

        $idx = (int) $choice - 1;
        if (isset($matches[$idx])) {
            return $matches[$idx];
        }
        echo "Invalid choice. Try again.\n";
    }
}

$username = cm_prompt("Username: ");
if (empty($username)) {
    echo "\nUsername is required. Aborting.\n";
    exit(1);
}

$member = cm_search_member($conn);
$displayName = $member['first_name'] . ' ' . $member['last_name'];
echo "\nLinked to: $displayName\n";

$password = cm_prompt_password("\nPassword (min 8 characters): ");
$roleInput = trim(strtolower(cm_prompt("Role [bishop/counselor/leader] (default: bishop): ")));
$role = in_array($roleInput, ['bishop', 'counselor', 'leader'], true) ? $roleInput : 'bishop';

if (strlen($password) < 8) {
    echo "\nPassword must be at least 8 characters. Aborting.\n";
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO users (username, member_id, display_name, password_hash, role, status)
     VALUES (?, ?, ?, ?, ?, 'active')
     ON DUPLICATE KEY UPDATE
        member_id = VALUES(member_id),
        display_name = VALUES(display_name),
        password_hash = VALUES(password_hash),
        role = VALUES(role),
        status = 'active'"
);
$stmt->bind_param('sisss', $username, $member['member_id'], $displayName, $passwordHash, $role);

if ($stmt->execute()) {
    echo "\nAccount '$username' ($role, linked to $displayName) created/updated successfully.\n";
} else {
    echo "\nFailed to create account: " . $stmt->error . "\n";
    exit(1);
}

$stmt->close();
$conn->close();
?>
