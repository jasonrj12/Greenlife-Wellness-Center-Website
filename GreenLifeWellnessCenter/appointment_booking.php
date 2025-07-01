<?php
/**
 * Enhanced Appointment Booking Page
 * GreenLife Wellness Center
 */

$pageTitle = "Book Appointment";
require_once 'includes/functions.php';

// Require user to be logged in
requireLogin();

$error = '';
$success = '';
$preselectedService = isset($_GET['service']) ? intval($_GET['service']) : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $therapistId = sanitizeInput($_POST['therapist'] ?? '');
        $serviceId = sanitizeInput($_POST['service'] ?? '');
        $appointmentDate = sanitizeInput($_POST['appointmentDate'] ?? '');
        $appointmentTime = sanitizeInput($_POST['appointmentTime'] ?? '');
        $clientNotes = sanitizeInput($_POST['client_notes'] ?? '');
        
        if (empty($therapistId) || empty($serviceId) || empty($appointmentDate) || empty($appointmentTime)) {
            $error = 'Please fill in all required fields';
        } else {
            $result = bookAppointment($_SESSION['user_id'], $therapistId, $serviceId, $appointmentDate, $appointmentTime);
            
            if ($result['success']) {
                $success = $result['message'];
                
                // Add client notes if provided
                if (!empty($clientNotes)) {
                    try {
                        $pdo = getDBConnection();
                        $stmt = $pdo->prepare("UPDATE appointments SET client_notes = ? WHERE user_id = ? AND appointment_date = ? AND appointment_time = ? ORDER BY created_at DESC LIMIT 1");
                        $result = $stmt->execute([$clientNotes, $_SESSION['user_id'], $appointmentDate, $appointmentTime]);
                        
                        if (!$result) {
                            error_log("Failed to add client notes for appointment");
                        }
                    } catch (PDOException $e) {
                        error_log("Error adding client notes: " . $e->getMessage());
                        // Don't fail the booking if notes can't be added
                    }
                }
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Get services and therapists for form
$services = getAllServices();

// If the current user is a therapist, exclude them from the therapist list
$excludeUserId = null;
if ($_SESSION['role'] === 'therapist') {
    $excludeUserId = $_SESSION['user_id'];
}
$therapists = getAllTherapists($excludeUserId);

require_once 'includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-calendar-plus me-2"></i>Book Appointment</h1>
            <p>Schedule your wellness session with our expert therapists</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <div class="mt-2">
                    <a href="view_appointments.php" class="btn btn-sm btn-success">View My Appointments</a>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Booking Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-form me-2"></i>Appointment Details</h4>
                    </div>
                    <div class="card-body">
                        <form id="appointmentForm" method="POST" action="" data-persist="true">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="book_appointment">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="service" class="form-label">Select Service *</label>
                                        <select class="form-control form-select" id="service" name="service" required>
                                            <option value="">Choose a service</option>
                                            <?php foreach ($services as $service): ?>
                                                <option value="<?php echo $service['service_id']; ?>" 
                                                        data-price="<?php echo $service['price']; ?>"
                                                        data-duration="<?php echo $service['duration']; ?>"
                                                        <?php echo ($preselectedService && $preselectedService == $service['service_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($service['service_name']); ?> - Rs. <?php echo number_format($service['price'], 2); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div id="servicePrice" class="mt-2 text-success fw-bold" style="display: none;"></div>
                                        <div class="validation-error-message" id="service-error"></div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="therapist" class="form-label">Select Therapist *</label>
                                        <select class="form-control form-select" id="therapist" name="therapist" required>
                                            <option value="">Choose a therapist</option>
                                            <?php foreach ($therapists as $therapist): ?>
                                                <option value="<?php echo $therapist['therapist_id']; ?>">
                                                    <?php echo htmlspecialchars($therapist['name']); ?> - <?php echo htmlspecialchars($therapist['specialization']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($_SESSION['role'] === 'therapist'): ?>
                                            <small class="text-info">
                                                <i class="fas fa-info-circle"></i> You can book appointments with other therapists, but not with yourself.
                                            </small>
                                        <?php endif; ?>
                                        <div class="validation-error-message" id="therapist-error"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="appointmentDate" class="form-label">Appointment Date *</label>
                                        <input type="date" class="form-control" id="appointmentDate" name="appointmentDate" required>
                                        <small class="text-muted">Appointments available Monday-Saturday, at least 24 hours in advance</small>
                                        <div class="validation-error-message" id="date-error"></div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="appointmentTime" class="form-label">Appointment Time *</label>
                                        <select class="form-control form-select" id="appointmentTime" name="appointmentTime" required disabled>
                                            <option value="">Select date and therapist first</option>
                                        </select>
                                        <small class="text-muted">Business hours: 9:00 AM - 6:00 PM (Mon-Sat)</small>
                                        <div class="validation-error-message" id="time-error"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="client_notes" class="form-label">Special Requirements or Notes</label>
                                <textarea class="form-control" id="client_notes" name="client_notes" rows="3" 
                                         placeholder="Please mention any special requirements, health conditions, or preferences..."></textarea>
                                <small class="text-muted">This information helps our therapists provide the best possible care</small>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Important:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Appointments must be booked at least 24 hours in advance</li>
                                    <li>Please arrive 10 minutes early for your appointment</li>
                                    <li>Cancellations must be made at least 4 hours in advance</li>
                                    <li>You will receive confirmation via email once your appointment is confirmed</li>
                                </ul>
                            </div>
                            
                            <div class="d-flex gap-3">
                                <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                    <i class="fas fa-calendar-check me-2"></i>Book Appointment
                                </button>
                                <a href="index.php" class="btn btn-outline btn-lg">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Services Information -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-spa me-2"></i>Our Services</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($services as $service): ?>
                            <div class="service-info mb-4">
                                <h6 class="text-success"><?php echo htmlspecialchars($service['service_name']); ?></h6>
                                <p class="small text-muted mb-2">
                                    <?php echo isset($service['service_description']) ? htmlspecialchars($service['service_description']) : ''; ?>
                                </p>
                                <div class="d-flex justify-content-between">
                                    <span class="small"><i class="fas fa-clock me-1"></i><?php echo $service['duration']; ?> minutes</span>
                                    <span class="small fw-bold text-success">Rs. <?php echo number_format($service['price'], 2); ?></span>
                                </div>
                            </div>
                            <?php if (next($services)): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h4><i class="fas fa-user-md me-2"></i>Our Therapists</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($therapists as $therapist): ?>
                            <div class="therapist-info mb-3">
                                <h6 class="text-dark"><?php echo htmlspecialchars($therapist['name']); ?></h6>
                                <p class="small text-muted mb-1"><?php echo htmlspecialchars($therapist['specialization']); ?></p>
                                <p class="small text-muted mb-2"><?php echo $therapist['experience_years']; ?> years experience</p>
                                <?php if ($therapist['bio']): ?>
                                    <p class="small"><?php echo htmlspecialchars(substr($therapist['bio'], 0, 100)); ?>...</p>
                                <?php endif; ?>
                            </div>
                            <?php if (next($therapists)): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Set minimum date to tomorrow
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('appointmentDate');
    const timeSelect = document.getElementById('appointmentTime');
    const therapistSelect = document.getElementById('therapist');
    
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    dateInput.min = tomorrow.toISOString().split('T')[0];
    
    // Set maximum date to 3 months from now
    const maxDate = new Date();
    maxDate.setMonth(maxDate.getMonth() + 3);
    dateInput.max = maxDate.toISOString().split('T')[0];
    
    // Show service price if service is preselected
    const serviceSelect = document.getElementById('service');
    if (serviceSelect.value) {
        updateServicePrice();
    }
    
    // Add event listener for service selection
    serviceSelect.addEventListener('change', updateServicePrice);
    
    // Add event listeners for date and therapist changes
    dateInput.addEventListener('change', loadAvailableTimeSlots);
    therapistSelect.addEventListener('change', loadAvailableTimeSlots);
});

// Load available time slots when both date and therapist are selected
async function loadAvailableTimeSlots() {
    const dateInput = document.getElementById('appointmentDate');
    const timeSelect = document.getElementById('appointmentTime');
    const therapistSelect = document.getElementById('therapist');
    
    const selectedDate = dateInput.value;
    const selectedTherapist = therapistSelect.value;
    
    // Reset time dropdown
    timeSelect.innerHTML = '<option value="">Select time slot</option>';
    timeSelect.disabled = true;
    
    // Check if both date and therapist are selected
    if (!selectedDate || !selectedTherapist) {
        timeSelect.innerHTML = '<option value="">Select date and therapist first</option>';
        return;
    }
    
    // Validate date (no Sundays, must be future date)
    const selectedDateObj = new Date(selectedDate);
    const today = new Date();
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const dayOfWeek = selectedDateObj.getDay();
    
    if (dayOfWeek === 0) { // Sunday
        timeSelect.innerHTML = '<option value="">We are closed on Sundays</option>';
        dateInput.value = '';
        alert('We are closed on Sundays. Please select another date.');
        return;
    }
    
    // Fix: Compare against tomorrow since minimum date is tomorrow
    if (selectedDateObj < tomorrow) {
        timeSelect.innerHTML = '<option value="">Please select a future date</option>';
        dateInput.value = '';
        alert('Please select a future date (at least 24 hours in advance).');
        return;
    }
    
    // Show loading state
    showLoading(timeSelect, true);
    
    try {
        const response = await fetch(`api/endpoints.php?action=get_available_slots&therapist_id=${selectedTherapist}&date=${selectedDate}`);
        const data = await response.json();
        
        if (data.success) {
            timeSelect.innerHTML = '<option value="">Select time slot</option>';
            
            if (data.data.slots && data.data.slots.length > 0) {
                data.data.slots.forEach(slot => {
                    const option = document.createElement('option');
                    option.value = slot.value;
                    option.textContent = slot.label;
                    timeSelect.appendChild(option);
                });
                timeSelect.disabled = false;
            } else {
                timeSelect.innerHTML = '<option value="">No available time slots</option>';
            }
        } else {
            timeSelect.innerHTML = '<option value="">Error loading time slots</option>';
            console.error('Error loading time slots:', data.message);
        }
    } catch (error) {
        timeSelect.innerHTML = '<option value="">Error loading time slots</option>';
        console.error('Error loading time slots:', error);
    }
}

function updateServicePrice() {
    const serviceSelect = document.getElementById('service');
    const servicePriceDiv = document.getElementById('servicePrice');
    const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
    
    if (selectedOption.value) {
        const price = selectedOption.dataset.price;
        const duration = selectedOption.dataset.duration;
        servicePriceDiv.innerHTML = `Price: Rs. ${parseFloat(price).toLocaleString()} | Duration: ${duration} minutes`;
        servicePriceDiv.style.display = 'block';
    } else {
        servicePriceDiv.style.display = 'none';
    }
}

// Load therapists for service selection
async function loadTherapists() {
    try {
        const response = await fetch('api/endpoints.php?action=get_therapists');
        const data = await response.json();
        
        if (data.success) {
            const therapistSelect = document.getElementById('therapist');
            therapistSelect.innerHTML = '<option value="">Choose a therapist</option>';
            data.therapists.forEach(therapist => {
                const option = document.createElement('option');
                option.value = therapist.therapist_id;
                option.textContent = `${therapist.name} - ${therapist.specialization}`;
                therapistSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading therapists:', error);
    }
}

// Show loading indicator
function showLoading(element, show = true) {
    if (show) {
        element.innerHTML = '<option value="">Loading...</option>';
        element.disabled = true;
    } else {
        element.disabled = false;
    }
}

// Auto-focus on service field
document.getElementById('service').focus();

// Enable form validation styling
document.getElementById('appointmentForm').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        GreenLifeApp.showNotification('Please fill in all required fields', 'error');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>