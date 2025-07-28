<?php
require_once 'db_connect.php'; // Make sure this path is correct

// Query to fetch member names and their current callings with dates
$sql = "
    SELECT 
        members.member_id,
        members.first_name AS `First Name`,
        members.last_name AS `Last Name`,
        members.gender,
        members.birthdate,
        IFNULL(GROUP_CONCAT(CONCAT_WS(' - ', callings.calling_name, current_callings.date_set_apart) 
            ORDER BY current_callings.date_set_apart DESC SEPARATOR ', '), 'No current calling') AS callings_info
    FROM members
    LEFT JOIN current_callings ON members.member_id = current_callings.member_id AND current_callings.date_released IS NULL
    LEFT JOIN callings ON current_callings.calling_id = callings.calling_id
    GROUP BY members.member_id
    ORDER BY members.last_name ASC, members.first_name ASC"; // Ordered alphabetically by last name, then first name

$result = $conn->query($sql);

$data = array();

if ($result->num_rows > 0) {
    // Fetch each row as an associative array
    while ($row = $result->fetch_assoc()) {
        // Add each member and their callings info to the data array
        $data[] = $row;
    }
} else {
    echo json_encode(['error' => 'No results found']);
    exit();
}

// Return the data as JSON

echo json_encode($data);

// Close the connection
$conn->close();
?>