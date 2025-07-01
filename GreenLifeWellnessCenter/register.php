<?php
/**
 * User Registration Page
 * GreenLife Wellness Center
 */

$pageTitle = "Register";
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: client_dashboard.php');
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    
    // Validation
    if (empty($name) || strlen($name) < 2) {
        $error = 'Please enter a valid name (at least 2 characters)';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address';
    } elseif (!validatePassword($password)) {
        $error = 'Password must be at least 8 characters and contain at least one uppercase letter, one lowercase letter, and one number';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (!validatePhone($phone)) {
        $error = 'Please enter a valid Sri Lankan phone number';
    } else {
        // Attempt registration
        $result = registerUser($name, $email, $password, $phone, $address);
        
        if ($result['success']) {
            $success = $result['message'] . ' You can now login.';
        } else {
            $error = $result['message'];
        }
    }
}

require_once 'includes/header.php';
?>

<div class="register-page">
    <div class="container">
        <div class="form-container">
            <div class="form-header">
                <div class="brand-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2>Create Your Account</h2>
                <p>Join GreenLife Wellness Center and start your wellness journey today</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <div class="success-actions">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Login Now
                    </a>
                    <a href="index.php" class="btn btn-outline-primary">
                        <i class="fas fa-home"></i>
                        Back to Home
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="" class="registration-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">
                                <i class="fas fa-user"></i>
                                Full Name *
                            </label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($name ?? ''); ?>" 
                                   placeholder="Enter your full name">
                            <div class="validation-error-message"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i>
                                Email Address *
                            </label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                                   placeholder="Enter your email address">
                            <div class="validation-error-message"></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">
                                <i class="fas fa-phone"></i>
                                Phone Number *
                            </label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($phone ?? ''); ?>" 
                                   placeholder="0771234567 or +94771234567">
                            <small class="form-text">Sri Lankan phone number format (e.g., +94771234567)</small>
                            <div class="validation-error-message"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">
                                <i class="fas fa-map-marker-alt"></i>
                                Address
                            </label>
                            <textarea id="address" name="address" class="form-control" rows="3" 
                                      placeholder="Enter your address"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">
                                <i class="fas fa-lock"></i>
                                Password *
                            </label>
                            <div class="password-input-container">
                                <input type="password" id="password" name="password" class="form-control" 
                                       placeholder="Enter a strong password">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="form-text">Must be at least 8 characters with uppercase, lowercase, and number</small>
                            <div class="validation-error-message"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmPassword">
                                <i class="fas fa-lock"></i>
                                Confirm Password *
                            </label>
                            <div class="password-input-container">
                                <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" 
                                       placeholder="Confirm your password">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirmPassword')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="validation-error-message"></div>
                        </div>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label class="checkbox-container">
                            <input type="checkbox" required>
                            <span class="checkmark"></span>
                            <span class="checkbox-text">
                                I agree to the <a href="#" class="terms-link">Terms of Service</a> and 
                                <a href="#" class="terms-link">Privacy Policy</a>
                            </span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-register">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="bottom-section">
                <p>Already have an account? <a href="login.php" class="login-link">Login here</a></p>
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const container = input.parentElement;
    const button = container.querySelector('.password-toggle');
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Real-time password validation
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasMinLength = password.length >= 8;
    
    const isValid = hasUpper && hasLower && hasNumber && hasMinLength;
    
    if (password.length > 0) {
        this.style.borderColor = isValid ? 'var(--success)' : 'var(--danger)';
    } else {
        this.style.borderColor = '#e2e8f0';
    }
});

// Password confirmation validation
document.getElementById('confirmPassword').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword.length > 0) {
        this.style.borderColor = password === confirmPassword ? 'var(--success)' : 'var(--danger)';
    } else {
        this.style.borderColor = '#e2e8f0';
    }
});

const form = document.querySelector('.registration-form');
const name = document.getElementById('name');
const email = document.getElementById('email');
const phone = document.getElementById('phone');
const password = document.getElementById('password');
const confirmPassword = document.getElementById('confirmPassword');

