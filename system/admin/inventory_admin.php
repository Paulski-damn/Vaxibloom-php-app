<?php
session_start();
require '../config.php';

// Only allow admins to access this page
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../system/admin/login.php');
    exit;
}

$barangay_filter = $_GET['barangay'] ?? ''; // Get the selected barangay from the URL

// Pagination variables
$rows_per_page = 10; // Increased from 5 to 10 for better data visibility
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

// Query to get all barangays for the dropdown
$barangays = $conn->query("SELECT DISTINCT barangay FROM vaccine_inventory");

// Query to fetch inventory based on the selected barangay
if ($barangay_filter) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM vaccine_inventory WHERE barangay = ?");
    $count_stmt->bind_param("s", $barangay_filter);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_rows = $count_result->fetch_assoc()['total'];
    
    $stmt = $conn->prepare("SELECT * FROM vaccine_inventory WHERE barangay = ? ORDER BY barangay, vaccine_type LIMIT ?, ?");
    $offset = ($current_page - 1) * $rows_per_page;
    $stmt->bind_param("sii", $barangay_filter, $offset, $rows_per_page);
} else {
    $count_result = $conn->query("SELECT COUNT(*) as total FROM vaccine_inventory");
    $total_rows = $count_result->fetch_assoc()['total'];
    
    $stmt = $conn->prepare("SELECT * FROM vaccine_inventory ORDER BY barangay, vaccine_type LIMIT ?, ?");
    $offset = ($current_page - 1) * $rows_per_page;
    $stmt->bind_param("ii", $offset, $rows_per_page);
}

$stmt->execute();
$result = $stmt->get_result();

