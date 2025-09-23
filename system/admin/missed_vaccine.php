<?php
session_start();
require '../config.php';

// Ensure only admins can access
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    header("Location: login.php");
    exit();
}

// Get the selected filter (if any)
$filter_barangay = isset($_GET['filter_barangay']) ? $_GET['filter_barangay'] : '';

// Pagination settings
$per_page = 3; // Number of babies per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure page is at least 1
$offset = ($page - 1) * $per_page;

// Build the base query
$query = "
    SELECT SQL_CALC_FOUND_ROWS b.*
    FROM babies b
    INNER JOIN schedule s ON b.baby_id = s.baby_id
    WHERE s.status = 'missed'
";

if (!empty($filter_barangay)) {
    $query .= " AND b.barangay = '" . $conn->real_escape_string($filter_barangay) . "'";
}

$query .= " GROUP BY b.baby_id ORDER BY b.baby_name, b.parent_name LIMIT $offset, $per_page";


// Fetch babies with optional filter and pagination
$babies = [];
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $baby_id = (int)$row['baby_id'];
        $schedule_query = "SELECT type_of_vaccine, schedule_date, status 
                          FROM schedule 
                          WHERE baby_id = $baby_id 
                          ORDER BY schedule_date";

        $schedule_result = $conn->query($schedule_query);
        $missed_vaccines = [];

        if ($schedule_result) {
            while ($schedule = $schedule_result->fetch_assoc()) {
                if ($schedule['status'] == 'missed') {
                    $missed_vaccines[] = $schedule['type_of_vaccine'] . " (" . $schedule['schedule_date'] . ")";
                }
            }
        }

        if (!empty($missed_vaccines)) {
            $row['missed_vaccines'] = implode(', ', $missed_vaccines);
            $babies[] = $row;
        }
    }
} else {
    // Handle query error if needed
    die("Error fetching babies: " . $conn->error);
}

// Get total count for pagination
$total_result = $conn->query("SELECT FOUND_ROWS()");
$total_babies = $total_result ? (int)$total_result->fetch_row()[0] : 0;
$total_pages = ceil($total_babies / $per_page);

