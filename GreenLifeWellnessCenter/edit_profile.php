<?php
/**
 * Edit Therapist Profile
 * GreenLife Wellness Center
 */

$pageTitle = "Edit Profile";
require_once 'includes/functions.php';

// Require therapist access
requireRole('therapist');

// Get therapist ID from user session - with better error handling
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $specialization = sanitizeInput($_POST['specialization'] ?? '');
        $bio = sanitizeInput($_POST['bio'] ?? '');
        $experienceYears = intval($_POST['experience_years'] ?? 0);
        $certification = sanitizeInput($_POST['certification'] ?? '');
        
        // Validation
        if (empty($name) || strlen($name) < 2) {
            $error = 'Please enter a valid name (at least 2 characters)';
        } elseif (!validateEmail($email)) {
            $error = 'Please enter a valid email address';
        } elseif (empty($specialization)) {
            $error = 'Please enter your specialization';
        } else {
            try {
                $pdo = getDBConnection();
                
                // Check if email exists for other users
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $stmt->execute([$email, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $error = 'Email already exists for another user';
                } else {
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    try {
                        // Update users table
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone_number = ?, address = ? WHERE user_id = ?");
                        $stmt->execute([$name, $email, $phone, $address, $_SESSION['user_id']]);
                        
                        // Check if therapists table has certification column
                        $stmt = $pdo->query("SHOW COLUMNS FROM therapists LIKE 'certification'");
                        $hasCertificationColumn = $stmt->fetch() !== false;
                        
                        // Update therapists table
                        if ($hasCertificationColumn) {
                            $updateStmt = $pdo->prepare("UPDATE therapists SET name = ?, specialization = ?, bio = ?, experience_years = ?, certification = ? WHERE user_id = ?");
                            $result = $updateStmt->execute([$name, $specialization, $bio, $experienceYears, $certification, $_SESSION['user_id']]);
                        } else {
                            $updateStmt = $pdo->prepare("UPDATE therapists SET name = ?, specialization = ?, bio = ?, experience_years = ? WHERE user_id = ?");
                            $result = $updateStmt->execute([$name, $specialization, $bio, $experienceYears, $_SESSION['user_id']]);
                        }
                        
                        // If update failed or no therapist record exists, check and create if needed
                        if (!$result) {
                            throw new Exception('Failed to update therapist information.');
                        }
                        
                        // Only create a new record if no therapist record exists for this user
                        if ($updateStmt->rowCount() === 0) {
                            $checkStmt = $pdo->prepare("SELECT therapist_id FROM therapists WHERE user_id = ?");
                            $checkStmt->execute([$_SESSION['user_id']]);
                            
                            if (!$checkStmt->fetch()) {
                                // No record exists, create one
                                if ($hasCertificationColumn) {
                                    $insertStmt = $pdo->prepare("INSERT INTO therapists (user_id, name, specialization, bio, experience_years, certification, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                                    if (!$insertStmt->execute([$_SESSION['user_id'], $name, $specialization, $bio, $experienceYears, $certification])) {
                                        throw new Exception('Failed to create new therapist record.');
                                    }
                                } else {
                                    $insertStmt = $pdo->prepare("INSERT INTO therapists (user_id, name, specialization, bio, experience_years, status) VALUES (?, ?, ?, ?, ?, 'active')");
                                    if (!$insertStmt->execute([$_SESSION['user_id'], $name, $specialization, $bio, $experienceYears])) {
                                        throw new Exception('Failed to create new therapist record.');
                                    }
                                }
                            }
                            // If record exists but no rows were affected, it means the data hasn't changed - this is fine
                        }
                        
                        // Update session data
                        $_SESSION['name'] = $name;
                        $_SESSION['email'] = $email;
                        
                        $pdo->commit();
                        $success = 'Profile updated successfully!';
                        
                        // Log the action
                        logAdminAction($_SESSION['user_id'], "Updated therapist profile");
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e; // Re-throw to be caught by the outer catch block
                    }
                }
            } catch (Exception $e) {
                error_log("Error updating therapist profile: " . $e->getMessage());
                $error = 'Error updating profile. Please try again.';
            }
        }
    }
}

