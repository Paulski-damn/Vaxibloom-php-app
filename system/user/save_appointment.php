<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'BHW') {
    header("Location: login.php");
    exit();
}

$baby_id = $_POST['baby_id'];
$vaccine_id = $_POST['vaccine_id'];
$appointment_date = $_POST['appointment_date'];
$bhw_barangay = $_SESSION['barangay'];

// Get baby birthdate and barangay
$babyStmt = $conn->prepare("SELECT birthdate, barangay FROM babies WHERE id = ?");
$babyStmt->bind_param("i", $baby_id);
$babyStmt->execute();
$babyResult = $babyStmt->get_result();
if ($babyResult->num_rows === 0) {
    die("Baby not found.");
}
$baby = $babyResult->fetch_assoc();

// Ensure baby is from same barangay
if ($baby['barangay'] !== $bhw_barangay) {
    die("You can only schedule appointments for babies in your barangay.");
}

// Calculate age in months
$birthdate = new DateTime($baby['birthdate']);
$appDate = new DateTime($appointment_date);
$ageMonths = $birthdate->diff($appDate)->y * 12 + $birthdate->diff($appDate)->m;

// Check vaccine age eligibility
$vaccineStmt = $conn->prepare("SELECT min_age_months, max_age_months FROM vaccines WHERE id = ?");
$vaccineStmt->bind_param("i", $vaccine_id);
$vaccineStmt->execute();
$vaccineResult = $vaccineStmt->get_result();
$vaccine = $vaccineResult->fetch_assoc();

if ($ageMonths < $vaccine['min_age_months'] || $ageMonths > $vaccine['max_age_months']) {
    die("This baby is not eligible for the selected vaccine.");
}

// Check for existing appointment for this baby & vaccine
$checkStmt = $conn->prepare("SELECT * FROM appointments WHERE baby_id = ? AND vaccine_id = ?");
$checkStmt->bind_param("ii", $baby_id, $vaccine_id);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    die("Appointment already exists for this baby and vaccine.");
}

// Insert appointment
$insertStmt = $conn->prepare("INSERT INTO appointments (baby_id, vaccine_id, appointment_date) VALUES (?, ?, ?)");
$insertStmt->bind_param("iis", $baby_id, $vaccine_id, $appointment_date);
if ($insertStmt->execute()) {
    echo "Appointment created successfully.";
} else {
    echo "Error: " . $conn->error;
}
?>
