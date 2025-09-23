<?php
session_start();
require '../config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    header("Location:login.php");
    exit();
}

$barangayFilter = isset($_GET['barangay']) ? $_GET['barangay'] : '';
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 10;
$offset = ($currentPage - 1) * $itemsPerPage;

// Handle status change actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $scheduleId = $_GET['id'];

    // Fetch appointment details
    $stmt = $pdo->prepare("SELECT type_of_vaccine, barangay, status FROM schedule WHERE id = :id");
    $stmt->execute([':id' => $scheduleId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($appointment && in_array($action, ['completed', 'canceled'])) {
        // Only deduct vaccine if marking as completed AND if it wasn't already completed
        if ($action === 'completed' && $appointment['status'] !== 'completed') {
            $stmt = $pdo->prepare("SELECT id, quantity FROM vaccine_inventory WHERE vaccine_type = :vaccine_type AND barangay = :barangay");
            $stmt->execute([
                ':vaccine_type' => $appointment['type_of_vaccine'],
                ':barangay' => $appointment['barangay']
            ]);
            $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inventory || $inventory['quantity'] < 1) {
                echo "<script>alert('Not enough vaccine stock available in this barangay.'); window.location.href = '" . $_SERVER['PHP_SELF'] . "?barangay=$barangayFilter&page=$currentPage';</script>";
                exit();
            }

            // Deduct 1 from stock
            $stmt = $pdo->prepare("UPDATE vaccine_inventory SET quantity = quantity - 1 WHERE id = :id");
            $stmt->execute([':id' => $inventory['id']]);
        }

        // Update appointment status
        $stmt = $pdo->prepare("UPDATE schedule SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $action, ':id' => $scheduleId]);

        header("Location: " . $_SERVER['PHP_SELF'] . "?barangay=$barangayFilter&page=$currentPage");
        exit();
    }
}


$sql = "SELECT * FROM schedule WHERE 1=1";
if ($barangayFilter) {
    $sql .= " AND barangay = :barangay";
}
$sql .= " LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
if ($barangayFilter) {
    $stmt->bindParam(':barangay', $barangayFilter, PDO::PARAM_STR);
}
$stmt->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$scheduleRecords = $stmt->fetchAll();

$totalQuery = "SELECT COUNT(*) FROM schedule WHERE 1=1";
if ($barangayFilter) {
    $totalQuery .= " AND barangay = :barangay";
}
$totalStmt = $pdo->prepare($totalQuery);
if ($barangayFilter) {
    $totalStmt->bindParam(':barangay', $barangayFilter, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRecords = $totalStmt->fetchColumn();
$totalPages = ceil($totalRecords / $itemsPerPage);

// Handle delete action
if (isset($_GET['delete_id'])) {
    $deleteId = $_GET['delete_id'];
    
    // Verify the record exists
    $stmt = $pdo->prepare("SELECT id FROM schedule WHERE id = :id");
    $stmt->execute([':id' => $deleteId]);
    
    if ($stmt->fetch()) {
        // Delete the record
        $deleteStmt = $pdo->prepare("DELETE FROM schedule WHERE id = :id");
        $deleteStmt->execute([':id' => $deleteId]);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?barangay=$barangayFilter&page=$currentPage");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Infant Management - Filter</title>
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link rel="stylesheet" href="../../css/admin/infant_management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<!-- Hamburger Button -->
<div class="hamburger-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </div>

    <!-- Sidebar Section -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <img src="../../img/logo1.png" alt="VaxiBloom Logo" class="sidebar-logo">
                </div>
                <h2 class="sidebar-title">VaxiBloom</h2>
            </div>
            <ul>
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> <span class="menu-text">Home</span></a></li>                <!-- Baby Management -->
                <li><a href="babies.php"><i class="fas fa-baby"></i> <span class="menu-text">Baby Management</span></a></li>

                <!-- Scheduling -->
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="far fa-calendar-check"></i>
                        <span class="menu-text">Scheduling</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li class="dropdown-header">View schedules</li>
                        <li><a href="admin_schedules.php?view=barangay" class="active"><i class="fas fa-map-marker-alt"></i> By Barangay</a></li>
                        <li class="divider"></li>
                        <li><a href="missed_appointments.php"><i class="fas fa-calendar-times"></i> Missed Appointments</a></li>
                    </ul>
                </li>

                <!-- Vaccine Management -->
                <li><a href="inventory_admin.php"><i class="fas fa-syringe"></i> <span class="menu-text">Vaccine Management</span></a></li>

                <!-- Reports -->
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="fas fa-file-medical"></i>
                        <span class="menu-text">Reports</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="map.php"><i class="fas fa-chart-pie"></i> Vaccination Status</a></li>
                        <li><a href="report.php"><i class="fas fa-map-marked-alt"></i> By Barangay</a></li>
                        <li class="divider"></li>
                        <li><a href="report.php"><i class="fas fa-file-pdf"></i> Generate PDF</a></li>
                    </ul>
                </li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> <span class="menu-text">Messages</span></a></li>
                
                <!-- Users -->
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="fas fa-user"></i>
                        <span class="menu-text">Users</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="users.php"><i class="fas fa-users"></i> All Users</a></li>
                        <li class="divider"></li>
                        <li><a href="change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
                    </ul>
                </li>
                <li><a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a></li>
            </ul>
        </div>

    <!-- Main Content Section -->
    <div class="content">
        <h1>Infant Vaccine Scheduling</h1>

        <!-- Filter Form -->
        <form method="GET" class="filter-form">
            <select name="barangay">
                <option value="">Filter by Barangay</option>
                <option value="Barangay 1" <?php if ($barangayFilter == 'Barangay 1') echo 'selected'; ?>>Barangay 1</option>
                <option value="Barangay 2" <?php if ($barangayFilter == 'Barangay 2') echo 'selected'; ?>>Barangay 2</option>
                <option value="Barangay 3" <?php if ($barangayFilter == 'Barangay 3') echo 'selected'; ?>>Barangay 3</option>
                <option value="Barangay 4" <?php if ($barangayFilter == 'Barangay 4') echo 'selected'; ?>>Barangay 4</option>
                <option value="Barangay 5" <?php if ($barangayFilter == 'Barangay 5') echo 'selected'; ?>>Barangay 5</option>
                <option value="Barangay Kabulusan" <?php if ($barangayFilter == 'Barangay Kabulusan') echo 'selected'; ?>>Barangay Kabulusan</option>
                <option value="Barangay Ramirez" <?php if ($barangayFilter == 'Barangay Ramirez') echo 'selected'; ?>>Barangay Ramirez</option>
                <option value="Barangay Agustin" <?php if ($barangayFilter == 'Barangay Agustin') echo 'selected'; ?>>Barangay Agustin</option>
                <option value="Barangay San Agustin" <?php if ($barangayFilter == 'Barangay San Agustin') echo 'selected'; ?>>Barangay San Agustin</option>
                <option value="Barangay Urdaneta" <?php if ($barangayFilter == 'Barangay Urdaneta') echo 'selected'; ?>>Barangay Urdaneta</option>
                <option value="Barangay Bendita 1" <?php if ($barangayFilter == 'Barangay Bendita 1') echo 'selected'; ?>>Barangay Bendita 1</option>
                <option value="Barangay Bendita 2" <?php if ($barangayFilter == 'Barangay Bendita 2') echo 'selected'; ?>>Barangay Bendita 2</option>
                <option value="Barangay Caluangan" <?php if ($barangayFilter == 'Barangay Caluangan') echo 'selected'; ?>>Barangay Caluangan</option>
                <option value="Barangay Baliwag" <?php if ($barangayFilter == 'Barangay Baliwag') echo 'selected'; ?>>Barangay Baliwag</option>
                <option value="Barangay Pacheco" <?php if ($barangayFilter == 'Barangay Pacheco') echo 'selected'; ?>>Barangay Pacheco</option>
                <option value="Barangay Medina" <?php if ($barangayFilter == 'Barangay Medina') echo 'selected'; ?>>Barangay Medina</option>
                <option value="Barangay Tua" <?php if ($barangayFilter == 'Barangay Tua') echo 'selected'; ?>>Barangay Tua</option>
            </select>
            <button type="submit">Apply Filters</button>
        </form>

        <h2>Upcoming Vaccine Schedules</h2>
        <table>
            <thead>
                <tr>
                    <th class="date-column">Date</th>
                    <th class="name-column">Name of Baby</th>
                    <th class="phone-column">Phone</th>
                    <th class="vaccine-column">Vaccine Type</th>
                    <th class="barangay-column">Barangay</th>
                    <th class="status-column">Status</th>
                    <th class="actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($scheduleRecords) > 0): ?>
                    <?php foreach ($scheduleRecords as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['date']); ?></td>
                            <td><?php echo htmlspecialchars($record['baby_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['phone']); ?></td>
                            <td><?php echo htmlspecialchars($record['type_of_vaccine']); ?></td>
                            <td><?php echo htmlspecialchars($record['barangay']); ?></td>
                            <td><?php echo htmlspecialchars($record['status']) ?: 'Pending'; ?></td>
                            <td>
                                <?php if ($record['status'] != 'completed' && $record['status'] != 'canceled'): ?>
                                    <button class="action-btn completed" onclick="changeStatus('<?php echo $record['id']; ?>', 'completed')">Completed</button>
                                    <button class="action-btn canceled" onclick="changeStatus('<?php echo $record['id']; ?>', 'canceled')">Canceled</button>
                                <?php endif; ?>
                                <button class="action-btn delete" onclick="confirmDelete('<?php echo $record['id']; ?>')"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No schedules found matching your filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination Links -->
        <div class="pagination">
            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                <a href="?barangay=<?php echo htmlspecialchars($barangayFilter); ?>&page=<?php echo $page; ?>" class="<?php echo ($page == $currentPage) ? 'active' : ''; ?>"><?php echo $page; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- Add JavaScript to handle status change -->
<script>
    function changeStatus(id, status) {
        var confirmAction = confirm("Are you sure you want to mark this schedule as " + status + "?");

        if (confirmAction) {
            window.location.href = "?action=" + status + "&id=" + id;
        }
    }
    // Toggle sidebar function
    function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('main-content');
            sidebar.classList.toggle('collapsed');
            
            // Save state to localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }
        
        // Check saved state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
            }
        });
        function confirmDelete(id) {
            if (confirm("Are you sure you want to delete this schedule? This action cannot be undone.")) {
                window.location.href = "?delete_id=" + id + "&barangay=<?php echo htmlspecialchars($barangayFilter); ?>&page=<?php echo $currentPage; ?>";
            }
        }
</script>

</body>
</html>
