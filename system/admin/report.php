<?php
session_start();
require '../config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    // Redirect to login page if not logged in
    header("Location:login.php");
    exit();
}

// Get list of barangays for filter
$barangays = [];
$sql = "SELECT DISTINCT barangay FROM babies ORDER BY barangay";
$stmt = $pdo->query($sql);
$barangays = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get selected barangay from filter
$selectedBarangay = $_GET['barangay'] ?? '';

// Base query to get babies with vaccine counts
$sql = "SELECT b.*, 
        COUNT(s.schedule_id) as total_vaccines, 
        SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) as completed_vaccines
        FROM babies b
        LEFT JOIN schedule s ON b.baby_id = s.baby_id
        WHERE 1=1";

// Add barangay filter if selected
if ($selectedBarangay) {
    $sql .= " AND b.barangay = :barangay";
}

$sql .= " GROUP BY b.baby_id ORDER BY b.barangay, b.baby_name";

$stmt = $pdo->prepare($sql);
if ($selectedBarangay) {
    $stmt->bindParam(':barangay', $selectedBarangay);
}
$stmt->execute();
$babies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Vaccine Report</title>
    <link rel="stylesheet" href="../../css/admin/report.css">
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>

/* ===== DASHBOARD BANNERS ===== */
.banner-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 30px;
}

        .filter-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        label {
            font-weight: 600;
            color: #333;
        }
        select, button {
            padding: 8px 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
            font-size: 14px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #45a049;
        }
        .report-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        .header h1 {
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .subtitle {
            color: #7f8c8d;
            font-style: italic;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .report-table th, .report-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .report-table th {
            background-color: #3498db;
            color: white;
        }
        .report-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .report-table tr:hover {
            background-color: #e6f7ff;
        }
        .toggle-details {
            color: #2980b9;
            cursor: pointer;
            font-weight: 600;
        }
        .vaccine-details {
            display: none;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            margin-top: 10px;
        }
        .vaccine-table {
            width: 100%;
            border-collapse: collapse;
        }
        .vaccine-table th, .vaccine-table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .vaccine-table th {
            background-color: #2c3e50;
            color: white;
        }
         /* Ensure print button is visible */
         .print-btn-container {
            display: flex !important;
            gap: 15px;
            margin-top: 20px;
            justify-content: center;
        }
        
        .print-btn, .pdf-btn {
            padding: 10px 20px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .print-btn {
            background-color: #3498db;
            color: white;
            border: none;
        }
        
        .print-btn:hover {
            background-color: #2980b9;
        }
        
        .pdf-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
        }
        
        .pdf-btn:hover {
            background-color: #c0392b;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                font-size: 12pt;
            }
            .report-table {
                page-break-inside: avoid;
            }
            .header h1 {
                color: black !important;
            }
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="report-container">
    <div class="no-print filter-container">
        <h2>Filter Reports</h2>
        <form method="get" action="" class="filter-form">
            <div class="filter-group">
                <label for="barangay">Barangay:</label>
                <select name="barangay" id="barangay">
                    <option value="">All Barangays</option>
                    <?php foreach ($barangays as $barangay): ?>
                        <option value="<?php echo htmlspecialchars($barangay); ?>" 
                            <?php echo ($selectedBarangay == $barangay) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($barangay); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Apply Filter</button>
            <button type="button" onclick="window.location.href='?'">Reset</button>
        </form>
    </div>

    <div class="report-card">
        <div class="header">
            <h1>BARANGAY VACCINATION REPORT</h1>
            <div class="subtitle">Official Immunization Record</div>
        </div>
        
        <div class="report-info">
            <p><strong>Report Date:</strong> <?php echo date('F j, Y'); ?></p>
            <?php if ($selectedBarangay): ?>
                <p><strong>Barangay:</strong> <?php echo htmlspecialchars($selectedBarangay); ?></p>
            <?php endif; ?>
            <p><strong>Total Children:</strong> <?php echo count($babies); ?></p>
        </div>
        
        <?php if (empty($babies)): ?>
            <p class="no-data">No children found matching the selected criteria.</p>
        <?php else: ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Child Name</th>
                        <th>Gender</th>
                        <th>Birthdate</th>
                        <th>Barangay</th>
                        <th>Parent/Guardian</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($babies as $index => $baby): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($baby['baby_name']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($baby['gender'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($baby['birthdate'])); ?></td>
                            <td><?php echo htmlspecialchars($baby['barangay']); ?></td>
                            <td><?php echo htmlspecialchars($baby['parent_name']); ?></td>
                            <td>
                            <td>
                                <span class="toggle-details" onclick="toggleDetails(<?php echo $baby['baby_id']; ?>)">
                                    <i class="fas fa-chevron-down"></i> View
                                </span>
                            </td>
                        </tr>
                        <tr id="details-<?php echo $baby['baby_id']; ?>" class="vaccine-details">
                            <td colspan="8">
                                <h3>Vaccine Records for <?php echo htmlspecialchars($baby['baby_name']); ?></h3>
                                <?php
                                // Fetch vaccine details for this baby
                                $sql = "SELECT * FROM schedule 
                                        WHERE baby_id = :baby_id 
                                        ORDER BY schedule_date";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute(['baby_id' => $baby['baby_id']]);
                                $vaccines = $stmt->fetchAll();
                                
                                if ($vaccines): ?>
                                    <table class="vaccine-table">
                                        <thead>
                                            <tr>
                                                <th>Vaccine Type</th>
                                                <th>Scheduled Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($vaccines as $vaccine): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($vaccine['type_of_vaccine']); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($vaccine['schedule_date'])); ?></td>
                                                    <td>
                                                        <span class="status-badge <?php echo $vaccine['status']; ?>">
                                                            <?php echo ucfirst($vaccine['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p>No vaccine records found for this child.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="print-btn-container no-print">
            <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button class="pdf-btn" onclick="generatePDF()">
                <i class="fas fa-file-pdf"></i> Export as PDF
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
    const dropdownToggles = document.querySelectorAll('.dropdown > a');
    
    dropdownToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            if (window.innerWidth > 768) {
                e.preventDefault();
                const dropdownMenu = this.nextElementSibling;
                
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                    if (menu !== dropdownMenu) {
                        menu.style.display = 'none';
                    }
                });
                
                // Toggle current dropdown
                if (dropdownMenu.style.display === 'block') {
                    dropdownMenu.style.display = 'none';
                } else {
                    dropdownMenu.style.display = 'block';
                }
            }
        });
    });

    // Close dropdowns when clicking outside (for desktop)
    document.addEventListener('click', function(e) {
        if (window.innerWidth > 768) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                    menu.style.display = 'none';
                });
            }
        }
    });

    // Make dropdowns stay open on mobile when sidebar is expanded
    const sidebar = document.getElementById('sidebar');
    sidebar.addEventListener('mouseleave', function() {
        if (window.innerWidth > 768) {
            document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                menu.style.display = 'none';
            });
        }
    });
