<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('get_youth_details', ['risk_level' => 'low', 'file' => 'get_youth_details.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

// Whitelist of valid youth groups mapped to gender + cohort-age range.
// Cohort age = calendar-year age (YEAR(CURDATE()) - YEAR(birthdate)), which is
// how LDS wards actually assign Sunday classes (by birth year, not exact
// birthday), so a class doesn't change mid-year.
$groups = [
    'all'                 => ['gender' => null, 'min_age' => 12, 'max_age' => 18],
    'deacons'              => ['gender' => 'M',  'min_age' => 12, 'max_age' => 13],
    'teachers'             => ['gender' => 'M',  'min_age' => 14, 'max_age' => 15],
    'priests'              => ['gender' => 'M',  'min_age' => 16, 'max_age' => 18],
    'gatherers-of-light'   => ['gender' => 'F',  'min_age' => 16, 'max_age' => 18],
    'messengers-of-hope'   => ['gender' => 'F',  'min_age' => 14, 'max_age' => 15],
    'builders-of-faith'    => ['gender' => 'F',  'min_age' => 12, 'max_age' => 13],
];

$group = $_GET['group'] ?? '';

if (!array_key_exists($group, $groups)) {
    echo json_encode(['error' => true, 'message' => 'Invalid youth group']);
    exit;
}

$config = $groups[$group];

try {
    $cohortAgeExpr = "(YEAR(CURDATE()) - YEAR(m.birthdate))";

    $sql = "SELECT
                m.member_id,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                m.gender,
                m.birthdate,
                $cohortAgeExpr as age,
                CASE
                    WHEN m.gender = 'M' AND $cohortAgeExpr BETWEEN 12 AND 13 THEN 'Deacons'
                    WHEN m.gender = 'M' AND $cohortAgeExpr BETWEEN 14 AND 15 THEN 'Teachers'
                    WHEN m.gender = 'M' AND $cohortAgeExpr BETWEEN 16 AND 18 THEN 'Priests'
                    WHEN m.gender = 'F' AND $cohortAgeExpr BETWEEN 16 AND 18 THEN 'Gatherers of Light'
                    WHEN m.gender = 'F' AND $cohortAgeExpr BETWEEN 14 AND 15 THEN 'Messengers of Hope'
                    WHEN m.gender = 'F' AND $cohortAgeExpr BETWEEN 12 AND 13 THEN 'Builders of Faith'
                    ELSE ''
                END as class_label
            FROM members m
            WHERE m.birthdate IS NOT NULL
            AND m.status NOT IN ('moved', 'deceased')
            AND $cohortAgeExpr BETWEEN ? AND ?";

    $params = [$config['min_age'], $config['max_age']];
    $types  = 'ii';

    if ($config['gender'] !== null) {
        $sql .= " AND m.gender = ?";
        $params[] = $config['gender'];
        $types .= 's';
    }

    $sql .= " ORDER BY $cohortAgeExpr DESC, m.gender ASC, m.last_name ASC, m.first_name ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $youth = [];
    while ($row = $result->fetch_assoc()) {
        $youth[] = [
            'member_id' => (int) $row['member_id'],
            'member_name' => $row['member_name'],
            'age' => (int) $row['age'],
            'birthdate' => $row['birthdate'],
            'class_label' => $row['class_label'],
        ];
    }

    $stmt->close();
    echo json_encode($youth);

} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
