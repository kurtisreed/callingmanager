<?php
require_once 'db_connect.php'; // Make sure this path is correct

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Step 1: Query the database to get callings grouped by 'grouping' along with the calling_id
$sql = "
    SELECT grouping, calling_name, priority, calling_id
    FROM callings
    ORDER BY grouping, priority IS NULL, priority, calling_name ASC
";

$result = $conn->query($sql);


// Step 2: Store the callings in a structured array
$callingsByGroup = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $grouping = $row['grouping'] ? $row['grouping'] : 'Other';
        $callingsByGroup[$grouping][] = [
            'calling_name' => $row['calling_name'],
            'priority' => $row['priority'],
            'calling_id' => $row['calling_id'] // Add calling_id to the array
        ];
    }
}

// Step 3: Generate the HTML dynamically, including data-calling-id
foreach ($callingsByGroup as $group => $callings) {
    echo "<div class='small-box {$group}' id='{$group}'>";
    echo "<h3 class='box-header'>" . htmlspecialchars($group) . "</h3>";

    foreach ($callings as $calling) {
        $callingName = htmlspecialchars($calling['calling_name']);
        $callingId = strtolower(str_replace(' ', '-', $callingName)); // Create an ID from the calling name
        $callingDbId = $calling['calling_id']; // The actual calling_id from the database

        // Add data-calling-id with the calling_id
        echo "<div class='box-title' data-title='{$callingName}' data-calling-id='{$callingDbId}'>{$callingName}:</div>";
        echo "<p class='box-content update' data-calling='{$callingName}' id='{$callingId}' data-calling-id='{$callingDbId}'></p>";
    }

    echo "</div>";
}

// Close the connection
$conn->close();
?>
