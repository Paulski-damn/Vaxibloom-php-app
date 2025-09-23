<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require '../config.php';

try {
    // Connect to DB
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Fetch barangay coordinates
    $sql_barangays = "SELECT * FROM barangays";
    $result_barangays = $conn->query($sql_barangays);
    if (!$result_barangays) {
        throw new Exception("Barangay query failed: " . $conn->error);
    }
    // Fetch vaccine type coordinates
    $sql_vaccine_type = "SELECT * FROM vaccine_inventory";
    $result_vaccine_type = $conn->query($sql_vaccine_type);
    if (!$result_vaccine_type) {
        throw new Exception("vaccines query failed: " . $conn->error);
    }
    $barangays = [];
    while ($row = $result_barangays->fetch_assoc()) {
        $coords = json_decode($row['coordinates'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            continue; // Skip if coordinates JSON invalid
        }
        $barangays[$row['name']] = $coords;
    }

    // Count infants per barangay from babies table
    $sql_infants = "SELECT barangay, COUNT(DISTINCT baby_name) AS infant_count FROM babies GROUP BY barangay";
    $result_infants = $conn->query($sql_infants);
    if (!$result_infants) {
        throw new Exception("Infant count query failed: " . $conn->error);
    }

    $infantCounts = [];
    while ($row = $result_infants->fetch_assoc()) {
        $infantCounts[$row['barangay']] = (int)$row['infant_count'];
    }

    // Get vaccination data by joining schedule and babies tables
    $sql_vaccines = "SELECT b.barangay, s.type_of_vaccine, COUNT(DISTINCT b.baby_name) AS vaccine_count
                     FROM schedule s
                     JOIN babies b ON s.baby_id = b.baby_id
                     WHERE s.status = 'completed'
                     GROUP BY b.barangay, s.type_of_vaccine";
    $result_vaccines = $conn->query($sql_vaccines);
    if (!$result_vaccines) {
        throw new Exception("Vaccine query failed: " . $conn->error);
    }

    $vaccineData = [];
    while ($row = $result_vaccines->fetch_assoc()) {
        $barangay = $row['barangay'];
        $vaccine = str_replace(' ', '_', $row['type_of_vaccine']);
        $count = (int)$row['vaccine_count'];

        if (!isset($vaccineData[$barangay])) {
            $vaccineData[$barangay] = [];
        }

        $infantCount = $infantCounts[$barangay] ?? 1;
        if ($infantCount <= 0) $infantCount = 1;

        $coverage = ($count / $infantCount) * 100;
        $vaccineData[$barangay][$vaccine] = min(100, round($coverage, 2));
    }

    // Get all distinct vaccine types (for the keys in GeoJSON)
    $sql_vaccine_types = "SELECT DISTINCT type_of_vaccine FROM schedule WHERE status = 'completed'";
    $result_types = $conn->query($sql_vaccine_types);
    if (!$result_types) {
        throw new Exception("Vaccine types query failed: " . $conn->error);
    }

    $allVaccines = [];
    while ($row = $result_types->fetch_assoc()) {
        $allVaccines[] = str_replace(' ', '_', $row['type_of_vaccine']);
    }

    // Prepare GeoJSON features
    $features = [];
    foreach ($barangays as $name => $coordinates) {
        if (empty($coordinates)) continue;

        // Initialize vaccine coverage data to zero
        $vaccines = array_fill_keys($allVaccines, 0);

        if (isset($vaccineData[$name])) {
            foreach ($vaccineData[$name] as $vaccine => $coverage) {
                $vaccines[$vaccine] = $coverage;
            }
        }

        $feature = [
            'type' => 'Feature',
            'properties' => [
                'name' => $name,
                'density' => $infantCounts[$name] ?? 0,
                'vaccines' => $vaccines
            ],
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => $coordinates
            ]
        ];

        $features[] = $feature;
    }

    if (empty($features)) {
        throw new Exception("No valid features generated - check your barangay coordinates");
    }

    // Output GeoJSON FeatureCollection
    $geojson = [
        'type' => 'FeatureCollection',
        'features' => $features
    ];

    echo json_encode($geojson);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>
