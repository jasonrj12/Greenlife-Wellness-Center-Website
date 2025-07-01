<?php
/**
 * Therapist Dashboard
 * GreenLife Wellness Center
 */

$pageTitle = "Therapist Dashboard";
require_once 'includes/functions.php';

// Require therapist access
requireRole('therapist');

// Get therapist ID from user session with better error handling
$therapistId = getTherapistIdByUserId($_SESSION['user_id']);

if (!$therapistId) {
    // Try to create a therapist record if it doesn't exist
    try {
        $pdo = getDBConnection();
        
        // Check if user exists and has therapist role
        $stmt = $pdo->prepare("SELECT user_id, name, role FROM users WHERE user_id = ? AND role = 'therapist'");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Create therapist record
            $stmt = $pdo->prepare("INSERT INTO therapists (user_id, name, specialization, experience_years, bio, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $result = $stmt->execute([$user['user_id'], $user['name'], 'General Practice', 0, '']);
            
            if ($result) {
                $therapistId = $pdo->lastInsertId();
                error_log("Created therapist record for user ID: " . $_SESSION['user_id']);
            } else {
                error_log("Failed to create therapist record for user ID: " . $_SESSION['user_id']);
                header('Location: login.php?error=therapist_setup_failed');
                exit;
            }
        } else {
            error_log("User not found or not a therapist: " . $_SESSION['user_id']);
            header('Location: login.php?error=invalid_user');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error creating therapist record: " . $e->getMessage());
        header('Location: login.php?error=database_error');
        exit;
    }
}

// Get dashboard statistics
try {
    $pdo = getDBConnection();
    
    // Get therapist info with fallback
    $stmt = $pdo->prepare("SELECT * FROM therapists WHERE therapist_id = ?");
    $stmt->execute([$therapistId]);
    $therapistInfo = $stmt->fetch();
    
    // If no therapist info found, create basic info from user data
    if (!$therapistInfo) {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        $therapistInfo = [
            'name' => $user['name'] ?? $_SESSION['name'] ?? 'Therapist',
            'specialization' => 'General Practice',
            'experience_years' => 0,
            'bio' => '',
            'certification' => ''
        ];
    }
    
    // Total appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE therapist_id = ?");
    $stmt->execute([$therapistId]);
    $totalAppointments = $stmt->fetch()['total'];
    
    // Today's appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE therapist_id = ? AND DATE(appointment_date) = CURDATE()");
    $stmt->execute([$therapistId]);
    $todayAppointments = $stmt->fetch()['total'];
    
    // Pending appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE therapist_id = ? AND status = 'pending'");
    $stmt->execute([$therapistId]);
    $pendingAppointments = $stmt->fetch()['total'];
    
    // This month's completed sessions
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM appointments 
        WHERE therapist_id = ? 
        AND status = 'completed' 
        AND MONTH(appointment_date) = MONTH(CURDATE()) 
        AND YEAR(appointment_date) = YEAR(CURDATE())
    ");
    $stmt->execute([$therapistId]);
    $monthlyCompleted = $stmt->fetch()['total'];
    
    // Today's appointments with details
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as client_name, u.phone_number as client_phone, s.service_name
        FROM appointments a
        JOIN users u ON a.user_id = u.user_id
        JOIN services s ON a.service_id = s.service_id
        WHERE a.therapist_id = ? 
        AND DATE(a.appointment_date) = CURDATE()
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute([$therapistId]);
    $todaySchedule = $stmt->fetchAll();
    
    // Upcoming appointments (next 7 days)
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as client_name, u.phone_number as client_phone, s.service_name
        FROM appointments a
        JOIN users u ON a.user_id = u.user_id
        JOIN services s ON a.service_id = s.service_id
        WHERE a.therapist_id = ? 
        AND a.appointment_date BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL 7 DAY
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 10
    ");
    $stmt->execute([$therapistId]);
    $upcomingAppointments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Therapist dashboard error: " . $e->getMessage());
    // Provide fallback data
    $therapistInfo = [
        'name' => $_SESSION['name'] ?? 'Therapist',
        'specialization' => 'General Practice',
        'experience_years' => 0,
        'bio' => '',
        'certification' => ''
    ];
    $totalAppointments = $todayAppointments = $pendingAppointments = $monthlyCompleted = 0;
    $todaySchedule = $upcomingAppointments = [];
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-user-md"></i> Therapist Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($therapistInfo['name'] ?? $_SESSION['name']); ?>! 
               <?php if ($therapistInfo): ?>
                   <span class="badge badge-info"><?php echo htmlspecialchars($therapistInfo['specialization']); ?></span>
               <?php endif; ?>
            </p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <span class="number"><?php echo $todayAppointments; ?></span>
                <span class="label">Today's Appointments</span>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <span class="number"><?php echo count($upcomingAppointments); // Fix: Display the count instead of the array ?></span>
                <span class="label">Upcoming Appointments</span>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-user-clock"></i>
                </div>
                <span class="number"><?php echo $pendingAppointments; ?></span>
                <span class="label">Pending Confirmation</span>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <span class="number"><?php echo $monthlyCompleted; ?></span>
                <span class="label">Completed Sessions</span>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-lightning-bolt"></i> Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-3">
                        <a href="view_appointments.php" class="btn btn-primary btn-lg" style="width: 100%;">
                            <i class="fas fa-calendar"></i><br>View All Appointments
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="view_appointments.php?status=pending" class="btn btn-outline btn-lg" style="width: 100%;">
                            <i class="fas fa-check"></i><br>Approve Appointments
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="update_notes.php" class="btn btn-outline btn-lg" style="width: 100%;">
                            <i class="fas fa-edit"></i><br>Update Session Notes
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="#schedule" class="btn btn-outline btn-lg" style="width: 100%;">
                            <i class="fas fa-calendar-alt"></i><br>Today's Schedule
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Today's Schedule -->
            <div class="col-8">
                <div class="card">
                    <div class="card-header">
                        <h3 id="schedule"><i class="fas fa-calendar-day"></i> Today's Schedule - <?php echo date('F j, Y'); ?></h3>
                        <a href="view_appointments.php?date=<?php echo date('Y-m-d'); ?>" class="btn btn-primary">View Details</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($todaySchedule)): ?>
                            <div class="text-center p-5">
                                <i class="fas fa-calendar-times" style="font-size: 4rem; color: var(--gray-400); margin-bottom: 1rem;"></i>
                                <h4>No Sessions Today</h4>
                                <p class="text-muted">Enjoy your day off! Check your upcoming appointments below.</p>
                            </div>
                        <?php else: ?>
                            <div class="schedule-list">
                                <?php foreach ($todaySchedule as $appointment): ?>
                                    <div class="schedule-item">
                                        <div class="schedule-time">
                                            <strong><?php echo formatTime($appointment['appointment_time']); ?></strong>
                                        </div>
                                        <div class="schedule-details">
                                            <h5><?php echo htmlspecialchars($appointment['client_name']); ?></h5>
                                            <p><?php echo htmlspecialchars($appointment['service_name']); ?></p>
                                            <?php if ($appointment['client_phone']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['client_phone']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="schedule-status">
                                            <?php
                                            $statusClass = '';
                                            switch ($appointment['status']) {
                                                case 'pending': $statusClass = 'badge-warning'; break;
                                                case 'confirmed': $statusClass = 'badge-info'; break;
                                                case 'completed': $statusClass = 'badge-success'; break;
                                                case 'canceled': $statusClass = 'badge-error'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                        <div class="schedule-actions">
                                            <?php if ($appointment['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-primary" onclick="updateStatus(<?php echo $appointment['appointment_id']; ?>, 'confirmed')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php elseif ($appointment['status'] === 'confirmed'): ?>
                                                <button class="btn btn-sm btn-success" onclick="updateStatus(<?php echo $appointment['appointment_id']; ?>, 'completed')">
                                                    <i class="fas fa-check-double"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="update_notes.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-outline">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-4">
                <!-- Therapist Profile -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-user-circle"></i> My Profile</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($therapistInfo): ?>
                            <p><strong>Name:</strong><br><?php echo htmlspecialchars($therapistInfo['name']); ?></p>
                            <p><strong>Specialization:</strong><br><?php echo htmlspecialchars($therapistInfo['specialization']); ?></p>
                            <p><strong>Experience:</strong><br><?php echo htmlspecialchars($therapistInfo['experience_years']); ?> years</p>
                            <?php if ($therapistInfo && isset($therapistInfo['certification']) && $therapistInfo['certification']): ?>
                                <p><strong>Certification:</strong><br><?php echo htmlspecialchars($therapistInfo['certification']); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted">Profile information not available</p>
                        <?php endif; ?>
                        <hr>
                        <button class="btn btn-outline" onclick="editProfile()" style="width: 100%;">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                    </div>
                </div>
                
                <!-- Upcoming Appointments -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-calendar-plus"></i> Upcoming Sessions</h4>
                        <a href="view_appointments.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingAppointments)): ?>
                            <p class="text-center text-muted">No upcoming sessions</p>
                        <?php else: ?>
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <div class="upcoming-item">
                                    <div class="upcoming-date">
                                        <?php echo formatDate($appointment['appointment_date']); ?>
                                        <small><?php echo formatTime($appointment['appointment_time']); ?></small>
                                    </div>
                                    <div class="upcoming-details">
                                        <strong><?php echo htmlspecialchars($appointment['client_name']); ?></strong>
                                        <br><small><?php echo htmlspecialchars($appointment['service_name']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-chart-pie"></i> This Week</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $stmt = $pdo->prepare("
                                SELECT COUNT(*) as count 
                                FROM appointments 
                                WHERE therapist_id = ? 
                                AND WEEK(appointment_date) = WEEK(CURDATE())
                                AND YEAR(appointment_date) = YEAR(CURDATE())
                            ");
                            $stmt->execute([$therapistId]);
                            $weeklyTotal = $stmt->fetch()['count'];
                            
                            $stmt = $pdo->prepare("
                                SELECT COUNT(*) as count 
                                FROM appointments 
                                WHERE therapist_id = ? 
                                AND status = 'completed'
                                AND WEEK(appointment_date) = WEEK(CURDATE())
                                AND YEAR(appointment_date) = YEAR(CURDATE())
                            ");
                            $stmt->execute([$therapistId]);
                            $weeklyCompleted = $stmt->fetch()['count'];
                        } catch (PDOException $e) {
                            $weeklyTotal = $weeklyCompleted = 0;
                        }
                        ?>
                        <div class="stat-row">
                            <span>Total Sessions:</span>
                            <span class="badge badge-info"><?php echo $weeklyTotal; ?></span>
                        </div>
                        <div class="stat-row">
                            <span>Completed:</span>
                            <span class="badge badge-success"><?php echo $weeklyCompleted; ?></span>
                        </div>
                        <div class="stat-row">
                            <span>Completion Rate:</span>
                            <span class="badge badge-primary">
                                <?php echo $weeklyTotal > 0 ? round(($weeklyCompleted / $weeklyTotal) * 100) : 0; ?>%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateStatus(appointmentId, newStatus) {
    if (confirm(`Are you sure you want to mark this appointment as ${newStatus}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'view_appointments.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="appointment_id" value="${appointmentId}">
            <input type="hidden" name="status" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function editProfile() {
    // Redirect to the edit profile page
    window.location.href = 'edit_profile.php';
}
</script>

<style>
.schedule-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.schedule-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius);
    background: white;
}

.schedule-time {
    flex: 0 0 80px;
    text-align: center;
    color: var(--primary-color);
}

.schedule-details {
    flex: 1;
    margin-left: 1rem;
}

.schedule-details h5 {
    margin-bottom: 0.25rem;
    color: var(--gray-800);
}

.schedule-details p {
    margin-bottom: 0.25rem;
    color: var(--gray-600);
}

.schedule-status {
    flex: 0 0 100px;
    text-align: center;
}

.schedule-actions {
    flex: 0 0 80px;
    display: flex;
    gap: 0.25rem;
    justify-content: flex-end;
}

.upcoming-item {
    display: flex;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--gray-100);
}

.upcoming-item:last-child {
    border-bottom: none;
}

.upcoming-date {
    flex: 0 0 80px;
    font-size: 0.875rem;
    color: var(--primary-color);
    text-align: center;
}

.upcoming-details {
    flex: 1;
    margin-left: 1rem;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.stat-row:last-child {
    margin-bottom: 0;
}
</style>

<?php require_once 'includes/footer.php'; ?>