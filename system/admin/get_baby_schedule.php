<?php
require '../config.php';

if (isset($_GET['baby_id'])) {
    $baby_id = (int)$_GET['baby_id'];

    $query = "SELECT schedule_id, type_of_vaccine, schedule_date 
              FROM schedule 
              WHERE baby_id = $baby_id AND status = 'missed' 
              ORDER BY schedule_date";

    $result = $conn->query($query);

    $schedules = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $schedules[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($schedules);
}
?>

