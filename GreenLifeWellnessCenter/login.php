<?php
/**
 * User Login Page
 * GreenLife Wellness Center
 */

$pageTitle = "Login";
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin':
            header('Location: admin_dashboard.php');
            break;
        case 'therapist':
            header('Location: therapist_dashboard.php');
            break;
        default:
            header('Location: client_dashboard.php');
    }
    exit();
}

$error = '';
$success = '';

// Check for success messages
if (isset($_GET['registered'])) {
    $success = 'Registration successful! Please login to continue.';
}

if (isset($_GET['reset'])) {
    $success = 'Password reset successful! Please login with your new password.';
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'access_denied':
            $error = 'Access denied. Please login to continue.';
            break;
        case 'session_expired':
            $error = 'Your session has expired. Please login again.';
            break;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address';
    } elseif (empty($password)) {
        $error = 'Please enter your password';
    } else {
        // Attempt login (removed remember parameter)
        $result = loginUser($email, $password);
        
        if ($result['success']) {
            // Redirect based on user role
            switch ($result['role']) {
                case 'admin':
                    header('Location: admin_dashboard.php');
                    break;
                case 'therapist':
                    header('Location: therapist_dashboard.php');
                    break;
                default:
                    header('Location: client_dashboard.php');
            }
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

require_once 'includes/header.php';
?>

<div class="login-page">
    <div class="container">
        <!-- Main Login Form -->
        <div class="form-container">
            <h2>Welcome Back</h2>
            <p>Sign in to your GreenLife Wellness account</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                           placeholder="Enter your email address" required>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter your password" required>
                </div>
                
                <div class="checkbox-row">
                    <div class="checkbox-container">
                        <input type="checkbox" id="showPassword"> 
                        <span>Show password</span>
                    </div>
                    <a href="forgot_password.php" class="forgot-link">
                        <i class="fas fa-question-circle"></i>
                        Forgot password?
                    </a>
                </div>
                
                <button type="submit" class="btn btn-primary" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>
            
            <div class="bottom-section">
                <p>Don't have an account? 
                   <a href="register.php">
                       <i class="fas fa-user-plus"></i>
                       Register here
                   </a>
                </p>
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const button = document.getElementById('loginBtn');
    const passwordInput = document.getElementById('password');
    const showPasswordCheckbox = document.getElementById('showPassword');
    
    // Show/hide password functionality
    showPasswordCheckbox.addEventListener('change', function() {
        if (this.checked) {
            passwordInput.type = 'text';
        } else {
            passwordInput.type = 'password';
        }
    });
    
    // Form submission with loading state
    form.addEventListener('submit', function() {
        button.classList.add('loading');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
    });
    
    // Form validation feedback
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value.trim() === '' && this.hasAttribute('required')) {
                this.style.borderColor = 'var(--danger)';
            } else if (this.type === 'email' && this.value.trim() !== '') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (emailRegex.test(this.value)) {
                    this.style.borderColor = 'var(--success)';
                } else {
                    this.style.borderColor = 'var(--danger)';
                }
            } else if (this.value.trim() !== '') {
                this.style.borderColor = 'var(--success)';
            }
        });
        
        input.addEventListener('focus', function() {
            this.style.borderColor = 'var(--primary)';
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>