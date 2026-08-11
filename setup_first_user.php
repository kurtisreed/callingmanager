<?php
/**
 * One-time web-based setup: creates the `users` table (if needed) and the
 * very first account, linked to a real member record.
 *
 * This page intentionally requires NO prior login — there's no way to log
 * in until an account exists. Instead it's protected several ways:
 *   1. It permanently refuses to run once ANY user account exists, checked
 *      fresh on every request (not a one-time flag, so it can't be
 *      re-armed by mistake).
 *   2. It requires a SETUP_TOKEN from .env that only you know, so a
 *      stranger who finds this URL before you visit it can't create the
 *      first (Bishop) account for themselves.
 *   3. Member names are never rendered to the browser until step 2 (AFTER
 *      the token has been validated server-side) — an unauthenticated
 *      visitor who hasn't proven they know the token never sees any
 *      ward member data, not even to populate a search box.
 *
 * Visit this page once after deploying, create your real Bishop account,
 * then you're done — every visit after that (by anyone, including you)
 * is refused because a user now exists. Use the in-app "Users" tab (or
 * create_user_cli.php, if you ever get CLI access) to add more accounts.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connect.php';

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

// Permanent lock: refuse outright if any account already exists.
$existingCount = 0;
$countResult = $conn->query("SELECT COUNT(*) as cnt FROM users");
if ($countResult) {
    $existingCount = (int) $countResult->fetch_assoc()['cnt'];
}

$setupToken = Config::get('SETUP_TOKEN');
$tokenConfigured = !empty($setupToken) && $setupToken !== 'change_this_to_a_long_random_string';

$error = null;
$success = false;
$tokenValidated = false;
$members = [];

if ($existingCount > 0) {
    $error = 'Setup has already been completed — an account already exists. This page no longer does anything. Use the in-app "Users" tab (as the Bishop) to add more accounts.';
} elseif (!$tokenConfigured) {
    $error = 'SETUP_TOKEN is not configured. Add a long random SETUP_TOKEN value to your .env file before using this page.';
}

if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['setup_token'] ?? '';
    $isFinalSubmit = isset($_POST['action']) && $_POST['action'] === 'create_account';

    if (!hash_equals($setupToken, $submittedToken)) {
        $error = 'Incorrect setup token.';
    } elseif (!$isFinalSubmit) {
        // Step 1 succeeded: token is correct. Render step 2 (account form
        // with the member list) without touching the database again yet.
        $tokenValidated = true;
    } else {
        // Step 2 final submission: re-validate everything server-side.
        $username    = trim($_POST['username'] ?? '');
        $displayInfo = trim($_POST['display_name'] ?? ''); // hidden, filled by JS from the select's label
        $member_id   = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
        $password    = $_POST['password'] ?? '';
        $role        = $_POST['role'] ?? '';

        if ($username === '') {
            $error = 'Username is required.';
        } elseif (!preg_match('/^[A-Za-z0-9_.\-]+$/', $username) || strlen($username) > 50) {
            $error = 'Username may only contain letters, numbers, and . _ - (max 50 characters).';
        } elseif ($member_id <= 0) {
            $error = 'Please select the member this account belongs to.';
        } elseif (!in_array($role, ['bishop', 'counselor', 'leader'], true)) {
            $error = 'Invalid role.';
        } elseif (!is_string($password) || strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            // Re-check the table is still empty right before inserting,
            // closing the race window between page load and submission.
            $recheck = $conn->query("SELECT COUNT(*) as cnt FROM users");
            $recheckCount = $recheck ? (int) $recheck->fetch_assoc()['cnt'] : 1;

            if ($recheckCount > 0) {
                $error = 'Setup has already been completed by someone else since this page loaded. Refresh to confirm.';
            } else {
                $memberCheck = $conn->prepare(
                    "SELECT first_name, last_name FROM members
                     WHERE member_id = ? AND status NOT IN ('moved', 'deceased')"
                );
                $memberCheck->bind_param('i', $member_id);
                $memberCheck->execute();
                $memberResult = $memberCheck->get_result();

                if ($memberResult->num_rows === 0) {
                    $memberCheck->close();
                    $error = 'Selected member not found.';
                } else {
                    $memberRow = $memberResult->fetch_assoc();
                    $memberCheck->close();
                    $displayName = $memberRow['first_name'] . ' ' . $memberRow['last_name'];

                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare(
                        "INSERT INTO users (username, member_id, display_name, password_hash, role, status)
                         VALUES (?, ?, ?, ?, ?, 'active')"
                    );
                    $stmt->bind_param('sisss', $username, $member_id, $displayName, $passwordHash, $role);
                    if ($stmt->execute()) {
                        $success = true;
                        error_log("First user account created via setup_first_user.php: '$username' ($role, linked to $displayName)");
                    } else {
                        $error = 'Failed to create account: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }

        // Stay on step 2 (with the member list) if the final submission failed.
        if ($error) {
            $tokenValidated = true;
        }
    }
}

// Load the member picker only once the token has been validated this request.
if ($tokenValidated && !$success) {
    $result = $conn->query(
        "SELECT m.member_id, CONCAT(m.first_name, ' ', m.last_name) AS member_name
         FROM members m
         LEFT JOIN users u ON u.member_id = m.member_id
         WHERE m.status NOT IN ('moved', 'deceased')
         AND u.user_id IS NULL
         ORDER BY m.last_name ASC, m.first_name ASC"
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calling Manager - First-Time Setup</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background-color: #f4f5f7;
            margin: 0;
            padding: 40px 20px;
        }
        .card {
            max-width: 420px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 32px;
        }
        h1 {
            font-size: 22px;
            margin: 0 0 8px;
            color: #0c2238;
        }
        p.subtitle {
            color: #666;
            margin: 0 0 24px;
            font-size: 14px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
            font-size: 14px;
        }
        input, select {
            display: block;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 18px;
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #0c2238;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover {
            background-color: #16324f;
        }
        .message {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .message.error {
            background-color: #fdecea;
            color: #a02b1f;
            border: 1px solid #f5c6cb;
        }
        .message.success {
            background-color: #e6f4ea;
            color: #1e7e34;
            border: 1px solid #b6dfc0;
        }
        a.login-link {
            display: inline-block;
            margin-top: 8px;
            color: #0c2238;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Calling Manager Setup</h1>
        <p class="subtitle">Create the first account (your Bishop login) to activate the app.</p>

        <?php if ($success): ?>
            <div class="message success">
                Account created successfully. This setup page is now permanently disabled.
            </div>
            <a class="login-link" href="index.html">Go to login &rarr;</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($existingCount === 0 && $tokenConfigured): ?>
                <?php if (!$tokenValidated): ?>
                    <!-- Step 1: token only. No member data is rendered until this passes. -->
                    <form method="POST">
                        <label for="setup_token">Setup Token:</label>
                        <input type="password" id="setup_token" name="setup_token" required autocomplete="off">
                        <button type="submit">Continue</button>
                    </form>
                <?php else: ?>
                    <!-- Step 2: token already validated server-side this request. -->
                    <form method="POST" id="setup-form">
                        <input type="hidden" name="action" value="create_account">
                        <input type="hidden" name="setup_token" value="<?php echo htmlspecialchars($submittedToken); ?>">
                        <input type="hidden" name="display_name" id="setup-display-name">

                        <label for="member_id">Member:</label>
                        <input type="text" id="member_filter" placeholder="Type to filter the list below..." autocomplete="off">
                        <select id="member_id" name="member_id" required size="8">
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo (int) $m['member_id']; ?>" data-name="<?php echo htmlspecialchars($m['member_name']); ?>">
                                    <?php echo htmlspecialchars($m['member_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required autocomplete="off">

                        <label for="role">Role:</label>
                        <select id="role" name="role" size="1">
                            <option value="bishop" selected>Bishop</option>
                            <option value="counselor">Counselor</option>
                            <option value="leader">Leader</option>
                        </select>

                        <label for="password">Password (min 8 characters):</label>
                        <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8">

                        <button type="submit">Create Account</button>
                    </form>
                    <script>
                        // Client-side filter for the already-rendered (server-validated) member list.
                        document.getElementById('member_filter').addEventListener('input', function () {
                            const term = this.value.toLowerCase();
                            const options = document.getElementById('member_id').options;
                            for (let i = 0; i < options.length; i++) {
                                const name = options[i].getAttribute('data-name').toLowerCase();
                                options[i].style.display = name.includes(term) ? '' : 'none';
                            }
                        });
                        // Carry the selected member's name into the hidden display_name field.
                        document.getElementById('setup-form').addEventListener('submit', function () {
                            const select = document.getElementById('member_id');
                            const selected = select.options[select.selectedIndex];
                            document.getElementById('setup-display-name').value = selected ? selected.getAttribute('data-name') : '';
                        });
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
