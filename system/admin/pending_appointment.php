<?php
session_start();
require '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    header("Location:login.php");
    exit();
}

// Get all barangays
$barangays_query = $conn->query("SELECT DISTINCT name FROM barangays ORDER BY name");
if (!$barangays_query) {
    die("Error getting barangays: " . $conn->error);
}

$all_barangays = [];
while ($row = $barangays_query->fetch_assoc()) {
    $all_barangays[] = $row['name'];
}

// Get pending vaccine counts grouped by barangay and vaccine type
$vaccine_counts = [];
$vaccines_query = "SELECT 
                    b.barangay,
                    s.type_of_vaccine,
                    COUNT(*) as count
                 FROM schedule s
                 JOIN babies b ON s.baby_id = b.baby_id
                 WHERE s.status IN ('Pending', 'Rescheduled')
                 GROUP BY b.barangay, s.type_of_vaccine
                 ORDER BY b.barangay, s.type_of_vaccine";

$result = $conn->query($vaccines_query);
if (!$result) {
    die("Error in vaccine counts query: " . $conn->error);
}

while ($row = $result->fetch_assoc()) {
    $barangay = $row['barangay'];
    $vaccine = $row['type_of_vaccine'];
    
    if (!isset($vaccine_counts[$barangay])) {
        $vaccine_counts[$barangay] = [];
    }
    
    $vaccine_counts[$barangay][$vaccine] = $row['count'];
}

// Get all vaccine types for headers
$vaccine_types_query = $conn->query("SELECT DISTINCT vaccine_type FROM vaccine_inventory ORDER BY vaccine_type");
if (!$vaccine_types_query) {
    die("Error getting vaccine types: " . $conn->error);
}

$vaccine_types = [];
while ($row = $vaccine_types_query->fetch_assoc()) {
    $vaccine_types[] = $row['vaccine_type'];
}

// Calculate total vaccines needed per barangay
$vaccines_per_barangay = [];
foreach ($all_barangays as $barangay) {
    $vaccines_per_barangay[$barangay] = isset($vaccine_counts[$barangay]) ? 
        array_sum($vaccine_counts[$barangay]) : 0;
}

