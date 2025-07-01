<?php
/**
 * Therapist Session Notes Management
 * GreenLife Wellness Center
 */

session_start();

$pageTitle = "Session Notes";
require_once 'includes/functions.php';

// Require therapist access
requireRole('therapist');

// Get therapist ID from user session
$therapistId = getTherapistIdByUserId($_SESSION['user_id']);

if (!$therapistId) {
    header('Location: ../login.php?error=therapist_not_found');
    exit;
}

$error = '';
$success = '';
$appointment = null;

// Get appointment ID from URL
$appointmentId = $_GET['id'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appointmentId = $_POST['appointment_id'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $markCompleted = $_POST['mark_completed'] ?? '';
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } elseif ($action === 'update_notes' && $appointmentId) {
        try {
            $pdo = getDBConnection();
            
            // Verify appointment belongs to this therapist
            $stmt = $pdo->prepare("SELECT appointment_id, status FROM appointments WHERE appointment_id = ? AND therapist_id = ?");
            $stmt->execute([$appointmentId, $therapistId]);
            $appointmentCheck = $stmt->fetch();
            
            if (!$appointmentCheck) {
                $error = 'Appointment not found or access denied';
            } else {
                // Begin transaction for atomic updates
                $pdo->beginTransaction();
                
                try {
                    // Update notes
                    $stmt = $pdo->prepare("UPDATE appointments SET therapist_notes = ? WHERE appointment_id = ? AND therapist_id = ?");
                    $result = $stmt->execute([$notes, $appointmentId, $therapistId]);
                    
                    if (!$result) {
                        throw new Exception('Failed to update session notes');
                    }
                    
                    // If mark as completed is requested
                    if ($markCompleted === '1' && $appointmentCheck['status'] !== 'completed') {
                        $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE appointment_id = ? AND therapist_id = ?");
                        $statusResult = $stmt->execute([$appointmentId, $therapistId]);
                        
                        if ($statusResult) {
                            // Create notification for client
                            $stmt = $pdo->prepare("SELECT user_id FROM appointments WHERE appointment_id = ?");
                            $stmt->execute([$appointmentId]);
                            $appointmentForNotification = $stmt->fetch();
                            
                            if ($appointmentForNotification) {
                                createNotification($appointmentForNotification['user_id'], 'Session Completed', 'Your therapy session has been completed by the therapist', 'appointment');
                            }
                        }
                    }
                    
                    $pdo->commit();
                    $success = 'Session notes updated successfully' . ($markCompleted === '1' ? ' and appointment marked as completed' : '');
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Error updating notes/status: " . $e->getMessage());
                    $error = 'Failed to save changes: ' . $e->getMessage();
                }
            }
        } catch (PDOException $e) {
            error_log("Database error in update_notes: " . $e->getMessage());
            $error = 'Database error occurred while saving notes';
        }
    } else {
        $error = 'Invalid request parameters';
    }
}

// Get appointment details if ID provided
if ($appointmentId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   u.name as client_name, u.email as client_email, u.phone_number as client_phone,
                   s.service_name, s.duration, s.price
            FROM appointments a
            JOIN users u ON a.user_id = u.user_id
            JOIN services s ON a.service_id = s.service_id
            WHERE a.appointment_id = ? AND a.therapist_id = ?
        ");
        $stmt->execute([$appointmentId, $therapistId]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            $error = 'Appointment not found or access denied';
        }
    } catch (PDOException $e) {
        $error = 'Error fetching appointment: ' . $e->getMessage();
    }
}

