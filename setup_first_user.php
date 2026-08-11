<?php
/**
 * One-time web-based setup: creates the `users` table (if needed) and the
 * very first account.
 *
 * This page intentionally requires NO prior login — there's no way to log
 * in until an account exists. Instead it's protected two ways:
 *   1. It permanently refuses to run once ANY user account exists, checked
 *      fresh on every request (not a one-time flag, so it can't be
 *      re-armed by mistake).
 *   2. It requires a SETUP_TOKEN from .env that only you know, so a
 *      stranger who finds this URL before you visit it can't create the
 *      first (Bishop) account for themselves.
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
    username VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('bishop', 'counselor', 'leader') NOT NULL DEFAULT 'leader',
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
)");

// Permanent lock: refuse outright if any account already exists.
$existingCount = 0;
$countResult = $conn->query("SELECT COUNT(*) as cnt FROM users");
if ($countResult) {
    $existingCount = (int) $countResult->fetch_assoc()['cnt'];
}

$setupToken = Config::get('SETUP_TOKEN');
$error = null;
$success = false;

if ($existingCount > 0) {
    $error = 'Setup has already been completed — an account already exists. This page no longer does anything. Use the in-app "Users" tab (as the Bishop) to add more accounts.';
} elseif (empty($setupToken) || $setupToken === 'change_this_to_a_long_random_string') {
    $error = 'SETUP_TOKEN is not configured. Add a long random SETUP_TOKEN value to your .env file before using this page.';
}

if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['setup_token'] ?? '';
    $username       = trim($_POST['username'] ?? '');
    $displayName    = trim($_POST['display_name'] ?? '');
    $password       = $_POST['password'] ?? '';
    $role           = $_POST['role'] ?? '';

    if (!hash_equals($setupToken, $submittedToken)) {
        $error = 'Incorrect setup token.';
    } elseif ($username === '' || $displayName === '') {
        $error = 'Username and display name are required.';
    } elseif (!preg_match('/^[A-Za-z0-9_.\-]+$/', $username) || strlen($username) > 50) {
        $error = 'Username may only contain letters, numbers, and . _ - (max 50 characters).';
    } elseif (!in_array($role, ['bishop', 'counselor', 'leader'], true)) {
        $error = 'Invalid role.';
    } elseif (!is_string($password) || strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        // Re-check emptiness right before inserting to close the race
        // window between the page load and this submission.
        $recheck = $conn->query("SELECT COUNT(*) as cnt FROM users");
        $recheckCount = $recheck ? (int) $recheck->fetch_assoc()['cnt'] : 1;

        if ($recheckCount > 0) {
            $error = 'Setup has already been completed by someone else since this page loaded. Refresh to confirm.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO users (username, display_name, password_hash, role, status)
                 VALUES (?, ?, ?, ?, 'active')"
            );
            $stmt->bind_param('ssss', $username, $displayName, $passwordHash, $role);
            if ($stmt->execute()) {
                $success = true;
                error_log("First user account created via setup_first_user.php: '$username' ($role)");
            } else {
                $error = 'Failed to create account: ' . $stmt->error;
            }
            $stmt->close();
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

            <?php if ($existingCount === 0 && $setupToken && $setupToken !== 'change_this_to_a_long_random_string'): ?>
            <form method="POST">
                <label for="setup_token">Setup Token:</label>
                <input type="password" id="setup_token" name="setup_token" required autocomplete="off">

                <label for="display_name">Display Name:</label>
                <input type="text" id="display_name" name="display_name" placeholder="e.g. Bishop Reed" required>

                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required autocomplete="off">

                <label for="role">Role:</label>
                <select id="role" name="role">
                    <option value="bishop" selected>Bishop</option>
                    <option value="counselor">Counselor</option>
                    <option value="leader">Leader</option>
                </select>

                <label for="password">Password (min 8 characters):</label>
                <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8">

                <button type="submit">Create Account</button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
