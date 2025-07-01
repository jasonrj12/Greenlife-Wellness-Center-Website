<?php
/**
 * User Logout Script
 * GreenLife Wellness Center
 */

require_once 'includes/functions.php';

// Get user ID before destroying session for remember token cleanup
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Clear remember token from database and cookie
if ($userId) {
    clearRememberToken($userId);
}

// Destroy session and redirect to login
session_start();
session_destroy();

// Clear any cookies if they exist
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login page with success message
header('Location: login.php?logout=success');
exit();
?>