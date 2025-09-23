<?php
session_start();
require '../config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../system/admin/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $action = $_POST['action'] ?? '';

    if (!$id || !$action) {
        header('Location: inventory_admin.php');
        exit;
    }

    // Get current record
    $stmt = $conn->prepare("SELECT * FROM vaccine_inventory WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    $stmt->close();

    if (!$record) {
        header('Location: inventory_admin.php');
        exit;
    }

    $current_quantity = (int) $record['quantity'];
    $vaccine_type = $record['vaccine_type'];
    $current_barangay = $record['barangay'];

    if ($action === 'adjust') {
        $adjust_qty = (int) ($_POST['adjust_qty'] ?? 0);
        $new_quantity = max(0, $current_quantity + $adjust_qty);

        $stmt = $conn->prepare("UPDATE vaccine_inventory SET quantity = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_quantity, $id);
        $stmt->execute();
        $stmt->close();

    } elseif ($action === 'transfer') {
        $target_barangay = $_POST['target_barangay'] ?? '';
        $transfer_qty = (int) ($_POST['transfer_qty'] ?? 0);

        if ($transfer_qty <= 0 || $transfer_qty > $current_quantity || $target_barangay === $current_barangay) {
            header('Location: inventory_admin.php');
            exit;
        }

        // Deduct from current
        $new_quantity = $current_quantity - $transfer_qty;
        $stmt = $conn->prepare("UPDATE vaccine_inventory SET quantity = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_quantity, $id);
        $stmt->execute();
        $stmt->close();

        // Add to target
        $stmt = $conn->prepare("SELECT id, quantity FROM vaccine_inventory WHERE barangay = ? AND vaccine_type = ?");
        $stmt->bind_param("ss", $target_barangay, $vaccine_type);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $target = $result->fetch_assoc();
            $target_id = $target['id'];
            $target_new_quantity = $target['quantity'] + $transfer_qty;

            $stmt = $conn->prepare("UPDATE vaccine_inventory SET quantity = ? WHERE id = ?");
            $stmt->bind_param("ii", $target_new_quantity, $target_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO vaccine_inventory (vaccine_type, barangay, quantity) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $vaccine_type, $target_barangay, $transfer_qty);
            $stmt->execute();
        }

        $stmt->close();
    }
}

header('Location: inventory_admin.php');
exit;