// Calculate total vaccines needed per type
$vaccines_per_type = [];
foreach ($vaccine_types as $vaccine) {
    $total = 0;
    foreach ($all_barangays as $barangay) {
        if (isset($vaccine_counts[$barangay][$vaccine])) {
            $total += $vaccine_counts[$barangay][$vaccine];
        }
    }
    $vaccines_per_type[$vaccine] = $total;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccine Preparation Dashboard</title>
    <link rel="stylesheet" href="../../css/admin/pending_appointment.css">
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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
<div class="main-content">
    <div class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="bi bi-clipboard2-pulse"></i> Vaccine Preparation Dashboard</h1>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="filter-container">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="barangay-filter" class="form-label">Filter by Barangay:</label>
                            <select id="barangay-filter" class="form-select" multiple>
                                <option value="" selected>All Barangays</option>
                                <?php foreach ($all_barangays as $barangay): ?>
                                    <option value="<?= htmlspecialchars($barangay) ?>"><?= htmlspecialchars($barangay) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-table"></i> Vaccines Needed per Barangay</h5>
                        <span class="badge badge-count">Updated: <?= date('M d, Y h:i A') ?></span>
                    </div>
                    <div class="table-responsive">
                        <table id="vaccineTable" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Vaccine Type</th>
                                    <?php foreach ($all_barangays as $barangay): ?>
                                        <th><?= htmlspecialchars($barangay) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vaccine_types as $vaccine): ?>
                                    <tr>
                                        <td class="vaccine-name-cell"><?= htmlspecialchars($vaccine) ?></td>
                                        <?php foreach ($all_barangays as $barangay): 
                                            $count = isset($vaccine_counts[$barangay][$vaccine]) ? $vaccine_counts[$barangay][$vaccine] : 0;
                                        ?>
                                            <td class="<?= $count > 0 ? 'highlight-cell' : 'zero-count' ?>">
                                                <?= $count > 0 ? $count : '-' ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td>Total per Barangay</td>
                                    <?php foreach ($all_barangays as $barangay): ?>
                                        <td><?= isset($vaccines_per_barangay[$barangay]) ? $vaccines_per_barangay[$barangay] : '-' ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    
    <script>
        $(document).ready(function() {
            // Initialize Select2 for multi-select filters
            $('#barangay-filter').select2({
                placeholder: "Select barangays...",
                allowClear: true
            });
            
            $('#vaccine-filter').select2({
                placeholder: "Select vaccine types...",
                allowClear: true
            });
            
            // Initialize DataTable
            var table = $('#vaccineTable').DataTable({
                paging: true,
                pageLength: 16,
                lengthMenu: [10, 25, 50, 100],
                dom: '<"top"lf>rt<"bottom"ip>',
                scrollX: true,
                scrollCollapse: true,
                fixedColumns: {
                    left: 1
                },
                columnDefs: [
                    { 
                        targets: '_all',
                        className: 'text-center'
                    },
                    {
                        targets: [0],
                        className: 'text-left'
                    },
                    {
                        targets: 'no-sort',
                        orderable: false
                    }
                ],
                initComplete: function() {
                    // Add custom filtering
                    $('#barangay-filter').on('change', function() {
                        var selectedBarangays = $(this).val();
                        
                        if (selectedBarangays == null || selectedBarangays.length == 0 || (selectedBarangays.length == 1 && selectedBarangays[0] == "")) {
                            table.columns().search('').draw();
                        } else {
                            // Get the column indexes for the selected barangays
                            var columnIndexes = [];
                            table.columns().every(function(index) {
                                var columnHeader = table.column(index).header();
                                if (columnHeader) {
                                    var barangayName = $(columnHeader).text().trim();
                                    if (selectedBarangays.includes(barangayName)) {
                                        columnIndexes.push(index);
                                    }
                                }
                            });
                            
                            // Show only selected columns
                            table.columns().visible(false);
                            table.columns(0).visible(true); // Always show vaccine type column
                            columnIndexes.forEach(function(index) {
                                table.columns(index).visible(true);
                            });
                        }
                    });
                    
                    $('#vaccine-filter').on('change', function() {
                        var selectedVaccines = $(this).val();
                        
                        if (selectedVaccines == null || selectedVaccines.length == 0 || (selectedVaccines.length == 1 && selectedVaccines[0] == "")) {
                            table.rows().search('').draw();
                        } else {
                            // Filter rows based on vaccine type
                            table.rows().every(function() {
                                var rowData = this.data();
                                var vaccineName = $(rowData[0]).text().trim();
                                
                                if (selectedVaccines.includes(vaccineName)) {
                                    this.search('').draw();
                                } else {
                                    this.search('^$', true, false).draw();
                                }
                            });
                        }
                    });
                }
            });
            
            // Add hover effects
            $('#vaccineTable').on('mouseenter', 'td.highlight-cell', function() {
                $(this).css('background-color', 'rgba(231, 76, 60, 0.2)');
                
                // Highlight the entire row and column
                var cell = this;
                var colIdx = table.cell(cell).index().column;
                var rowIdx = table.cell(cell).index().row;
                
                // Highlight column
                $(table.cells().nodes()).removeClass('column-highlight');
                $(table.column(colIdx).nodes()).addClass('column-highlight');
                
                // Highlight row
                $(table.rows().nodes()).removeClass('row-highlight');
                $(table.row(rowIdx).nodes()).addClass('row-highlight');
            });
            
            $('#vaccineTable').on('mouseleave', 'td.highlight-cell', function() {
                $(this).css('background-color', 'rgba(231, 76, 60, 0.1)');
                $(table.cells().nodes()).removeClass('column-highlight row-highlight');
            });
            
            // Add tooltips for better UX
            $('[data-toggle="tooltip"]').tooltip();
            
            // Reset filters button
            $('<button class="btn btn-sm btn-outline-secondary ms-2">Reset Filters</button>')
                .appendTo($('#barangay-filter').parent())
                .on('click', function() {
                    $('#barangay-filter').val(null).trigger('change');
                    $('#vaccine-filter').val(null).trigger('change');
                    table.columns().search('').draw();
                    table.rows().search('').draw();
                    table.columns().visible(true);
                });
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