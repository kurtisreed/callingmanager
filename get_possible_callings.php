<?php
require_once 'db_connect.php'; // Make sure this path is correct

// Get the calling ID from the request
$newCallingId = isset($_GET['newCalling']) ? intval($_GET['newCalling']) : 0;

// Prepare SQL query to get possible candidates for the specified calling
$sql = "
    SELECT 
        pc.id AS 'Possible Callings ID', 
        m.member_id AS 'Member ID', 
        m.first_name AS 'First Name', 
        m.last_name AS 'Last Name', 
        c.calling_name AS 'Calling', 
        c.calling_id AS 'Calling ID',
        cc.date_set_apart AS 'Date Set Apart',
        IFNULL((
            SELECT 
                GROUP_CONCAT(CONCAT_WS(' - ', cl.calling_name, cc2.date_set_apart)
                ORDER BY cc2.date_set_apart DESC SEPARATOR ', ')
            FROM current_callings cc2
            JOIN callings cl ON cc2.calling_id = cl.calling_id
            WHERE cc2.member_id = m.member_id AND cc2.date_released IS NULL
        ), 'No current calling') AS callings_info
    FROM possible_callings pc
    JOIN members m ON pc.member_id = m.member_id
    JOIN callings c ON pc.calling_id = c.calling_id
    LEFT JOIN current_callings cc ON pc.member_id = cc.member_id AND pc.calling_id = cc.calling_id
    WHERE pc.calling_id = ? AND pc.status = 'considered'
    ORDER BY m.last_name ASC, m.first_name ASC
";

// Prepare and execute the statement
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $newCallingId);
$stmt->execute();
$result = $stmt->get_result();

// Fetch the data
$data = array();
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Return the data as JSON
echo json_encode($data);

// Close the connection
$stmt->close();
$conn->close();
?>