// Get recent appointments for selection if no specific appointment is loaded
$recentAppointments = [];
if (!$appointment) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("\n            SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, a.therapist_notes,\n                   u.name as client_name, s.service_name\n            FROM appointments a\n            JOIN users u ON a.user_id = u.user_id\n            JOIN services s ON a.service_id = s.service_id\n            WHERE a.therapist_id = ?\n            ORDER BY a.appointment_date DESC, a.appointment_time DESC\n            LIMIT 20\n        ");
        $stmt->execute([$therapistId]);
        $recentAppointments = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = 'Error fetching appointments: ' . $e->getMessage();
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-edit"></i> Session Notes</h1>
            <p>Add or update notes for your therapy sessions</p>
            <a href="view_appointments.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Appointments
            </a>
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
        
        <?php if ($appointment): ?>
            <!-- Specific Appointment Notes -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user"></i> Session Details</h3>
                </div>
                <div class="card-body">
                    <div class="session-details">
                        <div class="row">
                            <div class="col-6">
                                <div class="detail-group">
                                    <label><i class="fas fa-user"></i> Client:</label>
                                    <span><?php echo htmlspecialchars($appointment['client_name']); ?></span>
                                </div>
                                <div class="detail-group">
                                    <label><i class="fas fa-spa"></i> Service:</label>
                                    <span><?php echo htmlspecialchars($appointment['service_name']); ?></span>
                                </div>
                                <div class="detail-group">
                                    <label><i class="fas fa-clock"></i> Duration:</label>
                                    <span><?php echo $appointment['duration']; ?> minutes</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="detail-group">
                                    <label><i class="fas fa-calendar"></i> Date:</label>
                                    <span><?php echo formatDate($appointment['appointment_date']); ?></span>
                                </div>
                                <div class="detail-group">
                                    <label><i class="fas fa-clock"></i> Time:</label>
                                    <span><?php echo formatTime($appointment['appointment_time']); ?></span>
                                </div>
                                <div class="detail-group">
                                    <label><i class="fas fa-info-circle"></i> Status:</label>
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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notes Form -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-sticky-note"></i> Session Notes</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_notes">
                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                        
                        <div class="form-group">
                            <label for="notes" class="form-label">
                                <i class="fas fa-edit"></i> Notes for this session:
                            </label>
                            <textarea 
                                id="notes" 
                                name="notes" 
                                class="form-control" 
                                rows="12" 
                                placeholder="Enter detailed notes about the therapy session, client progress, observations, recommendations, and next steps..."
                            ><?php echo htmlspecialchars($appointment['therapist_notes'] ?? ''); ?></textarea>
                            <small class="form-text">
                                These notes are confidential and will only be visible to you and authorized personnel.
                            </small>
                        </div>
                        
                        <!-- Quick Insert Templates -->
                        <div class="notes-templates">
                            <h5><i class="fas fa-template"></i> Quick Templates:</h5>
                            <div class="template-buttons">
                                <button type="button" class="btn btn-sm btn-outline" onclick="insertTemplate('assessment')">
                                    Assessment
                                </button>
                                <button type="button" class="btn btn-sm btn-outline" onclick="insertTemplate('progress')">
                                    Progress Notes
                                </button>
                                <button type="button" class="btn btn-sm btn-outline" onclick="insertTemplate('treatment')">
                                    Treatment Plan
                                </button>
                                <button type="button" class="btn btn-sm btn-outline" onclick="insertTemplate('homework')">
                                    Homework/Action Items
                                </button>
                                <button type="button" class="btn btn-sm btn-outline" onclick="insertTemplate('follow-up')">
                                    Follow-up
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Notes
                            </button>
                            <a href="view_appointments.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <?php if ($appointment['status'] !== 'completed' && in_array($appointment['status'], ['confirmed', 'pending'])): ?>
                                <button type="button" class="btn btn-success" onclick="markCompleted()">
                                    <i class="fas fa-check-double"></i> Save & Mark Complete
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Recent Appointments Selection -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Select Appointment to Add/Edit Notes</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($recentAppointments)): ?>
                        <div class="text-center p-5">
                            <i class="fas fa-calendar-times" style="font-size: 4rem; color: var(--gray-400); margin-bottom: 1rem;"></i>
                            <h4>No Appointments Found</h4>
                            <p class="text-muted">You don't have any appointments to add notes to.</p>
                            <a href="view_appointments.php" class="btn btn-primary">
                                <i class="fas fa-calendar"></i> View All Appointments
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="appointments-list">
                            <?php foreach ($recentAppointments as $apt): ?>
                                <div class="appointment-item">
                                    <div class="appointment-info">
                                        <div class="appointment-main">
                                            <h5><?php echo htmlspecialchars($apt['client_name']); ?></h5>
                                            <p><?php echo htmlspecialchars($apt['service_name']); ?></p>
                                        </div>
                                        <div class="appointment-meta">
                                            <span class="date">
                                                <i class="fas fa-calendar"></i> 
                                                <?php echo formatDate($apt['appointment_date']); ?>
                                            </span>
                                            <span class="time">
                                                <i class="fas fa-clock"></i> 
                                                <?php echo formatTime($apt['appointment_time']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="appointment-status">
                                        <?php
                                        $statusClass = '';
                                        switch ($apt['status']) {
                                            case 'pending': $statusClass = 'badge-warning'; break;
                                            case 'confirmed': $statusClass = 'badge-info'; break;
                                            case 'completed': $statusClass = 'badge-success'; break;
                                            case 'canceled': $statusClass = 'badge-error'; break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($apt['status']); ?>
                                        </span>
                                        <?php if (!empty($apt['therapist_notes'])): ?>
                                            <span class="badge badge-outline">
                                                <i class="fas fa-sticky-note"></i> Has Notes
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="appointment-actions">
                                        <a href="update_notes.php?id=<?php echo $apt['appointment_id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-edit"></i> 
                                            <?php echo !empty($apt['therapist_notes']) ? 'Edit Notes' : 'Add Notes'; ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="view_appointments.php" class="btn btn-outline">
                                <i class="fas fa-eye"></i> View All Appointments
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const templates = {
    assessment: `
INITIAL ASSESSMENT:
- Chief complaint:
- Current symptoms:
- Mental status:
- Risk assessment:
- Diagnostic impressions:
`,
    progress: `
PROGRESS NOTES:
- Session focus:
- Client mood/affect:
- Interventions used:
- Client response:
- Progress toward goals:
- Concerns noted:
`,
    treatment: `
TREATMENT PLAN:
- Primary goals:
- Therapeutic approach:
- Interventions planned:
- Timeline:
- Success measures:
`,
    homework: `
HOMEWORK/ACTION ITEMS:
- Tasks assigned:
- Skills to practice:
- Resources provided:
- Next session prep:
`,
    'follow-up': `
FOLLOW-UP NOTES:
- Items to review next session:
- Referrals needed:
- Family involvement:
- Medication considerations:
- Schedule changes:
`
};

function insertTemplate(templateType) {
    const textarea = document.getElementById('notes');
    const template = templates[templateType];
    
    if (template) {
        const currentValue = textarea.value;
        const newValue = currentValue + (currentValue ? '\n\n' : '') + template.trim();
        textarea.value = newValue;
        textarea.focus();
        
        // Move cursor to end
        textarea.setSelectionRange(newValue.length, newValue.length);
    }
}

function markCompleted() {
    if (confirm('This will save the notes and mark the appointment as completed. Continue?')) {
        // Add hidden field to mark as completed
        const form = document.querySelector('form');
        const hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.name = 'mark_completed';
        hiddenField.value = '1';
        form.appendChild(hiddenField);
        
        form.submit();
    }
}

// Auto-save functionality
let saveTimeout;
document.getElementById('notes').addEventListener('input', function() {
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(function() {
        // Auto-save implementation could go here
        console.log('Auto-saving notes...');
    }, 2000);
});
</script>

<style>
.session-details {
    background: var(--gray-50);
    padding: 1.5rem;
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
}

.detail-group {
    display: flex;
    align-items: center;
    margin-bottom: 0.75rem;
}

.detail-group label {
    flex: 0 0 120px;
    font-weight: 600;
    color: var(--gray-700);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.detail-group span {
    color: var(--gray-800);
}

.notes-templates {
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: var(--gray-50);
    border-radius: var(--border-radius);
}

.notes-templates h5 {
    margin-bottom: 1rem;
    color: var(--gray-700);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.template-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}

.appointments-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.appointment-item {
    display: flex;
    align-items: center;
    padding: 1.5rem;
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius);
    background: white;
    transition: box-shadow 0.2s ease;
}

.appointment-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.appointment-info {
    flex: 1;
}

.appointment-main h5 {
    margin-bottom: 0.25rem;
    color: var(--gray-800);
}

.appointment-main p {
    margin-bottom: 0.5rem;
    color: var(--gray-600);
}

.appointment-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.875rem;
    color: var(--gray-500);
}

.appointment-meta span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.appointment-status {
    flex: 0 0 150px;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
}

.appointment-actions {
    flex: 0 0 120px;
    display: flex;
    justify-content: flex-end;
}

#notes {
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .appointment-item {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .appointment-status,
    .appointment-actions {
        flex: none;
        align-items: stretch;
    }
    
    .template-buttons {
        flex-direction: column;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>