<?php
/**
 * Reusable Functions for GreenLife Wellness Center
 * Contains authentication, validation, and utility functions
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Enhanced email validation function
 */
function validateEmail($email) {
    // More comprehensive email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Check email length
    if (strlen($email) > 254) {
        return false;
    }
    
    // Check for common invalid patterns
    $invalidPatterns = [
        '/\.{2,}/',           // consecutive dots
        '/^\./',              // starts with dot
        '/\.$/',              // ends with dot
        '/@\./',              // @ followed by dot
        '/\.@/',              // dot followed by @
    ];
    
    foreach ($invalidPatterns as $pattern) {
        if (preg_match($pattern, $email)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Enhanced phone validation for Sri Lankan numbers
 */
function validatePhone($phone) {
    // Remove all non-digit characters except +
    $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
    
    // Sri Lankan phone number patterns
    $patterns = [
        '/^0\d{9}$/',           // 0771234567
        '/^\+94\d{9}$/',        // +94771234567
        '/^94\d{9}$/',          // 94771234567
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $cleanPhone)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    // At least 8 characters, one uppercase English letter, one lowercase English letter, one number
    // Only allow English letters, numbers, and common special characters
    if (strlen($password) < 8) {
        return false;
    }
    
    // Check for at least one lowercase English letter (a-z)
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    
    // Check for at least one uppercase English letter (A-Z)
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    
    // Check for at least one number (0-9)
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    // Only allow English letters, numbers, and safe special characters
    if (!preg_match('/^[a-zA-Z0-9@$!%*?&._-]+$/', $password)) {
        return false;
    }
    
    return true;
}

/**
 * Hash password securely
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check user role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Redirect if not specific role
 */
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: login.php?error=access_denied');
        exit();
    }
}

/**
 * Get user by email
 */
function getUserByEmail($email) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user by ID
 */
function getUserById($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return false;
    }
}

/**
 * Register new user
 */
