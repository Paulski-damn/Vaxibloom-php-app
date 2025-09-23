<?php
session_start();
require '../config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    // Redirect to login page if not logged in
    header("Location:login.php");
    exit();
}

// Handle pagination
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Handle search functionality
$searchQuery = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchQuery = htmlspecialchars(trim($_GET['search']));
    // Query to get distinct baby names with completed vaccines
    $sql = "SELECT DISTINCT s.baby_name 
            FROM schedule s 
            WHERE s.status = 'completed' AND s.baby_name LIKE :searchQuery 
            LIMIT :start, :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':searchQuery', '%' . $searchQuery . '%', PDO::PARAM_STR);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $babyNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get count for pagination
    $countSql = "SELECT COUNT(DISTINCT baby_name) FROM schedule WHERE status = 'completed' AND baby_name LIKE :searchQuery";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([':searchQuery' => '%' . $searchQuery . '%']);
    $totalRecords = $countStmt->fetchColumn();
} else {
    // Query to get distinct baby names with completed vaccines
    $sql = "SELECT DISTINCT baby_name FROM schedule WHERE status = 'completed' LIMIT :start, :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $babyNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get count for pagination
    $countSql = "SELECT COUNT(DISTINCT baby_name) FROM schedule WHERE status = 'completed'";
    $countStmt = $pdo->query($countSql);
    $totalRecords = $countStmt->fetchColumn();
}

// Get all completed vaccines for each baby
$babyRecords = [];
foreach ($babyNames as $babyName) {
    $vaccinesSql = "SELECT type_of_vaccine, date 
                    FROM schedule 
                    WHERE baby_name = :babyName AND status = 'completed' 
                    ORDER BY date";
    $vaccinesStmt = $pdo->prepare($vaccinesSql);
    $vaccinesStmt->execute([':babyName' => $babyName]);
    $vaccines = $vaccinesStmt->fetchAll();
    
    $babyRecords[] = [
        'name' => $babyName,
        'vaccines' => $vaccines
    ];
}

$totalPages = ceil($totalRecords / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Vaccinations</title>
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link rel="stylesheet" href="../../css/admin/vaccine_record.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<!-- Hamburger Button -->
<div class="hamburger-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </div>

    <div class="container">
        <!-- Sidebar Section -->
        <div class="sidebar collapsed" id="sidebar">
            <div class="sidebar-header">
                <!-- Logo Image Container -->
                <div class="logo-container">
                    <img src="../../img/logo1.png" alt="VaxiBloom Logo" class="sidebar-logo">
                </div>
                <h2 class="sidebar-title">VaxiBloom</h2>
        </div>
            <ul>
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> <span class="menu-text">Home</span></a></li>
                <li><a href="map.php"><i class="fas fa-map"></i> <span class="menu-text">Map</span></a></li>
                <li><a href="infant_management.php"><i class="far fa-calendar-check"></i> <span class="menu-text">Schedules</span></a></li>
                <li><a href="vaccine_record.php" class="active"><i class="fas fa-file-medical"></i> <span class="menu-text">Vaccine Record</span></a></li>
                <li><a href="inventory_admin.php"><i class="fas fa-syringe"></i> <span class="menu-text">Inventory</span></a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> <span class="menu-text">Messages</span></a></li>
                <li><a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a></li>
            </ul>
        </div>

    <!-- Main Content Section -->
    <div class="content">
        <form class="search-bar">
            <input type="text" id="searchInput" placeholder="Search baby name" 
                value="<?php echo htmlspecialchars($searchQuery); ?>">
            <button type="button"><i class="fas fa-search"></i></button>
        </form>

        <h1>Completed Vaccinations</h1>

        <table>
            <thead>
                <tr>
                    <th>Baby Name</th>
                    <th>Completed Vaccines</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($babyRecords) > 0): ?>
                    <?php foreach ($babyRecords as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['name']); ?></td>
                        <td>
                            <div class="vaccine-list">
                                <?php foreach ($record['vaccines'] as $vaccine): ?>
                                    <span class="vaccine-badge">
                                        <i class="fas fa-syringe"></i>
                                        <?php echo htmlspecialchars($vaccine['type_of_vaccine']); ?>
                                        (<?php echo date('M d, Y', strtotime($vaccine['date'])); ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <a href="report.php?baby_name=<?php echo urlencode($record['name']); ?>" target="_blank">
                                <button class="report-btn">Generate Report</button>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="no-records">No completed vaccinations found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination Section -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo htmlspecialchars($searchQuery); ?>">&laquo; Prev</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo htmlspecialchars($searchQuery); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo htmlspecialchars($searchQuery); ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('main-content');
        sidebar.classList.toggle('collapsed');
        
        // Save state to localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }
    
    // Check saved state on page load (your existing code)
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
        } else {
            sidebar.classList.remove('collapsed');
        }
        
        // Initialize search functionality
        setupSearch();
    });
    
    // Real-time search function
    function setupSearch() {
        const searchInput = document.getElementById('searchInput');
        
        searchInput.addEventListener('keyup', function() {
            const input = this.value.toLowerCase();
            const rows = document.querySelectorAll('table tbody tr');
            
            rows.forEach(row => {
                // Get the baby name from the first column
                const babyName = row.querySelector('td:first-child').textContent.toLowerCase();
                row.style.display = babyName.includes(input) ? '' : 'none';
            });
            
            // Hide "no records" message if we have any visible rows
            const noRecordsRow = document.querySelector('.no-records');
            if (noRecordsRow) {
                const hasVisibleRows = Array.from(rows).some(row => row.style.display !== 'none');
                noRecordsRow.style.display = hasVisibleRows ? 'none' : '';
            }
        });
    }
</script>
</body>
</html>