// Fetch all barangays for filter dropdown
$barangays_result = $conn->query("SELECT DISTINCT barangay FROM babies ORDER BY barangay");
$barangays = [];
if ($barangays_result) {
    while ($b = $barangays_result->fetch_assoc()) {
        $barangays[] = $b['barangay'];
    }
} else {
    // Optional error handling
    $barangays = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Baby Records Management</title>
    <link rel="stylesheet" href="../../css/admin/missed_vaccine.css" />
    <link rel="icon" href="../../img/logo1.png" type="image/png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
    .is-invalid {
        border-color: #dc3545;
    }
</style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="dashboard-container">
        <div class="header-content">
            <h1><i class="fas fa-syringe me-2"></i>Missed Vaccine</h1>
            <div class="breadcrumb">
                <span>Admin</span> <i class="fas fa-chevron-right mx-2"></i> <span class="active">Missed Vaccine</span>
            </div>
        </div>
<!-- Notification Prompt (must be in your HTML) -->
<div id="notificationPrompt" style="display:none; position:fixed; bottom:20px; right:20px; background:white; padding:15px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1); z-index:1000;">
    <p>Allow vaccine reminders?</p>
    <button id="allowNotificationsBtn" class="btn btn-sm btn-success me-2">Allow</button>
    <button id="denyNotificationsBtn" class="btn btn-sm btn-secondary">Later</button>
</div>

<!-- Notification Test Button (for debugging) -->
<button id="testNotificationBtn" class="btn btn-sm btn-info" style="position:fixed; bottom:20px; left:20px;">
    <i class="fas fa-bell"></i> Test Notifications
</button>
        <!-- Babies Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-baby-carriage me-2"></i>Infant Records</span>
                <span class="badge bg-primary">
                    <?= count($babies) ?>
                    <?= !empty($filter_barangay) ? 'in ' . htmlspecialchars($filter_barangay) : '' ?>
                </span>
            </div>
            <div class="card-body">
                <?php if (empty($babies)): ?>
                    <div class="alert alert-info text-center py-4">
                        <i class="fas fa-baby-carriage fa-2x mb-3"></i>
                        <h4>No baby records found <?= !empty($filter_barangay) ? "in " . htmlspecialchars($filter_barangay) : "" ?></h4>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Photo</th>
                                    <th>Baby Name</th>
                                    <th>Parent</th>
                                    <th>Age</th>
                                    <th>Barangay</th>
                                    <th>Missed Vaccine</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($babies as $baby): ?>
                                <tr class="<?= !empty($baby['missed_vaccines']) ? 'missed-vaccine' : '' ?>" onclick="loadBabyDetails(<?= (int)$baby['baby_id'] ?>)" style="cursor: pointer;">
                                    <td>
                                        <img src="../../assets/baby.png" class="baby-avatar me-3" alt="Baby photo" />
                                    </td>
                                    <td><?= htmlspecialchars($baby['baby_name']) ?></td>
                                    <td><?= htmlspecialchars($baby['parent_name']) ?></td>
                                    <td><?= calculateAge($baby['birthdate']) ?></td>
                                    <td><?= htmlspecialchars($baby['barangay']) ?></td>
                                    <td><?= htmlspecialchars($baby['missed_vaccines']) ?></td>
                                    <td>
                                        <button
                                            class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#babyModal"
                                            onclick="event.stopPropagation(); loadBabyDetails(<?= (int)$baby['baby_id'] ?>)">
                                            <i class="fas fa-calendar-alt"></i> Re-Sched
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($filter_barangay) ? '&filter_barangay=' . urlencode($filter_barangay) : '' ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?><?= !empty($filter_barangay) ? '&filter_barangay=' . urlencode($filter_barangay) : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($filter_barangay) ? '&filter_barangay=' . urlencode($filter_barangay) : '' ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Baby Details Modal -->
<div class="modal fade" id="babyModal" tabindex="-1" aria-labelledby="babyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="babyModalLabel">Re-Schedule</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="rescheduleForm" method="post">
        <input type="hidden" name="baby_id" id="modalBabyId"/> 
        <div id="babyDetails">
          <table class="table">
            <thead>
              <tr>
                <th>Vaccine</th>
                <th>Original Date</th>
                <th>New Schedule</th>
              </tr>
            </thead>
            <tbody id="babyScheduleBody">
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-calendar-alt"></i> Re-Sched
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


    <!-- JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>

    // ================== BABY DETAILS FUNCTIONS ================== //
    function loadBabyDetails(babyId) {
        $('#modalBabyId').val(babyId);
        $('#babyScheduleBody').html('<tr><td colspan="3" class="text-center">Loading...</td></tr>');

        $.ajax({
            url: 'get_baby_schedule.php',
            type: 'GET',
            data: { baby_id: babyId },
            dataType: 'json',
            success: function(response) {
                const tbody = $('#babyScheduleBody');
                tbody.empty();

                if (response.length === 0) {
                    tbody.append('<tr><td colspan="3" class="text-center">No missed vaccines found.</td></tr>');
                } else {
                    response.forEach(item => {
                        tbody.append(`
                            <tr>
                                <td>${item.type_of_vaccine}</td>
                                <td>${item.schedule_date}</td>
                                <td>
                                    <input type="hidden" name="schedule_id[]" value="${item.schedule_id}">
                                    <input type="text" class="datepicker" placeholder="Add New Schedule" name="new_date[]" required>
                                </td>
                            </tr>
                        `);
                    });

                    flatpickr(".datepicker", {
                        dateFormat: "Y-m-d",
                        minDate: "today"
                    });
                }
            },
            error: function() {
                $('#babyScheduleBody').html('<tr><td colspan="3" class="text-danger text-center">Error loading data.</td></tr>');
            }
        });
    }

    // ================== FORM HANDLING ================== //
    $('#rescheduleForm').on('submit', function(e) {
        e.preventDefault();

        let allDatesFilled = true;
        $('input[name="new_date[]"]').each(function() {
            if (!$(this).val()) {
                allDatesFilled = false;
                $(this).addClass('is-invalid'); 
            } else {
                $(this).removeClass('is-invalid');
            }
        });

        if (!allDatesFilled) {
            alert('Please fill in all new schedule dates before submitting.');
            return;
        }

        $.ajax({
            url: 'reschedule_vaccines.php',
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.trim() === 'success') {
                    alert('Vaccines rescheduled successfully!');
                    $('#babyModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + response);
                }
            },
            error: function(xhr) {
                alert('AJAX error: ' + xhr.responseText);
            }
        });
    });

    // ================== INITIALIZATION ================== //
    $(document).ready(function() {
        // Initialize datepickers
        flatpickr(".datepicker", {
            dateFormat: "Y-m-d",
            minDate: "today"
        });

        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();

        // Notification bell click handler
        $('#notificationBell').click(function() {
            if (Notification.permission === "granted") {
                showMissedVaccineNotification();
            } else {
                showNotificationPrompt();
            }
        });

        // Allow button click handler
        $('#allowNotificationsBtn').click(function(e) {
            e.preventDefault();
            requestNotificationPermission();
        });

        // Deny button click handler
        $('#denyNotificationsBtn').click(function(e) {
            e.preventDefault();
            $('#notificationPrompt').hide();
            localStorage.setItem('notificationPermission', 'denied');
        });

        // Check initial notification permission
        if (Notification.permission === "granted") {
            console.log("Notifications are already enabled");
        }
    });

    // ================== SIDEBAR FUNCTIONS ================== //
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }
// Notification System with Visual Feedback
const NOTIFICATION_KEY = 'vaxibloom_notifs_v2';