function registerUser($name, $email, $password, $phone, $address, $role = 'client', $specialization = null, $bio = null, $experienceYears = 0) {
    try {
        $pdo = getDBConnection();
        
        // Check if email already exists
        if (getUserByEmail($email)) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        // Validate password strength
        if (!validatePassword($password)) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters with uppercase, lowercase, and number'];
        }
        
        // Begin transaction to ensure both user and therapist records are created
        $pdo->beginTransaction();
        
        try {
            // Hash password and insert user
            $hashedPassword = hashPassword($password);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, phone_number, address) VALUES (?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$name, $email, $hashedPassword, $role, $phone, $address]);
            
            if (!$result) {
                throw new Exception('Failed to create user record');
            }
            
            $userId = $pdo->lastInsertId();
            
            // If the role is therapist, create a corresponding therapist record
            if ($role === 'therapist') {
                $stmt = $pdo->prepare("INSERT INTO therapists (user_id, name, specialization, bio, experience_years, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $therapistResult = $stmt->execute([$userId, $name, $specialization, $bio, $experienceYears]);
                
                if (!$therapistResult) {
                    throw new Exception('Failed to create therapist record');
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Send welcome email
            sendWelcomeEmail($email, $name);
            return ['success' => true, 'message' => 'Registration successful'];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log("Registration transaction error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed'];
        }
        
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed'];
    }
}

/**
 * Login user with remember me functionality
 */
function loginUser($email, $password, $remember = false) {
    try {
        $user = getUserByEmail($email);
        
        if ($user && verifyPassword($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Handle "Remember me" functionality
            if ($remember) {
                // Generate a secure remember token
                $rememberToken = bin2hex(random_bytes(32));
                $rememberExpiry = time() + (30 * 24 * 60 * 60); // 30 days
                
                // Store remember token in database
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, remember_expires = FROM_UNIXTIME(?) WHERE user_id = ?");
                $stmt->execute([$rememberToken, $rememberExpiry, $user['user_id']]);
                
                // Set secure HTTP-only cookie
                setcookie(
                    'greenlife_remember',
                    $rememberToken,
                    $rememberExpiry,
                    '/',
                    '',
                    isset($_SERVER['HTTPS']), // Secure flag (HTTPS only)
                    true // HTTP-only flag
                );
            }
            
            // Update last login
            updateLastLogin($user['user_id']);
            
            return ['success' => true, 'role' => $user['role']];
        } else {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Login failed'];
    }
}

/**
 * Update last login timestamp
 */
function updateLastLogin($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Error updating last login: " . $e->getMessage());
    }
}

/**
 * Generate password reset token
 */
function generatePasswordResetToken($email) {
    try {
        $user = getUserByEmail($email);
        if (!$user) {
            return ['success' => false, 'message' => 'Email not found'];
        }
        
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $result = $stmt->execute([$token, $expires, $email]);
        
        if ($result) {
            sendPasswordResetEmail($email, $user['name'], $token);
            return ['success' => true, 'message' => 'Password reset email sent'];
        }
        
        return ['success' => false, 'message' => 'Failed to generate reset token'];
    } catch (PDOException $e) {
        error_log("Password reset error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Reset failed'];
    }
}

/**
 * Reset password with token
 */
function resetPassword($token, $newPassword) {
    try {
        if (!validatePassword($newPassword)) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters with uppercase, lowercase, and number'];
        }
        
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }
        
        $hashedPassword = hashPassword($newPassword);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?");
        $result = $stmt->execute([$hashedPassword, $user['user_id']]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Password reset successful'];
        }
        
        return ['success' => false, 'message' => 'Failed to reset password'];
    } catch (PDOException $e) {
        error_log("Password reset error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Reset failed'];
    }
}

/**
 * Get all services (active only)
 */
function getAllServices() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT * FROM services WHERE status = 'active' ORDER BY service_name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching services: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all therapists (active only) - excludes current user if they are a therapist
 */
function getAllTherapists($excludeUserId = null) {
    try {
        $pdo = getDBConnection();
        
        $query = "
            SELECT t.* 
            FROM therapists t 
            INNER JOIN users u ON t.user_id = u.user_id 
            WHERE t.status = 'active' AND u.status = 'active'
        ";
        
        $params = [];
        
        // If excluding a specific user ID (for therapist self-booking prevention)
        if ($excludeUserId) {
            $query .= " AND t.user_id != ?";
            $params[] = $excludeUserId;
        }
        
        $query .= " ORDER BY t.name";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching therapists: " . $e->getMessage());
        return [];
    }
}

/**
 * Get therapist ID by user ID
 */
function getTherapistIdByUserId($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT therapist_id FROM therapists WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ? $result['therapist_id'] : null;
    } catch (PDOException $e) {
        error_log("Error fetching therapist ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Book appointment with enhanced validation
 */
function bookAppointment($userId, $therapistId, $serviceId, $appointmentDate, $appointmentTime) {
    try {
        $pdo = getDBConnection();
        
        // Validate appointment date (must be at least 24 hours in advance)
        $appointmentDateTime = new DateTime("$appointmentDate $appointmentTime");
        $now = new DateTime();
        $now->add(new DateInterval('PT24H'));
        
        if ($appointmentDateTime < $now) {
            return ['success' => false, 'message' => 'Appointments must be booked at least 24 hours in advance'];
        }
        
        // Check business hours (9 AM - 6 PM, Monday-Saturday)
        $dayOfWeek = $appointmentDateTime->format('w');
        $hour = (int)$appointmentDateTime->format('H');
        
        if ($dayOfWeek == 0 || $hour < 9 || $hour >= 18) {
            return ['success' => false, 'message' => 'Please select a time during business hours (9 AM - 6 PM, Monday-Saturday)'];
        }
        
        // Check for conflicting appointments
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE therapist_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ('canceled')");
        $stmt->execute([$therapistId, $appointmentDate, $appointmentTime]);
        
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Time slot not available'];
        }
        
        // Check if user has pending appointment
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'You have a pending appointment. Please wait for confirmation before booking another.'];
        }
        
        // Book the appointment
        $stmt = $pdo->prepare("INSERT INTO appointments (user_id, therapist_id, service_id, appointment_date, appointment_time) VALUES (?, ?, ?, ?, ?)");
        $result = $stmt->execute([$userId, $therapistId, $serviceId, $appointmentDate, $appointmentTime]);
        
        if ($result) {
            // Send confirmation emails
            sendAppointmentConfirmation($userId, $pdo->lastInsertId());
            createNotification($userId, 'Appointment Booked', 'Your appointment has been successfully booked and is pending confirmation.', 'appointment');
            
            return ['success' => true, 'message' => 'Appointment booked successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to book appointment'];
        }
    } catch (PDOException $e) {
        error_log("Booking error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Booking failed'];
    }
}

/**
 * Submit inquiry with priority
 */
function submitInquiry($userId, $subject, $message, $priority = 'medium') {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO inquiries (user_id, subject, message, priority) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$userId, $subject, $message, $priority]);
        
        if ($result) {
            // Notify admins
            notifyAdminsOfNewInquiry($pdo->lastInsertId());
            return ['success' => true, 'message' => 'Inquiry submitted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to submit inquiry'];
        }
    } catch (PDOException $e) {
        error_log("Inquiry error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Inquiry submission failed'];
    }
}

