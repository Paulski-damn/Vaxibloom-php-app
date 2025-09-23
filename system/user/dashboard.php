<?php
// Start session and include database connection
session_start();
require '../config.php'; // Update this path if needed

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Fetch user data
$userId = $_SESSION['user_id'];
$userQuery = "SELECT first_name, last_name FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userData = $userResult->fetch_assoc();

// Dates for filtering schedules
$today = date('Y-m-d');
$nextMonth = date('Y-m-d', strtotime('+40 days'));

// Fetch upcoming appointments (next 30 days) for babies added by this BHW
$appointmentQuery = "SELECT s.*, b.baby_name 
                    FROM schedule s
                    JOIN babies b ON s.baby_id = b.baby_id
                    WHERE b.user_id = ? 
                    AND s.schedule_date >= ? 
                    AND s.schedule_date <= ?
                    AND s.status IN ('pending', 'scheduled', 'confirmed') 
                    ORDER BY s.schedule_date ASC";

$appointmentStmt = $conn->prepare($appointmentQuery);
if (!$appointmentStmt) {
    die("Prepare failed: " . $conn->error);
}
$appointmentStmt->bind_param("iss", $userId, $today, $nextMonth);
if (!$appointmentStmt->execute()) {
    die("Execute failed: " . $appointmentStmt->error);
}
$upcomingAppointments = $appointmentStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch completed vaccinations (last 3) for babies added by this BHW
$completedQuery = "SELECT s.*, b.baby_name 
                   FROM schedule s
                   JOIN babies b ON s.baby_id = b.baby_id
                   WHERE b.user_id = ? 
                   AND s.schedule_date < ? 
                   AND s.status = 'completed' 
                   ORDER BY s.schedule_date DESC LIMIT 3";

$completedStmt = $conn->prepare($completedQuery);
$completedStmt->bind_param("is", $userId, $today);
$completedStmt->execute();
$completedVaccines = $completedStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count total distinct vaccine types available in inventory
$totalVaccinesQuery = "SELECT COUNT(DISTINCT vaccine_type) as total FROM vaccine_inventory";
$totalStmt = $conn->prepare($totalVaccinesQuery);
$totalStmt->execute();
$totalVaccines = $totalStmt->get_result()->fetch_assoc()['total'];

// Count completed vaccines for babies added by this BHW
$completedCountQuery = "SELECT COUNT(DISTINCT s.type_of_vaccine) as completed 
                        FROM schedule s
                        JOIN babies b ON s.baby_id = b.baby_id
                        WHERE b.user_id = ? AND s.status = 'completed'";

$completedCountStmt = $conn->prepare($completedCountQuery);
$completedCountStmt->bind_param("i", $userId);
$completedCountStmt->execute();
$completedVaccinesCount = $completedCountStmt->get_result()->fetch_assoc()['completed'];

// Calculate coverage percentage
$coveragePercentage = $totalVaccines > 0 ? round(($completedVaccinesCount / $totalVaccines) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>VaxiBloom - Scheduling System</title>
    <link rel="icon" href="../../img/logo1.png" type="png" />
    <link rel="stylesheet" href="../../css/user/parent_dashboard.css" />
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
    />
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    />
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

    <div class="dashboard-container">
        <!-- Welcome Section -->
        <section class="welcome-section">
            <div class="welcome-content">
                <h1>Hi, <?= htmlspecialchars($userData['first_name']); ?>!</h1>
                <h2>
                    Plan, track, and manage the babies' vaccination schedules
                    effortlessly.
                </h2>
                <p class="subtext">
                    The babies' health journey  in your barangay starts here. Keep track of upcoming
                    vaccines and appointments all in one place.
                </p>
                <div class="action-buttons">
                    <a href="appointment.php" class="primary-btn"
                        ><i class="fas fa-calendar-plus"></i> Schedule an
                        Appointment</a
                    >
                    <a href="vaccine_facts.php" class="secondary-btn"
                        ><i class="fas fa-book"></i> Learn About Vaccines</a
                    >
                </div>
            </div>
            <div class="welcome-image">
                <img src="../../img/background.jpg" alt="Happy baby illustration" />
            </div>
        </section>

        <!-- Quick Stats Section -->
        <section class="stats-section">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-syringe"></i>
                </div>
                <div class="stat-info">
                    <h3>Upcoming Vaccines</h3>
                    <p>
                        <?= count($upcomingAppointments) ?> vaccine
                        <?= count($upcomingAppointments) !== 1 ? 's' : '' ?> due soon
                    </p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3>Next Appointment</h3>
                    <p>
                        <?php if (!empty($upcomingAppointments)): ?>
                        <?= date('M j, Y', strtotime($upcomingAppointments[0]['schedule_date'])) ?>
                        - <?= htmlspecialchars($upcomingAppointments[0]['type_of_vaccine']) ?> for
                        <?= htmlspecialchars($upcomingAppointments[0]['baby_id']) ?>
                        <?php else: ?>
                        No upcoming appointments
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="stat-info">
                    <h3>Vaccine Coverage</h3>
                    <p><?= $coveragePercentage ?>% completed</p>
                </div>
            </div>
        </section>

        <!-- Recent Activity Section -->
        <section class="activity-section">
            <h2 class="section-title">Recent Activity</h2>
            <div class="activity-list">
                <?php if (!empty($upcomingAppointments)): ?>
                <?php foreach ($upcomingAppointments as $appointment): ?>
                <div class="activity-item">
                    <div class="activity-icon upcoming">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="activity-details">
                        <h3><?= htmlspecialchars($appointment['type_of_vaccine']) ?></h3>
                        <p>
                            Scheduled for <?= date('M j, Y', strtotime($appointment['schedule_date'])) ?>
                            for <?= htmlspecialchars($appointment['baby_name']) ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($completedVaccines)): ?>
                <?php foreach ($completedVaccines as $vaccine): ?>
                <div class="activity-item">
                    <div class="activity-icon completed">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="activity-details">
                        <h3><?= htmlspecialchars($vaccine['type_of_vaccine']) ?></h3>
                        <p>
                            Completed on <?= date('M j, Y', strtotime($vaccine['schedule_date'])) ?>
                            for <?= htmlspecialchars($vaccine['baby_name']) ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (empty($upcomingAppointments) && empty($completedVaccines)): ?>
                <div class="activity-item">
                    <div class="activity-details">
                        <p>No recent activity found</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script>
        // Mobile menu toggle
        document.querySelector('.menu-toggle').addEventListener('click', function () {
            document.getElementById('nav-menu').classList.toggle('active');
        });
    </script>
</body>
</html>
