<?php
session_start();
require '../config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../system/admin/login.php');
    exit;
}

// Delete message logic
if (isset($_POST['delete_message'])) {
    $message_id = $_POST['message_id'];
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([$message_id]);
    
    // Set success message
    $_SESSION['flash_message'] = "Message deleted successfully!";
    header('Location: messages.php');
    exit;
}

// Get search and filter parameters from URL
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';
$messages_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $messages_per_page;

// Base SQL query
$sql = "SELECT * FROM messages WHERE 1=1";
$params = [];
$types = '';

// Add search condition if search term exists
if (!empty($search)) {
    $sql .= " AND (fullname LIKE ? OR email LIKE ? OR message LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sss';
}

// Add filter condition based on selected filter
if (!empty($filter)) {
    $currentDate = date('Y-m-d');
    switch ($filter) {
        case 'today':
            $sql .= " AND DATE(created_at) = ?";
            $params[] = $currentDate;
            $types .= 's';
            break;
        case 'week':
            $sql .= " AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'month':
            $sql .= " AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
            break;
    }
}

// Add sorting and pagination
$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params = array_merge($params, [$messages_per_page, $offset]);
$types .= 'ii';

// Prepare and execute the query
$stmt = $pdo->prepare($sql);

// Bind parameters dynamically
foreach ($params as $index => $param) {
    $paramType = $types[$index] === 's' ? PDO::PARAM_STR : PDO::PARAM_INT;
    $stmt->bindValue($index + 1, $param, $paramType);
}

$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination (without LIMIT)
$countSql = str_replace('SELECT *', 'SELECT COUNT(*) as total', explode('LIMIT', $sql)[0]);
$countStmt = $pdo->prepare($countSql);

// Bind parameters for count query
foreach (array_slice($params, 0, count($params) - 2) as $index => $param) {
    $paramType = $types[$index] === 's' ? PDO::PARAM_STR : PDO::PARAM_INT;
    $countStmt->bindValue($index + 1, $param, $paramType);
}

