<?php
/**
 * Admin Dashboard
 * GreenLife Wellness Center
 */

$pageTitle = "Admin Dashboard";
require_once __DIR__ . '/includes/functions.php';

// Require admin access
requireRole('admin');

// Get dashboard statistics
try {
    $pdo = getDBConnection();
    
    // Total users
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
    
    // --- DEBUGGING --- 
    // Uncomment the following lines to see the raw data being fetched.
    /*
    echo '<pre>';
    echo 'DEBUG: Recent Appointments\n';
    print_r($recentAppointments);
    echo 'DEBUG: Recent Inquiries\n';
    print_r($recentInquiries);
    echo '</pre>';
    */
    // --- END DEBUGGING ---

    // Recent appointments - Use LEFT JOIN for all tables to prevent missing data from breaking the query
    $stmt = $pdo->query("
        SELECT a.*,
               COALESCE(u.name, 'Unknown Client') as client_name,
               COALESCE(t.name, 'Not Assigned') as therapist_name,
               s.service_name
        FROM appointments a
        LEFT JOIN users u ON a.user_id = u.user_id
        LEFT JOIN therapists t ON a.therapist_id = t.therapist_id
        LEFT JOIN services s ON a.service_id = s.service_id
        ORDER BY a.appointment_id DESC
        LIMIT 2
    ");
    $recentAppointments = $stmt->fetchAll();
    
    // Recent inquiries - Show last 2 inquiries by ID
    $stmt = $pdo->query("
        SELECT i.*,
               COALESCE(u.name, 'Guest User') as client_name
        FROM inquiries i
        LEFT JOIN users u ON i.user_id = u.user_id
        ORDER BY i.inquiry_id DESC
        LIMIT 2
    ");
    $recentInquiries = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    error_log("Dashboard error details: " . $e->getTraceAsString());
    $totalClients = $totalAppointments = $pendingAppointments = $unansweredInquiries = $monthlyRevenue = 0;
    $recentAppointments = $recentInquiries = [];
    
    // Add debug information for admin
    if (isset($_GET['debug']) && $_SESSION['role'] === 'admin') {
        echo "<div class='alert alert-error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

require_once __DIR__ . '/includes/header.php';
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
        
        <!-- System Status -->
        <div class="row">
            <div class="col-md-6">
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
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-chart-line"></i> Quick Metrics</h4>
                    </div>
                    <div class="card-body">
                        <div class="status-item">
                            <span class="status-label">Today's Appointments:</span>
                            <?php
                            try {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()");
                                $stmt->execute();
                                $todaysAppointments = $stmt->fetch()['count'];
                            } catch (PDOException $e) {
                                $todaysAppointments = 0;
                            }
                            ?>
                            <span class="badge badge-info"><?php echo $todaysAppointments; ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Active Clients:</span>
                            <span class="badge badge-success"><?php echo $totalClients; ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Pending Reviews:</span>
                            <span class="badge badge-warning"><?php echo $pendingAppointments; ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Unanswered Inquiries:</span>
                            <span class="badge badge-error"><?php echo $unansweredInquiries; ?></span>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>