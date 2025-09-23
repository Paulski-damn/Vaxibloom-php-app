<?php
session_start();
require_once '../config.php';


if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

// 🔽 Handle POST request for saving other vaccine
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $babyId = $_POST['baby_id'] ?? '';
    $vaccineName = $_POST['vaccine_name'] ?? '';
    $scheduleDate = $_POST['schedule_date'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $userId = $_SESSION['user_id'];

    if (empty($babyId) || empty($vaccineName) || empty($scheduleDate)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO schedule (baby_id, type_of_vaccine, schedule_date, vaccinated_by, status, user_id)
            VALUES (?, ?, ?, ?, 'Completed', ?)
        ");
        $stmt->execute([$babyId, $vaccineName, $scheduleDate, $remarks, $userId]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
    }
    exit;
}


if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

if (empty($_GET['baby_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Baby ID is missing.']);
    exit;
}

$babyId = $_GET['baby_id'];
$action = $_GET['action'] ?? ''; // 'edit' or empty for view

try {
    // Get baby details
    $stmt = $pdo->prepare("SELECT * FROM babies WHERE baby_id = ?");
    $stmt->execute([$babyId]);
    $baby = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$baby) {
        die(json_encode(['success' => false, 'message' => 'Baby not found']));
    }

    // If this is an AJAX request for editing, return JSON data
    if ($action === 'edit') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => [
                'baby_id' => $baby['baby_id'],
                'parent_name' => $baby['parent_name'],
                'baby_name' => $baby['baby_name'],
                'birthdate' => $baby['birthdate'],
                'gender' => $baby['gender'],
                'place_of_birth' => $baby['place_of_birth'],
                'address' => $baby['address'],
                'health_condition' => $baby['health_condition'],
                'birth_height' => $baby['birth_height'],
                'birth_weight' => $baby['birth_weight'],
                'contact_no' => $baby['contact_no'],
                'barangay' => $baby['barangay'],
            ]
        ]);
        exit();
    }

    // Get vaccine records for this baby from schedule table
    $vaccineStmt = $pdo->prepare("
        SELECT 
            type_of_vaccine as type_of_vaccine, 
            schedule_date, 
            status,
            vaccinated_by
        FROM schedule
        WHERE baby_id = ? AND status = 'Completed'
        ORDER BY schedule_date ASC
    ");
    $vaccineStmt->execute([$babyId]);
    $vaccines = $vaccineStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate age
    $birthDate = new DateTime($baby['birthdate']);
    $today = new DateTime();
    $age = $today->diff($birthDate);

    // Prepare the response data
    $response = [
        'success' => true,
        'data' => [
            'baby' => [
                'baby_id' => $baby['baby_id'],
                'baby_name' => $baby['baby_name'],
                'parent_name' => $baby['parent_name'],
                'birthdate' => $baby['birthdate'],
                'gender' => $baby['gender'],
                'place_of_birth' => $baby['place_of_birth'],
                'address' => $baby['address'],
                'health_condition' => $baby['health_condition'],
                'birth_height' => $baby['birth_height'],
                'birth_weight' => $baby['birth_weight'],
                'contact_no' => $baby['contact_no'],
                'barangay' => $baby['barangay'],
                'age' => $age->y.' years, '.$age->m.' months, '.$age->d.' days',
                'formatted_birthdate' => $birthDate->format('F j, Y')
            ],
            'vaccines' => $vaccines
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>