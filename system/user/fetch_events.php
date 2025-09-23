<?php
require '../config.php'; // Include your database configuration file

header('Content-Type: application/json'); // Set the content type to JSON

try {
    // Define the total slots available per day
    $totalSlots = 10;

    // Query to fetch the count of appointments grouped by date
    $query = "SELECT date, COUNT(*) as booked_slots FROM schedule GROUP BY date";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $bookedSlots = (int)$row['booked_slots']; // Number of booked slots
        $remainingSlots = $totalSlots - $bookedSlots; // Calculate remaining slots

        // Add each date with its booked and remaining slots to the array
        $appointments[] = [
            'date' => $row['date'], // Date of the appointment
            'bookedSlots' => $bookedSlots, // Number of booked slots
            'remainingSlots' => $remainingSlots // Remaining slots
        ];
    }

    // Return the data in JSON format
    echo json_encode($appointments); // JSON response for FullCalendar
} catch (Exception $e) {
    // Return error in JSON format
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
    http_response_code(500); // Internal server error
}
?>
