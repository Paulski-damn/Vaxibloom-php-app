<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json'); // Always set JSON header

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get and validate input data
$required_fields = ['baby_id', 'parent_name', 'baby_name', 'birthdate', 'gender', 
                   'place_of_birth', 'address', 'health_condition', 'birth_height', 'birth_weight', 'contact_no'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
    ]);
    exit();
}

try {
    // Verify the baby belongs to the user's barangay
    $check_stmt = $pdo->prepare("SELECT baby_id FROM babies WHERE baby_id = ? AND barangay = ?");
    $check_stmt->execute([$_POST['baby_id'], $_SESSION['barangay']]);
    
    if (!$check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Baby record not found or access denied']);
        exit();
    }

    // Update the baby record
    $update_stmt = $pdo->prepare("UPDATE babies SET 
        parent_name = ?, 
        baby_name = ?, 
        birthdate = ?, 
        gender = ?, 
        place_of_birth = ?,
        address = ?, 
        health_condition = ?,  
        birth_height = ?, 
        birth_weight = ?, 
        contact_no = ?
        WHERE baby_id = ?");
        
    $success = $update_stmt->execute([
        $_POST['parent_name'],
        $_POST['baby_name'],
        $_POST['birthdate'],
        $_POST['gender'],
        $_POST['place_of_birth'],
        $_POST['address'],
        $_POST['health_condition'],
        $_POST['birth_height'],
        $_POST['birth_weight'],
        $_POST['contact_no'],
        $_POST['baby_id']
    ]);

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Baby record updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update baby record']);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vaccineId = $_POST['id'] ?? null;
    $type = $_POST['type_of_vaccine'] ?? '';
    $date = $_POST['schedule_date'] ?? '';
    $administeredBy = $_POST['vaccinated_by'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            UPDATE vaccines 
            SET type_of_vaccine = :type, 
                schedule_date = :date, 
                vaccinated_by = :administeredBy,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':id' => $vaccineId,
            ':type' => $type,
            ':date' => $date,
            ':administeredBy' => $administeredBy
        ]);
        
        $response['success'] = true;
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
?>