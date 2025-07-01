<?php
/**
 * Client Profile Page
 * GreenLife Wellness Center
 */

$pageTitle = "My Profile";
require_once 'includes/functions.php';

// Require client login
requireLogin();
if ($_SESSION['role'] !== 'client') {
    header('Location: login.php?error=access_denied');
    exit();
}

// Get user information
$user = getUserById($_SESSION['user_id']);

// Handle profile updates
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $name = sanitizeInput($_POST['name'] ?? '');
        $phone = sanitizeInput($_POST['phone_number'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');

        if (empty($name)) {
            $error = 'Full name is required.';
        } else {
            $result = updateUserProfile($_SESSION['user_id'], $name, $phone, $address);
            if ($result) {
                $success = 'Profile updated successfully!';
                // Refresh user data
                $user = getUserById($_SESSION['user_id']);
            } else {
                $error = 'Failed to update profile. Please try again.';
            }
        }
    }
}


require_once 'includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-user-circle me-2"></i>My Profile</h1>
            <p>View and update your personal information.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h4>Your Details</h4>
                <div class="profile-actions">
                    <button class="btn btn-sm btn-primary btn-edit-profile" onclick="showEditProfileForm()">
                        <div class="icon-container">
                            <i class="fas fa-edit"></i>
                        </div>
                        <span>Edit Profile</span>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2 text-center">
                        <i class="fas fa-user-circle fa-6x text-success"></i>
                    </div>
                    <div class="col-md-5">
                        <p><strong>Full Name:</strong><br> <?php echo htmlspecialchars($user['name']); ?></p>
                        <p><strong>Email Address:</strong><br> <?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div class="col-md-5">
                        <p><strong>Phone Number:</strong><br> <?php echo htmlspecialchars($user['phone_number'] ?: 'Not provided'); ?></p>
                        <p><strong>Address:</strong><br> <?php echo nl2br(htmlspecialchars($user['address'] ?: 'Not provided')); ?></p>
                    </div>
                </div>
                <hr>
                <p class="text-muted">Member Since: <?php echo formatDate($user['created_at']); ?></p>
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
            <form id="profileForm" method="POST" action="client_profile.php">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="update_profile" value="1">
                
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

// Close modal when clicking outside
document.getElementById('profileModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeProfileModal();
    }
});
</script>

<style>
    .btn-edit-profile {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    
    .btn-edit-profile .icon-container {
        width: 24px;
        height: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin-right: 8px;
        background: rgba(255, 255, 255, 0.2);
    }
    
    .btn-edit-profile .icon-container i {
        font-size: 0.875rem;
        color: #fff;
    }
</style>

<?php require_once 'includes/footer.php'; ?>