// Calculate total pages
$total_pages = ceil($total_rows / $rows_per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccine Inventory - Admin</title>
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link rel="stylesheet" href="../../css/admin/inventory_admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="content-container">
        <div class="header-container animate__animated animate__fadeIn">
            <h2><i class="fas fa-vial me-2"></i>Vaccine Inventory Management</h2>
            <div class="breadcrumb">
                <span>Admin</span> <i class="fas fa-chevron-right mx-2"></i> <span class="active">Inventory</span>
            </div>
        </div>

        <!-- Filter and Search Section -->
        <div class="card filter-card mb-4 animate__animated animate__fadeIn">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <!-- Barangay filter -->
                        <form method="GET" action="inventory_admin.php" class="mb-4 filter-form">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-filter"></i></span>
                                <select name="barangay" class="form-select select2" onchange="this.form.submit()">
                                    <option value="">-- All Barangays --</option>
                                    <?php while ($barangay = $barangays->fetch_assoc()): ?>
                                        <option value="<?= $barangay['barangay'] ?>" <?= $barangay['barangay'] === $barangay_filter ? 'selected' : '' ?>>
                                            <?= $barangay['barangay'] ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <input type="hidden" name="page" value="1">
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 text-md-end text-center">
            <a href="export_inventory.php?barangay=<?= urlencode($barangay_filter) ?>" class="btn btn-success">
                <i class="fas fa-file-excel me-2"></i> Export to Excel
            </a>
        </div>

        <!-- Vaccine Inventory Table -->
        <div class="card table-card animate__animated animate__fadeIn">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="inventoryTable">
                        <thead class="table-dark">
                            <tr>
                                <th><i class="fas fa-vial me-1"></i> Vaccine Type</th>
                                <th><i class="fas fa-map-marker-alt me-1"></i> Barangay</th>
                                <th><i class="fas fa-boxes me-1"></i> Quantity</th>
                                <th><i class="fas fa-cogs me-1"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['vaccine_type']) ?></td>
                                <td>
                                    <span class="badge bg-primary"><?= htmlspecialchars($row['barangay']) ?></span>
                                </td>
                                <td>
                                    <div class="quantity-display">
                                        <span class="quantity-value"><?= $row['quantity'] ?></span>
                                        <?php if ($row['quantity'] == 0): ?>
                                            <span class="badge bg-danger ms-2"><i class="fas fa-times-circle"></i> Out of Stock</span>
                                        <?php elseif ($row['quantity'] < 30): ?>
                                            <span class="badge bg-warning ms-2"><i class="fas fa-exclamation-triangle"></i> Low Stock</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- Update Quantity Button (Triggers Modal) -->
                                        <button class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#updateModal" 
                                            data-id="<?= $row['id'] ?>" 
                                            data-vaccine="<?= htmlspecialchars($row['vaccine_type']) ?>"
                                            data-current="<?= $row['quantity'] ?>">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                        
                                        <!-- Transfer Button (Triggers Modal) -->
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#transferModal"
                                            data-id="<?= $row['id'] ?>"
                                            data-vaccine="<?= htmlspecialchars($row['vaccine_type']) ?>"
                                            data-barangay="<?= htmlspecialchars($row['barangay']) ?>"
                                            data-current="<?= $row['quantity'] ?>">
                                            <i class="fas fa-exchange-alt"></i> Transfer
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="showing-entries">
                            Showing <?= ($current_page - 1) * $rows_per_page + 1 ?> to <?= min($current_page * $rows_per_page, $total_rows) ?> of <?= $total_rows ?> entries
                        </div>
                    </div>
                    <div class="col-md-6">
                        <nav aria-label="Page navigation" class="float-end">
                            <ul class="pagination pagination-sm">
                                <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?barangay=<?= urlencode($barangay_filter) ?>&page=1" aria-label="First">
                                            <span aria-hidden="true"><i class="fas fa-angle-double-left"></i></span>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?barangay=<?= urlencode($barangay_filter) ?>&page=<?= $current_page - 1 ?>" aria-label="Previous">
                                            <span aria-hidden="true"><i class="fas fa-angle-left"></i></span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php 
                                // Show page numbers
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?barangay='.urlencode($barangay_filter).'&page=1">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?barangay=<?= urlencode($barangay_filter) ?>&page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor;
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?barangay='.urlencode($barangay_filter).'&page='.$total_pages.'">'.$total_pages.'</a></li>';
                                }
                                ?>

                                <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?barangay=<?= urlencode($barangay_filter) ?>&page=<?= $current_page + 1 ?>" aria-label="Next">
                                            <span aria-hidden="true"><i class="fas fa-angle-right"></i></span>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?barangay=<?= urlencode($barangay_filter) ?>&page=<?= $total_pages ?>" aria-label="Last">
                                            <span aria-hidden="true"><i class="fas fa-angle-double-right"></i></span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Quantity Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="updateModalLabel">Update Vaccine Quantity</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="edit_inventory.php">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="updateVaccineId">
                        <div class="mb-3">
                            <label for="vaccineType" class="form-label">Vaccine Type</label>
                            <input type="text" class="form-control" id="updateVaccineType" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="currentQty" class="form-label">Current Quantity</label>
                            <input type="text" class="form-control" id="updateCurrentQty" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="adjustQty" class="form-label">Adjustment (+/-)</label>
                            <input type="number" name="adjust_qty" class="form-control" id="updateAdjustQty" required>
                            <small class="text-muted">Enter positive number to add, negative to subtract</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="adjust" class="btn btn-primary">Update Quantity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Transfer Vaccine Modal -->
    <div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="transferModalLabel">Transfer Vaccines</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="edit_inventory.php">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="transferVaccineId">
                        <div class="mb-3">
                            <label for="vaccineType" class="form-label">Vaccine Type</label>
                            <input type="text" class="form-control" id="transferVaccineType" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="currentBarangay" class="form-label">Current Barangay</label>
                            <input type="text" class="form-control" id="transferCurrentBarangay" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="currentQty" class="form-label">Current Quantity</label>
                            <input type="text" class="form-control" id="transferCurrentQty" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="targetBarangay" class="form-label">Transfer To</label>
                            <select name="target_barangay" class="form-select" id="transferTargetBarangay" required>
                                <option value="">-- Select Barangay --</option>
                                <option value="Barangay 1">Barangay 1</option>
                                <option value="Barangay 2">Barangay 2</option>
                                <option value="Barangay 3">Barangay 3</option>
                                <option value="Barangay 4">Barangay 4</option>
                                <option value="Barangay 5">Barangay 5</option>
                                <option value="Barangay Kabulusan">Barangay Kabulusan</option>
                                <option value="Barangay Ramirez">Barangay Ramirez</option>
                                <option value="Barangay San Agustin">Barangay San Agustin</option>
                                <option value="Barangay Urdaneta">Barangay Urdaneta</option>
                                <option value="Barangay Bendita 1">Barangay Bendita 1</option>
                                <option value="Barangay Bendita 2">Barangay Bendita 2</option>
                                <option value="Barangay Caluangan">Barangay Caluangan</option>
                                <option value="Barangay Baliwag">Barangay Baliwag</option>
                                <option value="Barangay Pacheco">Barangay Pacheco</option>
                                <option value="Barangay Medina">Barangay Medina</option>
                                <option value="Barangay Tua">Barangay Tua</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="transferQty" class="form-label">Quantity to Transfer</label>
                            <input type="number" name="transfer_qty" class="form-control" id="transferQty" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="transfer" class="btn btn-success">Transfer Vaccines</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="liveToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto" id="toastTitle">Notification</strong>
                <small>Just now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastMessage">
                Action completed successfully.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2 for dropdowns
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: "-- All Barangays --",
                allowClear: true
            });
            
            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();
            
            // Update Modal Handler
            $('#updateModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var vaccine = button.data('vaccine');
                var current = button.data('current');
                
                var modal = $(this);
                modal.find('#updateVaccineId').val(id);
                modal.find('#updateVaccineType').val(vaccine);
                modal.find('#updateCurrentQty').val(current);
            });
            
            // Transfer Modal Handler
            $('#transferModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var vaccine = button.data('vaccine');
                var barangay = button.data('barangay');
                var current = button.data('current');
                
                var modal = $(this);
                modal.find('#transferVaccineId').val(id);
                modal.find('#transferVaccineType').val(vaccine);
                modal.find('#transferCurrentBarangay').val(barangay);
                modal.find('#transferCurrentQty').val(current);
                modal.find('#transferTargetBarangay').val('');
                modal.find('#transferQty').val('');
            });
            
            // Table search functionality
            $('#inventorySearch').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('#inventoryTable tbody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
            
            // Show toast if there's a message in URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('message')) {
                showToast(urlParams.get('title') || 'Notification', urlParams.get('message'));
            }
        });
        
        function showToast(title, message) {
            $('#toastTitle').text(title);
            $('#toastMessage').text(message);
            $('.toast').toast('show');
        }
        
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