<?php
// --- REPLACE WITH YOUR ACTUAL CREDENTIALS ---
$servername = "localhost";
$username   = "kbr37_1"; // e.g., hgator_dbuser
$password   = "dbuser1";
$dbname     = "kbr37_callingmanager";   // e.g., hgator_dbname

// --- DO NOT EDIT BELOW THIS LINE ---

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // Connection Failed
    echo "<h1>Connection Failed!</h1>";
    echo "<p>There was an error connecting to the database.</p>";
    echo "<p><strong>Error Message:</strong> " . $conn->connect_error . "</p>";
    echo "<p>Please double-check your credentials and permissions.</p>";
} else {
    // Connection Successful
    echo "<h1>Success!</h1>";
    echo "<p>Successfully connected to the database '<strong>" . $dbname . "</strong>' as user '<strong>" . $username . "</strong>'.</p>";
    echo "<p>Your application should now be able to connect.</p>";
}

$conn->close();

?>