/**
 * Get user appointments
 */
function getUserAppointments($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT a.*, s.service_name, s.price, s.duration, t.name as therapist_name, t.specialization 
            FROM appointments a 
            JOIN services s ON a.service_id = s.service_id 
            JOIN therapists t ON a.therapist_id = t.therapist_id 
            INNER JOIN users u ON t.user_id = u.user_id
            WHERE a.user_id = ? AND u.status = 'active'
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching appointments: " . $e->getMessage());
        return [];
    }
}

/**
 * Create notification
 */
function createNotification($userId, $title, $message, $type = 'system') {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $message, $type]);
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
    }
}

/**
 * Send welcome email
 */
function sendWelcomeEmail($email, $name) {
    $subject = "Welcome to GreenLife Wellness Center";
    $message = "
    <html>
    <body>
        <h2>Welcome to GreenLife Wellness Center, $name!</h2>
        <p>Thank you for joining our wellness community. We're excited to help you on your journey to better health and wellbeing.</p>
        <p>You can now:</p>
        <ul>
            <li>Book appointments with our expert therapists</li>
            <li>Submit inquiries and get personalized advice</li>
            <li>Track your wellness journey</li>
        </ul>
        <p>Visit our website to get started: <a href='http://localhost/greenlife'>GreenLife Wellness Center</a></p>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $name, $token) {
    $resetLink = "http://localhost/greenlife/reset_password.php?token=$token";
    $subject = "Password Reset - GreenLife Wellness Center";
    $message = "
    <html>
    <body>
        <h2>Password Reset Request</h2>
        <p>Hi $name,</p>
        <p>You requested a password reset for your GreenLife Wellness Center account.</p>
        <p>Click the link below to reset your password (expires in 1 hour):</p>
        <p><a href='$resetLink'>Reset Password</a></p>
        <p>If you didn't request this reset, please ignore this email.</p>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

/**
 * Send appointment confirmation email
 */
function sendAppointmentConfirmation($userId, $appointmentId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT a.*, u.name, u.email, s.service_name, s.price, t.name as therapist_name
            FROM appointments a
            JOIN users u ON a.user_id = u.user_id
            JOIN services s ON a.service_id = s.service_id
            JOIN therapists t ON a.therapist_id = t.therapist_id
            WHERE a.appointment_id = ?
        ");
        $stmt->execute([$appointmentId]);
        $appointment = $stmt->fetch();
        
        if ($appointment) {
            $subject = "Appointment Confirmation - GreenLife Wellness Center";
            $message = "
            <html>
            <body>
                <h2>Appointment Confirmation</h2>
                <p>Hi {$appointment['name']},</p>
                <p>Your appointment has been successfully booked!</p>
                <h3>Appointment Details:</h3>
                <ul>
                    <li><strong>Service:</strong> {$appointment['service_name']}</li>
                    <li><strong>Therapist:</strong> {$appointment['therapist_name']}</li>
                    <li><strong>Date:</strong> " . formatDate($appointment['appointment_date']) . "</li>
                    <li><strong>Time:</strong> " . formatTime($appointment['appointment_time']) . "</li>
                    <li><strong>Price:</strong> Rs. " . number_format($appointment['price'], 2) . "</li>
                    <li><strong>Status:</strong> Pending Confirmation</li>
                </ul>
                <p>Please arrive 10 minutes early for your appointment.</p>
            </body>
            </html>
            ";
            
            return sendEmail($appointment['email'], $subject, $message);
        }
    } catch (PDOException $e) {
        error_log("Error sending appointment confirmation: " . $e->getMessage());
    }
}

