<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not authenticated']));
}

$user_id = $_SESSION['user_id'];

// Get user's barangay
$stmt = $conn->prepare("SELECT barangay FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userInfo = $result->fetch_assoc();
$stmt->close();

// Get vaccines the user has already received (status = 'completed')
$stmt = $conn->prepare("SELECT DISTINCT type_of_vaccine FROM schedule WHERE user_id = ? AND status = 'completed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$vaccinated_vaccines = [];
while ($row = $result->fetch_assoc()) {
    $vaccinated_vaccines[] = $row['type_of_vaccine'];
}
$stmt->close();

$userInfo['vaccinated_vaccines'] = $vaccinated_vaccines;

echo json_encode($userInfo);
?>