// Get current profile data with better error handling
try {
    $pdo = getDBConnection();
    
    // Check if therapists table has certification column
    $stmt = $pdo->query("SHOW COLUMNS FROM therapists LIKE 'certification'");
    $hasCertificationColumn = $stmt->fetch() !== false;
    
    if ($hasCertificationColumn) {
        // Get user and therapist info with certification
        $stmt = $pdo->prepare("
            SELECT u.*, t.specialization, t.bio, t.experience_years, t.certification
            FROM users u
            LEFT JOIN therapists t ON u.user_id = t.user_id
            WHERE u.user_id = ?
        ");
    } else {
        // Get user and therapist info without certification
        $stmt = $pdo->prepare("
            SELECT u.*, t.specialization, t.bio, t.experience_years, NULL as certification
            FROM users u
            LEFT JOIN therapists t ON u.user_id = t.user_id
            WHERE u.user_id = ?
        ");
    }
    
    $stmt->execute([$_SESSION['user_id']]);
    $profileData = $stmt->fetch();
    
    if (!$profileData) {
        error_log("Profile data not found for user ID: " . $_SESSION['user_id']);
        $error = 'Profile data not found. Please contact support.';
        $profileData = [
            'name' => $_SESSION['name'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'phone_number' => '',
            'address' => '',
            'specialization' => '',
            'bio' => '',
            'experience_years' => 0,
            'certification' => ''
        ];
    }
    
} catch (PDOException $e) {
    error_log("Error fetching therapist profile: " . $e->getMessage());
    $error = 'Error loading profile data. Database connection issue.';
    // Provide fallback data from session
    $profileData = [
        'name' => $_SESSION['name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'phone_number' => '',
        'address' => '',
        'specialization' => 'General Practice',
        'bio' => '',
        'experience_years' => 0,
        'certification' => ''
    ];
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>
            <p>Update your professional information and contact details</p>
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
        
        <div class="row">
            <div class="col-8">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($profileData): ?>
                            <form method="POST" id="profileForm">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="name" class="form-label">Full Name *</label>
                                            <input type="text" id="name" name="name" class="form-control" 
                                                   value="<?php echo htmlspecialchars($profileData['name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="email" class="form-label">Email Address *</label>
                                            <input type="email" id="email" name="email" class="form-control" 
                                                   value="<?php echo htmlspecialchars($profileData['email']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" id="phone" name="phone" class="form-control" 
                                                   value="<?php echo htmlspecialchars($profileData['phone_number'] ?? ''); ?>" 
                                                   placeholder="+94 xxx xxx xxx">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="experience_years" class="form-label">Years of Experience</label>
                                            <input type="number" id="experience_years" name="experience_years" class="form-control" 
                                                   value="<?php echo $profileData['experience_years'] ?? 0; ?>" 
                                                   min="0" max="50">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea id="address" name="address" class="form-control" rows="2" 
                                              placeholder="Your full address"><?php echo htmlspecialchars($profileData['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="specialization" class="form-label">Specialization *</label>
                                    <input type="text" id="specialization" name="specialization" class="form-control" 
                                           value="<?php echo htmlspecialchars($profileData['specialization'] ?? ''); ?>" 
                                           placeholder="e.g., Massage Therapy, Physiotherapy, Yoga" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="certification" class="form-label">Certifications</label>
                                    <input type="text" id="certification" name="certification" class="form-control" 
                                           value="<?php echo htmlspecialchars($profileData['certification'] ?? ''); ?>" 
                                           placeholder="Professional certifications or licenses">
                                </div>
                                
                                <div class="form-group">
                                    <label for="bio" class="form-label">Professional Bio</label>
                                    <textarea id="bio" name="bio" class="form-control" rows="4" 
                                              placeholder="Tell clients about your background, expertise, and approach to wellness..."><?php echo htmlspecialchars($profileData['bio'] ?? ''); ?></textarea>
                                    <small class="text-muted">This will be displayed to clients when they view your profile.</small>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                    <a href="therapist_dashboard.php" class="btn btn-outline">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <p class="text-center text-muted">Unable to load profile data.</p>
                            <div class="text-center">
                                <a href="therapist_dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-4">
                <!-- Profile Preview -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-preview"></i> Profile Preview</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($profileData): ?>
                            <div class="profile-preview">
                                <div class="profile-avatar">
                                    <i class="fas fa-user-circle" style="font-size: 4rem; color: var(--primary);"></i>
                                </div>
                                <h5 id="preview-name"><?php echo htmlspecialchars($profileData['name']); ?></h5>
                                <p class="text-muted" id="preview-specialization"><?php echo htmlspecialchars($profileData['specialization'] ?? 'Wellness Therapist'); ?></p>
                                
                                <div class="profile-info">
                                    <div class="info-item">
                                        <i class="fas fa-envelope"></i>
                                        <span id="preview-email"><?php echo htmlspecialchars($profileData['email']); ?></span>
                                    </div>
                                    <?php if ($profileData['phone_number']): ?>
                                        <div class="info-item">
                                            <i class="fas fa-phone"></i>
                                            <span id="preview-phone"><?php echo htmlspecialchars($profileData['phone_number']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span id="preview-experience"><?php echo $profileData['experience_years'] ?? 0; ?> years experience</span>
                                    </div>
                                </div>
                                
                                <?php if ($profileData['bio']): ?>
                                    <div class="profile-bio">
                                        <h6>About</h6>
                                        <p id="preview-bio"><?php echo nl2br(htmlspecialchars($profileData['bio'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-link"></i> Quick Links</h4>
                    </div>
                    <div class="card-body">
                        <div class="quick-links">
                            <a href="therapist_dashboard.php" class="quick-link">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>Dashboard</span>
                            </a>
                            <a href="view_appointments.php" class="quick-link">
                                <i class="fas fa-calendar"></i>
                                <span>My Appointments</span>
                            </a>
                            <a href="update_notes.php" class="quick-link">
                                <i class="fas fa-notes-medical"></i>
                                <span>Session Notes</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Real-time preview updates
document.getElementById('name').addEventListener('input', function() {
    document.getElementById('preview-name').textContent = this.value || 'Your Name';
});

document.getElementById('email').addEventListener('input', function() {
    document.getElementById('preview-email').textContent = this.value || 'your.email@example.com';
});

document.getElementById('phone').addEventListener('input', function() {
    const phonePreview = document.getElementById('preview-phone');
    if (phonePreview) {
        phonePreview.textContent = this.value || 'Not provided';
    }
});

document.getElementById('specialization').addEventListener('input', function() {
    document.getElementById('preview-specialization').textContent = this.value || 'Wellness Therapist';
});

document.getElementById('experience_years').addEventListener('input', function() {
    document.getElementById('preview-experience').textContent = (this.value || 0) + ' years experience';
});

document.getElementById('bio').addEventListener('input', function() {
    const bioPreview = document.getElementById('preview-bio');
    if (bioPreview) {
        bioPreview.innerHTML = this.value.replace(/\n/g, '<br>') || 'No bio available';
    }
});

// Form validation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const specialization = document.getElementById('specialization').value.trim();
    
    if (name.length < 2) {
        alert('Name must be at least 2 characters long');
        e.preventDefault();
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Please enter a valid email address');
        e.preventDefault();
        return false;
    }
    
    if (specialization.length < 2) {
        alert('Please enter your specialization');
        e.preventDefault();
        return false;
    }
    
    return true;
});
</script>

<style>
.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
}

.profile-preview {
    text-align: center;
}

.profile-avatar {
    margin-bottom: 1rem;
}

.profile-info {
    margin: 1.5rem 0;
    text-align: left;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
}

.info-item i {
    width: 16px;
    color: var(--primary);
}

.profile-bio {
    text-align: left;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
}

.profile-bio h6 {
    margin-bottom: 0.5rem;
    color: var(--dark);
}

.profile-bio p {
    font-size: 0.875rem;
    line-height: 1.5;
    margin: 0;
}

.quick-links {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.quick-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius);
    text-decoration: none;
    color: var(--dark);
    transition: var(--transition);
}

.quick-link:hover {
    background: var(--gray-50);
    border-color: var(--primary);
    color: var(--primary);
    text-decoration: none;
}

.quick-link i {
    color: var(--primary);
}

@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>