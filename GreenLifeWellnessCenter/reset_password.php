<?php
/**
 * Password Reset Page
 * GreenLife Wellness Center
 */

$pageTitle = "Reset Password";
require_once 'includes/functions.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $resetToken = $_POST['token'] ?? '';
    
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } elseif (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please fill in all fields';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        $result = resetPassword($resetToken, $newPassword);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center min-vh-100 align-items-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="text-success">
                            <i class="fas fa-lock me-2"></i>Reset Password
                        </h2>
                        <p class="text-muted">Create a new password for your account</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            <br><a href="login.php" class="btn btn-success mt-3">Login Now</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required
                                           placeholder="Enter new password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-requirements mt-2">
                                    <small class="text-muted">Password must contain:</small>
                                    <ul class="list-unstyled mt-1" style="font-size: 0.85em;">
                                        <li id="req-length"><i class="fas fa-times text-danger"></i> At least 8 characters</li>
                                        <li id="req-lower"><i class="fas fa-times text-danger"></i> One lowercase English letter (a-z)</li>
                                        <li id="req-upper"><i class="fas fa-times text-danger"></i> One uppercase English letter (A-Z)</li>
                                        <li id="req-number"><i class="fas fa-times text-danger"></i> One number (0-9)</li>
                                        <li id="req-chars"><i class="fas fa-times text-danger"></i> Only English letters, numbers, and @$!%*?&._-</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                                           placeholder="Confirm new password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="password-match-feedback" class="mt-1"></div>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-key me-2"></i>Reset Password
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <a href="login.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password strength validation
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/;
    
    // Update password requirements feedback
    document.getElementById('req-length').innerHTML = password.length >= 8 ? 
        '<i class="fas fa-check text-success"></i> At least 8 characters' : 
        '<i class="fas fa-times text-danger"></i> At least 8 characters';
    
    document.getElementById('req-lower').innerHTML = /[a-z]/.test(password) ? 
        '<i class="fas fa-check text-success"></i> One lowercase English letter (a-z)' : 
        '<i class="fas fa-times text-danger"></i> One lowercase English letter (a-z)';
    
    document.getElementById('req-upper').innerHTML = /[A-Z]/.test(password) ? 
        '<i class="fas fa-check text-success"></i> One uppercase English letter (A-Z)' : 
        '<i class="fas fa-times text-danger"></i> One uppercase English letter (A-Z)';
    
    document.getElementById('req-number').innerHTML = /\d/.test(password) ? 
        '<i class="fas fa-check text-success"></i> One number (0-9)' : 
        '<i class="fas fa-times text-danger"></i> One number (0-9)';
    
    const specialChars = /[@$!%*?&._-]/;
    document.getElementById('req-chars').innerHTML = specialChars.test(password) ? 
        '<i class="fas fa-check text-success"></i> Only English letters, numbers, and @$!%*?&._-' : 
        '<i class="fas fa-times text-danger"></i> Only English letters, numbers, and @$!%*?&._-';
    
    if (password.length > 0) {
        if (regex.test(password)) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        }
    } else {
        this.classList.remove('is-valid', 'is-invalid');
    }
});

// Confirm password validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword.length > 0) {
        if (password === confirmPassword) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
            document.getElementById('password-match-feedback').innerHTML = 
                '<small class="text-success"><i class="fas fa-check-circle"></i> Passwords match</small>';
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
            document.getElementById('password-match-feedback').innerHTML = 
                '<small class="text-danger"><i class="fas fa-times-circle"></i> Passwords do not match</small>';
        }
    } else {
        this.classList.remove('is-valid', 'is-invalid');
        document.getElementById('password-match-feedback').innerHTML = '';
    }
});

// Toggle password visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordField = document.getElementById('password');
    const passwordType = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordField.setAttribute('type', passwordType);
    this.querySelector('i').classList.toggle('fa-eye-slash');
});

document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
    const confirmPasswordField = document.getElementById('confirm_password');
    const passwordType = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
    confirmPasswordField.setAttribute('type', passwordType);
    this.querySelector('i').classList.toggle('fa-eye-slash');
});
</script>

<?php require_once 'includes/footer.php'; ?>