<?php
/**
 * Contact Us / Inquiry Form
 * GreenLife Wellness Center
 */

$pageTitle = "Contact Us";
require_once 'includes/functions.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $subject = sanitizeInput($_POST['subject'] ?? '');
        $message = sanitizeInput($_POST['message'] ?? '');
        $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
        
        // For non-logged in users, get contact details
        $guestName = sanitizeInput($_POST['name'] ?? '');
        $guestEmail = sanitizeInput($_POST['email'] ?? '');
        $guestPhone = sanitizeInput($_POST['phone'] ?? '');
        
        // Validation
        if (empty($subject)) {
            $error = 'Please enter a subject';
        } elseif (empty($message) || strlen($message) < 10) {
            $error = 'Please enter a message (at least 10 characters)';
        } elseif (!isLoggedIn()) {
            // Additional validation for guest users
            if (empty($guestName)) {
                $error = 'Please enter your name';
            } elseif (!validateEmail($guestEmail)) {
                $error = 'Please enter a valid email address';
            }
        }
        
        if (!$error) {
            // If not logged in, prepend contact details to message
            if (!isLoggedIn()) {
                $message = "Contact Details:\nName: $guestName\nEmail: $guestEmail\nPhone: $guestPhone\n\nMessage:\n$message";
            }
            
            // Submit inquiry
            $result = submitInquiry($userId, $subject, $message);
            
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header text-center">
            <h1><i class="fas fa-envelope"></i> Contact Us</h1>
            <p>We're here to help you on your wellness journey. Get in touch with us!</p>
        </div>
        
        <div class="row">
            <div class="col-8">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-message"></i> Send Us a Message</h3>
                    </div>
                    
                    <div class="card-body">
                        <div class="error-message" style="color: red; <?php echo empty($error) ? 'display: none;' : ''; ?>">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                                <?php if (isLoggedIn()): ?>
                                    <br><a href="index.php" class="btn btn-primary mt-3">Back to Dashboard</a>
                                <?php else: ?>
                                    <br><a href="index.php" class="btn btn-primary mt-3">Back to Home</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <form id="inquiryForm" method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <?php if (!isLoggedIn()): ?>
                                    <!-- Guest user contact details -->
                                    <div class="form-group">
                                        <label for="name" class="form-label">Your Name *</label>
                                        <input type="text" id="name" name="name" class="form-control <?php echo ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($guestName)) ? 'is-invalid' : ''; ?>" 
                                               value="<?php echo htmlspecialchars($guestName ?? ''); ?>" 
                                               placeholder="Enter your full name">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label for="email" class="form-label">Email Address *</label>
                                                <input type="email" id="email" name="email" class="form-control <?php echo ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateEmail($guestEmail ?? '')) ? 'is-invalid' : ''; ?>" 
                                                       value="<?php echo htmlspecialchars($guestEmail ?? ''); ?>" 
                                                       placeholder="Enter your email">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label for="phone" class="form-label">Phone Number</label>
                                                <input type="tel" id="phone" name="phone" class="form-control" 
                                                       value="<?php echo htmlspecialchars($guestPhone ?? ''); ?>" 
                                                       placeholder="Your phone number">
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> 
                                        Sending inquiry as: <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong> 
                                        (<?php echo htmlspecialchars($_SESSION['email']); ?>)
                                    </div>
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <select id="subject" name="subject" class="form-control form-select <?php echo ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($subject)) ? 'is-invalid' : ''; ?>">
                                        <option value="">Select a topic</option>
                                        <option value="General Inquiry" <?php echo ($subject ?? '') === 'General Inquiry' ? 'selected' : ''; ?>>General Inquiry</option>
                                        <option value="Appointment Booking" <?php echo ($subject ?? '') === 'Appointment Booking' ? 'selected' : ''; ?>>Appointment Booking</option>
                                        <option value="Service Information" <?php echo ($subject ?? '') === 'Service Information' ? 'selected' : ''; ?>>Service Information</option>
                                        <option value="Pricing Question" <?php echo ($subject ?? '') === 'Pricing Question' ? 'selected' : ''; ?>>Pricing Question</option>
                                        <option value="Therapist Information" <?php echo ($subject ?? '') === 'Therapist Information' ? 'selected' : ''; ?>>Therapist Information</option>
                                        <option value="Feedback/Complaint" <?php echo ($subject ?? '') === 'Feedback/Complaint' ? 'selected' : ''; ?>>Feedback/Complaint</option>
                                        <option value="Technical Support" <?php echo ($subject ?? '') === 'Technical Support' ? 'selected' : ''; ?>>Technical Support</option>
                                        <option value="Other" <?php echo ($subject ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message" class="form-label">Message *</label>
                                    <textarea id="message" name="message" class="form-control <?php echo ($_SERVER['REQUEST_METHOD'] === 'POST' && (empty($message) || strlen($message) < 10)) ? 'is-invalid' : ''; ?>" rows="6" 
                                              placeholder="Please describe your inquiry in detail..."><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                                    <small class="text-muted">Minimum 10 characters</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane"></i> Send Message
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-4">
                <!-- Contact Information -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-map-marker-alt"></i> Visit Us</h4>
                    </div>
                    <div class="card-body">
                        <p><strong>GreenLife Wellness Center</strong></p>
                        <p>123 Wellness Street<br>Colombo 07, Sri Lanka</p>
                        
                        <hr>
                        
                        <h5>Operating Hours</h5>
                        <p><strong>Monday - Saturday:</strong><br>8:00 AM - 8:00 PM</p>
                        <p><strong>Sunday:</strong><br>Closed</p>
                        
                        <hr>
                        
                        <h5>Contact Information</h5>
                        <p><i class="fas fa-phone text-primary"></i> +94 11 234 5678</p>
                        <p><i class="fas fa-envelope text-primary"></i> info@greenlifewellness.com</p>
                        <p><i class="fas fa-globe text-primary"></i> www.greenlifewellness.com</p>
                    </div>
                </div>
                
                <!-- Emergency Contact -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-ambulance"></i> Emergency</h4>
                    </div>
                    <div class="card-body">
                        <p>For medical emergencies, please contact:</p>
                        <p><strong>Emergency Hotline:</strong><br>+94 11 269 1111</p>
                        <p><strong>Accident Service:</strong><br>110</p>
                        <p class="text-muted small">Our wellness center provides therapeutic services and is not equipped for medical emergencies.</p>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-lightning-bolt"></i> Quick Actions</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isLoggedIn()): ?>
                            <a href="appointment_booking.php" class="btn btn-primary mb-2" style="width: 100%;">
                                <i class="fas fa-calendar-plus"></i> Book Appointment
                            </a>
                            <a href="client_dashboard.php" class="btn btn-outline mb-2" style="width: 100%;">
                                <i class="fas fa-tachometer-alt"></i> My Dashboard
                            </a>
                        <?php else: ?>
                            <a href="register.php" class="btn btn-primary mb-2" style="width: 100%;">
                                <i class="fas fa-user-plus"></i> Register Now
                            </a>
                            <a href="login.php" class="btn btn-outline mb-2" style="width: 100%;">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        <?php endif; ?>
                        <a href="index.php#services" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-spa"></i> Our Services
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- FAQ Section -->
        <div class="card mt-4">
            <div class="card-header">
                <h3><i class="fas fa-question-circle"></i> Frequently Asked Questions</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="faq-item">
                            <h5>How do I book an appointment?</h5>
                            <p>You can book appointments online through our booking system after creating an account, or call us directly at +94 11 234 5678.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h5>What should I bring to my appointment?</h5>
                            <p>Please bring comfortable clothing, a water bottle, and any relevant medical information or concerns you'd like to discuss.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h5>Do you accept insurance?</h5>
                            <p>We accept most major health insurance plans. Please contact us to verify your specific coverage before your appointment.</p>
                        </div>
                    </div>
                    
                    <div class="col-6">
                        <div class="faq-item">
                            <h5>Can I cancel my appointment?</h5>
                            <p>Yes, you can cancel appointments up to 24 hours in advance without penalty. Last-minute cancellations may incur a fee.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h5>Are your therapists certified?</h5>
                            <p>Yes, all our therapists are professionally trained and certified in their respective specializations with years of experience.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h5>What payment methods do you accept?</h5>
                            <p>We accept cash, credit/debit cards, and bank transfers. Payment is typically made at the center after your session.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.faq-item {
    margin-bottom: 2rem;
}

.faq-item h5 {
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.faq-item p {
    color: var(--gray-600);
    margin-bottom: 0;
}
</style>

<?php require_once 'includes/footer.php'; ?>