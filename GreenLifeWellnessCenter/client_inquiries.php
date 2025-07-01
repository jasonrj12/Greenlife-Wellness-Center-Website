<?php
/**
 * Client Inquiries Page
 * GreenLife Wellness Center
 */

$pageTitle = "My Inquiries";
require_once 'includes/functions.php';

// Require client login
requireLogin();
if ($_SESSION['role'] !== 'client') {
    header('Location: login.php?error=access_denied');
    exit();
}

// Get user inquiries
$inquiries = getUserInquiries($_SESSION['user_id']);

// Get statistics
$totalInquiries = count($inquiries);
$answeredCount = count(array_filter($inquiries, function($inquiry) {
    return !empty($inquiry['admin_response']);
}));
$pendingCount = $totalInquiries - $answeredCount;

require_once 'includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-envelope me-2"></i>My Inquiries</h1>
            <p>View your submitted inquiries and admin responses</p>
        </div>
        
        <!-- Inquiry Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon"><i class="fas fa-envelope"></i></div>
                <div class="number"><?php echo $totalInquiries; ?></div>
                <div class="label">Total Inquiries</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <div class="number"><?php echo $pendingCount; ?></div>
                <div class="label">Pending Response</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="number"><?php echo $answeredCount; ?></div>
                <div class="label">Answered</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-plus-circle"></i></div>
                <div class="number">
                    <a href="contact_us.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> New Inquiry
                    </a>
                </div>
                <div class="label">Submit New</div>
            </div>
        </div>
        
        <!-- Inquiries List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Your Inquiries</h3>
                <div class="card-actions">
                    <a href="contact_us.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Submit New Inquiry
                    </a>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (empty($inquiries)): ?>
                    <div class="text-center p-5">
                        <i class="fas fa-envelope-open" style="font-size: 4rem; color: var(--gray-400); margin-bottom: 1rem;"></i>
                        <h4>No inquiries yet</h4>
                        <p class="text-muted">You haven't submitted any inquiries yet. Need help or have questions?</p>
                        <a href="contact_us.php" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit Your First Inquiry
                        </a>
                    </div>
                <?php else: ?>
                    <div class="inquiries-list">
                        <?php foreach ($inquiries as $inquiry): ?>
                            <div class="inquiry-card" data-inquiry-id="<?php echo $inquiry['inquiry_id']; ?>">
                                <div class="inquiry-header">
                                    <div class="inquiry-info">
                                        <h5><?php echo htmlspecialchars($inquiry['subject']); ?></h5>
                                        <div class="inquiry-meta">
                                            <span class="text-muted">
                                                <i class="fas fa-calendar fa-sm"></i>
                                                Submitted: <?php echo formatDate($inquiry['created_at']); ?>
                                            </span>
                                            <?php if (!empty($inquiry['admin_response'])): ?>
                                                <span class="text-muted ms-3">
                                                    <i class="fas fa-reply fa-sm"></i>
                                                    Responded: <?php echo formatDate($inquiry['responded_at']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="inquiry-status">
                                        <?php if (!empty($inquiry['admin_response'])): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Answered
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-clock"></i> Pending Response
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="inquiry-content">
                                    <div class="original-message">
                                        <h6><i class="fas fa-comment"></i> Your Message:</h6>
                                        <div class="message-content">
                                            <p><?php echo nl2br(htmlspecialchars($inquiry['message'])); ?></p>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($inquiry['admin_response'])): ?>
                                        <div class="admin-response">
                                            <h6><i class="fas fa-reply text-success"></i> Our Response:</h6>
                                            <div class="response-content">
                                                <p><?php echo nl2br(htmlspecialchars($inquiry['admin_response'])); ?></p>
                                                <div class="response-signature">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user-shield fa-sm"></i>
                                                        Response by: <?php echo htmlspecialchars($inquiry['admin_name'] ?? 'GreenLife Team'); ?> 
                                                        on <?php echo formatDate($inquiry['responded_at']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="pending-response">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Waiting for Response</strong><br>
                                                Our team is reviewing your inquiry and will respond within 24-48 hours. 
                                                Thank you for your patience!
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="inquiry-actions">
                                    <?php if (empty($inquiry['admin_response'])): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-hourglass-half"></i> 
                                            Response typically within 24-48 hours
                                        </small>
                                    <?php else: ?>
                                        <small class="text-success">
                                            <i class="fas fa-check"></i> 
                                            Inquiry resolved - response received
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Help Section -->
        <div class="card mt-4">
            <div class="card-header">
                <h4><i class="fas fa-question-circle"></i> Need More Help?</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="help-item">
                            <h6><i class="fas fa-phone text-primary"></i> Call Us</h6>
                            <p>+94 11 234 5678<br>
                            <small class="text-muted">Mon-Fri: 9 AM - 6 PM<br>Sat: 10 AM - 4 PM</small></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="help-item">
                            <h6><i class="fas fa-envelope text-primary"></i> Email Us</h6>
                            <p>info@greenlifewellness.lk<br>
                            <small class="text-muted">Response within 24 hours</small></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="help-item">
                            <h6><i class="fas fa-comments text-primary"></i> Live Chat</h6>
                            <p>Available during business hours<br>
                            <small class="text-muted">Instant support</small></p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 text-center">
                    <a href="contact_us.php" class="btn btn-primary me-2">
                        <i class="fas fa-paper-plane me-2"></i>Submit New Inquiry
                    </a>
                    <a href="appointment_booking.php" class="btn btn-outline me-2">
                        <i class="fas fa-calendar-plus me-2"></i>Book Appointment
                    </a>
                    <a href="client_dashboard.php" class="btn btn-outline">
                        <i class="fas fa-home me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Inquiry Card Styles */
.inquiries-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.inquiry-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.5rem;
    background: white;
    transition: all 0.3s ease;
    border-left: 4px solid var(--primary);
}

.inquiry-card:hover {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.inquiry-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
}

.inquiry-info h5 {
    margin-bottom: 0.5rem;
    color: var(--dark);
    font-weight: 600;
}

.inquiry-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.875rem;
}

.inquiry-content {
    margin-bottom: 1rem;
}

.inquiry-content h6 {
    color: var(--dark);
    margin-bottom: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.message-content {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    border-left: 3px solid #dee2e6;
    margin-bottom: 1.5rem;
}

.admin-response {
    border-top: 1px solid #e5e7eb;
    padding-top: 1.5rem;
}

.response-content {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    padding: 1.25rem;
    border-radius: 8px;
    border-left: 4px solid var(--success);
    position: relative;
}

.response-signature {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.5);
}

.pending-response {
    border-top: 1px solid #e5e7eb;
    padding-top: 1.5rem;
}

.inquiry-actions {
    display: flex;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid #f3f4f6;
}

/* Badge styling */
.badge {
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.badge-success {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.badge-warning {
    background-color: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
}

/* Help section */
.help-item {
    text-align: center;
    padding: 1rem;
}

.help-item h6 {
    margin-bottom: 0.75rem;
    font-size: 1rem;
}

.help-item p {
    margin-bottom: 0;
    line-height: 1.5;
}

/* Stats grid for smaller cards */
.stats-grid .stat-card .number a {
    color: inherit;
    text-decoration: none;
}

.stats-grid .stat-card .number a:hover {
    text-decoration: none;
    opacity: 0.8;
}

/* Responsive design */
@media (max-width: 768px) {
    .inquiry-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .inquiry-meta {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .inquiry-card {
        padding: 1rem;
    }
    
    .response-content {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
}

@media (max-width: 576px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>