// Core Notification Functions
function showVaccineReminder() {
    if (!("Notification" in window)) {
        console.warn("Browser doesn't support notifications");
        showFallbackAlert("Your browser doesn't support notifications");
        return false;
    }

    if (Notification.permission === "granted") {
        try {
            const notification = new Notification("⚠️ Vaccine Reminder", {
                body: "You have missed vaccines that need to be rescheduled",
                icon: '../../img/logo1.png',
                requireInteraction: true // Keeps notification visible longer
            });

            notification.onclick = () => {
                window.focus();
                notification.close();
            };
            return true;
        } catch (e) {
            console.error("Notification failed:", e);
            showFallbackAlert("Couldn't show notification");
            return false;
        }
    }
    return false;
}

function showFallbackAlert(message) {
    // Create a visual alert if notifications fail
    const alertBox = document.createElement('div');
    alertBox.style = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #ff4444;
        color: white;
        padding: 15px;
        border-radius: 5px;
        z-index: 1000;
        animation: fadeIn 0.5s;
    `;
    alertBox.innerHTML = `
        <strong>Alert:</strong> ${message}
        <button style="margin-left:10px; background:none; border:none; color:white; cursor:pointer">×</button>
    `;
    alertBox.querySelector('button').onclick = () => alertBox.remove();
    document.body.appendChild(alertBox);
    setTimeout(() => alertBox.remove(), 5000);
}

// Permission Management
async function requestNotificationPermission() {
    if (!("Notification" in window)) return false;
    
    try {
        const permission = await Notification.requestPermission();
        localStorage.setItem(NOTIFICATION_KEY, permission);
        
        if (permission === "granted") {
            return showVaccineReminder();
        }
        return false;
    } catch (e) {
        console.error("Permission error:", e);
        return false;
    }
}

// UI Controls
function setupNotificationControls() {
    // Allow Button
    document.getElementById('allowNotificationsBtn')?.addEventListener('click', async (e) => {
        e.preventDefault();
        const success = await requestNotificationPermission();
        document.getElementById('notificationPrompt').style.display = 'none';
        if (!success) {
            showFallbackAlert("Please enable notifications in browser settings");
        }
    });

    // Deny Button
    document.getElementById('denyNotificationsBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('notificationPrompt').style.display = 'none';
        localStorage.setItem(NOTIFICATION_KEY, 'denied');
    });

    // Test Button
    document.getElementById('testNotificationBtn')?.addEventListener('click', () => {
        if (Notification.permission === "granted") {
            const success = showVaccineReminder();
            console.log("Test notification", success ? "worked" : "failed");
        } else {
            showFallbackAlert(`Permission status: ${Notification.permission}`);
        }
    });
}

// Initialization
document.addEventListener('DOMContentLoaded', function() {
    setupNotificationControls();
    
    // Show prompt if not already decided
    if (Notification.permission === "default" && 
        localStorage.getItem(NOTIFICATION_KEY) !== 'denied') {
        document.getElementById('notificationPrompt').style.display = 'block';
    }
    
    // If already permitted, show reminder immediately
    if (Notification.permission === "granted") {
        setTimeout(showVaccineReminder, 1000);
    }
    
    // Debug info
    console.log("Notification status:", {
        supported: "Notification" in window,
        permission: Notification.permission,
        stored: localStorage.getItem(NOTIFICATION_KEY)
    });
});
// Sidebar dropdown functionality
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

<?php
// Helper function to calculate age from birth date
function calculateAge($birthDate) {
    $birthDate = new DateTime($birthDate);
    $today = new DateTime();
    $age = $today->diff($birthDate);

    if ($age->y > 0) {
        return $age->y . ' year' . ($age->y > 1 ? 's' : '');
    } elseif ($age->m > 0) {
        return $age->m . ' month' . ($age->m > 1 ? 's' : '');
    } else {
        return $age->d . ' day' . ($age->d > 1 ? 's' : '');
    }
}
?>
