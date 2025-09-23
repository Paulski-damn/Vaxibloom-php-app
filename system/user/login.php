<?php
session_start();
require '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = htmlspecialchars(trim($_POST['username']));
    $password = htmlspecialchars(trim($_POST['password']));

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role']; // Make sure this is set
            $_SESSION['barangay'] = $user['barangay'] ?? null; // For BHWs
            
            // Redirect based on role
            switch (strtolower($user['role'])) {
                case 'admin':
                    header("Location: ../admin/dashboard.php");
                    break;
                case 'bhw':
                    header("Location: dashboard.php");
                    break;
                case 'parent':
                default:
                    header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - VaxiBloom</title>
    <link rel="stylesheet" href="../../css/user/login.css">
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
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
            <h2>Welcome Back!</h2>
            
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <div class="input-group">
                    <input type="text" name="username" placeholder="Username" required>
                    <i class="fas fa-user input-icon"></i>
                </div>

                <div class="input-group password-wrapper">
                    <input type="password" name="password" placeholder="Password" id="password" required>
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye toggle-password" id="togglePasswordIcon" onclick="togglePassword()"></i>
                </div>

                <button type="submit" class="login-btn">Login</button>
                
                <div class="form-footer">
                    <p>Don't have an account? <a href="../user/register.php">Register here</a></p>
                    <div class="role-links">
                        <a href="../admin/login.php">Admin Login</a>
                    </div>
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