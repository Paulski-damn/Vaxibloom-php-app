<?php
session_start();
require '../config.php';

// Ensure only admins can access
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    header("Location: login.php");
    exit();
}

// Day number to name mapping
$dayMap = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday'
];

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barangay'])) {
    $barangay = $_POST['barangay'];
    $month = $_POST['month'];
    $week1_days = $_POST['week1_days'];
    $week2_days = $_POST['week2_days'];
    $week3_days = $_POST['week3_days'];
    $week4_days = $_POST['week4_days'];

    // Get the barangay name from the barangays table first
    $barangayStmt = $conn->prepare("SELECT name FROM barangays WHERE barangay_id = ?");
    if ($barangayStmt === false) {
        die('Prepare failed: ' . $conn->error);
    }
    
    $barangayStmt->bind_param("i", $barangay);
    $barangayStmt->execute();
    $barangayResult = $barangayStmt->get_result();
    
    if ($barangayResult->num_rows === 0) {
        $message = "Barangay not found.";
    } else {
        $barangayName = $barangayResult->fetch_assoc()['name'];

        // Now insert with barangay_name
        $stmt = $conn->prepare("INSERT INTO barangay_schedules 
            (bs_id, barangay_name, vaccination_month, week1_days, week2_days, week3_days, week4_days) 
            VALUES (?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            week1_days = VALUES(week1_days),
            week2_days = VALUES(week2_days),
            week3_days = VALUES(week3_days),
            week4_days = VALUES(week4_days)");

        if ($stmt === false) {
            die('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param("sssssss", $barangay, $barangayName, $month, $week1_days, $week2_days, $week3_days, $week4_days);

        if ($stmt->execute()) {
            // Save individual dates
            $dates = array_merge(
                explode(",", $week1_days),
                explode(",", $week2_days),
                explode(",", $week3_days),
                explode(",", $week4_days)
            );

            // Delete existing dates for this barangay and month
            $deleteStmt = $conn->prepare("DELETE FROM vaccination_dates 
                WHERE barangay_id = ? AND YEAR(vaccination_date) = YEAR(?) AND MONTH(vaccination_date) = MONTH(?)");
            $monthDate = $month . '-01';
            $deleteStmt->bind_param("iss", $barangay, $monthDate, $monthDate);
            $deleteStmt->execute();

            // Insert new dates using prepared statements (safer)
            $insertStmt = $conn->prepare("INSERT INTO vaccination_dates (barangay_id, vaccination_date) VALUES (?, ?)");
            
            foreach ($dates as $day) {
                $day = trim($day);
                if ($day !== "" && is_numeric($day)) {
                    $date = $month . "-" . str_pad($day, 2, "0", STR_PAD_LEFT);
                    $insertStmt->bind_param("is", $barangay, $date);
                    $insertStmt->execute();
                }
            }

            $message = "Schedule saved successfully.";
            $insertStmt->close();
            $deleteStmt->close();
        } else {
            $message = "Failed to save schedule: " . $stmt->error;
        }
        
        $stmt->close();
    }
    
    $barangayStmt->close();
    
    // Redirect to prevent form resubmission on refresh
    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
    exit();
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Schedule saved successfully.";
}

$barangays = $conn->query("SELECT barangay_id, name FROM barangays ORDER BY name");
$schedules = $conn->query("SELECT * FROM barangay_schedules ORDER BY vaccination_month DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Barangay Schedules</title>
    <link rel="stylesheet" href="../../css/admin/admin_schedules.css">
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
            --dark-bg: #343a40;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            margin-left: 70px;
            padding: 30px;
            transition: margin-left 0.3s;
        }
        
        .sidebar:not(.collapsed) + .main-container {
            margin-left: 250px;
        }
        
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .page-header h2 {
            color: var(--secondary-color);
            font-weight: 600;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: none;
            margin-bottom: 25px;
        }
        
        .card-header {
            background-color: #A8D5BA;
            color: black;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--secondary-color);
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }
        
        .btn-primary {
            background-color: #A8D5BA;
            border-color: #2E7D32;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #2E7D32;
            border-color: #2E7D32;
        }
        
        .week-input-group {
            margin-bottom: 20px;
        }
        
        .week-input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-radius: 8px;
        }
        
        .table-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-top: 30px;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: var(--secondary-color);
            color: white;
            font-weight: 500;
        }
        
        .table td, .table th {
            padding: 12px 15px;
            vertical-align: middle;
        }
        
        .badge-month {
            background-color: #e3f2fd;
            color: #1976d2;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 5px;
        }
        
        .select2-container--default .select2-selection--single {
            height: 45px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 43px;
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 20px 15px;
            }
            
            .table-responsive {
                border-radius: 8px;
                overflow: hidden;
            }
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-container" id="main-content">
    <div class="container-fluid">
        <div class="header-content">
        <h1><i class="fas fa-calendar"></i>Schedule</h1>
        <div class="breadcrumb">
            <span>Admin</span> <i class="fas fa-chevron-right mx-2"></i> <span class="active">Schedule</span>
        </div>
    </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus-circle me-2"></i>Create New Schedule
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label for="barangay" class="form-label">Barangay</label>
                        <select name="barangay" id="barangay" class="form-select select2" required>
                            <option value="">-- Select Barangay --</option>
                            <?php while ($row = $barangays->fetch_assoc()): ?>
                                <option value="<?= $row['barangay_id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="month" class="form-label">Month</label>
                        <input type="month" name="month" id="month" class="form-control" required>
                    </div>
                    
                    <div class="col-12">
                        <h5 class="mt-4 mb-3">Schedule Days</h5>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="week-input-group">
                            <label for="week1_days">Week 1 Days</label>
                            <input type="text" name="week1_days" id="week1_days" class="form-control" placeholder="e.g., 1,3,5">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="week-input-group">
                            <label for="week2_days">Week 2 Days</label>
                            <input type="text" name="week2_days" id="week2_days" class="form-control" placeholder="e.g., 8,10,12">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="week-input-group">
                            <label for="week3_days">Week 3 Days</label>
                            <input type="text" name="week3_days" id="week3_days" class="form-control" placeholder="e.g., 15,17,19">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="week-input-group">
                            <label for="week4_days">Week 4 Days</label>
                            <input type="text" name="week4_days" id="week4_days" class="form-control" placeholder="e.g., 22,24,26">
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-2"></i>Save Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="table-container">
            <h4 class="mb-4"><i class="fas fa-list-alt me-2"></i>Existing Schedules</h4>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Barangay</th>
                            <th>Month</th>
                            <th>Week 1</th>
                            <th>Week 2</th>
                            <th>Week 3</th>
                            <th>Week 4</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $schedules->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['barangay_name']) ?></td>
                                <td><span class="badge-month"><?= htmlspecialchars($row['vaccination_month']) ?></span></td>
                                <td><?= htmlspecialchars($row['week1_days']) ?></td>
                                <td><?= htmlspecialchars($row['week2_days']) ?></td>
                                <td><?= htmlspecialchars($row['week3_days']) ?></td>
                                <td><?= htmlspecialchars($row['week4_days']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        placeholder: "Select an option",
        allowClear: true
    });
    
    // Toggle sidebar function
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('main-content');
        sidebar.classList.toggle('collapsed');
        
        // Save state to localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }
    
    // Check saved state on page load
    const sidebar = document.getElementById('sidebar');
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
    } else {
        sidebar.classList.remove('collapsed');
    }
});
const dropdownToggles = document.querySelectorAll('.dropdown > a');
    
    dropdownToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            if (window.innerWidth > 768) {
                e.preventDefault();
                const dropdownMenu = this.nextElementSibling;
                
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                    if (menu !== dropdownMenu) {
                        menu.style.display = 'none';
                    }
                });
                
                // Toggle current dropdown
                if (dropdownMenu.style.display === 'block') {
                    dropdownMenu.style.display = 'none';
                } else {
                    dropdownMenu.style.display = 'block';
                }
            }
        });
    });

    // Close dropdowns when clicking outside (for desktop)
    document.addEventListener('click', function(e) {
        if (window.innerWidth > 768) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                    menu.style.display = 'none';
                });
            }
        }
    });

    // Make dropdowns stay open on mobile when sidebar is expanded
    const sidebar = document.getElementById('sidebar');
    sidebar.addEventListener('mouseleave', function() {
        if (window.innerWidth > 768) {
            document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                menu.style.display = 'none';
            });
        }
    });
</script>
</body>
</html>