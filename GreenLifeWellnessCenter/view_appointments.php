<?php
/**
 * Therapist Appointment View
 * GreenLife Wellness Center
 */

$pageTitle = "My Appointments";
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

$error = '';
$success = '';

// Handle appointment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appointmentId = $_POST['appointment_id'] ?? '';
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } elseif ($action === 'update_status' && $appointmentId) {
        $newStatus = $_POST['status'] ?? '';
        
        if (in_array($newStatus, ['confirmed', 'completed', 'canceled'])) {
            try {
                $pdo = getDBConnection();
                
                // First verify the appointment belongs to this therapist
                $stmt = $pdo->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ? AND therapist_id = ?");
                $stmt->execute([$appointmentId, $therapistId]);
                
                if (!$stmt->fetch()) {
                    $error = 'Appointment not found or access denied';
                } else {
                    // Update the appointment status
                    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ? AND therapist_id = ?");
                    $result = $stmt->execute([$newStatus, $appointmentId, $therapistId]);
                    
                    if ($result && $stmt->rowCount() > 0) {
                        // Create notification for client
                        $stmt = $pdo->prepare("SELECT user_id FROM appointments WHERE appointment_id = ?");
                        $stmt->execute([$appointmentId]);
                        $appointment = $stmt->fetch();
                        
                        if ($appointment) {
                            $statusMessages = [
                                'confirmed' => 'Your appointment has been confirmed by the therapist',
                                'completed' => 'Your therapy session has been marked as completed',
                                'canceled' => 'Your appointment has been canceled by the therapist'
                            ];
                            
                            if (isset($statusMessages[$newStatus])) {
                                createNotification($appointment['user_id'], 'Appointment Update', $statusMessages[$newStatus], 'appointment');
                            }
                        }
                        
                        $success = 'Appointment status updated successfully';
                    } else {
                        $error = 'Failed to update appointment status';
                    }
                }
            } catch (PDOException $e) {
                error_log("Error updating appointment status: " . $e->getMessage());
                $error = 'Error updating appointment: Database error occurred';
            }
        } else {
            $error = 'Invalid status selected';
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$serviceFilter = $_GET['service'] ?? '';

// Build query conditions
$conditions = ['a.therapist_id = ?'];
$params = [$therapistId];

if ($statusFilter) {
    $conditions[] = "a.status = ?";
    $params[] = $statusFilter;
}

if ($dateFilter) {
    $conditions[] = "DATE(a.appointment_date) = ?";
    $params[] = $dateFilter;
}

if ($serviceFilter) {
    $conditions[] = "a.service_id = ?";
    $params[] = $serviceFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

// Get appointments with pagination
$page = $_GET['page'] ?? 1;
$limit = 15;
$offset = ($page - 1) * $limit;

try {
    $pdo = getDBConnection();
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM appointments a $whereClause";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalAppointments = $stmt->fetch()['total'];
    $totalPages = ceil($totalAppointments / $limit);
    
    // Get appointments for current page
    $query = "
        SELECT a.*, 
               u.name as client_name, u.email as client_email, u.phone_number as client_phone,
               s.service_name, s.duration, s.price
        FROM appointments a
        JOIN users u ON a.user_id = u.user_id
        JOIN services s ON a.service_id = s.service_id
        $whereClause
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $appointments = $stmt->fetchAll();
    
    // Get services for filter
    $stmt = $pdo->query("SELECT service_id, service_name FROM services ORDER BY service_name");
    $services = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Error fetching appointments: ' . $e->getMessage();
    $appointments = [];
    $services = [];
    $totalPages = 1;
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-calendar"></i> My Appointments</h1>
            <p>Manage your therapy sessions and client appointments</p>
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
        
        <!-- Quick Stats -->
        <div class="stats-grid">
            <?php
            try {
                $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM appointments WHERE therapist_id = ? GROUP BY status");
                $stmt->execute([$therapistId]);
                $statusStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE therapist_id = ? AND DATE(appointment_date) = CURDATE()");
                $stmt->execute([$therapistId]);
                $todayCount = $stmt->fetch()['count'];
            } catch (PDOException $e) {
                $statusStats = [];
                $todayCount = 0;
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
        </div>
        
        <!-- Filters -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter Appointments</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row">
                    <div class="col-3">
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
                    <div class="col-3">
                        <div class="form-group">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($dateFilter); ?>">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="service" class="form-label">Service</label>
                            <select id="service" name="service" class="form-control form-select">
                                <option value="">All Services</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?php echo $service['service_id']; ?>" 
                                            <?php echo $serviceFilter == $service['service_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($service['service_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="view_appointments.php" class="btn btn-outline">Clear</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Appointments List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Appointments (<?php echo $totalAppointments; ?>)</h3>
            </div>
            
            <div class="card-body">
                <?php if (empty($appointments)): ?>
                    <div class="text-center p-5">
                        <i class="fas fa-calendar-times" style="font-size: 4rem; color: var(--gray-400); margin-bottom: 1rem;"></i>
                        <h4>No Appointments Found</h4>
                        <p class="text-muted">No appointments match your current filters.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="appointment-card">
                            <div class="appointment-header">
                                <div class="appointment-datetime">
                                    <div class="date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo formatDate($appointment['appointment_date']); ?>
                                    </div>
                                    <div class="time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo formatTime($appointment['appointment_time']); ?>
                                    </div>
                                </div>
                                <div class="appointment-status">
                                    <?php
                                    $statusClass = '';
                                    switch ($appointment['status']) {
                                        case 'pending': $statusClass = 'badge-warning'; break;
                                        case 'confirmed': $statusClass = 'badge-info'; break;
                                        case 'completed': $statusClass = 'badge-success'; break;
                                        case 'canceled': $statusClass = 'badge-error'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?> badge-lg">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="appointment-content">
                                <div class="client-info">
                                    <h4><i class="fas fa-user"></i> <?php echo htmlspecialchars($appointment['client_name']); ?></h4>
                                    <p>
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['client_email']); ?>
                                        <?php if ($appointment['client_phone']): ?>
                                            <br><i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['client_phone']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                
                                <div class="service-info">
                                    <h5><i class="fas fa-spa"></i> <?php echo htmlspecialchars($appointment['service_name']); ?></h5>
                                    <p>
                                        <span class="badge badge-outline">
                                            <i class="fas fa-clock"></i> <?php echo $appointment['duration']; ?> minutes
                                        </span>
                                        <span class="badge badge-outline">
                                            <i class="fas fa-rupee-sign"></i> Rs. <?php echo number_format($appointment['price'], 2); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <?php if ($appointment['notes']): ?>
                                <div class="appointment-notes">
                                    <h6><i class="fas fa-sticky-note"></i> Session Notes:</h6>
                                    <p><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="appointment-actions">
                                <?php if ($appointment['status'] === 'pending'): ?>
                                    <button class="btn btn-success" onclick="updateStatus(<?php echo $appointment['appointment_id']; ?>, 'confirmed')">
                                        <i class="fas fa-check"></i> Confirm
                                    </button>
                                    <button class="btn btn-error" onclick="updateStatus(<?php echo $appointment['appointment_id']; ?>, 'canceled')">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                <?php elseif ($appointment['status'] === 'confirmed'): ?>
                                    <button class="btn btn-success" onclick="updateStatus(<?php echo $appointment['appointment_id']; ?>, 'completed')">
                                        <i class="fas fa-check-double"></i> Mark Complete
                                    </button>
                                    <button class="btn btn-error" onclick="updateStatus(<?php echo $appointment['appointment_id']; ?>, 'canceled')">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                <?php endif; ?>
                                
                                <a href="update_notes.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-outline">
                                    <i class="fas fa-edit"></i> <?php echo $appointment['notes'] ? 'Edit Notes' : 'Add Notes'; ?>
                                </a>
                                
                                <button class="btn btn-outline" onclick="viewDetails(<?php echo $appointment['appointment_id']; ?>)">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
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

<script>
function updateStatus(appointmentId, newStatus) {
    const statusText = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
    if (confirm(`Are you sure you want to mark this appointment as ${statusText}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="appointment_id" value="${appointmentId}">
            <input type="hidden" name="status" value="${newStatus}">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewDetails(appointmentId) {
    // Implementation for viewing detailed appointment information
    alert('View details for appointment ID: ' + appointmentId);
}
</script>

<style>
.appointment-card {
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius);
    margin-bottom: 1.5rem;
    padding: 1.5rem;
    background: white;
    transition: box-shadow 0.2s ease;
}

.appointment-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.appointment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--gray-100);
}

.appointment-datetime {
    display: flex;
    gap: 1.5rem;
}

.appointment-datetime .date,
.appointment-datetime .time {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary-color);
    font-weight: 600;
}

.appointment-content {
    display: flex;
    gap: 2rem;
    margin-bottom: 1rem;
}

.client-info,
.service-info {
    flex: 1;
}

.client-info h4,
.service-info h5 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    color: var(--gray-800);
}

.appointment-notes {
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--gray-50);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--info-color);
}

.appointment-notes h6 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    color: var(--gray-700);
}

.appointment-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.badge-lg {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .appointment-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .appointment-content {
        flex-direction: column;
        gap: 1rem;
    }
    
    .appointment-actions {
        flex-direction: column;
    }
    
    .appointment-actions .btn {
        width: 100%;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>