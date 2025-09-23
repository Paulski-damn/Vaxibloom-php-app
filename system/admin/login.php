<?php
session_start();
require '../config.php';

    // Handle login when form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_username = htmlspecialchars(trim($_POST['admin_username']));
    $admin_password = htmlspecialchars(trim($_POST['admin_password']));

    try{
        // Prepare a query to fetch the user based on the provided username
        $stmt = $pdo->prepare("SELECT * FROM admin_acc WHERE admin_username = :admin_username");
        $stmt->execute(['admin_username' => $admin_username]);
        $user = $stmt->fetch();

         // Check if the user exists and the password matches
        if ($user && password_verify($admin_password, $user['admin_password'])) {
            $_SESSION['admin_id'] = $user['admin_id'];
            $_SESSION['admin_username'] = $user['admin_username'];

            // Redirect to a dashboard
            header("Location: admin_dashboard.php");
            exit();

        } else {
                $error = "Invalid username or password.";
            }
    }catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();}
        }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../../css/admin/login.css">
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
    <h1>
        <a href="../../index.php" style="display: flex; align-items: center; text-decoration: none; color: black;">
            <img src="../../img/logo1.png" alt="VaxiBloom Logo" style="height: 50px; width: auto; margin-right: 5px">VaxiBloom
        </a>
    </h1>
    </header>
    <main class="main-content">
        <div class="login-container">
            <h2>Welcome Back Admin!</h2>
            
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <div class="input-group">
                    <input type="text" name="admin_username" placeholder="Username" required>
                    <i class="fas fa-user input-icon"></i>
                </div>

                <div class="input-group password-wrapper">
                    <input type="password" name="admin_password" placeholder="Password" id="admin_password" required>
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye toggle-password" id="togglePasswordIcon" onclick="togglePassword()"></i>
                </div>

                <button type="submit" class="login-btn">Login</button>
                
                <div class="form-footer">
                    <p class="admin-link"><a href="../user/login.php">User Login</a></p>
                </div>
            </form>
        </div>
    </main>
    <script>
            function togglePassword() {
            const passwordField = document.getElementById("password");
            const toggleIcon = document.getElementById("togglePasswordIcon");
            
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.classList.replace("fa-eye", "fa-eye-slash");
            } else {
                passwordField.type = "password";
                toggleIcon.classList.replace("fa-eye-slash", "fa-eye");
            }
        }
    </script>
</body>
</html>