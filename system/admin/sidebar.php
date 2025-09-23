<!-- Hamburger Button -->
<div class="hamburger-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </div>

    <!-- Sidebar Section -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <img src="../../img/logo1.png" alt="VaxiBloom Logo" class="sidebar-logo">
                </div>
                <h2 class="sidebar-title">VaxiBloom</h2>
            </div>
            <ul>
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> <span class="menu-text">Home</span></a></li>
                <li><a href="map.php"><i class="fas fa-globe"></i> <span class="menu-text">Map</span></a></li>

                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="fas fa-clipboard-list"></i>
                        <span class="menu-text">Management</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li class="dropdown-header">View Management</li>
                        <li class="divider"></li>
                        <li><a href="inventory_admin.php"><i class="fas fa-syringe"></i> Vaccine Management</a></li>
                        <li class="divider"></li>
                        <li><a href="users.php"><i class="fas fa-user"></i> User Management</a></li>
                    </ul>
                </li>

                <li><a href="admin_schedules.php"><i class="fas fa-calendar-check"></i> <span class="menu-text">Schedules</span></a></li>
                
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="fas fa-file-alt"></i>
                        <span class="menu-text">Reports</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li class="dropdown-header">View reports</li>
                        <li><a href="babies.php?view=barangay"><i class="fas fa-baby"></i> Immunization Reports</a></li>
                        <li class="divider"></li>
                        <li><a href="missed_vaccine.php"><i class="fas fa-syringe"></i> Missed Vaccine</a></li>
                        <li class="divider"></li>
                        <li><a href="pending_appointment.php"><i class="fas fa-hourglass-half"></i> Vaccine Preparation</a></li>
                    </ul>
                </li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> <span class="menu-text">Messages</span></a></li>
                
                <li><a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a></li>
            </ul>
        </div>