/**
 * Basic email sending function (disabled for this application)
 */
function sendEmail($to, $subject, $message) {
    // Email functionality disabled - just log the attempt and return success
    error_log("EMAIL DISABLED - Would send to: $to | Subject: $subject");
    
    try {
        $pdo = getDBConnection();
        // Log email attempt as 'disabled' 
        $stmt = $pdo->prepare("INSERT INTO email_logs (recipient_email, subject, status) VALUES (?, ?, ?)");
        $stmt->execute([$to, $subject, 'disabled']);
    } catch (Exception $e) {
        error_log("Email logging error: " . $e->getMessage());
    }
    
    // Always return true to prevent errors in the application flow
    return true;
}

/**
 * Notify admins of new inquiry
 */
function notifyAdminsOfNewInquiry($inquiryId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' AND status = 'active'");
        $admins = $stmt->fetchAll();
        
        foreach ($admins as $admin) {
            createNotification($admin['user_id'], 'New Inquiry', 'A new inquiry has been submitted and requires attention.', 'inquiry');
        }
    } catch (PDOException $e) {
        error_log("Error notifying admins: " . $e->getMessage());
    }
}

/**
 * Generate and verify CSRF tokens
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate booking date
 */
function isValidBookingDate($date) {
    $dateObj = new DateTime($date);
    $today = new DateTime();
    $today->setTime(0, 0, 0); // Set to beginning of today
    $tomorrow = clone $today;
    $tomorrow->add(new DateInterval('P1D')); // Add 1 day for tomorrow
    
    // Check if date is tomorrow or later
    if ($dateObj < $tomorrow) {
        return false;
    }
    
    // Check if it's not a Sunday (0 = Sunday)
    if ($dateObj->format('w') == 0) {
        return false;
    }
    
    return true;
}

/**
 * Get available time slots for a therapist on a specific date
 */
function getAvailableTimeSlots($therapistId, $date) {
    try {
        $pdo = getDBConnection();
        
        // Define business hours (9 AM to 6 PM)
        $businessStart = 9;
        $businessEnd = 18;
        $slotDuration = 60; // 1 hour slots
        
        // Generate all possible time slots
        $allSlots = [];
        for ($hour = $businessStart; $hour < $businessEnd; $hour++) {
            $allSlots[] = sprintf('%02d:00:00', $hour);
        }
        
        // Get booked slots for this therapist on this date
        $stmt = $pdo->prepare("
            SELECT appointment_time 
            FROM appointments 
            WHERE therapist_id = ? AND appointment_date = ? AND status NOT IN ('canceled')
        ");
        $stmt->execute([$therapistId, $date]);
        $bookedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Remove booked slots from available slots
        $availableSlots = array_diff($allSlots, $bookedSlots);
        
        return array_values($availableSlots);
    } catch (PDOException $e) {
        error_log("Error getting available slots: " . $e->getMessage());
        return [];
    }
}

/**
 * Format date for display
 */
function formatDate($date) {
    if (empty($date)) return '';
    return date('F j, Y', strtotime($date));
}

/**
 * Format time for display
 */
function formatTime($time) {
    if (empty($time)) return '';
    return date('g:i A', strtotime($time));
}

/**
 * Log admin actions for audit trail
 */
function logAdminAction($adminId, $action, $details = '') {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$adminId, $action, $details]);
    } catch (PDOException $e) {
        error_log("Error logging admin action: " . $e->getMessage());
    }
}

