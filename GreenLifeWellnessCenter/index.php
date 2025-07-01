<?php
/**
 * Main Index - GreenLife Wellness Center
 * Routes users to appropriate version based on login status and role
 */

// Start session and include functions
session_start();
require_once 'includes/functions.php';

// Check if user is logged in
if (isLoggedIn()) {
    // User is logged in - route to appropriate dashboard based on role
    $userRole = $_SESSION['role'] ?? 'client';
    
    switch ($userRole) {
        case 'admin':
            include 'admin_dashboard.php';
            break;
        case 'therapist':
            include 'therapist_dashboard.php';
            break;
        case 'client':
        default:
            include 'dashboard_home.php';
            break;
    }
} else {
    // User is not logged in - show public PHP version (not HTML)
    include 'public_home.php';
}
?>