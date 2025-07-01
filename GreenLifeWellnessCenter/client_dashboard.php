<?php
/**
 * Enhanced Client Dashboard
 * GreenLife Wellness Center
 */

$pageTitle = "My Dashboard";
require_once 'includes/functions.php';

// Require client login
requireLogin();
if ($_SESSION['role'] !== 'client') {
    header('Location: login.php?error=access_denied');
    exit();
}

// Get user information
$user = getUserById($_SESSION['user_id']);
$appointments = getUserAppointments($_SESSION['user_id']);

// Get user statistics
try {
    $pdo = getDBConnection();
    
    // Count appointments by status
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as canceled
        FROM appointments 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
    
    // Get next appointment
    $stmt = $pdo->prepare("
        SELECT a.*, s.service_name, t.name as therapist_name, t.specialization
        FROM appointments a
        JOIN services s ON a.service_id = s.service_id
        JOIN therapists t ON a.therapist_id = t.therapist_id
        WHERE a.user_id = ? AND a.appointment_date >= CURDATE() AND a.status IN ('pending', 'confirmed')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $nextAppointment = $stmt->fetch();
    
    // Get recent notifications
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $stats = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'canceled' => 0];
    $nextAppointment = null;
    $notifications = [];
}

require_once 'includes/header.php';
?>