/**
 * Get user's upcoming appointments count
 */
function getUserUpcomingAppointmentsCount($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments 
            WHERE user_id = ? AND appointment_date >= CURDATE() AND status IN ('pending', 'confirmed')
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting upcoming appointments count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if user can book new appointment
 */
function canUserBookAppointment($userId) {
    $pendingCount = getUserUpcomingAppointmentsCount($userId);
    return $pendingCount < 3; // Maximum 3 upcoming appointments
}

/**
 * Send appointment reminder emails
 */
function sendAppointmentReminders() {
    try {
        $pdo = getDBConnection();
        
        // Get appointments for tomorrow
        $stmt = $pdo->prepare("
            SELECT a.*, u.name, u.email, s.service_name, t.name as therapist_name
            FROM appointments a
            JOIN users u ON a.user_id = u.user_id
            JOIN services s ON a.service_id = s.service_id
            JOIN therapists t ON a.therapist_id = t.therapist_id
            WHERE a.appointment_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) 
            AND a.status = 'confirmed'
            AND a.reminder_sent = FALSE
        ");
        $stmt->execute();
        $appointments = $stmt->fetchAll();
        
        foreach ($appointments as $appointment) {
            $subject = "Appointment Reminder - GreenLife Wellness Center";
            $message = "
            <html>
            <body>
                <h2>Appointment Reminder</h2>
                <p>Hi {$appointment['name']},</p>
                <p>This is a friendly reminder about your appointment tomorrow:</p>
                <h3>Appointment Details:</h3>
                <ul>
                    <li><strong>Service:</strong> {$appointment['service_name']}</li>
                    <li><strong>Therapist:</strong> {$appointment['therapist_name']}</li>
                    <li><strong>Date:</strong> " . formatDate($appointment['appointment_date']) . "</li>
                    <li><strong>Time:</strong> " . formatTime($appointment['appointment_time']) . "</li>
                </ul>
                <p>Please arrive 10 minutes early for your appointment.</p>
                <p>If you need to reschedule or cancel, please contact us at least 4 hours in advance.</p>
            </body>
            </html>
            ";
            
            if (sendEmail($appointment['email'], $subject, $message)) {
                // Mark reminder as sent
                $updateStmt = $pdo->prepare("UPDATE appointments SET reminder_sent = TRUE WHERE appointment_id = ?");
                $updateStmt->execute([$appointment['appointment_id']]);
            }
        }
        
        return count($appointments);
    } catch (PDOException $e) {
        error_log("Error sending reminders: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get service by ID
 */
function getServiceById($serviceId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM services WHERE service_id = ?");
        $stmt->execute([$serviceId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching service: " . $e->getMessage());
        return false;
    }
}

/**
 * Get therapist by ID
 */
function getTherapistById($therapistId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM therapists WHERE therapist_id = ?");
        $stmt->execute([$therapistId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching therapist: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate age from birth date
 */
function calculateAge($birthDate) {
    if (empty($birthDate)) return null;
    
    $today = new DateTime();
    $birth = new DateTime($birthDate);
    return $today->diff($birth)->y;
}

/**
 * Sanitize filename for uploads
 */
function sanitizeFilename($filename) {
    // Remove any path information
    $filename = basename($filename);
    
    // Replace spaces with underscores
    $filename = str_replace(' ', '_', $filename);
    
    // Remove any characters that aren't alphanumeric, underscore, dash, or dot
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    return $filename;
}

/**
 * Generate random password
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Check if email domain is valid
 */
function isValidEmailDomain($email) {
    $domain = substr(strrchr($email, "@"), 1);
    return checkdnsrr($domain, "MX");
}

/**
 * Get system settings
 */
function getSystemSetting($key, $default = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        error_log("Error getting system setting: " . $e->getMessage());
        return $default;
    }
}

/**
 * Update system setting
 */
function updateSystemSetting($key, $value) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    } catch (PDOException $e) {
        error_log("Error updating system setting: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current logged-in user information
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return getUserById($_SESSION['user_id']);
}

/**
 * Get appropriate dashboard URL based on user role
 */
function getUserDashboard($role) {
    switch ($role) {
        case 'admin':
            return 'admin_dashboard.php';
        case 'therapist':
            return 'therapist_dashboard.php';
        case 'client':
        default:
            return 'client_dashboard.php';
    }
}

/**
 * Migrate existing therapist users to therapists table
 * This function creates therapist records for users with 'therapist' role who don't have one
 */
function migrateExistingTherapists() {
    try {
        $pdo = getDBConnection();
        
        // Find all users with therapist role who don't have a therapist record
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.name 
            FROM users u 
            LEFT JOIN therapists t ON u.user_id = t.user_id 
            WHERE u.role = 'therapist' AND t.therapist_id IS NULL
        ");
        $stmt->execute();
        $therapistUsers = $stmt->fetchAll();
        
        $migratedCount = 0;
        
        foreach ($therapistUsers as $user) {
            // Create therapist record for this user
            $stmt = $pdo->prepare("INSERT INTO therapists (user_id, name, specialization, experience_years, status) VALUES (?, ?, ?, ?, 'active')");
            $result = $stmt->execute([$user['user_id'], $user['name'], 'General Practice', 0]);
            
            if ($result) {
                $migratedCount++;
            }
        }
        
        return ['success' => true, 'message' => "Migrated $migratedCount therapist users", 'count' => $migratedCount];
        
    } catch (PDOException $e) {
        error_log("Error migrating therapists: " . $e->getMessage());
        return ['success' => false, 'message' => 'Migration failed'];
    }
}

/**
 * Update user and sync therapist data
 */
function updateUserAndSyncTherapist($userId, $name, $email, $role, $phone, $address, $specialization = null, $bio = null, $experienceYears = 0) {
    try {
        $pdo = getDBConnection();
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Update user record
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, phone_number = ?, address = ? WHERE user_id = ?");
            $result = $stmt->execute([$name, $email, $role, $phone, $address, $userId]);
            
            if (!$result) {
                throw new Exception('Failed to update user record');
            }
            
            // Handle therapist role changes
            if ($role === 'therapist') {
                // Check if therapist record exists
                $stmt = $pdo->prepare("SELECT therapist_id FROM therapists WHERE user_id = ?");
                $stmt->execute([$userId]);
                $therapistExists = $stmt->fetch();
                
                if (!$therapistExists) {
                    // Create therapist record if it doesn't exist
                    $stmt = $pdo->prepare("INSERT INTO therapists (user_id, name, specialization, bio, experience_years, status) VALUES (?, ?, ?, ?, ?, 'active')");
                    $therapistResult = $stmt->execute([$userId, $name, $specialization, $bio, $experienceYears]);
                    
                    if (!$therapistResult) {
                        throw new Exception('Failed to create therapist record');
                    }
                } else {
                    // Update existing therapist name, specialization, bio, and experience years
                    $stmt = $pdo->prepare("UPDATE therapists SET name = ?, specialization = ?, bio = ?, experience_years = ? WHERE user_id = ?");
                    $stmt->execute([$name, $specialization, $bio, $experienceYears, $userId]);
                }
            } else {
                // If role changed from therapist to something else, deactivate therapist record
                $stmt = $pdo->prepare("UPDATE therapists SET status = 'inactive' WHERE user_id = ?");
                $stmt->execute([$userId]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            return ['success' => true, 'message' => 'User updated successfully'];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log("Update user transaction error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed'];
        }
        
    } catch (PDOException $e) {
        error_log("Update user error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Update failed'];
    }
}

/**
 * Clean up orphaned therapist records
 * This function removes therapist records that don't have corresponding active user accounts
 */
function cleanupOrphanedTherapists() {
    try {
        $pdo = getDBConnection();
        
        // Find therapist records with deleted or inactive user accounts
        $stmt = $pdo->prepare("
            SELECT t.therapist_id, t.name, t.user_id 
            FROM therapists t 
            LEFT JOIN users u ON t.user_id = u.user_id 
            WHERE u.user_id IS NULL OR u.status != 'active'
        ");
        $stmt->execute();
        $orphanedTherapists = $stmt->fetchAll();
        
        $cleanedCount = 0;
        
        foreach ($orphanedTherapists as $therapist) {
            // Cancel any pending/confirmed appointments for this therapist
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'canceled', 
                    admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[System] Therapist account deleted - appointment canceled') 
                WHERE therapist_id = ? AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$therapist['therapist_id']]);
            
            // Delete the orphaned therapist record
            $stmt = $pdo->prepare("DELETE FROM therapists WHERE therapist_id = ?");
            $stmt->execute([$therapist['therapist_id']]);
            
            $cleanedCount++;
        }
        
        return ['success' => true, 'message' => "Cleaned up $cleanedCount orphaned therapist records", 'count' => $cleanedCount];
        
    } catch (PDOException $e) {
        error_log("Error cleaning orphaned therapists: " . $e->getMessage());
        return ['success' => false, 'message' => 'Cleanup failed'];
    }
}

/**
 * Updates a user's profile information.
 *
 * @param int $userId The ID of the user to update.
 * @param string $name The user's new full name.
 * @param string $phone The user's new phone number.
 * @param string $address The user's new address.
 * @return bool True on success, false on failure.
 */
function updateUserProfile($userId, $name, $phone, $address) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone_number = ?, address = ? WHERE user_id = ?");
        return $stmt->execute([$name, $phone, $address, $userId]);
    } catch (PDOException $e) {
        error_log("Error in updateUserProfile: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and handle remember me token for automatic login
 */
function checkRememberToken() {
    // Skip if user is already logged in
    if (isLoggedIn()) {
        return false;
    }
    
    // Check if remember token cookie exists
    if (!isset($_COOKIE['greenlife_remember']) || empty($_COOKIE['greenlife_remember'])) {
        return false;
    }
    
    try {
        $rememberToken = $_COOKIE['greenlife_remember'];
        $pdo = getDBConnection();
        
        // Find user with valid remember token
        $stmt = $pdo->prepare("
            SELECT * FROM users 
            WHERE remember_token = ? 
            AND remember_expires > NOW() 
            AND status = 'active'
        ");
        $stmt->execute([$rememberToken]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Automatically log in the user
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Update last login
            updateLastLogin($user['user_id']);
            
            // Extend the remember token expiry
            $newExpiry = time() + (30 * 24 * 60 * 60); // 30 days
            $stmt = $pdo->prepare("UPDATE users SET remember_expires = FROM_UNIXTIME(?) WHERE user_id = ?");
            $stmt->execute([$newExpiry, $user['user_id']]);
            
            // Update cookie expiry
            setcookie(
                'greenlife_remember',
                $rememberToken,
                $newExpiry,
                '/',
                '',
                isset($_SERVER['HTTPS']),
                true
            );
            
            return true;
        } else {
            // Invalid or expired token, remove cookie
            clearRememberToken();
            return false;
        }
        
    } catch (PDOException $e) {
        error_log("Error checking remember token: " . $e->getMessage());
        clearRememberToken();
        return false;
    }
}

/**
 * Clear remember token from database and cookie
 */
function clearRememberToken($userId = null) {
    try {
        // Clear from database if user ID provided
        if ($userId) {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE user_id = ?");
            $stmt->execute([$userId]);
        }
        
        // Clear cookie
        if (isset($_COOKIE['greenlife_remember'])) {
            setcookie(
                'greenlife_remember',
                '',
                time() - 3600,
                '/',
                '',
                isset($_SERVER['HTTPS']),
                true
            );
        }
        
    } catch (PDOException $e) {
        error_log("Error clearing remember token: " . $e->getMessage());
    }
}

/**
 * Get user by remember token
 */
function getUserByRememberToken($token) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM users 
            WHERE remember_token = ? 
            AND remember_expires > NOW() 
            AND status = 'active'
        ");
        $stmt->execute([$token]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching user by remember token: " . $e->getMessage());
        return false;
    }
}

/**
 * Enhanced business hours validation
 */
function isValidBusinessHours($date, $time) {
    $datetime = new DateTime("$date $time");
    $dayOfWeek = (int)$datetime->format('w'); // 0 = Sunday, 6 = Saturday
    $hour = (int)$datetime->format('H');
    
    // Business hours by day
    $businessHours = [
        0 => null,                    // Sunday - closed
        1 => ['start' => 9, 'end' => 18],  // Monday
        2 => ['start' => 9, 'end' => 18],  // Tuesday
        3 => ['start' => 9, 'end' => 18],  // Wednesday
        4 => ['start' => 9, 'end' => 18],  // Thursday
        5 => ['start' => 9, 'end' => 18],  // Friday
        6 => ['start' => 10, 'end' => 16], // Saturday
    ];
    
    // Check if closed on this day
    if ($businessHours[$dayOfWeek] === null) {
        return false;
    }
    
    // Check if within business hours
    $dayHours = $businessHours[$dayOfWeek];
    return $hour >= $dayHours['start'] && $hour < $dayHours['end'];
}

/**
 * Enhanced appointment booking validation
 */
function validateAppointmentBooking($userId, $therapistId, $serviceId, $appointmentDate, $appointmentTime) {
    $errors = [];
    
    try {
        $pdo = getDBConnection();
        
        // Check if service exists and is active
        $stmt = $pdo->prepare("SELECT service_id, service_name, status FROM services WHERE service_id = ?");
        $stmt->execute([$serviceId]);
        $service = $stmt->fetch();
        
        if (!$service) {
            $errors[] = 'Selected service does not exist';
        } elseif ($service['status'] !== 'active') {
            $errors[] = 'Selected service is currently unavailable';
        }
        
        // Check if therapist exists and is active
        $stmt = $pdo->prepare("SELECT therapist_id, name, status FROM therapists WHERE therapist_id = ?");
        $stmt->execute([$therapistId]);
        $therapist = $stmt->fetch();
        
        if (!$therapist) {
            $errors[] = 'Selected therapist does not exist';
        } elseif ($therapist['status'] !== 'active') {
            $errors[] = 'Selected therapist is currently unavailable';
        }
        
        // Validate appointment date and time
        $appointmentDateTime = new DateTime("$appointmentDate $appointmentTime");
        $now = new DateTime();
        $now->add(new DateInterval('PT24H')); // Add 24 hours
        
        if ($appointmentDateTime < $now) {
            $errors[] = 'Appointments must be booked at least 24 hours in advance';
        }
        
        // Check business hours
        if (!isValidBusinessHours($appointmentDate, $appointmentTime)) {
            $errors[] = 'Selected time is outside business hours';
        }
        
        // Check if time slot is available
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments 
            WHERE therapist_id = ? 
            AND appointment_date = ? 
            AND appointment_time = ? 
            AND status NOT IN ('canceled')
        ");
        $stmt->execute([$therapistId, $appointmentDate, $appointmentTime]);
        
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Selected time slot is not available';
        }
        
        // Check user's pending appointments limit
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments 
            WHERE user_id = ? 
            AND status IN ('pending', 'confirmed') 
            AND appointment_date >= CURDATE()
        ");
        $stmt->execute([$userId]);
        
        if ($stmt->fetchColumn() >= 3) {
            $errors[] = 'You can have maximum 3 upcoming appointments';
        }
        
    } catch (PDOException $e) {
        error_log("Validation error: " . $e->getMessage());
        $errors[] = 'Unable to validate appointment details';
    }
    
    return $errors;
}

/**
 * Get user inquiries with responses
 */
function getUserInquiries($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT i.*, 
                   admin.name as admin_name
            FROM inquiries i
            LEFT JOIN users admin ON i.responded_by = admin.user_id
            WHERE i.user_id = ?
            ORDER BY i.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching user inquiries: " . $e->getMessage());
        return [];
    }
}
?>