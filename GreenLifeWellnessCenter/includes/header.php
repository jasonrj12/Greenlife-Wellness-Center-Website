<?php
require_once 'functions.php';

// Check for remember token and auto-login if valid
if (!isLoggedIn()) {
    checkRememberToken();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>GreenLife Wellness Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="assets/css/icon-styles.css">
</head>
<body>
    <!-- Modern Fixed Navigation -->
    <nav class="modern-navbar" id="mainNavbar">
        <div class="container">
            <div class="navbar-content">
                <a class="navbar-brand" href="index.php">
                    <div class="brand-icon">
                        <img src="images/logo.png" alt="GreenLife Wellness Center Logo" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div class="brand-text">
                        <span class="brand-name">GreenLife</span>
                        <span class="brand-subtitle">Wellness Center</span>
                    </div>
                </a>
                
                <div class="navbar-menu" id="navbarMenu">
                    <ul class="navbar-nav">
                        <?php if (isLoggedIn()): ?>
                            <!-- Navigation for logged-in users -->
                            <li class="nav-item">
                                <a class="nav-link" href="index.php">
                                    <i class="fas fa-home nav-icon"></i>
                                    <span>Dashboard</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="appointment_booking.php">
                                    <i class="fas fa-calendar-plus nav-icon"></i>
                                    <span>Book Appointment</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="view_appointments.php">
                                    <i class="fas fa-calendar-check nav-icon"></i>
                                    <span>My Appointments</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="index.php#services">
                                    <i class="fas fa-spa nav-icon"></i>
                                    <span>Services</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="index.php#therapists">
                                    <i class="fas fa-user-md nav-icon"></i>
                                    <span>Therapists</span>
                                </a>
                            </li>
                        <?php else: ?>
                            <!-- Navigation for non-logged-in users -->
                            <li class="nav-item">
                                <a class="nav-link" href="index.php">
                                    <i class="fas fa-home nav-icon"></i>
                                    <span>Home</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="index.php#services">
                                    <i class="fas fa-spa nav-icon"></i>
                                    <span>Services</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="index.php#therapists">
                                    <i class="fas fa-user-md nav-icon"></i>
                                    <span>Therapists</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="index.php#about">
                                    <i class="fas fa-info-circle nav-icon"></i>
                                    <span>About</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="contact_us.php">
                                <i class="fas fa-envelope nav-icon"></i>
                                <span>Contact</span>
                            </a>
                        </li>
                    </ul>
                    
                    <div class="navbar-actions">
                        <?php if (isLoggedIn()): ?>
                            <div class="user-dropdown">
                                <button class="user-toggle" id="userToggle">
                                    <div class="user-avatar">
                                        <i class="fas fa-user-circle"></i>
                                    </div>
                                    <div class="user-info">
                                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                                        <span class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
                                    </div>
                                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                                </button>
                                <div class="dropdown-menu" id="userDropdown">
                                    <?php if (hasRole('client')): ?>
                                        <a class="dropdown-item" href="client_profile.php">
                                            <i class="fas fa-tachometer-alt"></i>
                                            <span>My Profile</span>
                                        </a>
                                        <a class="dropdown-item" href="client_dashboard.php">
                                            <i class="fas fa-calendar"></i>
                                            <span>My Appointments</span>
                                        </a>
                                    <?php elseif (hasRole('therapist')): ?>
                                        <a class="dropdown-item" href="therapist_dashboard.php">
                                            <i class="fas fa-tachometer-alt"></i>
                                            <span>My Dashboard</span>
                                        </a>
                                        <a class="dropdown-item" href="view_appointments.php">
                                            <i class="fas fa-calendar"></i>
                                            <span>My Appointments</span>
                                        </a>
                                    <?php elseif (hasRole('admin')): ?>
                                        <a class="dropdown-item" href="admin_dashboard.php">
                                            <i class="fas fa-cog"></i>
                                            <span>Admin Dashboard</span>
                                        </a>
                                        <a class="dropdown-item" href="manage_users.php">
                                            <i class="fas fa-users"></i>
                                            <span>Manage Users</span>
                                        </a>
                                        <a class="dropdown-item" href="manage_appointments.php">
                                            <i class="fas fa-calendar-check"></i>
                                            <span>Manage Appointments</span>
                                        </a>
                                    <?php endif; ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item logout" href="logout.php">
                                        <i class="fas fa-sign-out-alt"></i>
                                        <span>Logout</span>
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="auth-buttons">
                                <a class="btn-login" href="login.php">
                                    <i class="fas fa-sign-in-alt"></i>
                                    <span>Login</span>
                                </a>
                                <a class="btn-register" href="register.php" style="padding: 0.5rem 0.75rem; font-size: 0.9rem; margin-top: 2px;">
                                    <i class="fas fa-user-plus" style="font-size: 0.9rem;"></i>
                                    <span>Get Started</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <button class="mobile-toggle" id="mobileToggle">
                    <span class="toggle-line"></span>
                    <span class="toggle-line"></span>
                    <span class="toggle-line"></span>
                </button>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <?php if (isset($breadcrumb)): ?>
            <div class="container mt-3">
                <?php echo $breadcrumb; ?>
            </div>
        <?php endif; ?>