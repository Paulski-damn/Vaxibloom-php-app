<?php
session_start();
require_once '../config.php'; 

// Check if user is logged in and get their barangay
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_barangay = $_SESSION['barangay'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $parent_name = trim($_POST['parent_name']);
    $baby_name = trim($_POST['baby_name']);
    $birthdate = $_POST['birthdate'];
    $barangay = $_SESSION['barangay'];
    $place_of_birth = $_POST['place_of_birth'];
    $address = $_POST['address'];
    $health_condition = $_POST['health_condition'];
    $gender = $_POST['gender'];
    $birth_height = $_POST['birth_height'];
    $birth_weight = $_POST['birth_weight'];
    $contact_no = $_POST['contact_no'];
    $user_id = $_SESSION['user_id'];
    
    // Get vaccine data if provided
    $vaccine_ids = $_POST['vaccines'] ?? [];
    $vaccine_dates = $_POST['vaccine_dates'] ?? [];
    $baby_id = $_POST['baby_id'] ?? [];

    // Simple validation
    $errors = [];
    if (empty($parent_name)) $errors[] = "Parent name is required";
    if (empty($baby_name)) $errors[] = "Baby name is required";
    if (empty($birthdate)) $errors[] = "Birthdate is required";
    if (empty($barangay)) $errors[] = "Barangay information not found";
    if (empty($place_of_birth)) $errors[] = "Place of birth is required";
    if (empty($address)) $errors[] = "Address is required";
    if (empty($health_condition)) $errors[] = "Health Condition is required";
    if (empty($gender)) $errors[] = "Gender is required";
    if (empty($birth_height)) $errors[] = "Birth height is required";
    if (empty($birth_weight)) $errors[] = "Birth weight is required";
    if (empty($contact_no)) $errors[] = "Contact Number is required";

    // Validate vaccine dates
    foreach ($vaccine_dates as $date) {
        if (!empty($date) && strtotime($date) > strtotime('today')) {
            $errors[] = "Vaccine date cannot be in the future";
            break;
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert baby record
            $stmt = $pdo->prepare("INSERT INTO babies 
                                  (user_id, parent_name, baby_name, birthdate, barangay, gender, 
                                   place_of_birth, address, health_condition, birth_weight, birth_height, contact_no) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $user_id,
                $parent_name,
                $baby_name,
                $birthdate,
                $barangay,
                $gender,
                $place_of_birth,
                $address,
                $health_condition,
                $birth_weight,
                $birth_height,
                $contact_no
            ]);

            $baby_id = $pdo->lastInsertId();
            
            // Insert vaccine records if any
            // These come from your form submission
            $vaccines = $_POST['vaccines'] ?? []; // Array of vaccine types
            $vaccine_dates = $_POST['vaccine_dates'] ?? []; // Array of dates

            foreach ($vaccines as $index => $vaccine_type) {
                if (!empty($vaccine_type) && !empty($vaccine_dates[$index])) {
                    $stmt = $pdo->prepare("INSERT INTO schedule 
                                        (baby_id, user_id, schedule_id, type_of_vaccine, schedule_date, status) 
                                        VALUES (?, ?, ?, ?, ?, 'Completed')");
                    $stmt->execute([
                        $baby_id,
                        $user_id,
                        $schedule_id,
                        $vaccine_type,  // Now using the vaccine type from the form
                        $vaccine_dates[$index]
                    ]);
                }
            }
            
            $pdo->commit();
            
            $_SESSION['message'] = 'Baby record and vaccines added successfully!';
            header('Location: baby_record.php');
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
// Fetch babies only from the user's barangay
try {
    $stmt = $pdo->prepare("SELECT * FROM babies WHERE barangay = ? ORDER BY baby_name ASC");
    $stmt->execute([$user_barangay]);
    $babies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $babies = [];
    $errors[] = "Failed to fetch baby records: " . $e->getMessage();
}

// Fetch all available vaccines for the dropdown
try {
    $vaccine_stmt = $pdo->query("SELECT id, vaccine_type FROM vaccine_inventory ORDER BY vaccine_type ASC");
    $vaccines = $vaccine_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Remove duplicates by vaccine_type
    $all_vaccines = [];
    $unique_types = [];
    foreach ($vaccines as $vaccine) {
        if (!in_array($vaccine['vaccine_type'], $unique_types)) {
            $unique_types[] = $vaccine['vaccine_type'];
            $all_vaccines[] = $vaccine;
        }
    }
} catch (PDOException $e) {
    $all_vaccines = [];
    $errors[] = "Failed to fetch vaccine list: " . $e->getMessage();
}

// Check for messages
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$errors = $errors ?? [];
// Get search parameters
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_field = isset($_GET['search_field']) ? $_GET['search_field'] : 'all';

// Build the SQL query based on search parameters
$sql = "SELECT * FROM babies WHERE barangay = ?";
$params = [$user_barangay];

if (!empty($search_term)) {
    switch ($search_field) {
        case 'baby_name':
            $sql .= " AND baby_name LIKE ?";
            $params[] = "%{$search_term}%";
            break;
        case 'parent_name':
            $sql .= " AND parent_name LIKE ?";
            $params[] = "%{$search_term}%";
            break;
        case 'all':
        default:
            $sql .= " AND (baby_name LIKE ? OR parent_name LIKE ?)";
            $params[] = "%{$search_term}%";
            $params[] = "%{$search_term}%";
            break;
    }
}

$sql .= " ORDER BY baby_name ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $babies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error searching records: " . $e->getMessage();
    $babies = [];
}


// Count results for display
$total_records = count($babies);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/user/baby_record.css">
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <title>Barangay Baby Records</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Fredoka+One&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<header class="header-glass">
    <h1>
        <a href="dashboard.php" class="logo-link">
            <img src="../../img/logo1.png" alt="VaxiBloom Logo" class="logo-img">VaxiBloom
        </a>
    </h1>

    <nav id="nav-menu">
        <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'parent_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="appointment.php" class="<?= basename($_SERVER['PHP_SELF']) == 'appointment.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-check"></i> Appointment
        </a>
        <a href="baby_record.php" class="<?= basename($_SERVER['PHP_SELF']) == 'baby_record.php' ? 'active' : '' ?>">
            <i class="fas fa-baby"></i> Baby Records
        </a>
        <a href="contacts.php" class="<?= basename($_SERVER['PHP_SELF']) == 'contacts.php' ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i> Contact us
        </a>
        <a href="vaccine_facts.php" class="<?= basename($_SERVER['PHP_SELF']) == 'vaccine_facts.php' ? 'active' : '' ?>">
            <i class="fas fa-syringe"></i> Vaccine Facts
        </a>
        <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i> Profile
        </a>
        <a href="../../index.php" class="signout">
            <i class="fas fa-sign-out-alt"></i> Sign out
        </a>
    </nav>
    <button class="menu-toggle" aria-label="Toggle navigation">
        <i class="fas fa-bars"></i>
    </button>
</header>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h2>Baby Records - <?= htmlspecialchars($user_barangay) ?></h2>
        
        <!-- Search Form -->
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <form method="GET" action="baby_record.php" class="d-flex gap-2 align-items-center">
                <!-- Preserve other GET parameters if any -->
                <?php if (isset($_GET['page'])): ?>
                    <input type="hidden" name="page" value="<?= htmlspecialchars($_GET['page']) ?>">
                <?php endif; ?>
                
                <!-- Search Input Group -->
                <div class="input-group" style="min-width: 300px;">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           name="search" 
                           value="<?= htmlspecialchars($search_term) ?>"
                           placeholder="Search by baby name or parent name...">
                    <select name="search_field" class="form-select" style="max-width: 150px;">
                        <option value="all" <?= $search_field === 'all' ? 'selected' : '' ?>>All Fields</option>
                        <option value="baby_name" <?= $search_field === 'baby_name' ? 'selected' : '' ?>>Baby Name</option>
                        <option value="parent_name" <?= $search_field === 'parent_name' ? 'selected' : '' ?>>Parent Name</option>
                    </select>
                </div>
                
                <!-- Search Button -->
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-search"></i>
                </button>
                
                <!-- Clear Search Button -->
                <?php if (!empty($search_term)): ?>
                    <a href="baby_record.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
            
            <!-- Add New Baby Button -->
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBabyModal">
                <i class="fas fa-plus"></i> Add New Baby
            </button>
        </div>
    </div>

    <!-- Search Results Info -->
    <?php if (!empty($search_term)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            Search results for "<?= htmlspecialchars($search_term) ?>" 
            <?php if ($search_field !== 'all'): ?>
                in <?= ucfirst(str_replace('_', ' ', $search_field)) ?>
            <?php endif; ?>
            - Found <?= $total_records ?> record(s)
        </div>
    <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Baby Name</th>
                                <th>Parent Name</th>
                                <th>Birthdate</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Place of Birth</th>
                                <th>Address</th>
                                <th>Health Condition</th>
                                <th>Birth Height</th>
                                <th>Birth Weight</th>
                                <th>Contact Number</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($babies as $index => $baby): 
                                $birthDate = new DateTime($baby['birthdate']);
                                $today = new DateTime();
                                $age = $today->diff($birthDate);
                            ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($baby['baby_name']) ?></td>
                                <td><?= htmlspecialchars($baby['parent_name']) ?></td>
                                <td><?= $birthDate->format('M d, Y') ?></td>
                                <td><?= $age->y ?>y <?= $age->m ?>m <?= $age->d ?>d</td>
                                <td><?= htmlspecialchars($baby['gender']) ?></td>
                                <td><?= htmlspecialchars($baby['place_of_birth']) ?></td>
                                <td><?= htmlspecialchars($baby['address']) ?></td>
                                <td><?= htmlspecialchars($baby['health_condition']) ?></td>
                                <td><?= htmlspecialchars($baby['birth_height']) ?> cm</td>
                                <td><?= htmlspecialchars($baby['birth_weight']) ?> kg</td>
                                <td><?= htmlspecialchars($baby['contact_no']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning edit-baby-btn" 
                                            data-baby-id="<?= $baby['baby_id'] ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editBabyModal">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($babies)): ?>
                            <tr>
                                <td colspan="11" class="text-center">No baby records found in <?= htmlspecialchars($user_barangay) ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Baby Modal -->
<div class="modal fade" id="addBabyModal" tabindex="-1" aria-labelledby="addBabyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addBabyModalLabel">Add New Baby</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="baby_record.php">
                <div class="modal-body">
                    <!-- Baby Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Parent Name</label>
                                <input type="text" class="form-control" placeholder="Parent's Fullname" name="parent_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Baby Name</label>
                                <input type="text" class="form-control" placeholder="Baby's Fullname" name="baby_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Birthdate</label>
                                <input type="date" class="form-control" name="birthdate" required max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" placeholder="Permanent Address" name="address" required>
                            </div>                           
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Place of Birth</label>
                                <input type="text" class="form-control" placeholder="Baby's place of birth" name="place_of_birth" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Health Condition</label>
                                <input type="text" class="form-control" placeholder="Specify Baby's condition" name="health_condition" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Birth Height (cm)</label>
                                <input type="number" class="form-control" name="birth_height" min="0" step="0.1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Birth Weight (kg)</label>
                                <input type="number" class="form-control" name="birth_weight" min="0" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" name="contact_no" pattern="[0-9]{11}" placeholder="09XXXXXXXXX" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vaccine Section -->
                    <div class="card mt-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Vaccine Information (if already vaccinated)</h6>
                        </div>
                        <div class="card-body">
                            <div id="vaccineContainer">
                                <div class="vaccine-row mb-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Vaccine</label>
                                            <select name="vaccines[]" class="form-select">
                                                <option value="">Select Vaccine</option>
                                                <?php foreach ($all_vaccines as $vaccine): ?>
                                                    <option value="<?= htmlspecialchars($vaccine['vaccine_type']) ?>">
                                                        <?= htmlspecialchars($vaccine['vaccine_type']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">Date Schedule</label>
                                            <input type="date" name="vaccine_dates[]" class="form-control" max="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div class="col-md-1 d-flex align-items-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-vaccine-btn" disabled>
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="addVaccineBtn" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="fas fa-plus"></i> Add Another Vaccine
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Baby Details Modal -->
    <!-- Baby Details Modal -->
<div class="modal fade" id="babyDetailsModal" tabindex="-1" aria-labelledby="babyDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="babyDetailsModalLabel">Baby Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="babyDetailsContent">
                <!-- Content will be loaded via AJAX -->
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading baby details...</p>
                </div>
            </div>
            <div class="modal-footer no-print">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning edit-baby-btn">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button type="button" class="btn btn-primary print-btn">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Print Section (hidden until print) -->
<div id="printSection" class="print-section"></div>

    <!-- Edit Baby Modal -->
    <div class="modal fade" id="editBabyModal" tabindex="-1" aria-labelledby="editBabyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBabyModalLabel">Edit Baby Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editBabyForm">
                    <input type="hidden" id="edit_baby_id" name="baby_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_parent_name" class="form-label">Parent Name</label>
                            <input type="text" class="form-control" id="edit_parent_name" name="parent_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_baby_name" class="form-label">Baby Name</label>
                            <input type="text" class="form-control" id="edit_baby_name" name="baby_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_birthdate" class="form-label">Birthdate</label>
                            <input type="date" class="form-control" id="edit_birthdate" name="birthdate" required max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gender</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="gender" id="edit_genderMale" value="Male">
                                    <label class="form-check-label" for="edit_genderMale">Male</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="gender" id="edit_genderFemale" value="Female">
                                    <label class="form-check-label" for="edit_genderFemale">Female</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="edit_address" name="address" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_place_of_birth" class="form-label">Place of Birth</label>
                            <input type="text" class="form-control" id="edit_place_of_birth" name="place_of_birth" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_health_condition" class="form-label">Health Condition</label>
                            <input type="text" class="form-control" id="edit_health_condition" name="health_condition" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_birth_height" class="form-label">Birth Height (cm)</label>
                            <input type="number" class="form-control" id="edit_birth_height" name="birth_height" min="0" step="0.1" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_birth_weight" class="form-label">Birth Weight (kg)</label>
                            <input type="number" class="form-control" id="edit_birth_weight" name="birth_weight" min="0" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_contact_no" class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" id="edit_contact_no" name="contact_no" pattern="[0-9]{11}" placeholder="09XXXXXXXXX" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
     const modalElement = document.getElementById('editBabyModal');
    modalElement.addEventListener('hidden.bs.modal', function () {
    location.reload();
  });
    $(document).ready(function() {
    // Mobile menu toggle
    $('.menu-toggle').click(function() {
        $('#nav-menu').toggleClass('active');
        $(this).toggleClass('active');
    });

    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle edit baby button click
    $(document).on('click', '.edit-baby-btn', function(e) {
        e.stopPropagation();
        var babyId = $(this).data('baby-id');
        loadBabyDataForEdit(babyId);
    });

    // Handle edit form submission
    $('#editBabyForm').on('submit', function(e) {
        e.preventDefault();
        saveBabyChanges();
    });

    // ===== VACCINE MANAGEMENT =====
    // Add vaccine row (using event delegation)
    $(document).on('click', '#addVaccineBtn', function() {
        const newRow = `
            <div class="vaccine-row mb-3">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Vaccine</label>
                        <select name="vaccines[]" class="form-select">
                            <option value="">Select Vaccine</option>
                            <?php foreach ($all_vaccines as $vaccine): ?>
                                <option value="<?= htmlspecialchars($vaccine['vaccine_type']) ?>">
                                    <?= htmlspecialchars($vaccine['vaccine_type']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Date Schedule</label>
                        <input type="date" name="vaccine_dates[]" class="form-control" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-vaccine-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#vaccineContainer').append(newRow);
        
        // Enable all remove buttons
        $('.remove-vaccine-btn').prop('disabled', false);
    });

    // Remove vaccine row (using event delegation)
    $(document).on('click', '.remove-vaccine-btn', function() {
        $(this).closest('.vaccine-row').remove();
        
        // If only one row left, disable its remove button
        if ($('.vaccine-row').length === 1) {
            $('.remove-vaccine-btn').prop('disabled', true);
        }
    });

    // Rest of your code (baby details, print, etc.)
    $(document).on('click', 'tbody tr', function(e) {
        if ($(e.target).closest('.edit-baby-btn').length) return;
        const babyId = $(this).find('.edit-baby-btn').data('baby-id');
        if (babyId) loadBabyDetails(babyId);
    });

});


function loadBabyDetails(babyId) {
    $.ajax({
        url: 'get_baby_details.php',
        type: 'GET',
        data: { baby_id: babyId },
        success: function(response) {
            if (response.success) {
                const baby = response.data.baby;
                const vaccines = response.data.vaccines;

                const organizedVaccines = {};

                const vaccineDisplayOrder = [
                    'BCG',
                    'Hepatitis_B',
                    'Pentavalent_Vaccine',
                    'Oral_Polio_Vaccine',
                    'Inactivated_Polio_Vaccine',
                    'Pneumococcal_Conjugate_Vaccine',
                    'Measles_Mumps_Rubella',
                    { name: 'OTHER VACCINES', isHeader: true },
                ];

                // Prepare grouping of vaccines
                vaccines.forEach(vaccine => {
                    const baseName = vaccine.type_of_vaccine.replace(/_(\d+)(st|nd|rd|th)_dose/i, '').trim();

                    if (!organizedVaccines[baseName]) {
                        organizedVaccines[baseName] = {
                            doses: [],
                            dates: []
                        };
                    }

                    let doseMatch = vaccine.type_of_vaccine.match(/_(\d+)(st|nd|rd|th)_dose/i);
                    let doseNumber;

                    if (doseMatch) {
                        doseNumber = parseInt(doseMatch[1]);
                    } else {
                        // No suffix? Treat each as sequential dose
                        doseNumber = organizedVaccines[baseName].doses.length + 1;
                    }

                    organizedVaccines[baseName].doses.push(doseNumber);

                    organizedVaccines[baseName].dates.push({
                        date: new Date(vaccine.schedule_date).toLocaleDateString(),
                        administeredBy: vaccine.vaccinated_by || '-'
                    });
                });

                // Append "other" vaccine names not in predefined list
                Object.keys(organizedVaccines).forEach(vaccineName => {
                    const alreadyListed = vaccineDisplayOrder.some(item =>
                        typeof item === 'string' && item === vaccineName
                    );
                    if (!alreadyListed) {
                        vaccineDisplayOrder.push(vaccineName);
                    }
                });

                function getOrdinalSuffix(num) {
                    const j = num % 10, k = num % 100;
                    if (j === 1 && k !== 11) return 'st';
                    if (j === 2 && k !== 12) return 'nd';
                    if (j === 3 && k !== 13) return 'rd';
                    return 'th';
                }

                function formatVaccineName(name) {
                    return name.replace(/_/g, ' ')
                        .replace(/\b\w/g, l => l.toUpperCase())
                        .replace(/Vaccine/g, '')
                        .trim();
                }

                let vaccinesHtml = `
                    <div class="vaccine-records" style="margin-top: 30px;">
                        <h4 style="color: #003865; margin-bottom: 15px;">Immunization Schedule</h4>
                        <table style="width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 14px;">
                            <thead>
                                <tr style="background-color: #003865; color: #fff;">
                                    <th style="padding: 10px; border: 1px solid #ccc;">Bakuna</th>
                                    <th style="padding: 10px; border: 1px solid #ccc;">Doses</th>
                                    <th style="padding: 10px; border: 1px solid #ccc;">Petsa ng Bakuna</th>
                                    <th style="padding: 10px; border: 1px solid #ccc;">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                vaccineDisplayOrder.forEach(item => {
                    if (typeof item === 'object' && item.isHeader) {
                        vaccinesHtml += `
                            <tr style="background-color: #ffe8a1;">
                                <td colspan="4" style="padding: 8px; border: 1px solid #ccc; font-weight: bold;">
                                    ${item.name.replace(/_/g, ' ')}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4">
                                    <div class="row g-2 m-1">
                                        <div class="col-md-4">
                                            <input type="text" class="form-control" placeholder="Vaccine Name" id="otherVaccineName">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="date" class="form-control" id="otherVaccineDate">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" placeholder="Remarks" id="otherVaccineRemarks">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-success w-100" id="saveOtherVaccineBtn">Save</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        `;
                    } else {
                        const vaccineName = item;
                        const vaccineData = organizedVaccines[vaccineName] || { doses: [], dates: [] };

                        const uniqueDoses = [...new Set(vaccineData.doses)].sort((a, b) => a - b);
                        const doseText = uniqueDoses.length > 0 ?
                            uniqueDoses.map(d => `${d}${getOrdinalSuffix(d)} dose`).join(', ') :
                            '-';

                        const datesText = vaccineData.dates.length > 0 ?
                            vaccineData.dates.map(d => d.date).join('<br>') :
                            '-';

                        const remarksText = vaccineData.dates.length > 0 ?
                            vaccineData.dates.map(d => d.administeredBy).join('<br>') :
                            '-';

                        vaccinesHtml += `
                            <tr>
                                <td style="padding: 8px; border: 1px solid #ccc;">${formatVaccineName(vaccineName)}</td>
                                <td style="padding: 8px; border: 1px solid #ccc;">${doseText}</td>
                                <td style="padding: 8px; border: 1px solid #ccc;">${datesText}</td>
                                <td style="padding: 8px; border: 1px solid #ccc;">${remarksText}</td>
                            </tr>
                        `;
                    }
                });

                vaccinesHtml += `
                            </tbody>
                        </table>
                    </div>
                `;

                const detailsHtml = `
                    <div style="border: 2px solid #003865; padding: 20px; font-family: Arial, sans-serif; font-size: 14px; color: #000;">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <h2 style="color: #003865; margin: 0;">IMMUNIZATION CARD</h2>
                            <p style="color: #666; font-size: 12px;">Pag Kumpleto, Protektado</p>
                        </div>
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                            <tr>
                                <td style="padding: 5px;"><strong>Name:</strong> ${baby.baby_name}</td>
                                <td style="padding: 5px;"><strong>Parent's Name:</strong> ${baby.parent_name}</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px;"><strong>Date of Birth:</strong> ${baby.formatted_birthdate}</td>
                                <td style="padding: 5px;"><strong>Contact No:</strong> ${baby.contact_no}</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px;"><strong>Place of Birth:</strong> ${baby.place_of_birth}</td>
                                <td style="padding: 5px;"><strong>Birth Height:</strong> ${baby.birth_height} cm</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px;"><strong>Address:</strong> ${baby.address}</td>
                                <td style="padding: 5px;"><strong>Birth Weight:</strong> ${baby.birth_weight} kg</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px;"><strong>Sex:</strong> ${baby.gender}</td>
                                <td style="padding: 5px;"><strong>Health Condition:</strong> ${baby.health_condition}</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px;"><strong>Barangay:</strong> ${baby.barangay}</td>
                                <td style="padding: 5px;"><strong>Age:</strong> ${baby.age}</td>
                            </tr>
                        </table>
                        ${vaccinesHtml}
                    </div>
                `;

                $('#babyDetailsContent').html(detailsHtml);

                const detailsModal = new bootstrap.Modal(document.getElementById('babyDetailsModal'));
                detailsModal.show();
                $('#babyDetailsModal').data('baby_id', babyId);
            } else {
                alert('Failed to load baby details: ' + (response.message || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            alert('Error loading baby details: ' + error);
        }
    });
}

// 💾 Handle "Other Vaccine" Save
$(document).off('click', '#saveOtherVaccineBtn').on('click', '#saveOtherVaccineBtn', function () {
    const babyId = $('#babyDetailsModal').data('baby_id');
    const vaccineName = $('#otherVaccineName').val().trim();
    const date = $('#otherVaccineDate').val();
    const remarks = $('#otherVaccineRemarks').val().trim();

    if (!vaccineName || !date) {
        alert("Please fill in both the vaccine name and the date.");
        return;
    }

    $.ajax({
        url: 'get_baby_details.php',
        type: 'POST',
        data: {
            baby_id: babyId,
            vaccine_name: vaccineName,
            schedule_date: date,
            remarks: remarks
        },
        success: function (response) {
            const res = JSON.parse(response);
            if (res.success) {
                alert("Other vaccine saved!");
                loadBabyDetails(babyId); // reload the modal with updated data
            } else {
                alert("Failed to save: " + res.message);
            }
        },
        error: function () {
            alert("Error saving vaccine.");
        }
    });
});


// Edit button click handler - place after loadBabyDetails() but before print button handler
$(document).on('click', '.edit-baby-btn', function() {
    const babyId = $('#babyDetailsModal').data('baby_id');
    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('babyDetailsModal'));
    detailsModal.hide();
    
    $.ajax({
        url: 'get_baby_details.php',
        type: 'GET',
        data: { baby_id: babyId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const baby = response.data.baby;
                
                // Populate edit form fields...
                // (Keep all the field population code from the previous example)
                
                const editModal = new bootstrap.Modal(document.getElementById('editBabyModal'));
                editModal.show();
            }
        }
    });
});
// Update the print function to use the same layout as baby details modal
$(document).on('click', '.print-btn', function() {
    // Get the baby ID from the modal
    const babyId = $('#babyDetailsModal').data('baby_id');
    
    // Fetch the data again to ensure we have fresh data for printing
    $.ajax({
        url: 'get_baby_details.php',
        type: 'GET',
        data: { baby_id: babyId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const baby = response.data.baby;
                const vaccines = response.data.vaccines;
                
                // First, organize vaccines by their base name (without dose numbers)
                const organizedVaccines = {};
                
                // List of all possible vaccines in the order you want them displayed
                const vaccineDisplayOrder = [
                    // Infant vaccines
                    'BCG',
                    'Hepatitis_B',
                    'Pentavalent_Vaccine',
                    'Oral_Polio_Vaccine',
                    'Inactivated_Polio_Vaccine',
                    'Pneumococcal_Conjugate_Vaccine',
                    'Measles_Mumps_Rubella',
                    
                    // Other Vaccines (special entry)
                    { name: 'OTHER VACCINES', isHeader: true },
                ];
                
                // Initialize all vaccines with empty data
                vaccineDisplayOrder.forEach(vaccine => {
                    if (typeof vaccine === 'string') {
                        const baseName = vaccine.replace(/_(\d+)$/i, '').replace(/_(\d+)(st|nd|rd|th)_dose/i, '').trim();
                        if (!organizedVaccines[baseName]) {
                            organizedVaccines[baseName] = {
                                doses: [],
                                dates: []
                            };
                        }
                    }
                });
                // Now add the actual vaccine data
                    vaccines.forEach(vaccine => {
                        const baseName = vaccine.type_of_vaccine.replace(/_(\d+)(st|nd|rd|th)_dose/i, '').trim();

                        if (!organizedVaccines[baseName]) {
                            organizedVaccines[baseName] = {
                                doses: [],
                                dates: []
                            };
                        }

                        const doseMatch = vaccine.type_of_vaccine.match(/_(\d+)(st|nd|rd|th)_dose/i);
                        let doseNumber;

                        if (doseMatch) {
                            doseNumber = parseInt(doseMatch[1]);
                        } else {
                            doseNumber = organizedVaccines[baseName].doses.length + 1;
                        }

                        organizedVaccines[baseName].doses.push(doseNumber);
                        organizedVaccines[baseName].dates.push({
                            date: new Date(vaccine.schedule_date).toLocaleDateString(),
                            administeredBy: vaccine.vaccinated_by || '-'
                        });
                    });

                    // Append custom vaccines not in the default order
                    Object.keys(organizedVaccines).forEach(vaccineName => {
                        const alreadyListed = vaccineDisplayOrder.some(item =>
                            typeof item === 'string' && item === vaccineName
                        );
                        if (!alreadyListed) {
                            vaccineDisplayOrder.push(vaccineName);
                        }
                    });
                // Helper functions
                function getOrdinalSuffix(num) {
                    const j = num % 10;
                    const k = num % 100;
                    if (j === 1 && k !== 11) return 'st';
                    if (j === 2 && k !== 12) return 'nd';
                    if (j === 3 && k !== 13) return 'rd';
                    return 'th';
                }

                function formatVaccineName(name) {
                    return name.replace(/_/g, ' ')
                               .replace(/\b\w/g, l => l.toUpperCase())
                               .replace(/Vaccine/g, '')
                               .trim();
                }

                // Create the vaccine schedule table for print (matches modal exactly)
                let vaccinesHtml = `
                    <div class="vaccine-records" style="margin-top: 30px; page-break-inside: avoid;">
                        <h4 style="color: #003865; margin-bottom: 15px;">Immunization Schedule</h4>
                        <table style="width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 14px;">
                            <thead>
                                <tr style="background-color: #003865; color: #fff;">
                                    <th style="padding: 10px; border: 1px solid #ccc;">Bakuna</th>
                                    <th style="padding: 10px; border: 1px solid #ccc;">Doses</th>
                                    <th style="padding: 10px; border: 1px solid #ccc;">Petsa ng Bakuna</th>
                                    <th style="padding: 10px; border: 1px solid #ccc;">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                // Generate table rows in the specified order (matches modal exactly)
                vaccineDisplayOrder.forEach(item => {
                    if (typeof item === 'object' && item.isHeader) {
                        // Add the school-aged children header
                        vaccinesHtml += `
                            <tr style="background-color: #ffe8a1;">
                                <td style="padding: 8px; border: 1px solid #ccc; font-weight: bold;" colspan="4">
                                    ${item.name.replace(/_/g, ' ')}
                                </td>
                            </tr>
                        `;
                    } else {
                        const vaccineName = item;
                        const vaccineData = organizedVaccines[vaccineName] || { doses: [], dates: [] };
                        
                        const uniqueDoses = [...new Set(vaccineData.doses)].sort((a, b) => a - b);
                        const doseText = uniqueDoses.length > 0 ? 
                            uniqueDoses.map(d => `${d}${getOrdinalSuffix(d)} dose`).join(', ') : 
                            '-';
                        
                        const datesText = vaccineData.dates.length > 0 ? 
                            vaccineData.dates.map(d => d.date).join('<br>') : 
                            '-';
                        
                        const remarksText = vaccineData.dates.length > 0 ? 
                            vaccineData.dates.map(d => d.administeredBy).join('<br>') : 
                            '-';
                        
                        vaccinesHtml += `
                            <tr>
                                <td style="padding: 8px; border: 1px solid #ccc;">${formatVaccineName(vaccineName)}</td>
                                <td style="padding: 8px; border: 1px solid #ccc;">${doseText}</td>
                                <td style="padding: 8px; border: 1px solid #ccc;">${datesText}</td>
                                <td style="padding: 8px; border: 1px solid #ccc;">${remarksText}</td>
                            </tr>
                        `;
                    }
                });

                vaccinesHtml += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                const printContent = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Immunization Card - ${baby.baby_name}</title>
                        <style>
                            body { 
                                font-family: Arial, sans-serif; 
                                margin: 0; 
                                padding: 20px; 
                                color: #000; 
                                background-color: #fff;
                            }
                            .print-container { 
                                max-width: 1000px; 
                                margin: 0 auto; 
                                border: 2px solid #003865; 
                                padding: 20px;
                                background-color: #fff;
                            }
                            .print-header { 
                                text-align: center; 
                                margin-bottom: 20px; 
                            }
                            .print-header h2 { 
                                color: #003865; 
                                margin: 0; 
                                font-size: 24px;
                            }
                            .print-header p { 
                                color: #666; 
                                font-size: 12px; 
                                margin: 0; 
                            }
                            table { 
                                width: 100%; 
                                border-collapse: collapse; 
                                margin-bottom: 20px; 
                                font-size: 14px;
                            }
                            td { 
                                padding: 5px; 
                                vertical-align: top; 
                                border: none;
                            }
                            .vaccine-records { 
                                margin-top: 30px; 
                            }
                            .vaccine-records h4 { 
                                color: #003865; 
                                margin-bottom: 15px; 
                                font-size: 16px;
                            }
                            .vaccine-records th, 
                            .vaccine-records td { 
                                border: 1px solid #ccc; 
                                padding: 8px; 
                                text-align: left; 
                            }
                            .vaccine-records thead tr { 
                                background-color: #003865; 
                                color: #fff; 
                            }
                            .school-aged-header {
                                background-color: #ffe8a1 !important;
                                font-weight: bold;
                            }
                            @page { 
                                size: auto; 
                                margin: 10mm; 
                            }
                            @media print {
                                body { 
                                    margin: 0; 
                                    padding: 0; 
                                    background-color: #fff;
                                }
                                .print-container { 
                                    border: none; 
                                    padding: 0; 
                                    margin: 0;
                                    width: 100%;
                                }
                                .no-print { 
                                    display: none !important; 
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="print-container">
                            <!-- Header -->
                            <div class="print-header">
                                <h2>IMMUNIZATION CARD</h2>
                                <p>Pag Kumpleto, Protektado</p>
                            </div>

                            <!-- Baby Information -->
                            <table>
                                <tr>
                                    <td><strong>Name:</strong> ${baby.baby_name}</td>
                                    <td><strong>Parent's Name:</strong> ${baby.parent_name}</td>
                                </tr>
                                <tr>
                                    <td><strong>Date of Birth:</strong> ${baby.formatted_birthdate}</td>
                                    <td><strong>Contact No:</strong> ${baby.contact_no}</td>
                                </tr>
                                <tr>
                                    <td><strong>Place of Birth:</strong> ${baby.place_of_birth}</td>
                                    <td><strong>Birth Height:</strong> ${baby.birth_height} cm</td>
                                </tr>
                                <tr>
                                    <td><strong>Address:</strong> ${baby.address}</td>
                                    <td><strong>Birth Weight:</strong> ${baby.birth_weight} kg</td>
                                </tr>
                                <tr>
                                    <td><strong>Sex:</strong> ${baby.gender}</td>
                                    <td><strong>Health Condition:</strong> ${baby.health_condition}</td>
                                </tr>
                                <tr>
                                    <td><strong>Barangay:</strong> ${baby.barangay}</td>
                                    <td><strong>Age:</strong> ${baby.age}</td>
                                </tr>
                            </table>
                            
                            ${vaccinesHtml}
                        </div>
                    </body>
                    </html>
                `;

                // Open print window
                const printWindow = window.open('', '_blank');
                printWindow.document.write(printContent);
                printWindow.document.close();
                // Wait for content to load before printing
                printWindow.onload = function() {
                    setTimeout(function() {
                        printWindow.print();
                    }, 200);
                };
            } else {
                alert('Failed to load baby details for printing: ' + (response.message || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            alert('Error loading baby details for printing: ' + error);
        }
    });
});


        // Helper function to calculate age
        function calculateAge(birthdate) {
            const birthDate = new Date(birthdate);
            const today = new Date();
            let years = today.getFullYear() - birthDate.getFullYear();
            let months = today.getMonth() - birthDate.getMonth();
            
            if (months < 0 || (months === 0 && today.getDate() < birthDate.getDate())) {
                years--;
                months += 12;
            }
            
            return `${years} years, ${months} months`;
        }
        function filterTable() {
    const searchInput = document.getElementById('searchInput');
    const searchFilter = document.getElementById('searchFilter');
    const table = document.getElementById('babiesTable');
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = tbody.getElementsByTagName('tr');
    const noResultsRow = document.getElementById('noResultsRow');
    const clearButton = document.getElementById('clearSearch');
    const resultCount = document.getElementById('resultCount');
    const totalCount = document.getElementById('totalCount');
    
    const searchTerm = searchInput.value.toLowerCase().trim();
    const filterType = searchFilter.value;
    
    let visibleCount = 0;
    const totalRows = rows.length - 1; // Exclude the "no results" row
    
    // Show/hide clear button
    clearButton.style.display = searchTerm ? 'inline-block' : 'none';
    
    for (let i = 0; i < rows.length - 1; i++) { // Exclude the "no results" row
        const row = rows[i];
        const babyName = row.cells[1].textContent.toLowerCase();
        const parentName = row.cells[2].textContent.toLowerCase();
        
        let shouldShow = false;
        
        if (!searchTerm) {
            shouldShow = true;
        } else {
            switch (filterType) {
                case 'baby_name':
                    shouldShow = babyName.includes(searchTerm);
                    break;
                case 'parent_name':
                    shouldShow = parentName.includes(searchTerm);
                    break;
                case 'all':
                default:
                    shouldShow = babyName.includes(searchTerm) || parentName.includes(searchTerm);
                    break;
            }
        }
        
        if (shouldShow) {
            row.style.display = '';
            visibleCount++;
            // Update row number
            row.cells[0].textContent = visibleCount;
        } else {
            row.style.display = 'none';
        }
    }
    
    // Show/hide no results message
    noResultsRow.style.display = visibleCount === 0 && searchTerm ? '' : 'none';
    
    // Update counters
    resultCount.textContent = visibleCount;
    totalCount.textContent = totalRows;
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('searchFilter').value = 'all';
    filterTable();
    document.getElementById('searchInput').focus();
}

// Add some smooth animations and enhanced UX
document.getElementById('searchInput').addEventListener('input', function() {
    // Add a small delay to prevent excessive filtering on fast typing
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(filterTable, 300);
});

// Highlight search terms (optional enhancement)
function highlightSearchTerm() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
    if (!searchTerm) return;
    
    const tbody = document.getElementById('babiesTable').getElementsByTagName('tbody')[0];
    const rows = tbody.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length - 1; i++) {
        if (rows[i].style.display !== 'none') {
            const babyNameCell = rows[i].cells[1];
            const parentNameCell = rows[i].cells[2];
            
            // Simple highlight (you can enhance this further)
            const babyText = babyNameCell.textContent;
            const parentText = parentNameCell.textContent;
            
            if (babyText.toLowerCase().includes(searchTerm)) {
                babyNameCell.innerHTML = babyText.replace(
                    new RegExp(searchTerm, 'gi'), 
                    '<mark>$&</mark>'
                );
            }
            
            if (parentText.toLowerCase().includes(searchTerm)) {
                parentNameCell.innerHTML = parentText.replace(
                    new RegExp(searchTerm, 'gi'), 
                    '<mark>$&</mark>'
                );
            }
        }
    }
}

// Focus search input on page load (optional, but safe)
$(document).ready(function() {
    document.getElementById('searchInput').focus();
});

// JavaScript version of highlightSearchTerm (if needed)
function highlightSearchTerm(text, searchTerm) {
    if (!searchTerm) return text;
    const regex = new RegExp(searchTerm, 'gi');
    return text.replace(regex, '<mark>$&</mark>');
}

// Example usage in filterTable():
function filterTable() {
    const searchTerm = document.getElementById('searchInput').value.trim();
    // ... (rest of your filtering logic)
    
    // Highlight matches (optional)
    if (searchTerm) {
        const rows = document.querySelectorAll('#babiesTable tbody tr');
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const babyNameCell = row.cells[1];
                const parentNameCell = row.cells[2];
                babyNameCell.innerHTML = highlightSearchTerm(babyNameCell.textContent, searchTerm);
                parentNameCell.innerHTML = highlightSearchTerm(parentNameCell.textContent, searchTerm);
            }
        });
    }
}
// Edit form submission - place at the end of your JavaScript file
$('#editBabyForm').submit(function(e) {
    e.preventDefault();
    const formData = $(this).serialize();
    
    $.ajax({
        url: 'update_baby.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const editModal = bootstrap.Modal.getInstance(document.getElementById('editBabyModal'));
                editModal.hide();
                const babyId = $('#babyDetailsModal').data('baby_id');
                loadBabyDetails(babyId);
            }
        }
    });
});
    </script>
</body>
</html>