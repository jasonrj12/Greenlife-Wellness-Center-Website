<?php
/**
 * Admin Appointment Management
 * GreenLife Wellness Center
 */

$pageTitle = "Manage Appointments";
require_once 'includes/functions.php';

// Require admin access
requireRole('admin');

$error = '';
$success = '';

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appointmentId = $_POST['appointment_id'] ?? '';
    
    // Debug logging
    error_log("POST Request Debug - Action: " . $action . ", Appointment ID: " . $appointmentId);
    error_log("Full POST data: " . print_r($_POST, true));
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
        error_log("CSRF token verification failed");
    } else {
        error_log("CSRF token verified successfully");
        switch ($action) {
            case 'add':
                $userId = $_POST['user_id'] ?? '';
                $therapistId = $_POST['therapist_id'] ?? '';
                $serviceId = $_POST['service_id'] ?? '';
                $appointmentDate = $_POST['appointment_date'] ?? '';
                $appointmentTime = $_POST['appointment_time'] ?? '';
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                if ($userId && $therapistId && $serviceId && $appointmentDate && $appointmentTime) {
                    $result = bookAppointment($userId, $therapistId, $serviceId, $appointmentDate, $appointmentTime);
                    if ($result['success']) {
                        // Add notes if provided
                        if (!empty($notes)) {
                            try {
                                $pdo = getDBConnection();
                                $stmt = $pdo->prepare("UPDATE appointments SET notes = ? WHERE user_id = ? AND appointment_date = ? AND appointment_time = ? ORDER BY appointment_id DESC LIMIT 1");
                                $result = $stmt->execute([$notes, $userId, $appointmentDate, $appointmentTime]);
                                
                                if (!$result) {
                                    error_log("Failed to add notes to appointment");
                                }
                            } catch (PDOException $e) {
                                error_log("Error adding notes: " . $e->getMessage());
                                // Continue execution even if notes fail to save
                            }
                        }
                        $success = $result['message'];
                        logAdminAction($_SESSION['user_id'], "Created appointment for user ID $userId");
                    } else {
                        $error = $result['message'];
                    }
                } else {
                    $error = 'Please fill all required fields';
                }
                break;
                
            case 'update_status':
                $newStatus = $_POST['status'] ?? '';
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                error_log("Update Status Debug - Status: " . $newStatus . ", Notes: " . $notes . ", Appointment ID: " . $appointmentId);
                
                if ($appointmentId && in_array($newStatus, ['pending', 'confirmed', 'completed', 'canceled'])) {
                    try {
                        $pdo = getDBConnection();
                        
                        // First check if appointment exists
                        $checkStmt = $pdo->prepare("SELECT appointment_id, status FROM appointments WHERE appointment_id = ?");
                        $checkStmt->execute([$appointmentId]);
                        $existingAppointment = $checkStmt->fetch();
                        
                        error_log("Appointment exists check: " . ($existingAppointment ? "Found" : "Not found"));
                        
                        if (!$existingAppointment) {
                            $error = 'Appointment not found';
                            error_log("Error: Appointment ID $appointmentId not found in database");
                            break;
                        }
                        
                        // Build update query dynamically
                        $updateFields = ['status = ?'];
                        $params = [$newStatus];
                        
                        // Only update admin_notes if notes are provided
                        if (!empty($notes)) {
                            $updateFields[] = 'admin_notes = ?';
                            $params[] = $notes;
                        }
                        
                        $updateQuery = "UPDATE appointments SET " . implode(', ', $updateFields) . " WHERE appointment_id = ?";
                        $params[] = $appointmentId;
                        
                        error_log("SQL Query: " . $updateQuery);
                        error_log("SQL Params: " . print_r($params, true));
                        
                        $stmt = $pdo->prepare($updateQuery);
                        $result = $stmt->execute($params);
                        
                        error_log("SQL execution result: " . ($result ? "Success" : "Failed"));
                        error_log("Rows affected: " . $stmt->rowCount());
                        
                        if ($result) {
                            $success = 'Appointment updated successfully';
                            logAdminAction($_SESSION['user_id'], "Updated appointment ID $appointmentId status to $newStatus");
                            
                            // Create notification for client if status changed
                            if ($newStatus !== $existingAppointment['status']) {
                                $userStmt = $pdo->prepare("SELECT user_id FROM appointments WHERE appointment_id = ?");
                                $userStmt->execute([$appointmentId]);
                                $userResult = $userStmt->fetch();
                                
                                if ($userResult) {
                                    $statusMessages = [
                                        'confirmed' => 'Your appointment has been confirmed',
                                        'completed' => 'Your appointment has been marked as completed',
                                        'canceled' => 'Your appointment has been canceled'
                                    ];
                                    
                                    if (isset($statusMessages[$newStatus])) {
                                        createNotification($userResult['user_id'], 'Appointment Update', $statusMessages[$newStatus], 'appointment');
                                        error_log("Notification created for user: " . $userResult['user_id']);
                                    }
                                }
                            }
                        } else {
                            $error = 'Failed to update appointment';
                            error_log("Failed to update appointment - SQL execution returned false");
                        }
                    } catch (PDOException $e) {
                        error_log("PDO Error updating appointment: " . $e->getMessage());
                        $error = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Invalid status or appointment ID';
                    error_log("Validation failed - Appointment ID: $appointmentId, Status: $newStatus");
                    error_log("Valid statuses: " . implode(', ', ['pending', 'confirmed', 'completed', 'canceled']));
                }
                break;
                
            case 'delete':
                if ($appointmentId) {
                    try {
                        $pdo = getDBConnection();
                        
                        // Get appointment info for logging
                        $stmt = $pdo->prepare("SELECT u.name, a.appointment_date FROM appointments a JOIN users u ON a.user_id = u.user_id WHERE a.appointment_id = ?");
                        $stmt->execute([$appointmentId]);
                        $appointmentInfo = $stmt->fetch();
                        
                        $stmt = $pdo->prepare("DELETE FROM appointments WHERE appointment_id = ?");
                        $result = $stmt->execute([$appointmentId]);
                        
                        if ($result && $stmt->rowCount() > 0) {
                            $success = 'Appointment deleted successfully';
                            logAdminAction($_SESSION['user_id'], "Deleted appointment ID $appointmentId for {$appointmentInfo['name']}");
                        } else {
                            $error = 'Failed to delete appointment';
                        }
                    } catch (PDOException $e) {
                        $error = 'Error deleting appointment: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$therapistFilter = $_GET['therapist'] ?? '';
$clientFilter = $_GET['client'] ?? '';

// Build query conditions
$conditions = [];
$params = [];

if ($statusFilter) {
    $conditions[] = "a.status = ?";
    $params[] = $statusFilter;
}

if ($dateFilter) {
    $conditions[] = "DATE(a.appointment_date) = ?";
    $params[] = $dateFilter;
}

if ($therapistFilter) {
    $conditions[] = "a.therapist_id = ?";
    $params[] = $therapistFilter;
}

if ($clientFilter) {
    $conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$clientFilter%";
    $params[] = "%$clientFilter%";
}

$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get appointments with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    $pdo = getDBConnection();
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM appointments a JOIN users u ON a.user_id = u.user_id $whereClause";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalAppointments = $stmt->fetch()['total'];
    $totalPages = ceil($totalAppointments / $limit);
    
    // Get appointments for current page
    $query = "
        SELECT a.*, 
               u.name as client_name, u.email as client_email, u.phone_number as client_phone,
               t.name as therapist_name, t.specialization,
               s.service_name, s.price, s.duration
        FROM appointments a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN therapists t ON a.therapist_id = t.therapist_id
        LEFT JOIN services s ON a.service_id = s.service_id
        $whereClause
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $appointments = $stmt->fetchAll();
    
    // Get therapists for filter
    $stmt = $pdo->query("SELECT therapist_id, name FROM therapists WHERE status = 'active' ORDER BY name");
    $therapists = $stmt->fetchAll();
    
    // Get clients for dropdown
    $stmt = $pdo->query("SELECT user_id, name, email FROM users WHERE role = 'client' AND status = 'active' ORDER BY name");
    $clients = $stmt->fetchAll();
    
    // Get services for dropdown
    $stmt = $pdo->query("SELECT service_id, service_name, price FROM services WHERE status = 'active' ORDER BY service_name");
    $services = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Error fetching appointments: ' . $e->getMessage();
    $appointments = [];
    $therapists = [];
    $clients = [];
    $services = [];
    $totalPages = 1;
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-calendar"></i> Manage Appointments</h1>
            <p>View and manage all appointments in the system</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <!-- Appointment Statistics -->
        <div class="stats-grid">
            <?php
            try {
                $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
                $statusStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()");
                $todayCount = $stmt->fetch()['count'];
                
                $stmt = $pdo->query("SELECT SUM(s.price) as revenue FROM appointments a LEFT JOIN services s ON a.service_id = s.service_id WHERE a.status = 'completed' AND MONTH(a.appointment_date) = MONTH(CURDATE())");
                $monthlyRevenue = $stmt->fetch()['revenue'] ?? 0;
            } catch (PDOException $e) {
                $statusStats = [];
                $todayCount = 0;
                $monthlyRevenue = 0;
            }
            ?>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <div class="number"><?php echo $statusStats['pending'] ?? 0; ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-check"></i></div>
                <div class="number"><?php echo $statusStats['confirmed'] ?? 0; ?></div>
                <div class="label">Confirmed</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-calendar-day"></i></div>
                <div class="number"><?php echo $todayCount; ?></div>
                <div class="label">Today</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-check-double"></i></div>
                <div class="number"><?php echo $statusStats['completed'] ?? 0; ?></div>
                <div class="label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-dollar-sign"></i></div>
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
                        <button class="btn btn-primary btn-lg" onclick="addAppointment()" style="width: 100%;">
                            <i class="fas fa-plus"></i><br>Add Appointment
                        </button>
                    </div>
                    <div class="col-3">
                        <a href="manage_appointments.php?status=pending" class="btn btn-outline btn-lg" style="width: 100%;">
                            <i class="fas fa-clock"></i><br>Pending Appointments
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="manage_appointments.php?date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline btn-lg" style="width: 100%;">
                            <i class="fas fa-calendar-day"></i><br>Today's Schedule
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="manage_appointments.php?status=completed" class="btn btn-outline btn-lg" style="width: 100%;">
                            <i class="fas fa-chart-bar"></i><br>Completed Sessions
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters and Search -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter & Search Appointments</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-control form-select">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="canceled" <?php echo $statusFilter === 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($dateFilter); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="therapist" class="form-label">Therapist</label>
                            <select id="therapist" name="therapist" class="form-control form-select">
                                <option value="">All Therapists</option>
                                <?php foreach ($therapists as $therapist): ?>
                                    <option value="<?php echo $therapist['therapist_id']; ?>" 
                                            <?php echo $therapistFilter == $therapist['therapist_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($therapist['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="client" class="form-label">Search Client</label>
                            <input type="text" id="client" name="client" class="form-control" 
                                   placeholder="Name or email..." value="<?php echo htmlspecialchars($clientFilter); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Apply</button>
                                <a href="manage_appointments.php" class="btn btn-outline">Clear</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Appointments Table -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-table"></i> All Appointments</h3>
                <div class="card-actions">
                    <span class="badge badge-primary"><?php echo $totalAppointments; ?> Total</span>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (empty($appointments)): ?>
                    <div class="text-center p-5">
                        <i class="fas fa-calendar-times" style="font-size: 4rem; color: var(--gray-400); margin-bottom: 1rem;"></i>
                        <h4>No appointments found</h4>
                        <p class="text-muted">Try adjusting your filters or create a new appointment.</p>
                        <button class="btn btn-primary" onclick="addAppointment()">
                            <i class="fas fa-plus me-2"></i>Add New Appointment
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Service</th>
                                    <th>Therapist</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td><strong>#<?php echo $appointment['appointment_id']; ?></strong></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($appointment['client_name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($appointment['client_email']); ?></small>
                                                <?php if ($appointment['client_phone']): ?>
                                                    <br><small class="text-muted"><i class="fas fa-phone fa-xs"></i> <?php echo htmlspecialchars($appointment['client_phone']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($appointment['service_name'] ?? 'N/A'); ?></strong>
                                                <?php if ($appointment['duration']): ?>
                                                    <br><small class="text-muted"><i class="fas fa-clock fa-xs"></i> <?php echo $appointment['duration']; ?> mins</small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php echo htmlspecialchars($appointment['therapist_name'] ?? 'Not Assigned'); ?>
                                                <?php if ($appointment['specialization']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($appointment['specialization']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo formatDate($appointment['appointment_date']); ?></strong>
                                                <br><small><?php echo formatTime($appointment['appointment_time']); ?></small>
                                            </div>
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
                                            <strong>Rs. <?php echo number_format($appointment['price'] ?? 0, 0); ?></strong>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline" onclick="editAppointment(<?php echo htmlspecialchars(json_encode($appointment)); ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline text-danger" onclick="deleteAppointment(<?php echo $appointment['appointment_id']; ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php
                            $queryParams = $_GET;
                            for ($i = 1; $i <= $totalPages; $i++):
                                $queryParams['page'] = $i;
                                $queryString = http_build_query($queryParams);
                            ?>
                                <a href="?<?php echo $queryString; ?>" class="btn <?php echo $i == $page ? 'btn-primary' : 'btn-outline'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Appointment Modal -->
<div id="appointmentModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="appointmentModalTitle">Edit Appointment</h4>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="appointmentForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" id="appointmentAction" name="action" value="update_status">
                <input type="hidden" id="appointmentId" name="appointment_id">
                
                <div id="appointmentFields">
                    <!-- For editing existing appointments -->
                    <div class="form-group">
                        <label for="appointmentStatus" class="form-label">Status</label>
                        <select id="appointmentStatus" name="status" class="form-control form-select" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="canceled">Canceled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="appointmentNotes" class="form-label">Admin Notes</label>
                        <textarea id="appointmentNotes" name="notes" class="form-control" rows="4" 
                                  placeholder="Add any notes about this appointment..."></textarea>
                    </div>
                </div>
                
                <div id="newAppointmentFields" style="display: none;">
                    <!-- For creating new appointments -->
                    <div class="form-group">
                        <label for="newUserId" class="form-label">Client *</label>
                        <select id="newUserId" name="user_id" class="form-control form-select" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['user_id']; ?>">
                                    <?php echo htmlspecialchars($client['name'] . ' (' . $client['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="newTherapistId" class="form-label">Therapist *</label>
                        <select id="newTherapistId" name="therapist_id" class="form-control form-select" required>
                            <option value="">Select Therapist</option>
                            <?php foreach ($therapists as $therapist): ?>
                                <option value="<?php echo $therapist['therapist_id']; ?>">
                                    <?php echo htmlspecialchars($therapist['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="newServiceId" class="form-label">Service *</label>
                        <select id="newServiceId" name="service_id" class="form-control form-select" required>
                            <option value="">Select Service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['service_id']; ?>">
                                    <?php echo htmlspecialchars($service['service_name'] . ' - Rs. ' . number_format($service['price'], 0)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="newAppointmentDate" class="form-label">Date *</label>
                                <input type="date" id="newAppointmentDate" name="appointment_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="newAppointmentTime" class="form-label">Time *</label>
                                <select id="newAppointmentTime" name="appointment_time" class="form-control form-select" required>
                                    <option value="">Select Time</option>
                                    <?php
                                    for ($hour = 9; $hour < 18; $hour++) {
                                        $time = sprintf('%02d:00:00', $hour);
                                        $displayTime = date('g:i A', strtotime($time));
                                        echo "<option value='$time'>$displayTime</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="newAppointmentNotes" class="form-label">Notes</label>
                        <textarea id="newAppointmentNotes" name="notes" class="form-control" rows="3" 
                                  placeholder="Any special notes or requirements..."></textarea>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary" id="saveAppointmentBtn">Update Appointment</button>
                    <button type="button" class="btn btn-outline" onclick="closeAppointmentModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addAppointment() {
    document.getElementById('appointmentModalTitle').textContent = 'Add New Appointment';
    document.getElementById('appointmentForm').reset();
    document.getElementById('appointmentAction').value = 'add';
    document.getElementById('saveAppointmentBtn').textContent = 'Add Appointment';
    document.getElementById('appointmentFields').style.display = 'none';
    document.getElementById('newAppointmentFields').style.display = 'block';

    // Since reset() enables all form fields, we need to disable the ones we don't need
    document.querySelectorAll('#appointmentFields select, #appointmentFields textarea').forEach(el => el.disabled = true);
    document.querySelectorAll('#newAppointmentFields select, #newAppointmentFields input, #newAppointmentFields textarea').forEach(el => el.disabled = false);
    
    // Set minimum date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('newAppointmentDate').min = tomorrow.toISOString().split('T')[0];
    
    document.getElementById('appointmentModal').style.display = 'block';
}

function editAppointment(appointmentData) {
    console.log('Editing appointment:', appointmentData); // Debug log
    
    document.getElementById('appointmentModalTitle').textContent = 'Edit Appointment';
    document.getElementById('appointmentForm').reset(); // Clear previous data and enable all fields

    // Set values for the edit form
    document.getElementById('appointmentId').value = appointmentData.appointment_id;
    document.getElementById('appointmentStatus').value = appointmentData.status;
    
    // Handle different possible note field names from the database
    const notes = appointmentData.admin_notes || appointmentData.notes || appointmentData.client_notes || '';
    document.getElementById('appointmentNotes').value = notes;
    
    document.getElementById('appointmentAction').value = 'update_status';
    document.getElementById('saveAppointmentBtn').textContent = 'Update Appointment';
    document.getElementById('appointmentFields').style.display = 'block';
    document.getElementById('newAppointmentFields').style.display = 'none';

    // Since reset() enables all form fields, we need to disable the ones we don't need
    document.querySelectorAll('#newAppointmentFields select, #newAppointmentFields input, #newAppointmentFields textarea').forEach(el => el.disabled = true);
    document.querySelectorAll('#appointmentFields select, #appointmentFields textarea').forEach(el => el.disabled = false);

    document.getElementById('appointmentModal').style.display = 'block';
}

// Add form submission debugging
document.getElementById('appointmentForm').addEventListener('submit', function(e) {
    const action = document.getElementById('appointmentAction').value;
    const appointmentId = document.getElementById('appointmentId').value;
    const status = document.getElementById('appointmentStatus').value;
    const notes = document.getElementById('appointmentNotes').value;
    
    console.log('Form submission:', {
        action: action,
        appointmentId: appointmentId,
        status: status,
        notes: notes
    });
    
    // Validate required fields for edit action
    if (action === 'update_status') {
        if (!appointmentId || !status) {
            e.preventDefault();
            alert('Please ensure appointment ID and status are selected.');
            return false;
        }
    }
    
    return true;
});

function deleteAppointment(appointmentId) {
    if (confirm('Are you sure you want to delete this appointment? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="appointment_id" value="${appointmentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeAppointmentModal() {
    document.getElementById('appointmentModal').style.display = 'none';
}

// Close modal events
document.querySelector('.modal-close').onclick = closeAppointmentModal;

window.onclick = function(event) {
    const modal = document.getElementById('appointmentModal');
    if (event.target === modal) {
        closeAppointmentModal();
    }
}

// Date validation for new appointments
document.getElementById('newAppointmentDate').addEventListener('change', function() {
    const selectedDate = new Date(this.value);
    const today = new Date();
    const dayOfWeek = selectedDate.getDay();
    
    if (dayOfWeek === 0) { // Sunday
        alert('We are closed on Sundays. Please select another date.');
        this.value = '';
        return;
    }
    
    if (selectedDate <= today) {
        alert('Please select a future date (at least 24 hours in advance).');
        this.value = '';
        return;
    }
});
</script>

<style>
/* Modal Styles */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: white;
    border-radius: var(--border-radius);
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: var(--shadow-lg);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}

.modal-body {
    padding: 1.5rem;
}

.modal-close {
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    opacity: 0.8;
    background: none;
    border: none;
    padding: 0.5rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    opacity: 1;
    background: rgba(255, 255, 255, 0.1);
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.btn-group {
    display: flex;
    gap: 0.25rem;
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.card-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
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

.d-flex {
    display: flex;
}

.gap-2 {
    gap: 0.5rem;
}

/* Fix table text visibility issues */
.table td {
    color: #000 !important;
}

.table td .text-muted {
    color: #6b7280 !important;
}

.table td .badge {
    color: #000 !important;
    background-color: #f3f4f6 !important;
    border: 1px solid #d1d5db !important;
}

.table td .badge-warning {
    color: #000 !important;
    background-color: #fef3c7 !important;
    border: 1px solid #fcd34d !important;
}

.table td .badge-info {
    color: #fff !important;
    background-color: #3b82f6 !important;
    border: 1px solid #2563eb !important;
}

.table td .badge-success {
    color: #fff !important;
    background-color: #10b981 !important;
    border: 1px solid #059669 !important;
}

.table td .badge-error {
    color: #fff !important;
    background-color: #ef4444 !important;
    border: 1px solid #dc2626 !important;
}

.table td small {
    color: #374151 !important;
}

@media (max-width: 768px) {
    .row.g-3 > * {
        margin-bottom: 1rem;
    }
    
    .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    .modal-content {
        width: 95%;
        margin: 1rem;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>