$countStmt->execute();
$total_messages = $countStmt->fetchColumn();
$total_pages = ceil($total_messages / $messages_per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Admin Dashboard</title>
    <link rel="stylesheet" href="../../css/admin/messages.css">
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="content" id="main-content">
        <div class="header-container animate__animated animate__fadeIn">
            <div class="header-content">
                <h1><i class="fas fa-envelope me-2"></i>User Messages</h1>
                <div class="breadcrumb">
                    <span>Admin</span> <i class="fas fa-chevron-right mx-2"></i> <span class="active">Messages</span>
                </div>
            </div>
            
            <div class="search-filter-container">
                <form method="GET" action="messages.php" id="searchFilterForm">
                    <div class="search-box">
                        <input type="text" name="search" id="searchInput" placeholder="Search messages..." 
                               value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                    </div>
                    <div class="filter-dropdown">
                        <select name="filter" id="filterSelect" class="form-select">
                            <option value="">All Messages</option>
                            <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="week" <?= $filter === 'week' ? 'selected' : '' ?>>This Week</option>
                            <option value="month" <?= $filter === 'month' ? 'selected' : '' ?>>This Month</option>
                        </select>
                    </div>
                    <input type="hidden" name="page" value="1">
                </form>
            </div>
        </div>
        
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <?= $_SESSION['flash_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>
        
        
        <?php if (count($messages) > 0): ?>
        <div class="card table-card animate__animated animate__fadeIn">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="messagesTable">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Message Preview</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $message): ?>
                            <tr class="message-row" data-id="<?= htmlspecialchars($message['id']) ?>">
                                <td><?= htmlspecialchars($message['id']) ?></td>
                                <td>
                                    <div class="user-info">
                                        <span class="user-name"><?= htmlspecialchars($message['fullname']) ?></span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($message['contact']) ?></td>
                                <td><?= htmlspecialchars($message['email']) ?></td>
                                <td class="message-preview">
                                    <?= htmlspecialchars(substr($message['message'], 0, 50)) . (strlen($message['message']) > 50 ? '...' : '') ?>
                                </td>
                                <td>
                                    <div class="date-badge">
                                        <?= date('M j, Y', strtotime($message['created_at'])) ?>
                                        <small><?= date('g:i A', strtotime($message['created_at'])) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary view-btn" 
                                            data-id="<?= htmlspecialchars($message['id']) ?>"
                                            data-fullname="<?= htmlspecialchars($message['fullname']) ?>"
                                            data-contact="<?= htmlspecialchars($message['contact']) ?>"
                                            data-email="<?= htmlspecialchars($message['email']) ?>"
                                            data-message="<?= htmlspecialchars($message['message']) ?>"
                                            data-date="<?= date('M j, Y g:i A', strtotime($message['created_at'])) ?>">
                                            <i class="fas fa-eye me-1"></i> View
                                        </button>
                                        <form method="POST" class="delete-form d-inline">
                                            <input type="hidden" name="message_id" value="<?= htmlspecialchars($message['id']) ?>">
                                            <button type="submit" name="delete_message" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash-alt me-1"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="showing-entries">
                            Showing <?= ($page - 1) * $messages_per_page + 1 ?> to <?= min($page * $messages_per_page, $total_messages) ?> of <?= $total_messages ?> entries
                        </div>
                    </div>
                    <div class="col-md-6">
                        <nav aria-label="Page navigation" class="float-end">
                            <ul class="pagination pagination-sm">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>" aria-label="First">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>" aria-label="Previous">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php 
                                // Show page numbers
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1&search='.urlencode($search).'&filter='.urlencode($filter).'">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor;
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'&search='.urlencode($search).'&filter='.urlencode($filter).'">'.$total_pages.'</a></li>';
                                }
                                ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>" aria-label="Next">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>" aria-label="Last">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card no-messages-card animate__animated animate__fadeIn">
            <div class="card-body text-center py-5">
                <i class="fas fa-inbox fa-4x mb-4" style="color: var(--primary-color);"></i>
                <h4>No messages found</h4>
                <p class="text-muted">
                    <?php if (!empty($search) || !empty($filter)): ?>
                        No messages match your search criteria
                    <?php else: ?>
                        When you receive messages, they will appear here
                    <?php endif; ?>
                </p>
                <?php if (!empty($search) || !empty($filter)): ?>
                    <a href="messages.php" class="btn btn-primary mt-3">
                        <i class="fas fa-times me-1"></i> Clear filters
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Message View Modal -->
    <div class="modal fade" id="messageViewModal" tabindex="-1" aria-labelledby="messageViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="messageViewModalLabel">
                        <i class="fas fa-envelope-open-text me-2"></i>Message Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="info-item">
                                <label><i class="fas fa-user me-2"></i>Full Name:</label>
                                <span id="viewFullName" class="info-value"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <label><i class="fas fa-phone me-2"></i>Contact:</label>
                                <span id="viewContact" class="info-value"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="info-item">
                                <label><i class="fas fa-envelope me-2"></i>Email:</label>
                                <span id="viewEmail" class="info-value"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <label><i class="fas fa-calendar-alt me-2"></i>Date:</label>
                                <span id="viewDate" class="info-value"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <label><i class="fas fa-comment me-2"></i>Message:</label>
                        <div class="message-content p-3 mt-2 bg-light rounded" id="viewMessage"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this message? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash-alt me-1"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for filter dropdown
            $('#filterSelect').select2({
                minimumResultsForSearch: Infinity,
                width: '150px'
            });
            
            // Submit form when filter changes
            $('#filterSelect').change(function() {
                $('#searchFilterForm').submit();
            });
            
            // Initialize modals
            const messageModal = new bootstrap.Modal(document.getElementById('messageViewModal'));
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            
            // Handle view button clicks
            $('.view-btn').click(function() {
                const fullname = $(this).data('fullname');
                const contact = $(this).data('contact');
                const email = $(this).data('email');
                const message = $(this).data('message');
                const date = $(this).data('date');
                
                $('#viewFullName').text(fullname);
                $('#viewContact').text(contact);
                $('#viewEmail').text(email);
                $('#viewMessage').text(message);
                $('#viewDate').text(date);
                
                messageModal.show();
            });
            
            // Handle delete button clicks
            let deleteForm = null;
            $('.delete-form').submit(function(e) {
                e.preventDefault();
                deleteForm = this;
                deleteModal.show();
            });
            
            // Confirm delete
            $('#confirmDeleteBtn').click(function() {
                if (deleteForm) {
                    deleteForm.submit();
                }
                deleteModal.hide();
            });
            
            // Client-side search for instant feedback
            $('#searchInput').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                $('.message-row').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.includes(searchTerm));
                });
            });
            
            // Highlight search terms in results
            function highlightSearchTerms() {
                const searchTerm = "<?= htmlspecialchars($search) ?>";
                if (searchTerm) {
                    $('.message-preview').each(function() {
                        const text = $(this).text();
                        const highlighted = text.replace(new RegExp(searchTerm, 'gi'), 
                            match => `<span class="highlight">${match}</span>`);
                        $(this).html(highlighted);
                    });
                }
            }
            
            highlightSearchTerms();
            
            // Highlight row on hover
            $('.message-row').hover(
                function() {
                    $(this).addClass('table-active');
                },
                function() {
                    $(this).removeClass('table-active');
                }
            );
            
            // Auto-dismiss alert after 5 seconds
            $('.alert').delay(5000).fadeOut('slow');
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

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
            
            // Update hamburger button state if needed
            const hamburgerBtn = document.querySelector('.hamburger-btn');
            if (hamburgerBtn) {
                hamburgerBtn.classList.toggle('active');
            }
        }

        // Initialize sidebar state
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial state (collapsed by default on mobile)
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.add('collapsed');
            }
            
            // Handle responsive behavior
            window.addEventListener('resize', function() {
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth <= 768) {
                    sidebar.classList.add('collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                }
            });
        });
    </script>
</body>
</html>