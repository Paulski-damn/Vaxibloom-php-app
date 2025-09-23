<?php
header('Content-Type: application/json');
require_once '../config.php';

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_POST['vaccine_id'])) {
        throw new Exception('Vaccine ID not provided');
    }

    $vaccineId = $_POST['vaccine_id'];
    $fields = [
        'type_of_vaccine', 'schedule_date', 
        'vaccinated_by', 'status'
    ];

    $updateData = [];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $updateData[$field] = $_POST[$field];
        }
    }

    if (empty($updateData)) {
        throw new Exception('No data provided for update');
    }

    $setParts = [];
    $params = [];
    foreach ($updateData as $field => $value) {
        $setParts[] = "$field = ?";
        $params[] = $value;
    }
    $params[] = $vaccineId;

    $sql = "UPDATE schedule SET " . implode(', ', $setParts) . " WHERE vaccine_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $response['success'] = true;
    $response['message'] = 'Vaccine record updated successfully';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>