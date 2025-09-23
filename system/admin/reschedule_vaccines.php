<?php
require '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_ids = $_POST['schedule_id'] ?? [];
    $new_dates = $_POST['new_date'] ?? [];

    if (count($schedule_ids) !== count($new_dates)) {
        echo 'Mismatch in schedules';
        exit;
    }

    $success = true;

    for ($i = 0; $i < count($schedule_ids); $i++) {
        $schedule_id = (int)$schedule_ids[$i];
        $new_date = $conn->real_escape_string($new_dates[$i]);

        $update = $conn->query("
            UPDATE schedule 
            SET schedule_date = '$new_date', status = 'rescheduled' 
            WHERE schedule_id = $schedule_id
        ");

        if (!$update) {
            $success = false;
            break;
        }
    }

    echo $success ? 'success' : 'fail';
}
?>
