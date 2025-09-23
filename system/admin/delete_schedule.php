<?php
session_start();
require '../config.php';

// Ensure only admins can access
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if barangay is provided
if (!isset($_POST['barangay'])) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['error' => 'Barangay name is required']);
    exit();
}

$barangay = trim($_POST['barangay']);

// Prepare and execute the delete statement
try {
    $stmt = $conn->prepare("DELETE FROM barangay_schedules WHERE barangay_name = ?");
    $stmt->bind_param("s", $barangay);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'No schedule found for the specified barangay']);
        }
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>