// Toggle sidebar function
    function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('main-content');
            sidebar.classList.toggle('collapsed');
            
            // Save state to localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }
        
        // Check saved state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
            }
        });
    // Toggle vaccine details
    function toggleDetails(babyId) {
        const detailsRow = document.getElementById(`details-${babyId}`);
        const icon = detailsRow.previousElementSibling.querySelector('i');
        
        if (detailsRow.style.display === 'table-row') {
            detailsRow.style.display = 'none';
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        } else {
            detailsRow.style.display = 'table-row';
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        }
    }
    
    // Generate PDF with jsPDF
    function generatePDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'pt', 'a4');
        
        // Title
        doc.setFontSize(18);
        doc.setTextColor(40, 40, 40);
        doc.text('BARANGAY VACCINATION REPORT', 40, 50);
        
        // Subtitle
        doc.setFontSize(12);
        doc.setTextColor(100, 100, 100);
        doc.text('Official Immunization Record', 40, 70);
        
        // Report info
        doc.setFontSize(10);
        doc.text(`Report Date: ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}`, 40, 90);
        <?php if ($selectedBarangay): ?>
            doc.text(`Barangay: <?php echo htmlspecialchars($selectedBarangay); ?>`, 40, 110);
        <?php endif; ?>
        doc.text(`Total Children: <?php echo count($babies); ?>`, 40, 130);
        
        // Prepare data for the table
        const headers = [
            '#',
            'Child Name',
            'Gender',
            'Birthdate',
            'Barangay',
            'Parent/Guardian',
        ];
        
        const rows = [];
        <?php foreach ($babies as $index => $baby): ?>
            rows.push([
                '<?php echo $index + 1; ?>',
                '<?php echo addslashes($baby['baby_name']); ?>',
                '<?php echo ucfirst($baby['gender']); ?>',
                '<?php echo date('M j, Y', strtotime($baby['birthdate'])); ?>',
                '<?php echo addslashes($baby['barangay']); ?>',
                '<?php echo addslashes($baby['parent_name']); ?>',
            ]);
        <?php endforeach; ?>
        
        // Add the main table
        doc.autoTable({
            startY: 150,
            head: [headers],
            body: rows,
            margin: { top: 150 },
            styles: {
                fontSize: 9,
                cellPadding: 6,
                overflow: 'linebreak'
            },
            headStyles: {
                fillColor: [52, 152, 219],
                textColor: 255,
                fontStyle: 'bold'
            },
            alternateRowStyles: {
                fillColor: [242, 242, 242]
            }
        });
        
        // Footer
        doc.setFontSize(10);
        doc.setTextColor(150, 150, 150);
        doc.text('This is an official document from VaxiBloom Immunization System', 
            40, 
            doc.internal.pageSize.height - 20);
        
        // Save the PDF
        const filename = 'Vaccine_Report_<?php echo $selectedBarangay ? str_replace(' ', '_', $selectedBarangay) : 'All_Barangays'; ?>_<?php echo date('Y-m-d'); ?>.pdf';
        doc.save(filename);
    }
    
    // Ensure print button works
    document.addEventListener('DOMContentLoaded', function() {
        // Make sure print button is accessible
        const printBtn = document.querySelector('.print-btn');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                window.print();
            });
        }
    });
</script>
</body>
</html>