<?php
// Include database configuration
require 'config.php';

try {
    // Fetch all users with plain text passwords
    $stmt = $pdo->query("SELECT admin_id, username, password FROM admin_acc");
    $users = $stmt->fetchAll();

    foreach ($users as $user) {
        $plainTextPassword = $user['password'];

        // Check if the password is already hashed (skip if hashed)
        if (password_get_info($plainTextPassword)['algo'] === 0) {
            // Hash the plain text password
            $hashedPassword = password_hash($plainTextPassword, PASSWORD_DEFAULT);

            // Update the database with the hashed password
            $updateStmt = $pdo->prepare("UPDATE admin_acc SET password = :hashedPassword WHERE admin_id = :id");
            $updateStmt->execute([
                'hashedPassword' => $hashedPassword,
                'admin_id' => $user['id']
            ]);

            echo "Password for user '{$user['username']}' has been hashed.\n";
        } else {
            echo "Password for user '{$user['username']}' is already hashed. Skipping.\n";
        }
    }

    echo "Password hashing completed.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
