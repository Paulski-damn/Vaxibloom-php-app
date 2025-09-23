<?php
session_start();
require '../config.php';

// Ensure only admins can access
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    die(json_encode(['error' => 'Unauthorized access']));
}

// Get the barangay parameter
if (!isset($_GET['barangay'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Barangay not specified']));
}

$barangay = $conn->real_escape_string($_GET['barangay']);

// Fetch the schedule with additional filtering if needed
$result = $conn->query("SELECT * FROM barangay_schedules WHERE barangay_name = '$barangay'");
if (!$result) {
    http_response_code(500);
    die(json_encode(['error' => 'Database error: ' . $conn->error]));
}

if ($result->num_rows === 0) {
    http_response_code(404);
    die(json_encode(['error' => 'Schedule not found']));
}
if (!isset($_GET['barangay'])) {
    die(json_encode(['error' => 'Barangay not specified']));
}

$barangay = $conn->real_escape_string($_GET['barangay']);

// Fetch the schedule
$result = $conn->query("SELECT * FROM barangay_schedules WHERE barangay_name = '$barangay'");
if (!$result || $result->num_rows === 0) {
    die(json_encode(['error' => 'Schedule not found']));
}

$schedule = $result->fetch_assoc();

// Prepare the edit form HTML
ob_start();
?>
<div class="row mb-3">
    <div class="col-md-6">
        <label for="editBarangay" class="form-label">Barangay</label>
        <input type="text" class="form-control" id="editBarangay" name="barangay" 
               value="<?= htmlspecialchars($schedule['barangay_name']) ?>" readonly>
    </div>
    <div class="col-md-6">
        <label for="editMonth" class="form-label">Vaccination Month</label>
        <input type="month" class="form-control" name="month" id="editMonth" 
               value="<?= htmlspecialchars($schedule['vaccination_month']) ?>" required>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="week-selection">
            <h5>Week 1 (Days 1-7)</h5>
            <div class="d-flex flex-wrap">
                <?php 
                $week1_days = explode(',', $schedule['week1_days']);
                for ($i = 1; $i <= 7; $i++): ?>
                    <div class="day-checkbox">
                        <input type="checkbox" name="week1[]" value="<?= $i ?>" id="editW1d<?= $i ?>"
                            <?= in_array($i, $week1_days) ? 'checked' : '' ?>>
                        <label for="editW1d<?= $i ?>"><?= $i ?></label>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="week-selection">
            <h5>Week 2 (Days 8-14)</h5>
            <div class="d-flex flex-wrap">
                <?php 
                $week2_days = explode(',', $schedule['week2_days']);
                for ($i = 8; $i <= 14; $i++): ?>
                    <div class="day-checkbox">
                        <input type="checkbox" name="week2[]" value="<?= $i ?>" id="editW2d<?= $i ?>"
                            <?= in_array($i, $week2_days) ? 'checked' : '' ?>>
                        <label for="editW2d<?= $i ?>"><?= $i ?></label>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="week-selection">
            <h5>Week 3 (Days 15-21)</h5>
            <div class="d-flex flex-wrap">
                <?php 
                $week3_days = explode(',', $schedule['week3_days']);
                for ($i = 15; $i <= 21; $i++): ?>
                    <div class="day-checkbox">
                        <input type="checkbox" name="week3[]" value="<?= $i ?>" id="editW3d<?= $i ?>"
                            <?= in_array($i, $week3_days) ? 'checked' : '' ?>>
                        <label for="editW3d<?= $i ?>"><?= $i ?></label>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="week-selection">
            <h5>Week 4 (Days 22-28)</h5>
            <div class="d-flex flex-wrap">
                <?php 
                $week4_days = explode(',', $schedule['week4_days']);
                for ($i = 22; $i <= 28; $i++): ?>
                    <div class="day-checkbox">
                        <input type="checkbox" name="week4[]" value="<?= $i ?>" id="editW4d<?= $i ?>"
                            <?= in_array($i, $week4_days) ? 'checked' : '' ?>>
                        <label for="editW4d<?= $i ?>"><?= $i ?></label>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>
<?php
$html = ob_get_clean();
echo $html;
?>