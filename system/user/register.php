<?php
require '../config.php';


// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fetch barangay list
$barangays = [];
$result = $conn->query("SELECT name FROM barangays ORDER BY name ASC");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $barangays[] = $row['name'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $username = htmlspecialchars(trim($_POST['username']));
    $password = htmlspecialchars(trim($_POST['password']));
    $confirm_password = htmlspecialchars(trim($_POST['confirm_password']));
    $barangay = htmlspecialchars(trim($_POST['barangay']));

    if (empty($first_name) || empty($last_name) || empty($username) || empty($password) || empty($confirm_password) || empty($barangay)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Username already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, barangay, role, first_name, last_name)
                                       VALUES (:username, :password, :barangay, 'bhw', :first_name, :last_name)");
                $stmt->execute([
                    'username' => $username,
                    'password' => $hashed_password,
                    'barangay' => $barangay,
                    'first_name' => $first_name,
                    'last_name' => $last_name
                ]);
                header("Location: login.php?success=1");
                exit();
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BHW Registration</title>
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link rel="stylesheet" href="../../css/user/register.css">
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

<div class="register-container">
    <h2>Register BHW Account</h2>

    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <div class="input-group">
                    <select name="barangay" required>
                        <option value="" disabled selected>Select Barangay</option>
                        <?php foreach ($barangays as $brgy): ?>
                            <option value="<?php echo htmlspecialchars($brgy); ?>"><?php echo htmlspecialchars($brgy); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i class="fas fa-map-marker-alt input-icon"></i>
                </div>
            </div>
            <div class="form-group">
                <div class="input-group">
                    <input type="text" name="first_name" placeholder="First Name" required>
                    <i class="fas fa-user input-icon"></i>
                </div>
            </div>
            <div class="form-group">
                <div class="input-group">
                    <input type="text" name="last_name" placeholder="Last Name" required>
                    <i class="fas fa-id-badge input-icon"></i>
                </div>
            </div>
            <div class="form-group">
                <div class="input-group">
                    <input type="text" name="username" placeholder="Username" required>
                    <i class="fas fa-user input-icon"></i>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <div class="input-group password-wrapper">
                    <input type="password" name="password" placeholder="Password" id="password" required>
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye toggle-password" id="togglePasswordIcon1" onclick="togglePassword('password', 'togglePasswordIcon1')"></i>
                </div>
            </div>
            <div class="form-group">
                <div class="input-group password-wrapper">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" id="confirm_password" required>
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye toggle-password" id="togglePasswordIcon2" onclick="togglePassword('confirm_password', 'togglePasswordIcon2')"></i>
                </div>
            </div>
        </div>

        <button type="submit">Register</button>
    </form>

    <p>Already registered? <a href="login.php">Login here</a></p>
</div>

<script>
function togglePassword(id, iconId) {
    var passwordField = document.getElementById(id);
    var toggleIcon = document.getElementById(iconId);
    if (passwordField.type === "password") {
        passwordField.type = "text";
        toggleIcon.classList.remove("fa-eye");
        toggleIcon.classList.add("fa-eye-slash");
    } else {
        passwordField.type = "password";
        toggleIcon.classList.remove("fa-eye-slash");
        toggleIcon.classList.add("fa-eye");
    }
}
</script>

</body>
</html>
