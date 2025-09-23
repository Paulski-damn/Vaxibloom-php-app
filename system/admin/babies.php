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
$per_page = 10; // Number of babies per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure page is at least 1
$offset = ($page - 1) * $per_page;

// Get the search query (if any)
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build the base query
$query = "SELECT SQL_CALC_FOUND_ROWS b.* FROM babies b";
$where_clauses = [];

if (!empty($filter_barangay)) {
    $where_clauses[] = "b.barangay = '" . $conn->real_escape_string($filter_barangay) . "'";
}

if (!empty($search_query)) {
    $search_term = $conn->real_escape_string($search_query);
    $where_clauses[] = "(b.baby_name LIKE '%$search_term%' OR b.parent_name LIKE '%$search_term%')";
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " ORDER BY b.baby_name, b.parent_name LIMIT $offset, $per_page";

// Fetch babies with optional filter and pagination
$babies = [];
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Get vaccination schedules for this baby
        $baby_id = $row['baby_id'];
        $schedule_query = "SELECT type_of_vaccine, schedule_date, status 
                          FROM schedule
                          WHERE baby_id = $baby_id 
                          ORDER BY schedule_date";
        
        $schedule_result = $conn->query($schedule_query);
        $vaccines = [];
        $vaccine_count = 0;
        
        if ($schedule_result) {
            while ($schedule = $schedule_result->fetch_assoc()) {
                $vaccines[] = $schedule['type_of_vaccine'];
                if ($schedule['status'] == 'Completed') {
                    $vaccine_count++;
                }
            }
        }
        
        // Add vaccine info to baby data
        $row['vaccines_received'] = implode(', ', $vaccines);
        $row['vaccine_count'] = $vaccine_count;
        $row['schedules'] = $vaccines;
        
        $babies[] = $row;
    }
}

// Get total count for pagination
$total_result = $conn->query("SELECT FOUND_ROWS()");
$total_babies = $total_result->fetch_row()[0];
$total_pages = ceil($total_babies / $per_page);

