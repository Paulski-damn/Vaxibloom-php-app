<?php
session_start();
error_log("POST vaccinated_by: " . ($_POST['vaccinated_by'] ?? 'NOT SET'));

require '../config.php';

// Ensure only BHWs can access
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$bhw_barangay = $_SESSION['barangay'];

// Fetch barangay-specific schedule
$scheduleQuery = $conn->prepare("
    SELECT bs.*, b.name AS barangay_name 
    FROM barangay_schedules bs
    JOIN barangays b ON bs.bs_id = b.barangay_id
    WHERE b.name = ?
    ORDER BY bs.vaccination_month DESC
    LIMIT 1
");
$scheduleQuery->bind_param("s", $bhw_barangay);
$scheduleQuery->execute();
$barangaySchedule = $scheduleQuery->get_result()->fetch_assoc();
$scheduleQuery->close();

if (!$barangaySchedule) {
    $barangaySchedule = [
        'vaccination_month' => date('Y-m', strtotime('+1 month')),
        'week1_days' => '1,2,3,4,5,6,7',
        'week2_days' => '8,9,10,11,12,13,14',
        'week3_days' => '15,16,17,18,19,20,21',
        'week4_days' => '22,23,24,25,26,27,28'
    ];
}

$appointment_month = $barangaySchedule['vaccination_month'];

// Age constraints in months
$vaccine_age_constraints = [
    'BCG' => ['min' => 0, 'max' => 12],
    'Hepatitis_B' => ['min' => 0, 'max' => 12],
    'Pentavalent_Vaccine_1st_dose' => ['min' => 1.5],
    'Pentavalent_Vaccine_2nd_dose' => [
        'min' => 2.5,
        'depends_on' => 'Pentavalent_Vaccine_1st_dose',
        'min_gap' => 1, // Minimum 1 month required
        'gap_message' => 'Pentavalent 2nd dose must be at least 1 month after 1st dose'
    ],
    'Pentavalent_Vaccine_3rd_dose' => [
        'min' => 3.5,
        'depends_on' => 'Pentavalent_Vaccine_2nd_dose',
        'min_gap' => 1 ,// 1 month gap required
        'gap_message' => 'Pentavalent 3rd dose must be at least 1 month after 2nd dose'
    ],
    'Oral_Polio_Vaccine_1st_dose' => ['min' => 1.5],
    'Oral_Polio_Vaccine_2nd_dose' => [
        'min' => 2.5,
        'depends_on' => 'Pentavalent_Vaccine_1st_dose',
        'min_gap' => 1, // 1 month gap required
        'gap_message' => 'Oral Polio Vaccine 2nd dose must be at least 1 month after 1st dose'
    ],
    'Oral_Polio_Vaccine_3rd_dose' => [
        'min' => 3.5,
        'depends_on' => 'Pentavalent_Vaccine_2nd_dose',
        'min_gap' => 1, // 1 month gap required
        'gap_message' => 'Oral Polio Vaccine 3rd dose must be at least 1 month after 2nd dose'
    ],
    'Inactivated_Polio_Vaccine_1st_dose' => ['min' => 3.5],
    'Inactivated_Polio_Vaccine_2nd_dose' => ['min' => 9],
    'Pneumococcal_Conjugate_Vaccine_1st_dose' => ['min' => 1.5],
    'Pneumococcal_Conjugate_Vaccine_2nd_dose' => [
        'min' => 2.5,
        'depends_on' => 'Pneumococcal_Conjugate_Vaccine_1st_dose',
        'min_gap' => 1, // 1 month gap required
        'gap_message' => 'Pneumococcal Conjugate Vaccine 2nd dose must be at least 1 month after 1st dose'
    ],
    'Pneumococcal_Conjugate_Vaccine_3rd_dose' => [
        'min' => 3.5,
        'depends_on' => 'Pneumococcal_Conjugate_Vaccine_2nd_dose',
        'min_gap' => 1, // 1 month gap required
        'gap_message' => 'Pneumococcal Conjugate Vaccine 3rd dose must be at least 1 month after 2nd dose'
    ],    
    'Measles_Mumps_Rubella_1st_dose' => ['min' => 9],
    'Measles_Mumps_Rubella_2nd_dose' => ['min' => 12],
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action_type'])) {
        $schedule_id = $_POST['schedule_id'];
        $action = $_POST['action_type'];

        try {
            $conn->begin_transaction();

            if ($action === 'completed') {
                $vaccinated_by = isset($_POST['vaccinated_by']) ? trim($_POST['vaccinated_by']) : 'Unknown';
                if (empty($vaccinated_by)) {
                    $vaccinated_by = 'Unknown';
                }

                $stmt = $conn->prepare("UPDATE schedule SET status = 'completed', vaccinated_by = ? WHERE schedule_id = ?");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                $stmt->bind_param("si", $vaccinated_by, $schedule_id);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    error_log("Successfully updated schedule ID $schedule_id with $vaccinated_by");
                } else {
                    error_log("No rows updated. Schedule ID might be wrong?");
                }
                
                $stmt->close();

                $_SESSION['success'] = "Appointment marked as vaccinated.";
            } elseif ($action === 'missed') {
                $stmt = $conn->prepare("UPDATE schedule SET status = 'missed' WHERE schedule_id = ?");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['success'] = "Appointment marked as missed.";
            }

            $conn->commit();
            header("Location: appointment.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error: " . $e->getMessage();
            header("Location: appointment.php");
            exit();
        }
    } else {
        // Modified appointment creation code for multiple vaccines
        $baby_id = $_POST['baby_id'] ?? '';
        $vaccine_ids = $_POST['vaccine_id'] ?? [];
        $appointment_week = $_POST['appointment_week'] ?? 1;
        // Validate baby exists and birthdate
        $baby_stmt = $conn->prepare("SELECT baby_name, birthdate FROM babies WHERE baby_id = ?");
        $baby_stmt->bind_param("i", $baby_id);
        $baby_stmt->execute();
        $baby_result = $baby_stmt->get_result();
        $baby = $baby_result->fetch_assoc();
        $baby_stmt->close();

        if (!$baby) {
            $_SESSION['error'] = "Baby not found.";
            header("Location: appointment.php");
            exit();
        }
        // Validate at least one vaccine selected 
        if (empty($vaccine_ids) || !is_array($vaccine_ids)) {
            $_SESSION['error'] = "Please select at least one vaccine.";
            header("Location: appointment.php");
            exit();
        }
        $vaccine_ids = array_filter($vaccine_ids, 'is_numeric');
        // Get available days for the selected week
        switch ($appointment_week) {
            case 1: $week_days = explode(",", $barangaySchedule['week1_days']); break;
            case 2: $week_days = explode(",", $barangaySchedule['week2_days']); break;
            case 3: $week_days = explode(",", $barangaySchedule['week3_days']); break;
            case 4: $week_days = explode(",", $barangaySchedule['week4_days']); break;
            default: $week_days = [];
        }

        $week_days = array_filter(array_map('trim', $week_days));
        if (empty($week_days)) {
            $_SESSION['error'] = "No available days in this week's schedule.";
            header("Location: appointment.php");
            exit();
        }

        $random_day = str_pad($week_days[array_rand($week_days)], 2, "0", STR_PAD_LEFT);
        $schedule_date = "$appointment_month-$random_day";
        // Check age eligibility
        $birthDate = new DateTime($baby['birthdate']);
        $appointmentDate = new DateTime($schedule_date);
        $ageInMonths = $birthDate->diff($appointmentDate)->m + ($birthDate->diff($appointmentDate)->y * 12);

        try {
            $conn->begin_transaction();
            // Process each selected vaccine
            foreach ($vaccine_ids as $vaccine_id) {
                $vaccine_stmt = $conn->prepare("SELECT vaccine_type, quantity FROM vaccine_inventory WHERE id = ? AND barangay = ?");
                $vaccine_stmt->bind_param("is", $vaccine_id, $bhw_barangay);
                $vaccine_stmt->execute();
                $vaccine = $vaccine_stmt->get_result()->fetch_assoc();
                $vaccine_stmt->close();

                if (!$vaccine) {
                    throw new Exception("Vaccine not found in your barangay.");
                }

                $vaccine_type = $vaccine['vaccine_type'];
                // Check age constraints
                if (isset($vaccine_age_constraints[$vaccine_type])) {
                    $minAge = $vaccine_age_constraints[$vaccine_type]['min'];
                    if ($ageInMonths < $minAge) {
                        throw new Exception("Baby is $ageInMonths months old, which is outside the range for $vaccine_type ($minAge months).");
                    }
                }
                // Check for dependent vaccines
                if (isset($vaccine_age_constraints[$vaccine_type]['depends_on'])) {
                    $dependsOn = $vaccine_age_constraints[$vaccine_type]['depends_on'];
                    $requiredGap = $vaccine_age_constraints[$vaccine_type]['min_gap'] ?? 0;
                    
                    // Check if previous dose was completed
                    $prevDoseStmt = $conn->prepare("
                        SELECT schedule_date 
                        FROM schedule 
                        WHERE baby_id = ? 
                        AND type_of_vaccine = ? 
                        AND status = 'completed'
                        ORDER BY schedule_date DESC
                        LIMIT 1
                    ");
                    $prevDoseStmt->bind_param("is", $baby_id, $dependsOn);
                    $prevDoseStmt->execute();
                    $prevDoseResult = $prevDoseStmt->get_result();
                    $prevDose = $prevDoseResult->fetch_assoc();
                    $prevDoseStmt->close();
                    
                    if (!$prevDose) {
                        throw new Exception("Must complete $dependsOn before scheduling $vaccine_type");
                    }
                    
                    // Calculate gap between doses
                    $prevDate = new DateTime($prevDose['schedule_date']);
                    $proposedDate = new DateTime($schedule_date);
                    $gap = $prevDate->diff($proposedDate);
                    $gapMonths = $gap->y * 12 + $gap->m;
                    
                    // Only check minimum gap
                    if ($gapMonths < $requiredGap) {
                        $earliestDate = clone $prevDate;
                        $earliestDate->add(new DateInterval("P{$requiredGap}M"));
                        throw new Exception(
                            $vaccine_age_constraints[$vaccine_type]['gap_message'] ?? 
                            "Minimum gap of $requiredGap month(s) required. Earliest date: " . $earliestDate->format('M d, Y')
                        );
                    }
                }
                // Check if already vaccinated
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM schedule WHERE baby_id = ? AND type_of_vaccine = ? AND status = 'completed'");
                $check_stmt->bind_param("is", $baby_id, $vaccine_type);
                $check_stmt->execute();
                $check_stmt->bind_result($count);
                $check_stmt->fetch();
                $check_stmt->close();

                if ($count > 0) {
                    throw new Exception("$vaccine_type has already been administered.");
                }
                // Check for duplicate pending appointment
                $dup_stmt = $conn->prepare("SELECT COUNT(*) FROM schedule WHERE baby_id = ? AND type_of_vaccine = ? AND status = 'pending'");
                $dup_stmt->bind_param("is", $baby_id, $vaccine_type);
                $dup_stmt->execute();
                $dup_stmt->bind_result($dup_count);
                $dup_stmt->fetch();
                $dup_stmt->close();

                if ($dup_count > 0) {
                    throw new Exception("Pending appointment already exists for $vaccine_type.");
                }
                // Check vaccine availability
                if ($vaccine['quantity'] <= 0) {
                    throw new Exception("No stock for $vaccine_type.");
                }
                // Update vaccine inventory
                $update_stmt = $conn->prepare("UPDATE vaccine_inventory SET quantity = quantity - 1 WHERE id = ?");
                $update_stmt->bind_param("i", $vaccine_id);
                $update_stmt->execute();
                $update_stmt->close();
                // In your appointment creation code, modify the INSERT statement:
                $insert_stmt = $conn->prepare("INSERT INTO schedule (user_id, baby_id, type_of_vaccine, schedule_date, status) VALUES (?, ?, ?, ?, 'pending')");
                $insert_stmt->bind_param("iiss", $_SESSION['user_id'], $baby_id, $vaccine_type, $schedule_date);
                $insert_stmt->execute();
                $insert_stmt->close();
            }

            $conn->commit();
            $_SESSION['success'] = "Appointment(s) scheduled for " . date('F j, Y', strtotime($schedule_date));
            header("Location: appointment.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
            header("Location: appointment.php");
            exit();
        }
    }
}


// Fetch babies in this barangay
$babyQuery = $conn->prepare("
    SELECT b.baby_id, b.baby_name, b.birthdate, GROUP_CONCAT(s.type_of_vaccine) as vaccinated_vaccines
    FROM babies b
    LEFT JOIN schedule s ON b.baby_id = s.baby_id AND s.status = 'completed'
    WHERE b.barangay = ?
    GROUP BY b.baby_id
");
$babyQuery->bind_param("s", $bhw_barangay);
$babyQuery->execute();
$babies = $babyQuery->get_result();

// Fetch vaccines
$vaccineQuery = $conn->prepare("SELECT id, vaccine_type, quantity FROM vaccine_inventory WHERE barangay = ?");
$vaccineQuery->bind_param("s", $bhw_barangay);
$vaccineQuery->execute();
$vaccines = $vaccineQuery->get_result();

// Fetch appointments
$appointmentsQuery = $conn->prepare("
    SELECT s.schedule_id, s.baby_id, b.baby_name, s.type_of_vaccine, s.schedule_date, s.status, s.vaccinated_by
    FROM schedule s
    JOIN babies b ON s.baby_id = b.baby_id
    WHERE b.barangay = ?
    ORDER BY s.schedule_date DESC, s.status
");

$appointmentsQuery->bind_param("s", $bhw_barangay);
$appointmentsQuery->execute();
$allAppointments = $appointmentsQuery->get_result();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/user/appointment.css">
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <title>Appointment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Fredoka+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .vaccinated {
            text-decoration: line-through;
            color: #6c757d;
        }
        .vaccine-option {
            position: relative;
        }
        .vaccine-status {
            font-size: 0.8em;
            font-style: italic;
        }
        .already-vaccinated {
            color: #28a745;
        }
        .week-option {
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .week-option:hover {
            background-color: #f8f9fa;
        }
        .week-option.selected {
            background-color: #e2f0fd;
            border-color: #86b7fe;
        }
        .week-days {
            font-size: 0.9em;
            color: #6c757d;
        }
        .week-radio {
            display: none;
        }
        .modal-lg-custom {
    max-width: 90% !important; 
    width: 90%;
}
    </style>
</head>
<body>
    <!-- Header Section -->
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

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Display success/error messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h4 class="m-0 font-weight-bold">Create New Vaccination Appointment</h4>
                        <button type="button" class="btn btn-dark btn-sm custom-appointments-btn" data-bs-toggle="modal" data-bs-target="#appointmentsModal">
                            <i class="fas fa-calendar-alt"></i> View All Appointments
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="vaccine-schedule mb-4">
                            <h5 class="schedule-title">Vaccination Schedule for <?= htmlspecialchars($bhw_barangay) ?></h5>
                            <p class="mb-1">Month: <strong><?= date('F Y', strtotime($barangaySchedule['vaccination_month'])) ?></strong></p>
                            <p class="mb-1">Available Days:</p>
                            <ul class="list-unstyled">
                                <?php if (!empty($barangaySchedule['week1_days'])): ?>
                                    <li>Week 1: <?= htmlspecialchars($barangaySchedule['week1_days']) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($barangaySchedule['week2_days'])): ?>
                                    <li>Week 2: <?= htmlspecialchars($barangaySchedule['week2_days']) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($barangaySchedule['week3_days'])): ?>
                                    <li>Week 3: <?= htmlspecialchars($barangaySchedule['week3_days']) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($barangaySchedule['week4_days'])): ?>
                                    <li>Week 4: <?= htmlspecialchars($barangaySchedule['week4_days']) ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <form method="POST" action="appointment.php">
                            <div class="mb-4">
                                <label for="baby_id" class="form-label">Select Baby</label>
                                <select class="form-select" name="baby_id" id="babySelect" required>
                                    <option value="" selected disabled>Choose a baby...</option>
                                    <?php while ($baby = $babies->fetch_assoc()): 
                                        $vaccinated = !empty($baby['vaccinated_vaccines']) ? explode(',', $baby['vaccinated_vaccines']) : [];
                                    ?>
                                        <option value="<?= $baby['baby_id'] ?>" 
                                                data-vaccinated='<?= json_encode($vaccinated) ?>'>
                                            <?= htmlspecialchars($baby['baby_name']) ?> 
                                            (DOB: <?= date('M d, Y', strtotime($baby['birthdate'])) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="form-text">Only babies from <?= htmlspecialchars($bhw_barangay) ?> are listed</div>
                            </div>

                            <!-- Replace the vaccine selection section with this -->
                            <div class="card mt-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Vaccine Selection</h6>
                                </div>
                                <div class="card-body" id="vaccineContainer">
                                    <!-- First vaccine select will be here by default -->
                                    <div class="vaccine-row mb-3">
                                        <div class="row">
                                            <div class="col-md-11">
                                                <label class="form-label">Select Vaccine</label>
                                                <select class="form-select vaccine-select" name="vaccine_id[]" required>
                                                    <option value="" selected disabled>Select a vaccine...</option>
                                                    <?php
                                                    $vaccines->data_seek(0); // Reset pointer
                                                    while ($vaccine = $vaccines->fetch_assoc()):
                                                        $vaccineType = htmlspecialchars($vaccine['vaccine_type']);
                                                        $quantity = (int)$vaccine['quantity'];
                                                        $isOutOfStock = $quantity <= 0;
                                                        $isLowStock = $quantity > 0 && $quantity < 30;

                                                        $statusLabel = '';
                                                        if ($isOutOfStock) {
                                                            $statusLabel = ' - No Stock';
                                                        } elseif ($isLowStock) {
                                                            $statusLabel = ' - Low Stock';
                                                        } else {
                                                            $statusLabel = ' - In Stock';
                                                        }

                                                        $optionClass = $isOutOfStock ? 'out-of-stock' : '';
                                                    ?>
                                                        <option value="<?= $vaccine['id'] ?>"
                                                            <?= $isOutOfStock ? 'disabled' : '' ?>
                                                            data-vaccine-type="<?= $vaccineType ?>"
                                                            data-quantity="<?= $quantity ?>"
                                                            class="<?= $optionClass ?>">
                                                            <?= $vaccineType ?> (Stock available: <?= $quantity ?> doses<?= $statusLabel ?>)
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>

                                                <!-- Legend -->
                                                <div class="vaccine-status mt-2">
                                                    <small class="text-muted">
                                                        <strong>Legend:</strong><br>
                                                        • In Stock = 30 doses or more<br>
                                                        • Low Stock = Less than 30 doses<br>
                                                        • No Stock = 0 doses (disabled)
                                                    </small>
                                                </div>
                                            </div>


                                            <div class="col-md-1 d-flex align-items-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-vaccine-btn" disabled>
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="button" id="addVaccineBtn" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-plus"></i> Add Another Vaccine
                                    </button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Select Week for Vaccination</label>
                                <div id="weekOptions">
                                    <?php if (!empty($barangaySchedule['week1_days'])): ?>
                                        <label class="week-option" for="week1">
                                            <input type="radio" class="week-radio" id="week1" name="appointment_week" value="1" required>
                                            <strong>Week 1</strong>
                                            <div class="week-days">Days: <?= htmlspecialchars($barangaySchedule['week1_days']) ?></div>
                                        </label>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($barangaySchedule['week2_days'])): ?>
                                        <label class="week-option" for="week2">
                                            <input type="radio" class="week-radio" id="week2" name="appointment_week" value="2">
                                            <strong>Week 2</strong>
                                            <div class="week-days">Days: <?= htmlspecialchars($barangaySchedule['week2_days']) ?></div>
                                        </label>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($barangaySchedule['week3_days'])): ?>
                                        <label class="week-option" for="week3">
                                            <input type="radio" class="week-radio" id="week3" name="appointment_week" value="3">
                                            <strong>Week 3</strong>
                                            <div class="week-days">Days: <?= htmlspecialchars($barangaySchedule['week3_days']) ?></div>
                                        </label>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($barangaySchedule['week4_days'])): ?>
                                        <label class="week-option" for="week4">
                                            <input type="radio" class="week-radio" id="week4" name="appointment_week" value="4">
                                            <strong>Week 4</strong>
                                            <div class="week-days">Days: <?= htmlspecialchars($barangaySchedule['week4_days']) ?></div>
                                        </label>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <input type="hidden" name="appointment_month" value="<?= $barangaySchedule['vaccination_month'] ?>">

                            <div class="action-buttons mt-5">
                                <a href="bhw_dashboard.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-calendar-plus"></i> Schedule Vaccine
                                </button>
                            </div>
                            

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointments Modal -->
    <div class="modal fade" id="appointmentsModal" tabindex="-1" aria-labelledby="appointmentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg-custom modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="appointmentsModalLabel">All Appointments - <?= htmlspecialchars($bhw_barangay) ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="container my-4 modal-body-scrollable">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Baby Name</th>
                                    <th>Vaccine</th>
                                    <th>Schedule Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                    <th>Administered By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($appointment = $allAppointments->fetch_assoc()): ?>
                                    <tr class="appointment-row">
                                        <td><?= htmlspecialchars($appointment['baby_name']) ?></td>
                                        <td><?= htmlspecialchars($appointment['type_of_vaccine']) ?></td>
                                        <td><?= date('M d, Y', strtotime($appointment['schedule_date'])) ?></td>
                                        <td>
                                            <?php 
                                                $statusClass = '';
                                                switch($appointment['status']) {
                                                    case 'pending': $statusClass = 'status-pending'; break;
                                                    case 'completed': $statusClass = 'status-completed'; break;
                                                    case 'missed': $statusClass = 'status-missed'; break;
                                                    case 'rescheduled': $statusClass = 'status-rescheduled'; break;
                                                }
                                            ?>
                                            <span class="status-badge <?= $statusClass ?>">
                                                <?= ucfirst($appointment['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                            <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'rescheduled'): ?>
                                                <div class="btn-group" role="group">
                                                <form method="POST" action="appointment.php" class="d-inline">
                                                <input type="hidden" name="schedule_id" value="<?= $appointment['schedule_id'] ?>">
                                                <div class="input-group input-group-sm mb-2">
                                                 <input type="text" name="vaccinated_by" placeholder="Vaccinator name" class="form-control" required>
                                                 <input type="hidden" name="action_type" value="completed">
                                                    <button type="submit" class="btn btn-success">
                                                <i class="fas fa-check"></i> Vaccinated
                                                </button>
                                                    </div>
                                                    </form>
                                                    <form method="POST" action="appointment.php" class="d-inline">
                                                        <input type="hidden" name="schedule_id" value="<?= $appointment['schedule_id'] ?>">
                                                        <input type="hidden" name="action_type" value="missed">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-times"></i> Missed
                                                        </button>
                                                    </form>
                                                </div>
                            <?php elseif ($appointment['status'] === 'missed'): ?>
                                                <button class="btn btn-sm btn-warning" disabled>
                                                    <i class="fas fa-exclamation-triangle"></i> Missed
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= isset($appointment['vaccinated_by']) ? htmlspecialchars($appointment['vaccinated_by']) : '-' ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Mobile menu toggle
            $('.menu-toggle').click(function() {
                $('#nav-menu').toggleClass('active');
                $(this).toggleClass('active');
            });

            // Week selection styling
            $('.week-option').click(function() {
                $('.week-option').removeClass('selected');
                $(this).addClass('selected');
                $(this).find('input[type="radio"]').prop('checked', true);
            });

            // Function to check vaccine status for selected baby
            function checkVaccineStatus() {
                const babyId = $('#babySelect').val();
                const vaccineType = $('#vaccineSelect option:selected').data('vaccine-type');
                
                if (!babyId || !vaccineType) {
                    $('#vaccineStatus').html('');
                    return;
                }
                
                $.ajax({
                    url: 'check_vaccine_status.php',
                    type: 'POST',
                    data: { baby_id: babyId, vaccine_type: vaccineType },
                    success: function(response) {
                        $('#vaccineStatus').html(response);
                    },
                    error: function() {
                        $('#vaccineStatus').html('<div class="text-danger">Error checking vaccine status</div>');
                    }
                });
            }

            // In your JavaScript
            function updateVaccineOptions() {
                const selectedBaby = $('#babySelect option:selected');
                const vaccinatedVaccines = JSON.parse(selectedBaby.data('vaccinated') || []);
                
                // Reset all options first
                $('.vaccine-select option').each(function() {
                    const $option = $(this);
                    $option.removeClass('already-vaccinated');
                    $option.prop('disabled', false);
                    $option.find('.vaccine-status').remove();
                });
                
                // Disable and mark already vaccinated options
                vaccinatedVaccines.forEach(vaccineType => {
                    $(`.vaccine-select option[data-vaccine-type="${vaccineType}"]`).each(function() {
                        const $option = $(this);
                        $option.addClass('already-vaccinated');
                        $option.prop('disabled', true);
                        $option.append('<span class="vaccine-status"> (Already vaccinated)</span>');
                    });
                });
                
                // Disable dependent vaccines if previous dose not completed
                $('.vaccine-select option').each(function() {
                    const $option = $(this);
                    const vaccineType = $option.data('vaccine-type');
                    const dependsOn = vaccineConstraints[vaccineType]?.depends_on;
                    
                    if (dependsOn && !vaccinatedVaccines.includes(dependsOn)) {
                        $option.prop('disabled', true);
                        $option.append('<span class="vaccine-status"> (Requires ' + dependsOn + ' first)</span>');
                    }
                });
                
                showVaccineStatus();
            }

            // Function to show vaccine status info
            function showVaccineStatus() {
                const selectedBaby = $('#babySelect').val();
                const selectedVaccine = $('#vaccineSelect option:selected').data('vaccine-type');
                const vaccinatedVaccines = selectedBaby ? JSON.parse($('#babySelect option:selected').data('vaccinated')) : [];
                
                if (!selectedBaby || !selectedVaccine) {
                    $('#vaccineStatus').html('');
                    return;
                }
                
                if (vaccinatedVaccines.includes(selectedVaccine)) {
                    $('#vaccineStatus').html('<div class="text-danger mt-2"><i class="fas fa-exclamation-circle"></i> This baby has already received this vaccine</div>');
                } else {
                    $('#vaccineStatus').html('');
                }
            }

            // Event listeners
            $('#babySelect').change(function() {
                updateVaccineOptions();
                checkVaccineStatus();
            });

            $('#vaccineSelect').change(function() {
                showVaccineStatus();
                checkVaccineStatus();
            });

            // Initialize on page load
            updateVaccineOptions();
        });
        document.addEventListener('DOMContentLoaded', function() {
    let vaccineCount = 1;
    const maxVaccines = 10; // Set maximum number of vaccines that can be added
    
    // Add Vaccine Button Click Handler
    document.getElementById('addVaccineBtn').addEventListener('click', function() {
        if (vaccineCount >= maxVaccines) {
            alert('Maximum number of vaccines reached.');
            return;
        }
        
        vaccineCount++;
        
        // Create new vaccine row
        const newVaccineRow = document.createElement('div');
        newVaccineRow.className = 'vaccine-row mb-3';
        newVaccineRow.innerHTML = `
            <div class="row">
                <div class="col-md-11">
                    <label for="vaccine_id_${vaccineCount}" class="form-label">Select Vaccine</label>
                    <select class="form-select vaccineSelect" name="vaccine_id[]" id="vaccine_id_${vaccineCount}" required>
                        <option value="" selected disabled>Select a vaccine...</option>
                        ${getVaccineOptions()}
                    </select>
                    <div class="form-text">Only vaccines available in <?= htmlspecialchars($bhw_barangay) ?> are shown</div>
                    <div class="vaccineStatus mt-2"></div>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-vaccine-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        
        // Insert before the Add button
        const addButton = document.getElementById('addVaccineBtn');
        addButton.parentNode.insertBefore(newVaccineRow, addButton);
        
        // Update remove button states
        updateRemoveButtons();
        
        // Add event listener to new remove button
        const removeBtn = newVaccineRow.querySelector('.remove-vaccine-btn');
        removeBtn.addEventListener('click', function() {
            removeVaccineRow(this);
        });
        
        // Add event listener to new select dropdown
        const newSelect = newVaccineRow.querySelector('.vaccineSelect');
        newSelect.addEventListener('change', function() {
            updateVaccineStatus(this);
        });
    });
    
    // Remove Vaccine Row Function
    function removeVaccineRow(button) {
        const vaccineRow = button.closest('.vaccine-row');
        vaccineRow.remove();
        vaccineCount--;
        updateRemoveButtons();
    }
    
    // Update Remove Button States
    function updateRemoveButtons() {
        const removeButtons = document.querySelectorAll('.remove-vaccine-btn');
        removeButtons.forEach(btn => {
            btn.disabled = removeButtons.length <= 1;
        });
    }
    
    // Get Vaccine Options (from original select)
    function getVaccineOptions() {
        const originalSelect = document.getElementById('vaccineSelect');
        let options = '';
        
        for (let i = 1; i < originalSelect.options.length; i++) {
            const option = originalSelect.options[i];
            options += `<option value="${option.value}" 
                        ${option.disabled ? 'disabled' : ''} 
                        data-vaccine-type="${option.getAttribute('data-vaccine-type')}" 
                        data-quantity="${option.getAttribute('data-quantity')}" 
                        class="${option.className}">
                        ${option.innerHTML}
                    </option>`;
        }
        
        return options;
    }
    
    // Update Vaccine Status (similar to original functionality)
    function updateVaccineStatus(selectElement) {
        const statusDiv = selectElement.parentNode.querySelector('.vaccineStatus');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        
        if (selectedOption && selectedOption.value) {
            const quantity = selectedOption.getAttribute('data-quantity');
            const vaccineType = selectedOption.getAttribute('data-vaccine-type');
            
            if (quantity <= 0) {
                statusDiv.innerHTML = '<div class="alert alert-danger alert-sm">This vaccine is out of stock</div>';
            } else if (quantity < 5) {
                statusDiv.innerHTML = '<div class="alert alert-warning alert-sm">Low stock warning: Only ' + quantity + ' doses remaining</div>';
            } else {
                statusDiv.innerHTML = '<div class="alert alert-success alert-sm">Available: ' + quantity + ' doses</div>';
            }
        } else {
            statusDiv.innerHTML = '';
        }
    }
    
    // Initialize remove buttons on first load
    updateRemoveButtons();
    
    // Add event listener to original select if it exists
    const originalSelect = document.getElementById('vaccineSelect');
    if (originalSelect) {
        originalSelect.addEventListener('change', function() {
            const statusDiv = document.getElementById('vaccineStatus');
            updateVaccineStatus(this);
        });
    }
});
$(document).ready(function() {
    let vaccineCount = 1;
    const maxVaccines = 5; // Maximum number of vaccines that can be added

    // Add Vaccine Button Click Handler
    $('#addVaccineBtn').click(function() {
        if (vaccineCount >= maxVaccines) {
            alert('Maximum of ' + maxVaccines + ' vaccines per appointment.');
            return;
        }

        vaccineCount++;
        
        // Clone the first vaccine row and clear its values
        const newRow = $('.vaccine-row:first').clone();
        newRow.find('select').val('');
        newRow.find('.vaccine-status').empty();
        newRow.find('.remove-vaccine-btn').prop('disabled', false);
        
        // Add to container
        $('#vaccineContainer').append(newRow);
        
        // Update remove button states
        updateRemoveButtons();
    });

    // Remove Vaccine Row
    $(document).on('click', '.remove-vaccine-btn', function() {
        if ($('.vaccine-row').length > 1) {
            $(this).closest('.vaccine-row').remove();
            vaccineCount--;
            updateRemoveButtons();
        }
    });

    // Update Vaccine Status when selection changes
    $(document).on('change', '.vaccine-select', function() {
        const selectedOption = $(this).find('option:selected');
        const statusDiv = $(this).closest('.vaccine-row').find('.vaccine-status');
        
        if (selectedOption.val()) {
            const quantity = selectedOption.data('quantity');
            const vaccineType = selectedOption.data('vaccine-type');
            
            if (quantity <= 0) {
                statusDiv.html('<div class="alert alert-danger alert-sm">This vaccine is out of stock</div>');
            } else if (quantity < 5) {
                statusDiv.html('<div class="alert alert-warning alert-sm">Low stock: ' + quantity + ' doses remaining</div>');
            } else {
                statusDiv.html('<div class="alert alert-success alert-sm">Available: ' + quantity + ' doses</div>');
            }
        } else {
            statusDiv.empty();
        }
    });

    // Function to update remove button states
    function updateRemoveButtons() {
        $('.remove-vaccine-btn').prop('disabled', $('.vaccine-row').length <= 1);
    }
});
const vaccineConstraints = {
    'Pentavalent_Vaccine_2nd_dose': { depends_on: 'Pentavalent_Vaccine_1st_dose' },
    'Pentavalent_Vaccine_3rd_dose': { depends_on: 'Pentavalent_Vaccine_2nd_dose' },
    'Oral_Polio_Vaccine_2nd_dose': { depends_on: 'Oral_Polio_Vaccine_1st_dose' },
    'Oral_Polio_Vaccine_3rd_dose': { depends_on: 'Oral_Polio_Vaccine_2nd_dose' },
    'Pneumococcal_Conjugate_Vaccine_2nd_dose': { depends_on: 'Pneumococcal_Conjugate_Vaccine_1st_dose' },
    'Pneumococcal_Conjugate_Vaccine_3rd_dose': { depends_on: 'Pneumococcal_Conjugate_Vaccine_2nd_dose' },

};

// Update vaccine options when baby selection changes
$('#babySelect').change(updateVaccineOptions);
function showGapWarning() {
    // Check if scheduling a pentavalent dose
    const isPentavalent = $('.vaccine-select option:selected').filter(function() {
        return $(this).data('vaccine-type').includes('Pentavalent_Vaccine_');
    }).length > 0;

    if (isPentavalent) {
        alert("Remember: Pentavalent doses require at least 1 month between doses");
    }
}

// Call this when vaccine selection changes
$('.vaccine-select').change(showGapWarning);
    </script>
    </script>
</body>
</html>