const validateName = () => {
    const errorContainer = name.closest('.form-group').querySelector('.validation-error-message');
    let message = '';
    if (name.value && name.value.trim().length < 2) {
        message = 'Please enter a valid name (at least 2 characters).';
    }
    errorContainer.textContent = message;
    name.classList.toggle('is-invalid', !!message);
    if(name.value && !message) name.classList.add('is-valid');
    return !message;
};

const validateEmail = () => {
    const errorContainer = email.closest('.form-group').querySelector('.validation-error-message');
    let message = '';
    if (email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        message = 'Please enter a valid email address.';
    }
    errorContainer.textContent = message;
    email.classList.toggle('is-invalid', !!message);
    if(email.value && !message) email.classList.add('is-valid');
    return !message;
};

const validatePhone = () => {
    const errorContainer = phone.closest('.form-group').querySelector('.validation-error-message');
    let message = '';
    if (phone.value && !/^(\+94|0)[0-9]{9}$/.test(phone.value.replace(/\s|-/g, ''))) {
        message = 'Please enter a valid Sri Lankan phone number.';
    }
    errorContainer.textContent = message;
    phone.classList.toggle('is-invalid', !!message);
    if(phone.value && !message) phone.classList.add('is-valid');
    return !message;
};

const validatePassword = () => {
    const errorContainer = password.closest('.form-group').querySelector('.validation-error-message');
    let message = '';
    // Remove the duplicate password validation message - server-side handles this
    errorContainer.textContent = message;
    
    // Keep visual validation styling
    if (password.value) {
        const hasUpper = /[A-Z]/.test(password.value);
        const hasLower = /[a-z]/.test(password.value);
        const hasNumber = /[0-9]/.test(password.value);
        const hasMinLength = password.value.length >= 8;
        const isValid = hasUpper && hasLower && hasNumber && hasMinLength;
        
        password.classList.toggle('is-invalid', !isValid);
        if (isValid) password.classList.add('is-valid');
        return isValid;
    }
    
    return true; // Don't show error for empty field during typing
};

const validateConfirmPassword = () => {
    const errorContainer = confirmPassword.closest('.form-group').querySelector('.validation-error-message');
    let message = '';
    if (password.value && confirmPassword.value && password.value !== confirmPassword.value) {
        message = 'Passwords do not match.';
    }
    errorContainer.textContent = message;
    confirmPassword.classList.toggle('is-invalid', !!message);
    if(confirmPassword.value && !message) confirmPassword.classList.add('is-valid');
    return !message;
};

if (form) {
    form.addEventListener('submit', (e) => {
        // Reset all validation states
        [name, email, phone, password, confirmPassword].forEach(field => {
            field.classList.remove('is-valid', 'is-invalid');
            const errorContainer = field.closest('.form-group').querySelector('.validation-error-message');
            if (errorContainer) errorContainer.textContent = '';
        });

        let isValid = true;

        // Validate Name
        if (!name.value.trim()) {
            name.classList.add('is-invalid');
            isValid = false;
        } else {
            isValid = validateName() && isValid;
        }

        // Validate Email
        if (!email.value.trim()) {
            email.classList.add('is-invalid');
            isValid = false;
        } else {
            isValid = validateEmail() && isValid;
        }

        // Validate Phone
        if (!phone.value.trim()) {
            phone.classList.add('is-invalid');
            isValid = false;
        } else {
            isValid = validatePhone() && isValid;
        }

        // Validate Password
        if (!password.value) {
            password.classList.add('is-invalid');
            isValid = false;
        } else {
            isValid = validatePassword() && isValid;
        }

        // Validate Confirm Password
        if (!confirmPassword.value) {
            confirmPassword.classList.add('is-invalid');
            isValid = false;
        } else {
            isValid = validateConfirmPassword() && isValid;
        }

        if (!isValid) {
            e.preventDefault();
        }
    });

    // Real-time validation for all fields
    name.addEventListener('input', validateName);
    email.addEventListener('input', validateEmail);
    phone.addEventListener('input', validatePhone);
    password.addEventListener('input', () => {
        validatePassword();
        validateConfirmPassword();
    });
    confirmPassword.addEventListener('input', validateConfirmPassword);
}
</script>

<?php require_once 'includes/footer.php'; ?>