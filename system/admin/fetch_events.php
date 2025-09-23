<?php
require '../config.php';

// Fetch all appointments with details
$query = "SELECT DATE_FORMAT(date, '%Y-%m-%dT%H:%i:%s') as date, baby_name, status, type_of_vaccine FROM schedule";
$result = $conn->query($query);

if (!$result) {
    error_log("Database query failed: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database query failed']);
    exit();
}

$appointments = [];
while ($row = $result->fetch_assoc()) {
    $appointments[] = [
        'date' => $row['date'],
        'baby_name' => $row['baby_name'],
        'status' => $row['status'],
        'type_of_vaccine' => $row['type_of_vaccine']
    ];
}

echo json_encode($appointments); // Send the appointments as JSON
?>
