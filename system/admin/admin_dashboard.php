<?php
session_start();
require '../config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    // Redirect to login page if not logged in
    header("Location:login.php");
    exit();
}
// Fetch the counts for the banners from the database
$user=0;
$baby_name = 0;
$pending_requests = 0;
$completed_requests = 0;
$canceled_requests = 0;

// Query to get the total number of babies (you can adjust based on your schema)
$result = mysqli_query($conn, "SELECT COUNT(*) AS baby_name FROM babies");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $baby_name = $row['baby_name'];
}
// Query to get the total number of babies (you can adjust based on your schema)
$result = mysqli_query($conn, "SELECT COUNT(*) AS users FROM users");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $user = $row['users'];
}

//Fetch the Barangay
$sql = "SELECT COUNT(*) as total_barangays FROM barangays";
$result = $conn->query($sql);

if ($result) {
    $row = $result->fetch_assoc();
    $barangay_count = $row['total_barangays']; // Stores the count (e.g., "25")
} else {
    $barangay_count = 0; // Default if query fails
}

// Query to get the pending, completed, and canceled requests
$result = mysqli_query($conn, "SELECT status, COUNT(*) AS count FROM schedule GROUP BY status");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['status'] == 'pending') {
            $pending_requests = $row['count'];
        } elseif ($row['status'] == 'completed') {
            $completed_requests = $row['count'];
        } elseif ($row['status'] == 'missed') {
            $canceled_requests = $row['count'];
        }
    }
}


// Fetch data for bar chart (vaccine counts)

$vaccine_counts = [];
$result = mysqli_query($conn, "SELECT DISTINCT vaccine_type FROM vaccine_inventory");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $vaccine_counts[$row['vaccine_type']] = 0; // Initialize all types with 0 count
    }
}

// Now count actual vaccinations from schedule table
$result = mysqli_query($conn, 
    "SELECT type_of_vaccine, COUNT(*) as count 
     FROM schedule 
     GROUP BY type_of_vaccine");

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if (array_key_exists($row['type_of_vaccine'], $vaccine_counts)) {
            $vaccine_counts[$row['type_of_vaccine']] = $row['count'];
        }
    }
}

// Fetch data for line chart (missed appointments by barangay)
$missed_appointments = [];
$result = mysqli_query($conn, 
    "SELECT b.name, COUNT(s.schedule_id) as missed 
     FROM barangays b
     LEFT JOIN users u ON b.barangay_id = u.user_id
     LEFT JOIN schedule s ON u.user_id = s.user_id AND s.status = 'missed'
     GROUP BY b.name
     ORDER BY missed DESC");

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $missed_appointments[$row['name']] = $row['missed'];
    }
}
// Fetch monthly vaccination data
$monthly_data = array_fill(0, 12, 0); // Initialize array with 12 months (0-11)

$result = mysqli_query($conn, 
    "SELECT MONTH(schedule_date) as month, COUNT(*) as count
     FROM schedule
     WHERE status = 'completed' AND YEAR(schedule_date) = YEAR(CURDATE())
     GROUP BY MONTH(schedule_date)");

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $monthly_data[$row['month'] - 1] = $row['count']; // month-1 because array is 0-indexed
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../../css/admin/dashboard.css">
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Add this with your other script imports -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="content">
        <!-- Header -->
        <div class="header-content">
                <h1><i class="fas fa-home"></i>Dashboard</h1>
                <div class="breadcrumb">
                    <span>Admin</span> <i class="fas fa-chevron-right mx-2"></i> <span class="active">Dashboard</span>
                </div>
            </div>

        <!-- Dashboard Stats -->
        <div class="stats-grid">
            <!-- Total Patients/Users -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($user); ?></div>
                <div class="stat-label">Total user</div>
                <div class="stat-details">
                    <div class="stat-detail-item">
                        <span class="stat-detail-label">Registered Children(0-12 months)</span>
                        <span class="stat-detail-value"><?php echo number_format($baby_name); ?></span>
                    </div>
                </div>
            </div>

            <!-- Total Appointments -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fa fa-calendar"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($pending_requests + $completed_requests + $canceled_requests); ?></div>
                <div class="stat-label">Total Appointments</div>
                <div class="stat-details">
                    <div class="stat-detail-item">
                        <span class="stat-detail-label">Pending Appointments</span>
                        <span class="stat-detail-value"><?php echo number_format($pending_requests); ?></span>
                    </div>
                </div>
            </div>

            <!-- Coverage Areas -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fa fa-map-marker-alt"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($barangay_count); ?></div>
                <div class="stat-label">Coverage Areas</div>
                <div class="stat-details">
                    <div class="stat-detail-item">
                        <span class="stat-detail-label">Active Barangays</span>
                        <span class="stat-detail-value"><?php echo number_format($barangay_count); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Container -->
        <div class="charts-container">
            <!-- First Chart - Larger Line Chart -->
            <div class="chart-section large-chart">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Monthly Vaccination Trends</h3>
                </div>
                <div class="chart-canvas">
                    <canvas id="monthlyTrendsChart"></canvas>
                </div>
            </div>
            </div>

            <!-- Second Chart - Smaller Pie Chart -->
            <div class="chart-section small-chart">
                <div class="chart-header">
                    <h3 class="chart-title">Vaccination Status</h3>
                </div>
                <div class="chart-canvas">
                    <canvas id="financialChart"></canvas>
                </div>
                <div class="financial-stats">
                    <div class="financial-item">
                        <span class="financial-label">Total Completed:</span>
                        <span class="financial-value"><?php echo number_format($completed_requests); ?></span>
                    </div>
                    <div class="financial-item">
                        <span class="financial-label">Total Pending:</span>
                        <span class="financial-value"><?php echo number_format($pending_requests); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get PHP data
const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
            const monthlyChart = new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Vaccinations',
                        data: <?php echo json_encode(array_values($monthly_data)); ?>,
                        fill: true,
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderColor: '#3b82f6',
                        borderWidth: 3,
                        tension: 0.4,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                	position: 'bottom',
                	labels: {
                   		usePointStyle: true,
                    		padding: 20
               		}
            }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f5f9'
                            }
                        }
                    }
                }
            });

            // Financial Overview Donut Chart - using appointment status data
            const financialCtx = document.getElementById('financialChart').getContext('2d');
            new Chart(financialCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Pending', 'Missed'],
                    datasets: [{
                        data: [<?php echo $completed_requests; ?>, <?php echo $pending_requests; ?>, <?php echo $canceled_requests; ?>],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 0,
                        cutout: '70%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
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
    </script>
</body>
</html>