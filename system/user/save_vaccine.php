<?php
require_once '../config.php';
session_start();

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $baby_id = $_POST['baby_id'] ?? null;
    $vaccine_name = $_POST['vaccine_name'] ?? '';
    $dose = $_POST['dose'] ?? '';
    $date = $_POST['date'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $user_id = $_SESSION['user_id'] ?? null;

    if ($baby_id && $user_id) {
        try {
            // Construct the full vaccine name with dose
            $doseNumber = preg_replace('/[^0-9]/', '', $dose);
            $doseSuffix = preg_replace('/[0-9]/', '', $dose);
            $fullVaccineName = $vaccine_name . '_' . $doseNumber . $doseSuffix . '_dose';

            // Insert into database
            $stmt = $pdo->prepare("INSERT INTO schedule 
                                  (baby_id, user_id, type_of_vaccine, schedule_date, status, vaccinated_by) 
                                  VALUES (?, ?, ?, ?, 'Completed', ?)");
            $stmt->execute([$baby_id, $user_id, $fullVaccineName, $date, $remarks]);
            
            $response['success'] = true;
        } catch (PDOException $e) {
            $response['message'] = "Database error: " . $e->getMessage();
        }
    } else {
        $response['message'] = "Missing required parameters";
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>