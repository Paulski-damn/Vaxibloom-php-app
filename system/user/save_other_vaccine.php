<?php
require '../config.php'; // Adjust path if needed

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and retrieve POST data
    $baby_id = isset($_POST['baby_id']) ? intval($_POST['baby_id']) : 0;
    $vaccine_name = isset($_POST['vaccine_name']) ? trim($_POST['vaccine_name']) : '';
    $schedule_date = isset($_POST['schedule_date']) ? $_POST['schedule_date'] : '';
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

    // Basic validation
    if ($baby_id <= 0 || empty($vaccine_name) || empty($schedule_date)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    // Insert into your vaccination table
    $stmt = $conn->prepare("INSERT INTO schedule (baby_id, type_of_vaccine, schedule_date, vaccinated_by) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("isss", $baby_id, $vaccine_name, $schedule_date, $remarks);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