<style>
    /* Fix for the stat card icons */
    .stat-card {
        /* ...existing styles... */
    }
    
    .stat-card .icon {
        width: 60px;
        height: 60px;
        line-height: 60px;
        font-size: 1.5rem;
        background-color: #28a745;
        color: white;
        border-radius: 50%;
        margin: 0 auto 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Remove any previous z-index styling for these specific icons */
    .bg-success.rounded-circle {
        position: relative;
    }
    
    .fas, .fa {
        position: relative;
    }
</style>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-tachometer-alt me-2"></i>Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
            <p>Manage your appointments and track your wellness journey</p>
        </div>
        
        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <span class="number"><?php echo $stats['total']; ?></span>
                <span class="label">Total Appointments</span>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
                <span class="number"><?php echo $stats['pending']; ?></span>
                <span class="label">Pending Confirmation</span>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <span class="number"><?php echo $stats['confirmed']; ?></span>
                <span class="label">Confirmed</span>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <span class="number"><?php echo $stats['completed']; ?></span>
                <span class="label">Completed Sessions</span>
            </div>
        </div>
        
        <div class="row">
            <!-- Next Appointment -->
            <div class="col-lg-8">
                <?php if ($nextAppointment): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4><i class="fas fa-calendar-check me-2"></i>Next Appointment</h4>
                        </div>
                        <div class="card-body">
                            <div class="appointment-card">
                                <div class="appointment-header">
                                    <div class="appointment-datetime">
                                        <div class="date">
                                            <i class="fas fa-calendar me-2"></i>
                                            <strong><?php echo formatDate($nextAppointment['appointment_date']); ?></strong>
                                        </div>
                                        <div class="time">
                                            <i class="fas fa-clock me-2"></i>
                                            <strong><?php echo formatTime($nextAppointment['appointment_time']); ?></strong>
                                        </div>
                                    </div>
                                    <div class="appointment-status">
                                        <?php if ($nextAppointment['status'] === 'pending'): ?>
                                            <span class="badge badge-warning badge-lg">Pending Confirmation</span>
                                        <?php elseif ($nextAppointment['status'] === 'confirmed'): ?>
                                            <span class="badge badge-success badge-lg">Confirmed</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="appointment-content">
                                    <div class="service-info">
                                        <h5><i class="fas fa-spa me-2"></i>Service</h5>
                                        <p><?php echo htmlspecialchars($nextAppointment['service_name']); ?></p>
                                    </div>
                                    
                                    <div class="therapist-info">
                                        <h5><i class="fas fa-user-md me-2"></i>Therapist</h5>
                                        <p><?php echo htmlspecialchars($nextAppointment['therapist_name']); ?></p>
                                        <small class="text-muted"><?php echo htmlspecialchars($nextAppointment['specialization']); ?></small>
                                    </div>
                                </div>
                                
                                <?php if ($nextAppointment['client_notes']): ?>
                                    <div class="appointment-notes">
                                        <h6><i class="fas fa-sticky-note me-2"></i>Your Notes</h6>
                                        <p><?php echo htmlspecialchars($nextAppointment['client_notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="appointment-actions">
                                    <a href="appointment_booking.php" class="btn btn-success">
                                        <i class="fas fa-plus me-2"></i>Book Another
                                    </a>
                                    <a href="contact_us.php" class="btn btn-outline">
                                        <i class="fas fa-phone me-2"></i>Contact Us
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4><i class="fas fa-calendar-plus me-2"></i>No Upcoming Appointments</h4>
                        </div>
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-plus" style="font-size: 4rem; color: var(--gray-300); margin-bottom: 1rem;"></i>
                            <h5>Ready to book your next wellness session?</h5>
                            <p class="text-muted">Choose from our range of therapeutic services and book with your preferred therapist.</p>
                            <a href="appointment_booking.php" class="btn btn-success btn-lg">
                                <i class="fas fa-calendar-check me-2"></i>Book Appointment
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Recent Appointments -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-history me-2"></i>Recent Appointments</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <p class="text-center text-muted">No appointments found. Book your first session today!</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Service</th>
                                            <th>Therapist</th>
                                            <th>Status</th>
                                            <th>Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($appointments, 0, 5) as $appointment): ?>
                                            <tr>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo formatDate($appointment['appointment_date']); ?></strong><br>
                                                        <?php echo formatTime($appointment['appointment_time']); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo htmlspecialchars($appointment['therapist_name']); ?></strong><br>
                                                        <span class="text-muted"><?php echo htmlspecialchars($appointment['specialization']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'pending' => 'badge-warning',
                                                        'confirmed' => 'badge-info',
                                                        'completed' => 'badge-success',
                                                        'canceled' => 'badge-error'
                                                    ];
                                                    ?>
                                                    <span class="badge <?php echo $statusClass[$appointment['status']] ?? 'badge-outline'; ?>">
                                                        <?php echo ucfirst($appointment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>Rs. <?php echo number_format($appointment['price'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if (count($appointments) > 5): ?>
                                <div class="text-center mt-3">
                                    <button class="btn btn-outline" onclick="showAllAppointments()">
                                        <i class="fas fa-list me-2"></i>View All Appointments
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-lightning me-2"></i>Quick Actions</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="appointment_booking.php" class="btn btn-success">
                                <i class="fas fa-calendar-plus me-2"></i>Book New Appointment
                            </a>
                            <a href="contact_us.php" class="btn btn-outline">
                                <i class="fas fa-question-circle me-2"></i>Submit Inquiry
                            </a>
                            <a href="#profile" class="btn btn-outline" onclick="showProfileModal()">
                                <i class="fas fa-user-edit me-2"></i>Update Profile
                            </a>
                            <a href="client_inquiries.php" class="btn btn-outline">
                                <i class="fas fa-envelope me-2"></i>My Inquiries
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-bell me-2"></i>Recent Notifications</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <p class="text-center text-muted small">No notifications</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item mb-3 p-2 rounded <?php echo $notification['is_read'] ? 'bg-light' : 'bg-white'; ?>" 
                                     onclick="markNotificationRead(<?php echo $notification['notification_id']; ?>)">
                                    <div class="d-flex justify-content-between">
                                        <strong class="small"><?php echo htmlspecialchars($notification['title']); ?></strong>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge badge-info" style="font-size: 0.6rem;">New</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="small text-muted mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <small class="text-muted"><?php echo formatDate($notification['created_at']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Wellness Tips -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-lightbulb me-2"></i>Wellness Tips</h4>
                    </div>
                    <div class="card-body">
                        <div class="wellness-tip">
                            <h6><i class="fas fa-leaf me-2 text-success"></i>Daily Mindfulness</h6>
                            <p class="small">Take 5-10 minutes each day for mindful breathing to reduce stress and improve focus.</p>
                        </div>
                        <hr>
                        <div class="wellness-tip">
                            <h6><i class="fas fa-dumbbell me-2 text-success"></i>Stay Active</h6>
                            <p class="small">Regular movement, even just a 10-minute walk, can boost your mood and energy levels.</p>
                        </div>
                        <hr>
                        <div class="wellness-tip">
                            <h6><i class="fas fa-moon me-2 text-success"></i>Quality Sleep</h6>
                            <p class="small">Aim for 7-9 hours of quality sleep each night for optimal physical and mental health.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Profile Update Modal -->
<div id="profileModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Update Profile</h4>
            <button class="modal-close" onclick="closeProfileModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="profileForm" method="POST" action="update_profile.php">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="mb-3">
                    <label for="profile_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="profile_name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="profile_phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="profile_phone" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="profile_address" class="form-label">Address</label>
                    <textarea class="form-control" id="profile_address" name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Update Profile
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeProfileModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showProfileModal() {
    document.getElementById('profileModal').style.display = 'flex';
}

function closeProfileModal() {
    document.getElementById('profileModal').style.display = 'none';
}

function showAllAppointments() {
    // Toggle showing all appointments in the table
    const tbody = document.querySelector('.table tbody');
    const hiddenRows = tbody.querySelectorAll('tr:nth-child(n+6)');
    
    hiddenRows.forEach(row => {
        row.style.display = row.style.display === 'none' ? '' : 'none';
    });
    
    const button = event.target.closest('button');
    if (hiddenRows[0].style.display === 'none') {
        button.innerHTML = '<i class="fas fa-list me-2"></i>View All Appointments';
    } else {
        button.innerHTML = '<i class="fas fa-eye-slash me-2"></i>Show Less';
    }
}

async function markNotificationRead(notificationId) {
    try {
        const formData = new FormData();
        formData.append('action', 'mark_notification_read');
        formData.append('notification_id', notificationId);
        
        await fetch('api/endpoints.php', {
            method: 'POST',
            body: formData
        });
        
        // Update UI
        const notificationElement = event.target.closest('.notification-item');
        notificationElement.classList.remove('bg-white');
        notificationElement.classList.add('bg-light');
        
        const newBadge = notificationElement.querySelector('.badge');
        if (newBadge) {
            newBadge.remove();
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

// Close modal when clicking outside
document.getElementById('profileModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeProfileModal();
    }
});

// Auto-refresh notifications every 30 seconds
setInterval(async function() {
    try {
        const response = await fetch('api/endpoints.php?action=get_notifications');
        const data = await response.json();
        
        if (data.success && data.notifications.length > 0) {
            // Update notification indicator if available
            const indicator = document.getElementById('notificationIndicator');
            const unreadCount = data.notifications.filter(n => !n.is_read).length;
            
            if (indicator && unreadCount > 0) {
                indicator.textContent = unreadCount;
                indicator.style.display = 'block';
            }
        }
    } catch (error) {
        console.error('Error refreshing notifications:', error);
    }
}, 30000);
</script>

<?php require_once 'includes/footer.php'; ?>