<?php
header('Content-Type: application/json');
require_once '../config.php';

$response = ['success' => false, 'message' => ''];

try {
    $requiredFields = ['baby_id', 'type_of_vaccine', 'schedule_date'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $data = [
        'baby_id' => $_POST['baby_id'],
        'type_of_vaccine' => $_POST['type_of_vaccine'],
        'schedule_date' => $_POST['schedule_date'],
        'vaccinated_by' => $_POST['vaccinated_by'] ?? null,
        'status' => $_POST['status'] ?? 'scheduled'
    ];

    $sql = "INSERT INTO schedule (baby_id, type_of_vaccine, schedule_date, vaccinated_by, status) 
            VALUES (:baby_id, :type_of_vaccine, :schedule_date, :vaccinated_by, :status)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    $response['success'] = true;
    $response['message'] = 'Vaccine record added successfully';
    $response['vaccine_id'] = $pdo->lastInsertId();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>