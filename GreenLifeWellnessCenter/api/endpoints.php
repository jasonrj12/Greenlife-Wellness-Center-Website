<?php
/**
 * API Endpoints for AJAX requests
 * GreenLife Wellness Center
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/functions.php';

/**
 * Send JSON response
 */
function sendResponse($success, $message = '', $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

/**
 * Require authenticated user
 */
function requireAuth() {
    if (!isLoggedIn()) {
        sendResponse(false, 'Authentication required');
    }
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        
        // Get all active services
        case 'get_services':
            $services = getAllServices();
            sendResponse(true, 'Services retrieved successfully', ['services' => $services]);
            break;
            
        // Get all active therapists
        case 'get_therapists':
            // Check if we need to exclude a specific user (for therapist self-booking prevention)
            $excludeUserId = null;
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'therapist') {
                $excludeUserId = $_SESSION['user_id'];
            }
            
            $therapists = getAllTherapists($excludeUserId);
            sendResponse(true, 'Therapists retrieved successfully', ['therapists' => $therapists]);
            break;
            
        // Get available time slots for a therapist on a specific date
        case 'get_available_slots':
            $therapistId = $_GET['therapist_id'] ?? '';
            $date = $_GET['date'] ?? '';
            
            if (empty($therapistId) || empty($date)) {
                sendResponse(false, 'Therapist ID and date are required');
            }
            
            if (!isValidBookingDate($date)) {
                sendResponse(false, 'Invalid booking date');
            }
            
            $slots = getAvailableTimeSlots($therapistId, $date);
            $formattedSlots = [];
            
            foreach ($slots as $slot) {
                $formattedSlots[] = [
                    'value' => $slot,
                    'label' => formatTime($slot)
                ];
            }
            
            sendResponse(true, 'Available slots retrieved', ['slots' => $formattedSlots]);
            break;
            
        // Book appointment
        case 'book_appointment':
            requireAuth();
            
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                sendResponse(false, 'Invalid security token');
            }
            
            $therapistId = sanitizeInput($_POST['therapist'] ?? '');
            $serviceId = sanitizeInput($_POST['service'] ?? '');
            $appointmentDate = sanitizeInput($_POST['appointmentDate'] ?? '');
            $appointmentTime = sanitizeInput($_POST['appointmentTime'] ?? '');
            $clientNotes = sanitizeInput($_POST['client_notes'] ?? '');
            
            if (empty($therapistId) || empty($serviceId) || empty($appointmentDate) || empty($appointmentTime)) {
                sendResponse(false, 'All fields are required');
            }
            
            $result = bookAppointment($_SESSION['user_id'], $therapistId, $serviceId, $appointmentDate, $appointmentTime);
            
            if ($result['success'] && !empty($clientNotes)) {
                try {
                    $pdo = getDBConnection();
                    $stmt = $pdo->prepare("UPDATE appointments SET client_notes = ? WHERE user_id = ? AND appointment_date = ? AND appointment_time = ? ORDER BY created_at DESC LIMIT 1");
                    $result_notes = $stmt->execute([$clientNotes, $_SESSION['user_id'], $appointmentDate, $appointmentTime]);
                    
                    if (!$result_notes) {
                        error_log("Failed to add client notes for appointment booking via API");
                    }
                } catch (PDOException $e) {
                    error_log("Error adding client notes via API: " . $e->getMessage());
                    // Don't fail the booking response if notes can't be added
                }
            }
            
            sendResponse($result['success'], $result['message']);
            break;
            
        // Submit inquiry
        case 'submit_inquiry':
            $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
            
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                sendResponse(false, 'Invalid security token');
            }
            
            $subject = sanitizeInput($_POST['subject'] ?? '');
            $message = sanitizeInput($_POST['message'] ?? '');
            $priority = sanitizeInput($_POST['priority'] ?? 'medium');
            
            if (empty($subject) || empty($message)) {
                sendResponse(false, 'Subject and message are required');
            }
            
            $result = submitInquiry($userId, $subject, $message, $priority);
            sendResponse($result['success'], $result['message']);
            break;
            
        // Get user notifications
        case 'get_notifications':
            requireAuth();
            
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
                $stmt->execute([$_SESSION['user_id']]);
                $notifications = $stmt->fetchAll();
                
                sendResponse(true, 'Notifications retrieved', ['notifications' => $notifications]);
            } catch (PDOException $e) {
                error_log("Error fetching notifications: " . $e->getMessage());
                sendResponse(false, 'Error retrieving notifications');
            }
            break;
            
        // Mark notification as read
        case 'mark_notification_read':
            requireAuth();
            
            $notificationId = (int)($_POST['notification_id'] ?? 0);
            
            if ($notificationId <= 0) {
                sendResponse(false, 'Invalid notification ID');
            }
            
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE notification_id = ? AND user_id = ?");
                $result = $stmt->execute([$notificationId, $_SESSION['user_id']]);
                
                if ($result) {
                    sendResponse(true, 'Notification marked as read');
                } else {
                    sendResponse(false, 'Failed to update notification');
                }
            } catch (PDOException $e) {
                error_log("Error updating notification: " . $e->getMessage());
                sendResponse(false, 'Error updating notification');
            }
            break;
            
        // Update appointment status (for therapists/admin)
        case 'update_appointment_status':
            requireAuth();
            
            if (!hasRole('admin') && !hasRole('therapist')) {
                sendResponse(false, 'Access denied');
            }
            
            $appointmentId = (int)($_POST['appointment_id'] ?? 0);
            $status = sanitizeInput($_POST['status'] ?? '');
            $notes = sanitizeInput($_POST['notes'] ?? '');
            
            if ($appointmentId <= 0 || empty($status)) {
                sendResponse(false, 'Appointment ID and status are required');
            }
            
            $validStatuses = ['pending', 'confirmed', 'completed', 'canceled'];
            if (!in_array($status, $validStatuses)) {
                sendResponse(false, 'Invalid status');
            }
            
            try {
                $pdo = getDBConnection();
                
                // Check if appointment exists and user has permission
                $stmt = $pdo->prepare("
                    SELECT a.*, t.user_id as therapist_user_id 
                    FROM appointments a 
                    LEFT JOIN therapists t ON a.therapist_id = t.therapist_id 
                    WHERE a.appointment_id = ?
                ");
                $stmt->execute([$appointmentId]);
                $appointment = $stmt->fetch();
                
                if (!$appointment) {
                    sendResponse(false, 'Appointment not found');
                }
                
                // Check permissions
                if (hasRole('therapist') && $appointment['therapist_user_id'] != $_SESSION['user_id']) {
                    sendResponse(false, 'You can only update your own appointments');
                }
                
                // Update appointment
                $updateQuery = "UPDATE appointments SET status = ?";
                $params = [$status];
                
                if (hasRole('therapist') && !empty($notes)) {
                    $updateQuery .= ", therapist_notes = ?";
                    $params[] = $notes;
                } elseif (hasRole('admin') && !empty($notes)) {
                    $updateQuery .= ", admin_notes = ?";
                    $params[] = $notes;
                }
                
                $updateQuery .= " WHERE appointment_id = ?";
                $params[] = $appointmentId;
                
                $stmt = $pdo->prepare($updateQuery);
                $result = $stmt->execute($params);
                
                if ($result) {
                    // Create notification for client
                    $statusMessages = [
                        'confirmed' => 'Your appointment has been confirmed',
                        'completed' => 'Your appointment has been completed',
                        'canceled' => 'Your appointment has been canceled'
                    ];
                    
                    if (isset($statusMessages[$status])) {
                        createNotification($appointment['user_id'], 'Appointment Update', $statusMessages[$status], 'appointment');
                    }
                    
                    // Log admin action
                    if (hasRole('admin')) {
                        logAdminAction($_SESSION['user_id'], "Updated appointment status to $status", "Appointment ID: $appointmentId");
                    }
                    
                    sendResponse(true, 'Appointment status updated successfully');
                } else {
                    sendResponse(false, 'Failed to update appointment status');
                }
            } catch (PDOException $e) {
                error_log("Error updating appointment status: " . $e->getMessage());
                sendResponse(false, 'Error updating appointment status');
            }
            break;
            
        // Get appointment details
        case 'get_appointment':
            requireAuth();
            
            $appointmentId = (int)($_GET['appointment_id'] ?? 0);
            
            if ($appointmentId <= 0) {
                sendResponse(false, 'Invalid appointment ID');
            }
            
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("
                    SELECT a.*, s.service_name, s.price, s.duration, t.name as therapist_name, t.specialization, u.name as client_name, u.email as client_email, u.phone_number as client_phone
                    FROM appointments a
                    JOIN services s ON a.service_id = s.service_id
                    JOIN therapists t ON a.therapist_id = t.therapist_id
                    JOIN users u ON a.user_id = u.user_id
                    WHERE a.appointment_id = ?
                ");
                $stmt->execute([$appointmentId]);
                $appointment = $stmt->fetch();
                
                if (!$appointment) {
                    sendResponse(false, 'Appointment not found');
                }
                
                // Check permissions
                if (!hasRole('admin')) {
                    if (hasRole('client') && $appointment['user_id'] != $_SESSION['user_id']) {
                        sendResponse(false, 'Access denied');
                    }
                    if (hasRole('therapist')) {
                        $therapistId = getTherapistIdByUserId($_SESSION['user_id']);
                        if ($appointment['therapist_id'] != $therapistId) {
                            sendResponse(false, 'Access denied');
                        }
                    }
                }
                
                sendResponse(true, 'Appointment retrieved', ['appointment' => $appointment]);
            } catch (PDOException $e) {
                error_log("Error fetching appointment: " . $e->getMessage());
                sendResponse(false, 'Error retrieving appointment');
            }
            break;
            
        // Get dashboard statistics (for admin)
        case 'get_dashboard_stats':
            requireAuth();
            
            if (!hasRole('admin')) {
                sendResponse(false, 'Access denied');
            }
            
            try {
                $pdo = getDBConnection();
                
                // Get appointment statistics
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_appointments,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_appointments,
                        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_appointments,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
                        SUM(CASE WHEN DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as today_appointments
                    FROM appointments
                ");
                $appointmentStats = $stmt->fetch();
                
                // Get user statistics
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_users,
                        SUM(CASE WHEN role = 'client' THEN 1 ELSE 0 END) as total_clients,
                        SUM(CASE WHEN role = 'therapist' THEN 1 ELSE 0 END) as total_therapists,
                        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_users_today
                    FROM users
                ");
                $userStats = $stmt->fetch();
                
                // Get inquiry statistics
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_inquiries,
                        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_inquiries,
                        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_inquiries_today
                    FROM inquiries
                ");
                $inquiryStats = $stmt->fetch();
                
                // Calculate revenue
                $stmt = $pdo->query("
                    SELECT 
                        SUM(s.price) as total_revenue,
                        SUM(CASE WHEN MONTH(a.appointment_date) = MONTH(CURDATE()) AND YEAR(a.appointment_date) = YEAR(CURDATE()) THEN s.price ELSE 0 END) as monthly_revenue
                    FROM appointments a
                    JOIN services s ON a.service_id = s.service_id
                    WHERE a.status = 'completed'
                ");
                $revenueStats = $stmt->fetch();
                
                $stats = [
                    'appointments' => $appointmentStats,
                    'users' => $userStats,
                    'inquiries' => $inquiryStats,
                    'revenue' => $revenueStats
                ];
                
                sendResponse(true, 'Dashboard statistics retrieved', ['stats' => $stats]);
            } catch (PDOException $e) {
                error_log("Error fetching dashboard stats: " . $e->getMessage());
                sendResponse(false, 'Error retrieving statistics');
            }
            break;
            
        // Search functionality
        case 'search':
            requireAuth();
            
            $query = sanitizeInput($_GET['query'] ?? '');
            $type = sanitizeInput($_GET['type'] ?? 'all');
            
            if (empty($query)) {
                sendResponse(false, 'Search query is required');
            }
            
            try {
                $pdo = getDBConnection();
                $results = [];
                
                if ($type === 'all' || $type === 'appointments') {
                    $stmt = $pdo->prepare("
                        SELECT 'appointment' as type, a.appointment_id as id, 
                               CONCAT(u.name, ' - ', s.service_name, ' - ', DATE_FORMAT(a.appointment_date, '%M %d, %Y')) as title,
                               a.status, a.appointment_date
                        FROM appointments a
                        JOIN users u ON a.user_id = u.user_id
                        JOIN services s ON a.service_id = s.service_id
                        WHERE u.name LIKE ? OR s.service_name LIKE ?
                        ORDER BY a.appointment_date DESC
                        LIMIT 10
                    ");
                    $searchTerm = "%$query%";
                    $stmt->execute([$searchTerm, $searchTerm]);
                    $results = array_merge($results, $stmt->fetchAll());
                }
                
                if ($type === 'all' || $type === 'users') {
                    $stmt = $pdo->prepare("
                        SELECT 'user' as type, user_id as id, 
                               CONCAT(name, ' (', email, ')') as title,
                               role as status, created_at as appointment_date
                        FROM users
                        WHERE name LIKE ? OR email LIKE ?
                        ORDER BY created_at DESC
                        LIMIT 10
                    ");
                    $searchTerm = "%$query%";
                    $stmt->execute([$searchTerm, $searchTerm]);
                    $results = array_merge($results, $stmt->fetchAll());
                }
                
                sendResponse(true, 'Search results retrieved', ['results' => $results]);
            } catch (PDOException $e) {
                error_log("Error performing search: " . $e->getMessage());
                sendResponse(false, 'Error performing search');
            }
            break;
            
        // Update user profile
        case 'update_profile':
            requireAuth();
            
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                sendResponse(false, 'Invalid security token');
            }
            
            $name = sanitizeInput($_POST['name'] ?? '');
            $phoneNumber = sanitizeInput($_POST['phone_number'] ?? '');
            $address = sanitizeInput($_POST['address'] ?? '');
            
            if (empty($name)) {
                sendResponse(false, 'Name is required');
            }
            
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("UPDATE users SET name = ?, phone_number = ?, address = ? WHERE user_id = ?");
                $result = $stmt->execute([$name, $phoneNumber, $address, $_SESSION['user_id']]);
                
                if ($result) {
                    $_SESSION['name'] = $name;
                    sendResponse(true, 'Profile updated successfully');
                } else {
                    sendResponse(false, 'Failed to update profile');
                }
            } catch (PDOException $e) {
                error_log("Error updating profile: " . $e->getMessage());
                sendResponse(false, 'Error updating profile');
            }
            break;
            
        default:
            sendResponse(false, 'Invalid action');
            break;
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    sendResponse(false, 'An unexpected error occurred');
}
?>