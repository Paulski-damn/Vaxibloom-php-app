<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../system/user/login.php');
    exit;
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    $_SESSION['error_message'] = "Error fetching user data.";
    header('Location: profile.php');
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Trim and sanitize inputs
    $new_first_name = trim($_POST['first_name'] ?? $user['first_name']);
    $new_last_name = trim($_POST['last_name'] ?? $user['last_name']);
    $new_username = trim($_POST['username'] ?? $user['username']);
    $new_barangay = trim($_POST['barangay'] ?? $user['barangay']);

    // Validate inputs
    $errors = [];

    // First name validation (no numbers, only letters, spaces, hyphens, and apostrophes)
    if (preg_match('/[0-9]/', $new_first_name)) {
        $errors[] = "First name should not contain numbers";
    } elseif (!preg_match('/^[A-Za-z\s\-\']+$/', $new_first_name)) {
        $errors[] = "First name contains invalid characters";
    }

    // Last name validation
    if (preg_match('/[0-9]/', $new_last_name)) {
        $errors[] = "Last name should not contain numbers";
    } elseif (!preg_match('/^[A-Za-z\s\-\']+$/', $new_last_name)) {
        $errors[] = "Last name contains invalid characters";
    }

    // Barangay validation - updated to allow numbers
    if (!preg_match('/^[A-Za-z0-9\s\-\']+$/', $new_barangay)) {
        $errors[] = "Barangay name contains invalid characters. Only letters, numbers, spaces, hyphens, and apostrophes are allowed.";
    }

    // Username validation (alphanumeric with underscores)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores";
    }

    // If there are errors, store them in session and redirect back
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        header('Location: profile.php');
        exit;
    }

    // Proceed with update if no errors
    $update_sql = "UPDATE users SET first_name = ?, last_name = ?, username = ?, barangay = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssssi", $new_first_name, $new_last_name, $new_username, $new_barangay, $user_id);

    if ($update_stmt->execute()) {
        // Refresh user data after update
        $sql = "SELECT * FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        $_SESSION['success_message'] = "Profile updated successfully!";
        header('Location: profile.php');
        exit;
    } else {
        $_SESSION['error_message'] = "Error updating profile: " . $conn->error;
        header('Location: profile.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - VaxiBloom</title>
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../css/user/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&display=swap" rel="stylesheet">
    <style>
        .hidden {
            display: none;
        }
        .edit-form {
            display: none;
        }
        .edit-form.active {
            display: block;
        }
        .notification-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transform: translateX(200%);
            transition: transform 0.3s ease-in-out;
        }
        .notification-toast.show {
            transform: translateX(0);
        }
        .notification-toast.success {
            background-color: #2ecc71;
        }
        .notification-toast.error {
            background-color: #e74c3c;
        }
    </style>
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

<!-- Notification Toast -->
<div id="notification-toast" class="notification-toast hidden">
    <div class="toast-content">
        <span id="toast-message"></span>
        <button onclick="hideToast()" class="toast-close">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<!-- Main Content -->
<div class="container">
    <div class="back-button-container">
        <button onclick="goBack()" class="back-button">
            <i class="fas fa-arrow-left"></i> Back
        </button>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast('<?php echo addslashes($_SESSION['success_message']); ?>', 'success');
            });
        </script>
        <?php unset($_SESSION['success_message']); ?>
    <?php elseif (isset($_SESSION['error_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast('<?php echo addslashes($_SESSION['error_message']); ?>', 'error');
            });
        </script>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="profile-header">
        <strong><h1 class="profile-title">MY PROFILE</h1></strong>
    </div>

    <div class="profile-card">
        <div class="user-info">
            <div class="avatar-container">
                <div class="avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <button class="avatar-upload">
                    <i class="fas fa-camera"></i>
                </button>
            </div>
            <div class="user-details">
                <h2><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                <p class="user-role">Barangay Health Worker</p>
            </div>
        </div>

        <div class="profile-sections">
            <!-- Personal Information Section -->
            <div class="personal-info section-card">
                <div class="section-header">
                    <h3><i class="fas fa-user-tag"></i> Personal Information</h3>
                    <button id="editButton" onclick="toggleEditMode()" class="edit-button">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                </div>

                <!-- Display Section -->
                <div id="infoDisplay" class="info-display">
                    <div class="info-item">
                        <span class="info-label">First Name:</span>
                        <span class="info-value"><?= htmlspecialchars($user['first_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Name:</span>
                        <span class="info-value"><?= htmlspecialchars($user['last_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Username:</span>
                        <span class="info-value"><?= htmlspecialchars($user['username']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Barangay:</span>
                        <span class="info-value"><?= htmlspecialchars($user['barangay']) ?></span>
                    </div>
                </div>

                <!-- Edit Form Section -->
                <form id="editForm" action="profile.php" method="POST" class="edit-form">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required
                               pattern="[A-Za-z\s\-']+" title="Only letters, spaces, hyphens, and apostrophes are allowed">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required
                               pattern="[A-Za-z\s\-']+" title="Only letters, spaces, hyphens, and apostrophes are allowed">
                    </div>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required
                               pattern="[a-zA-Z0-9_]+" title="Only letters, numbers, and underscores are allowed">
                    </div>
                    <div class="form-group">
                        <label for="barangay">Barangay</label>
                        <input type="text" id="barangay" name="barangay" value="<?= htmlspecialchars($user['barangay']) ?>" required
                            pattern="[A-Za-z0-9\s\-']+" title="Letters, numbers, spaces, hyphens, and apostrophes are allowed">
                    </div>
                    <div class="form-actions">
                        <button type="button" onclick="toggleEditMode()" class="cancel-button">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="save-button">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Account Security Section -->
            <div class="security-info section-card">
                <div class="section-header">
                    <h3><i class="fas fa-shield-alt"></i> Account Security</h3>
                </div>
                <div class="security-items">
                    <div class="security-item">
                        <i class="fas fa-lock"></i>
                        <div>
                            <h4>Password</h4>
                        </div>
                        <button class="security-action" onclick="openPasswordModal()">
                            Change <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Password Change Modal -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Change Password</h3>
            <button onclick="closePasswordModal()" class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="passwordForm" class="modal-form">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <div class="password-input">
                    <input type="password" id="current_password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('current_password')"></i>
                </div>
            </div>
            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="password-input">
                    <input type="password" id="new_password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('new_password')"></i>
                </div>
                <div class="password-strength">
                    <div class="strength-meter">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <span id="strengthText">Weak</span>
                </div>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="password-input">
                    <input type="password" id="confirm_password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')"></i>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" onclick="closePasswordModal()" class="cancel-button">
                    Cancel
                </button>
                <button type="submit" class="save-button">
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.getElementById('nav-menu').classList.toggle('active');
            this.classList.toggle('active');
        });
    });

    // Toggle edit mode
    function toggleEditMode() {
        const infoDisplay = document.getElementById('infoDisplay');
        const editForm = document.getElementById('editForm');
        const editButton = document.getElementById('editButton');

        infoDisplay.classList.toggle('hidden');
        editForm.classList.toggle('active');
        
        if (editForm.classList.contains('active')) {
            editButton.innerHTML = '<i class="fas fa-times"></i> Cancel';
            editButton.classList.add('cancel-mode');
        } else {
            editButton.innerHTML = '<i class="fas fa-edit"></i> Edit';
            editButton.classList.remove('cancel-mode');
        }
    }

    // Password modal functions
    function openPasswordModal() {
        document.getElementById('passwordModal').classList.add('active');
    }

    function closePasswordModal() {
        document.getElementById('passwordModal').classList.remove('active');
    }

    // Toggle password visibility
    function togglePassword(id) {
        const input = document.getElementById(id);
        const icon = input.nextElementSibling;
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Password strength checker
    document.getElementById('new_password').addEventListener('input', function() {
        const password = this.value;
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        
        // Calculate strength
        let strength = 0;
        if (password.length > 0) strength += 1;
        if (password.length >= 8) strength += 1;
        if (/[A-Z]/.test(password)) strength += 1;
        if (/[0-9]/.test(password)) strength += 1;
        if (/[^A-Za-z0-9]/.test(password)) strength += 1;
        
        // Update UI
        const width = strength * 20;
        strengthFill.style.width = `${width}%`;
        
        if (strength <= 2) {
            strengthFill.style.backgroundColor = '#e74c3c';
            strengthText.textContent = 'Weak';
        } else if (strength <= 3) {
            strengthFill.style.backgroundColor = '#f39c12';
            strengthText.textContent = 'Moderate';
        } else {
            strengthFill.style.backgroundColor = '#2ecc71';
            strengthText.textContent = 'Strong';
        }
    });

    // Toast notification functions
    function showToast(message, type) {
        const toast = document.getElementById('notification-toast');
        const toastMessage = document.getElementById('toast-message');
        
        toastMessage.textContent = message;
        toast.className = `notification-toast ${type} show`;
        
        setTimeout(() => {
            hideToast();
        }, 5000);
    }

    function hideToast() {
        const toast = document.getElementById('notification-toast');
        toast.classList.remove('show');
    }
    
    function goBack() {
        window.history.back();
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('passwordModal');
        if (event.target === modal) {
            closePasswordModal();
        }
    });
</script>
</body>
</html>