<?php
session_start();
require '../config.php';

// Only allow admins to access this page
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../system/admin/login.php');
    exit;
}

$barangay_filter = $_GET['barangay'] ?? '';

// Query to fetch inventory based on the selected barangay
if ($barangay_filter) {
    $stmt = $conn->prepare("SELECT * FROM vaccine_inventory WHERE barangay = ? ORDER BY barangay, vaccine_type");
    $stmt->bind_param("s", $barangay_filter);
} else {
    $stmt = $conn->prepare("SELECT * FROM vaccine_inventory ORDER BY barangay, vaccine_type");
}

$stmt->execute();
$result = $stmt->get_result();

// Group data by barangay
$inventory = [];
while ($row = $result->fetch_assoc()) {
    $barangay = $row['barangay'];
    if (!isset($inventory[$barangay])) {
        $inventory[$barangay] = [];
    }
    $inventory[$barangay][] = $row;
}

// Set headers for Excel file download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="vaccine_inventory_' . date('Y-m-d') . '.xls"');

// Start Excel content
echo '<table border="1">';
echo '<tr>';
echo '<th colspan="3" style="font-size:18px; background-color:#dff0d8;">Vaccine Inventory Report - ' . date('F j, Y') . '</th>';
echo '</tr>';

foreach ($inventory as $barangay => $vaccines) {
    echo '<tr>';
    echo '<td colspan="3" style="background-color:#5bc0de; color:white; font-weight:bold;">Barangay: ' . htmlspecialchars($barangay) . '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th style="background-color:#337ab7; color:white;">Vaccine Type</th>';
    echo '<th style="background-color:#337ab7; color:white;">Quantity</th>';
    echo '<th style="background-color:#337ab7; color:white;">Stock Status</th>';
    echo '</tr>';
    
    foreach ($vaccines as $row) {
        // Determine stock status
        $status = '';
        if ($row['quantity'] == 0) {
            $status = 'Out of Stock';
        } elseif ($row['quantity'] < 30) {
            $status = 'Low Stock';
        } else {
            $status = 'In Stock';
        }
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['vaccine_type']) . '</td>';
        echo '<td>' . $row['quantity'] . '</td>';
        echo '<td>' . $status . '</td>';
        echo '</tr>';
    }
    
    // Add empty row between barangays
    echo '<tr><td colspan="3"></td></tr>';
}

echo '</table>';
exit;
?>