// Fetch all barangays for filter dropdown
$barangays = $conn->query("SELECT DISTINCT barangay FROM babies ORDER BY barangay");


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baby Records Management</title>
    <link rel="stylesheet" href="../../css/admin/babies.css">
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="dashboard-container">
        <div class="header-content">
        <h1><i class="fas fa-baby"></i>Immunization Record Reports</h1>
        <div class="breadcrumb">
            <span>Admin</span> <i class="fas fa-chevron-right mx-2"></i> <span class="active">Immunization Report</span>
        </div>
    </div>
        
        <!-- Filter Section -->
        <div class="filter-container card">
            <div class="card-header">
                <i class="fas fa-filter me-2"></i>Filter Babies
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <div class="filter-group">
                            <label for="filter_barangay" class="form-label">Filter by Barangay:</label>
                            <select class="form-select" name="filter_barangay" id="filter_barangay">
                                <option value="">All Barangays</option>
                                <?php while ($row = $barangays->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($row['barangay']) ?>"
                                        <?= ($filter_barangay == $row['barangay']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($row['barangay']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                        <div class="col-md-4">
                            <div class="filter-group">
                                <label for="search_query" class="form-label">Search:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search" id="search_query" 
                                        placeholder="Search by name..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                                </div>
                            </div>
                        </div>
                    <div class="col-md-4">
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Apply Filter
                            </button>
                            <a href="babies.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
       <!-- Babies Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-baby-carriage me-2"></i>Infants Records</span>
        <span class="badge bg-primary">
            <?= count($babies) ?> 
            <?= !empty($filter_barangay) ? 'in ' . htmlspecialchars($filter_barangay) : '' ?>
            <?= !empty($search_query) ? 'matching "' . htmlspecialchars($search_query) . '"' : 'Total' ?>
        </span>
    </div>
    <div class="card-body">
        <?php if (empty($babies)): ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas fa-baby-carriage fa-2x mb-3"></i>
                <h4>No baby records found <?= !empty($filter_barangay) ? "in $filter_barangay" : "" ?>
                <?= !empty($search_query) ? 'matching "' . htmlspecialchars($search_query) . '"' : '' ?></h4>
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
                            <th>Birthdate</th>
                            <th>Barangay</th>
                            <th>Sex</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($babies as $baby): ?>
                        <tr onclick="loadBabyDetails(<?= $baby['baby_id'] ?>)" style="cursor: pointer;">
                            <td>
                                <img src="../../assets/baby.png" class="baby-avatar me-3-table" alt="Baby photo">
                            </td>
                            <td><?= htmlspecialchars($baby['baby_name']) ?></td>
                            <td><?= htmlspecialchars($baby['parent_name']) ?></td>
                            <td><?= calculateAge($baby['birthdate']) ?></td>
                            <td><?= date('M d, Y', strtotime($baby['birthdate'])) ?></td>
                            <td><?= htmlspecialchars($baby['barangay']) ?></td>
                            <td>
                                <?= $baby['gender'] ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#babyModal"
                                        onclick="event.stopPropagation(); loadBabyDetails(<?= $baby['baby_id'] ?>)">
                                    <i class="fas fa-eye"></i> View
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
   
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?><?= !empty($filter_barangay) ? '&filter_barangay='.urlencode($filter_barangay) : '' ?><?= !empty($search_query) ? '&search='.urlencode($search_query) : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page == $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page+1 ?><?= !empty($filter_barangay) ? '&filter_barangay='.urlencode($filter_barangay) : '' ?>" aria-label="Next">
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
</div>

    <!-- Baby Details Modal -->
    <div class="modal fade" id="babyModal" tabindex="-1" aria-labelledby="babyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light text-dark">
                    <h5 class="modal-title" id="babyModalLabel">Baby Details</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="babyDetails">
                    <!-- Content will be loaded via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="printBabyBtn">
                        <i class="fas fa-print me-2"></i>Print Record
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Load baby details when modal opens
        function loadBabyDetails(babyId) {
    $('#babyDetails').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `);

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
                ];

                vaccines.forEach(vaccine => {
                    const baseName = vaccine.type_of_vaccine.replace(/_(\d+)(st|nd|rd|th)_dose/i, '').trim();
                    if (!organizedVaccines[baseName]) {
                        organizedVaccines[baseName] = { doses: [], dates: [] };
                    }

                    let doseMatch = vaccine.type_of_vaccine.match(/_(\d+)(st|nd|rd|th)_dose/i);
                    let doseNumber = doseMatch ? parseInt(doseMatch[1]) : (organizedVaccines[baseName].doses.length + 1);

                    organizedVaccines[baseName].doses.push(doseNumber);
                    organizedVaccines[baseName].dates.push({
                        date: new Date(vaccine.schedule_date).toLocaleDateString(),
                        administeredBy: vaccine.vaccinated_by || '-'
                    });
                });

                // Add unlisted vaccines to the order
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
                                    <th style="padding: 10px; border: 1px solid #ccc;">Administered By</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                vaccineDisplayOrder.forEach(item => {
                    if (typeof item === 'object' && item.isHeader) {
                        vaccinesHtml += `
                                    ${item.name.replace(/_/g, ' ')}
                        `;
                    } else {
                        const vaccineName = item;
                        const vaccineData = organizedVaccines[vaccineName] || { doses: [], dates: [] };

                        const uniqueDoses = [...new Set(vaccineData.doses)].sort((a, b) => a - b);
                        const doseText = uniqueDoses.length > 0 ?
                            uniqueDoses.map(d => `${d}${getOrdinalSuffix(d)} dose`).join(', ') : '-';

                        const datesText = vaccineData.dates.length > 0 ?
                            vaccineData.dates.map(d => d.date).join('<br>') : '-';

                        const remarksText = vaccineData.dates.length > 0 ?
                            vaccineData.dates.map(d => d.administeredBy).join('<br>') : '-';

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

                $('#babyDetails').html(detailsHtml); // <- Load to your container (could be modal or div)

            } else {
                $('#babyDetails').html(`
                    <div class="alert alert-warning">
                        Failed to load baby details: ${response.message || 'Unknown error'}
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            $('#babyDetails').html(`
                <div class="alert alert-danger">
                    Error loading baby details: ${xhr.responseText || 'Unknown error'}
                </div>
            `);
        }
    });
}

        
        // Print button functionality
        $('#printBabyBtn').click(function () {
            const printContent = $('#babyDetails').html();
            const originalContent = document.body.innerHTML;

            // Set up afterprint event to reload the page
            window.onafterprint = function () {
                location.reload();
            };

            document.body.innerHTML = `
                <div class="container mt-4">
                    <h2 class="text-center mb-4">Baby Health Record</h2>
                    ${printContent}
                </div>
            `;

            window.print();
            // Restore original content immediately (in case of older browsers)
            document.body.innerHTML = originalContent;
        });

        // Initialize tooltips
        $(function () {
            $('[data-bs-toggle="tooltip"]').tooltip()
        });
        document.addEventListener('DOMContentLoaded', function() {
    // Handle dropdown menus
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