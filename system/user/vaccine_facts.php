<?php
session_start();
require '../config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../system/user/login.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccine Facts - VaxiBloom</title>
    <link rel="stylesheet" href="../../css/user/vaccine_facts.css">
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animate.css for animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body>
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

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-content">
        <h1 class="animate__animated animate__fadeInDown">Vaccine Information Center</h1>
        <p class="animate__animated animate__fadeInUp">Essential facts about childhood vaccines to keep your little ones protected</p>
        <div class="search-container animate__animated animate__fadeIn">
            <input type="text" id="vaccine-search" placeholder="Search vaccines...">
            <button id="search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="hero-wave">
        <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" fill="#4CAF50"></path>
            <path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5" fill="#4CAF50"></path>
            <path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" fill="#4CAF50"></path>
        </svg>
    </div>
</section>

<!-- Main Content -->
<div class="container">
    <div class="filter-options">
        <button class="filter-btn active" data-filter="all">All Vaccines</button>
        <button class="filter-btn" data-filter="infant">Infant Vaccines</button>
        <button class="filter-btn" data-filter="toddler">Recommended Vaccines</button>
    </div>
    
    <div class="vaccine-grid" id="vaccine-container">
        <!-- BCG -->
        <div class="vaccine-card" data-category="infant">
            <div class="card-front">
                <div class="vaccine-badge">Essential</div>
                <img src="../../img/bcg.jpeg" alt="BCG Vaccine" loading="lazy">
                <h3>BCG</h3>
                <p>Protects against tuberculosis (TB). Given shortly after birth.</p>
                <button class="info-btn">More Info <i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="card-back">
                <h3>BCG Vaccine Details</h3>
                <ul>
                    <li><strong>Disease:</strong> Tuberculosis</li>
                    <li><strong>Recommended Age:</strong> At birth</li>
                    <li><strong>Doses:</strong> Single dose</li>
                    <li><strong>Effectiveness:</strong> 70-80% against severe forms</li>
                    <li><strong>Side Effects:</strong> Mild swelling at injection site</li>
                </ul>
                <button class="back-btn"><i class="fas fa-chevron-left"></i> Back</button>
            </div>
        </div>

        <!-- Hepatitis -B -->
        <div class="vaccine-card" data-category="infant">
            <div class="card-front">
                <div class="vaccine-badge">Essential</div>
                <img src="../../img/hepa_b.jpeg" alt="BCG Vaccine" loading="lazy">
                <h3>Hepatitis B</h3>
                <p>Prevents liver infection caused by the hepatitis B virus.</p>
                <button class="info-btn">More Info <i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="card-back">
                <h3>Hepatitis B Vaccine Details</h3>
                <ul>
                    <li><strong>Disease:</strong> Hepatitis B virus</li>
                    <li><strong>Recommended Age:</strong> At birth</li>
                    <li><strong>Doses:</strong> Single dose</li>
                    <li><strong>Effectiveness:</strong> 70-80% against severe forms</li>
                    <li><strong>Side Effects:</strong> Mild swelling at injection site</li>
                </ul>
                <button class="back-btn"><i class="fas fa-chevron-left"></i> Back</button>
            </div>
        </div>
        <!-- Pentavalent  -->
        <div class="vaccine-card" data-category="infant">
            <div class="card-front">
                <div class="vaccine-badge">Essential</div>
                <img src="../../img/pentavalent.jpg" alt="BCG Vaccine" loading="lazy">
                <h3>Pentavalent </h3>
                <p>Combines combines five vaccines into one dose.</p>
                <button class="info-btn">More Info <i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="card-back">
                <h3>Pentavalent Details</h3>
                <ul>
                    <li><strong>Disease:</strong> diphtheria, pertussis, tetanus, hepatitis B, and Hib</li>
                    <li><strong>Recommended Age:</strong>6 weeks to 14 weeks old</li>
                    <li><strong>Doses:</strong> Three dose</li>
                    <li><strong>Effectiveness:</strong> 70-80% against severe forms</li>
                    <li><strong>Side Effects:</strong> Mild swelling at injection site</li>
                </ul>
                <button class="back-btn"><i class="fas fa-chevron-left"></i> Back</button>
            </div>
        </div>

        <!-- Oral Polio  -->
        <div class="vaccine-card" data-category="infant">
            <div class="card-front">
                <div class="vaccine-badge">Essential</div>
                <img src="../../img/opv.jpg" alt="BCG Vaccine" loading="lazy">
                <h3>Oral Polio Vaccine </h3>
                <p>Protects against polio, a disabling disease.</p>
                <button class="info-btn">More Info <i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="card-back">
                <h3>OPV Details</h3>
                <ul>
                    <li><strong>Disease:</strong> Polio</li>
                    <li><strong>Recommended Age:</strong>6 weeks to 14 weeks old</li>
                    <li><strong>Doses:</strong> Three dose</li>
                    <li><strong>Effectiveness:</strong> 70-80% against severe forms</li>
                    <li><strong>Side Effects:</strong> Mild swelling at injection site</li>
                </ul>
                <button class="back-btn"><i class="fas fa-chevron-left"></i> Back</button>
            </div>
        </div>
        <!-- I Polio  -->
        <div class="vaccine-card" data-category="infant">
            <div class="card-front">
                <div class="vaccine-badge">Essential</div>
                <img src="../../img/ipv.jpg" alt="BCG Vaccine" loading="lazy">
                <h3>Inactived Polio </h3>
                <p>Protection against polio inactivated virus.</p>
                <button class="info-btn">More Info <i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="card-back">
                <h3>IPV Details</h3>
                <ul>
                    <li><strong>Disease:</strong> Polio with inactived virus</li>
                    <li><strong>Recommended Age:</strong>14 weeks to 41 weeks</li>
                    <li><strong>Doses:</strong> Two dose</li>
                    <li><strong>Effectiveness:</strong> 70-80% against severe forms</li>
                    <li><strong>Side Effects:</strong> Mild swelling at injection site</li>
                </ul>
                <button class="back-btn"><i class="fas fa-chevron-left"></i> Back</button>
            </div>
        </div>
        <div class="vaccine-card" data-category="infant">
            <div class="card-front">
                <div class="vaccine-badge">Essential</div>
                <img src="../../img/pcv.jpg" alt="BCG Vaccine" loading="lazy">
                <h3>PCV </h3>
                <p>Protects against pneumonia, meningitis.</p>
                <button class="info-btn">More Info <i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="card-back">
                <h3>PCV Details</h3>
                <ul>
                    <li><strong>Disease:</strong> Pneumonia, Meningitis</li>
                    <li><strong>Recommended Age:</strong>6 weeks to 14 weeks old</li>
                    <li><strong>Doses:</strong> Three dose</li>
                    <li><strong>Effectiveness:</strong> 70-80% against severe forms</li>
                    <li><strong>Side Effects:</strong> Mild swelling at injection site</li>
                </ul>
                <button class="back-btn"><i class="fas fa-chevron-left"></i> Back</button>
            </div>
        </div>
        <div class="vaccine-card" data-category="infant">
            <div class="card-front">
                <div class="vaccine-badge">Essential</div>
                <img src="../../img/mmr.jpg" alt="BCG Vaccine" loading="lazy">
                <h3>MMR </h3>
                <p>Protects against measles, mumps, and rubella </p>
                <button class="info-btn">More Info <i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="card-back">
                <h3>MMR Details</h3>
                <ul>
                    <li><strong>Disease:</strong> Measles, mumps, rubella</li>
                    <li><strong>Recommended Age:</strong>44 weeks to 52 weeks old</li>
                    <li><strong>Doses:</strong> Two doses</li>
                    <li><strong>Effectiveness:</strong> 70-80% against severe forms</li>
                    <li><strong>Side Effects:</strong> Mild swelling at injection site</li>
                </ul>
                <button class="back-btn"><i class="fas fa-chevron-left"></i> Back</button>
            </div>
        </div>
        <!-- Other vaccine cards with similar structure -->
        
    </div>
    
    <div class="vaccine-timeline">
        <h2>Recommended Vaccination Schedule</h2>
        <div class="timeline-container">
            <div class="timeline">
                <div class="timeline-event" data-age="Birth">
                    <div class="event-dot"></div>
                    <div class="event-content">
                        <h4>At Birth</h4>
                        <p>BCG, Hepatitis B (1st dose)</p>
                    </div>
                </div>
                <div class="timeline-event" data-age="6 Weeks">
                    <div class="event-dot"></div>
                    <div class="event-content">
                        <h4>6 Weeks</h4>
                        <p>Pentavalent (1st dose), OPV (1st dose), PCV (1st dose)</p>
                    </div>
                </div>
                <div class="timeline-event" data-age="6 Weeks">
                    <div class="event-dot"></div>
                    <div class="event-content">
                        <h4>10 Weeks</h4>
                        <p>Pentavalent (2nd dose), OPV (2nd dose), PCV (2nd dose)</p>
                    </div>
                </div>
                <div class="timeline-event" data-age="6 Weeks">
                    <div class="event-dot"></div>
                    <div class="event-content">
                        <h4>14 Weeks</h4>
                        <p>Pentavalent (3rd dose), OPV (3rd dose), PCV (3rd dose)</p>
                    </div>
                </div>
                <div class="timeline-event" data-age="6 Weeks">
                    <div class="event-dot"></div>
                    <div class="event-content">
                        <h4>44 Weeks</h4>
                        <p>MRR(1st dose)</p>
                    </div>
                </div>
                <div class="timeline-event" data-age="6 Weeks">
                    <div class="event-dot"></div>
                    <div class="event-content">
                        <h4>52 Weeks</h4>
                        <p>MRR(2nd dose)</p>
                    </div>
                </div>
                <!-- Add more timeline events -->
            </div>
        </div>
    </div>
</div>

<!-- Modal for detailed vaccine info -->
<div class="modal" id="vaccine-modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <div class="modal-body" id="modal-vaccine-content">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>


<script src="../../js/vaccine_facts.js"></script>
</body>
</html>