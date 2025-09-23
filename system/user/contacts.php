<?php
session_start();
require '../config.php'; // Include database configuration file
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../system/user/login.php'); // Redirect to login if not logged in
    exit; // Stop further execution after redirect
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $contact = $_POST['contact'];
    $email = $_POST['email'];
    $message = $_POST['message'];

    // Validate required fields
    if (empty($fullname) || empty($contact) || empty($email) || empty($message)) {
        $error = "All fields are required.";
    } else {
        // Save the message into the database
        $stmt = $conn->prepare("INSERT INTO messages (fullname, contact, email, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $fullname, $contact, $email, $message);

        if ($stmt->execute()) {
            $success = "Your message has been sent successfully!";
        } else {
            $error = "Failed to send your message. Please try again.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <!-- Link the CSS -->
    <link rel="stylesheet" href="../../css/user/contacts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&display=swap" rel="stylesheet">


</head>
<body>
    <!-- Header Section -->
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


    <!-- Main Content -->
    <div class="container">
        <!-- Left Section: Image -->
        <div class="left-content">
            <img src="../../img/contacts.jpg" alt="Contact Us Illustration">
        </div>

        <!-- Right Section: Contact Form -->
        <div class="right-content">
            <h2>Message Us</h2>
            <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
            <?php if (isset($success)) echo "<p style='color:green;'>$success</p>"; ?>
            <form action="contacts.php" method="POST">
                <label for="fullname">Full Name:</label>
                <input type="text" id="fullname" name="fullname" placeholder="John Doe" required>

                <label for="contact">Contact #:</label>
                <input type="text" id="contact" name="contact" placeholder="09XXXXXXXXX" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" placeholder="xxxxxxx@gmail.com" required>

                <label for="message">Message:</label>
                <textarea id="message" name="message" placeholder="Write your message here" required></textarea>

                <button type="submit">Send Message here</button>
            </form>
        </div>
    </div>
    <script>
document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.getElementById('nav-menu').classList.toggle('active');
            this.classList.toggle('active');
        });

</script>
</body>
</html>
