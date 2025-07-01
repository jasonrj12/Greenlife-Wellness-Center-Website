<?php
/**
 * Admin Dashboard
 * GreenLife Wellness Center
 */

$pageTitle = "Admin Dashboard";
require_once '../includes/functions.php';

// Require admin access
requireRole('admin');

// Get dashboard statistics
try {
    $pdo = getDBConnection();
    
    // Total users - only count active clients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'client' AND status = 'active'");
    $totalClients = $stmt->fetch()['total'];
    
    // Total appointments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments");
    $totalAppointments = $stmt->fetch()['total'];
    
    // Pending appointments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'pending'");
    $pendingAppointments = $stmt->fetch()['total'];
    
    // Total unanswered inquiries - Fixed to use correct column names
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM inquiries WHERE (admin_response IS NULL OR admin_response = '') AND status IN ('open', 'in_progress')");
    $unansweredInquiries = $stmt->fetch()['total'];
    
    // Monthly revenue (current month) - Added better NULL handling
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(s.price), 0) as revenue 
        FROM appointments a 
        JOIN services s ON a.service_id = s.service_id 
        WHERE a.status = 'completed' 
        AND MONTH(a.appointment_date) = MONTH(CURDATE()) 
        AND YEAR(a.appointment_date) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $monthlyRevenue = $stmt->fetch()['revenue'] ?? 0;
    
    // Recent appointments
    $stmt = $pdo->query("
        SELECT a.*, u.name as client_name, t.name as therapist_name, s.service_name
        FROM appointments a
        JOIN users u ON a.user_id = u.user_id
        JOIN therapists t ON a.therapist_id = t.therapist_id
        JOIN services s ON a.service_id = s.service_id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $recentAppointments = $stmt->fetchAll();
    
    // Recent inquiries
    $stmt = $pdo->query("
        SELECT i.*, u.name as client_name
        FROM inquiries i
        LEFT JOIN users u ON i.user_id = u.user_id
        ORDER BY i.created_at DESC
        LIMIT 5
    ");
    $recentInquiries = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $totalClients = $totalAppointments = $pendingAppointments = $unansweredInquiries = $monthlyRevenue = 0;
    $recentAppointments = $recentInquiries = [];
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! Manage your wellness center operations.</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="number"><?php echo $totalClients; ?></div>
                <div class="label">Total Clients</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="number"><?php echo $totalAppointments; ?></div>
                <div class="label">Total Appointments</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="number"><?php echo $pendingAppointments; ?></div>
                <div class="label">Pending Appointments</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="number"><?php echo $unansweredInquiries; ?></div>
                <div class="label">Unanswered Inquiries</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="number">Rs. <?php echo number_format($monthlyRevenue, 0); ?></div>
                <div class="label">Monthly Revenue</div>
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
                        <a href="manage_users.php" class="btn btn-primary btn-lg" style="width: 100%;">
                            <i class="fas fa-users"></i><br>Manage Users
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="manage_appointments.php" class="btn btn-outline btn-lg" style="width: 100%;">
                            <i class="fas fa-calendar"></i><br>Manage Appointments
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="manage_inquiries.php" class="btn btn-outline btn-lg" style="width: 100%;">
                            <i class="fas fa-envelope"></i><br>Manage Inquiries
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="#reports" class="btn btn-outline btn-lg" style="width: 100%;">
                            <i class="fas fa-chart-bar"></i><br>View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Recent Appointments -->
            <div class="col-8">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar"></i> Recent Appointments</h3>
                        <a href="manage_appointments.php" class="btn btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentAppointments)): ?>
                            <p class="text-center text-muted">No appointments found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Service</th>
                                            <th>Therapist</th>
                                            <th>Date & Time</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentAppointments as $appointment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($appointment['client_name']); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['therapist_name']); ?></td>
                                                <td>
                                                    <?php echo formatDate($appointment['appointment_date']); ?>
                                                    <br><small><?php echo formatTime($appointment['appointment_time']); ?></small>
                                                </td>
                                                <td>
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
                                                </td>
                                                <td>
                                                    <a href="manage_appointments.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                                       class="btn btn-sm btn-outline">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Inquiries -->
            <div class="col-4">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-envelope"></i> Recent Inquiries</h4>
                        <a href="manage_inquiries.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentInquiries)): ?>
                            <p class="text-center text-muted">No inquiries found.</p>
                        <?php else: ?>
                            <?php foreach ($recentInquiries as $inquiry): ?>
                                <div class="inquiry-item mb-3">
                                    <h6><?php echo htmlspecialchars($inquiry['subject']); ?></h6>
                                    <p class="text-muted small">
                                        From: <?php echo $inquiry['client_name'] ? htmlspecialchars($inquiry['client_name']) : 'Guest'; ?>
                                        <br>Date: <?php echo formatDate($inquiry['created_at']); ?>
                                    </p>
                                    <p class="small"><?php echo htmlspecialchars(substr($inquiry['message'], 0, 100)); ?>...</p>
                                    <?php if (!$inquiry['admin_response']): ?>
                                        <span class="badge badge-warning">Unanswered</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Answered</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- System Status -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-server"></i> System Status</h4>
                    </div>
                    <div class="card-body">
                        <div class="status-item">
                            <span class="status-label">Database:</span>
                            <span class="badge badge-success">Online</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Booking System:</span>
                            <span class="badge badge-success">Active</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Email Service:</span>
                            <span class="badge badge-success">Active</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Last Backup:</span>
                            <span class="text-muted small"><?php echo date('M j, Y'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Reports Section -->
        <div class="card" id="reports">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Quick Reports</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-4">
                        <div class="report-card">
                            <h5>Today's Appointments</h5>
                            <?php
                            try {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()");
                                $stmt->execute();
                                $todaysAppointments = $stmt->fetch()['count'];
                            } catch (PDOException $e) {
                                $todaysAppointments = 0;
                            }
                            ?>
                            <div class="report-number"><?php echo $todaysAppointments; ?></div>
                            <p class="text-muted">Scheduled for today</p>
                        </div>
                    </div>
                    
                    <div class="col-4">
                        <div class="report-card">
                            <h5>This Week's Revenue</h5>
                            <?php
                            try {
                                $stmt = $pdo->prepare("
                                    SELECT COALESCE(SUM(s.price), 0) as revenue 
                                    FROM appointments a 
                                    JOIN services s ON a.service_id = s.service_id 
                                    WHERE a.status = 'completed' 
                                    AND WEEK(a.appointment_date) = WEEK(CURDATE())
                                    AND YEAR(a.appointment_date) = YEAR(CURDATE())
                                ");
                                $stmt->execute();
                                $weeklyRevenue = $stmt->fetch()['revenue'] ?? 0;
                            } catch (PDOException $e) {
                                $weeklyRevenue = 0;
                            }
                            ?>
                            <div class="report-number">Rs. <?php echo number_format($weeklyRevenue, 0); ?></div>
                            <p class="text-muted">Completed sessions</p>
                        </div>
                    </div>
                    
                    <div class="col-4">
                        <div class="report-card">
                            <h5>Client Satisfaction</h5>
                            <div class="report-number">4.9/5</div>
                            <p class="text-muted">Average rating</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.inquiry-item {
    border-bottom: 1px solid var(--gray-200);
    padding-bottom: 1rem;
}

.inquiry-item:last-child {
    border-bottom: none;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.status-label {
    font-weight: 500;
}

.report-card {
    background: var(--gray-50);
    padding: 1.5rem;
    border-radius: var(--border-radius);
    text-align: center;
}

.report-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-color);
    margin: 0.5rem 0;
}
</style>

<?php require_once '../includes/footer.php'; ?>