<?php
session_start();
require_once '../config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    // Redirect to login page if not logged in
    header("Location:login.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        // Add new user
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $barangay = $_POST['barangay'];
        $role = $_POST['role'];
        
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, username, password, barangay, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$firstName, $lastName, $username, $password, $barangay, $role]);
        
        $_SESSION['message'] = "User added successfully!";
        header('Location: users.php');
        exit;
    } elseif (isset($_POST['update_user'])) {
        // Update existing user
        $userId = $_POST['user_id'];
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $username = trim($_POST['username']);
        $barangay = $_POST['barangay'];
        $role = $_POST['role'];
        
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, barangay = ?, role = ? WHERE user_id = ?");
        $stmt->execute([$firstName, $lastName, $username, $barangay, $role, $userId]);
        
        $_SESSION['message'] = "User updated successfully!";
        header('Location: users.php');
        exit;
    } elseif (isset($_POST['delete_user'])) {
        // Delete user
        $userId = $_POST['user_id'];
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $_SESSION['message'] = "User deleted successfully!";
        header('Location: users.php');
        exit;
    }
}

// Fetch all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY last_name, first_name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch barangays that already have accounts
$stmt = $pdo->query("SELECT barangay FROM users WHERE barangay IS NOT NULL GROUP BY barangay");
$occupiedBarangays = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch barangay list
$barangays = [];
$result = $conn->query("SELECT name FROM barangays ORDER BY name ASC");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $barangays[] = $row['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | VaxiBloom</title>
    <link rel="stylesheet" href="../../css/admin/users.css">
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

</head>
<body>
<?php include 'sidebar.php'; ?>

<!-- Main Content -->
        <div class="main-content">
            <div class="container-fluid">
                <div class="header-content">
                    <h1><i class="fas fa-user"></i>User Management</h1>
                    <div class="breadcrumb">
                        <span>Admin</span> <i class="fas fa-chevron-right mx-2"></i> <span class="active">User</span>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <!-- Add/Edit User Form -->
                <div class="user-form">
                    <h4><?= isset($_GET['edit']) ? 'Edit User' : 'Add New User'; ?></h4>
                    <form method="POST">
                        <?php if (isset($_GET['edit'])): 
                            $editUserId = $_GET['edit'];
                            $editStmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                            $editStmt->execute([$editUserId]);
                            $editUser = $editStmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                            <input type="hidden" name="user_id" value="<?= $editUser['user_id']; ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= isset($editUser) ? $editUser['first_name'] : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= isset($editUser) ? $editUser['last_name'] : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?= isset($editUser) ? $editUser['username'] : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <?php if (!isset($_GET['edit'])): ?>
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                            <div class="mb-3">
                                <label for="barangay" class="form-label">Barangay</label>
                                <select class="form-select" id="barangay" name="barangay" required>
                                    <option value="">Select Barangay</option>
                                    <?php foreach ($barangays as $brgy): 
                                        $isOccupied = in_array($brgy, $occupiedBarangays);
                                        $isCurrent = isset($editUser) && $editUser['barangay'] == $brgy;
                                    ?>
                                        <option value="<?= $brgy; ?>" 
                                            <?= $isCurrent ? 'selected' : ''; ?>
                                            <?= ($isOccupied && !$isCurrent) ? 'disabled' : ''; ?>>
                                            <?= $brgy; ?>
                                            <?= ($isOccupied && !$isCurrent) ? ' (already have account)' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="bhw" <?= (isset($editUser) && $editUser['role'] == 'bhw') ? 'selected' : ''; ?>>Health Worker</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <?php if (isset($_GET['edit'])): ?>
                                <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                                <a href="users.php" class="btn btn-secondary">Cancel</a>
                            <?php else: ?>
                                <button type="submit" name="add_user" class="btn btn-success">Add User</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Users Table -->
                <div class="table-container">
                    <h4>User List</h4>
                    <div class="table-responsive">
                        <table id="usersTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Barangay</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= $user['user_id']; ?></td>
                                        <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                        <td><?= htmlspecialchars($user['username']); ?></td>
                                        <td><?= htmlspecialchars($user['barangay']); ?></td>
                                        <td>
                                            <span class="badge <?= $user['role'] == 'bhw' ? 'bg-primary' : 'bg-success'; ?>">
                                                <?= ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                            </span>
                                        </td>
                                        <td class="action-btns">
                                            <a href="users.php?edit=<?= $user['user_id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
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
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  sidebar.classList.toggle('collapsed');
  
  // Update hamburger button state if needed
  const hamburgerBtn = document.querySelector('.hamburger-btn');
  hamburgerBtn.classList.toggle('active');
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
        $(document).ready(function() {
            $('#usersTable').DataTable({
                responsive: true,
                columnDefs: [
                    { responsivePriority: 1, targets: 1 }, // Name
                    { responsivePriority: 2, targets: 5 }, // Actions
                    { responsivePriority: 3, targets: 3 }, // Barangay
                    { responsivePriority: 4, targets: 4 }, // Role
                    { responsivePriority: 5, targets: 2 }, // Username
                    { responsivePriority: 6, targets: 0 }  // ID
                ]
            });
            
            // Scroll to form when editing
            <?php if (isset($_GET['edit'])): ?>
                $('html, body').animate({
                    scrollTop: $(".user-form").offset().top - 20
                }, 500);
            <?php endif; ?>
        });
        
    </script>
</body>
</html>