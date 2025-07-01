/**
 * GreenLife Wellness Center - Modern Navigation JavaScript
 * Enhanced with modern navbar functionality
 */

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

/**
 * Initialize the application
 */
function initializeApp() {
    // Initialize modern navbar
    initializeModernNavbar();
    
    // Initialize forms
    initializeForms();
    
    // Initialize mobile navigation
    initializeMobileNav();
    
    // Auto-hide alerts after 5 seconds
    autoHideAlerts();
    
    // Initialize scroll effects
    initializeScrollEffects();
    
    // Initialize date/time validation
    initializeDateTimeValidation();
    
    // Initialize form persistence
    initializeFormPersistence();
    
    // Initialize contact form validation
    initializeContactFormValidation();
    
    // Enhance accessibility
    enhanceFormAccessibility();
    
    // Initialize progress indicators
    initializeProgressIndicators();
    
    // Validate service consistency
    validateServiceConsistency();
}

/**
 * Initialize modern navbar functionality
 */
function initializeModernNavbar() {
    const navbar = document.getElementById('mainNavbar');
    const userToggle = document.getElementById('userToggle');
    const userDropdown = document.getElementById('userDropdown');
    const mobileToggle = document.getElementById('mobileToggle');
    const navbarMenu = document.getElementById('navbarMenu');
    
    // Navbar scroll effect
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }
    
    // User dropdown functionality
    if (userToggle && userDropdown) {
        userToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            userToggle.classList.toggle('active');
            userDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) {
                userToggle.classList.remove('active');
                userDropdown.classList.remove('show');
            }
        });
        
        // Close dropdown when pressing escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                userToggle.classList.remove('active');
                userDropdown.classList.remove('show');
            }
        });
    }
    
    // Mobile toggle functionality
    if (mobileToggle && navbarMenu) {
        mobileToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            mobileToggle.classList.toggle('active');
            navbarMenu.classList.toggle('show');
            
            // Prevent body scroll when menu is open
            if (navbarMenu.classList.contains('show')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });
        
        // Close mobile menu when clicking on nav links
        const navLinks = navbarMenu.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileToggle.classList.remove('active');
                navbarMenu.classList.remove('show');
                document.body.style.overflow = '';
            });
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!mobileToggle.contains(e.target) && !navbarMenu.contains(e.target)) {
                mobileToggle.classList.remove('active');
                navbarMenu.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    }
    
    // Active page highlighting
    highlightActivePage();
    
    // Smooth scroll for anchor links
    initializeSmoothScroll();
}

/**
 * Highlight active page in navigation
 */
function highlightActivePage() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && (href === currentPage || href.includes(currentPage))) {
            link.classList.add('active');
        }
    });
}

/**
 * Initialize smooth scroll for anchor links
 */
function initializeSmoothScroll() {
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                e.preventDefault();
                
                // Account for fixed navbar height
                const navbarHeight = document.getElementById('mainNavbar')?.offsetHeight || 80;
                const targetPosition = targetElement.offsetTop - navbarHeight - 20;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
                
                // Close mobile menu if open
                const mobileToggle = document.getElementById('mobileToggle');
                const navbarMenu = document.getElementById('navbarMenu');
                if (mobileToggle && navbarMenu) {
                    mobileToggle.classList.remove('active');
                    navbarMenu.classList.remove('show');
                    document.body.style.overflow = '';
                }
            }
        });
    });
}

/**
 * Initialize scroll effects for animations
 */
function initializeScrollEffects() {
    // Intersection Observer for fade-in animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe cards and sections for animation
    const animateElements = document.querySelectorAll('.card, .service-card, .therapist-card');
    animateElements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(element);
    });
}

/**
 * Initialize all forms
 */
function initializeForms() {
    // Handle all forms with proper submission
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Please wait...';
                submitBtn.disabled = true;
                
                // Re-enable button after 10 seconds in case of issues
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 10000);
            }
        });
    });
    
    // Enhanced form validation
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateField(this);
            }
        });
    });
}

/**
 * Validate individual form field
 */
function validateField(field) {
    const value = field.value.trim();
    const isRequired = field.hasAttribute('required');
    const fieldType = field.type;
    let isValid = true;
    let errorMessage = '';
    
    // Required field validation
    if (isRequired && !value) {
        isValid = false;
        errorMessage = 'This field is required';
    }
    
    // Email validation
    if (fieldType === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address';
        }
    }
    
    // Phone validation
    if (field.name === 'phone' && value) {
        const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
        if (!phoneRegex.test(value.replace(/[\s\-\(\)]/g, ''))) {
            isValid = false;
            errorMessage = 'Please enter a valid phone number';
        }
    }
    
    // Password strength validation
    if (fieldType === 'password' && value && field.name === 'password') {
        if (value.length < 8) {
            isValid = false;
            errorMessage = 'Password must be at least 8 characters long';
        }
    }
    
    // Update field appearance
    if (isValid) {
        field.classList.remove('error');
        field.style.borderColor = '';
        removeFieldError(field);
    } else {
        field.classList.add('error');
        field.style.borderColor = '#ef4444';
        showFieldError(field, errorMessage);
    }
    
    return isValid;
}

/**
 * Show field error message
 */
function showFieldError(field, message) {
    removeFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.color = '#ef4444';
    errorDiv.style.fontSize = '0.875rem';
    errorDiv.style.marginTop = '0.25rem';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
}

/**
 * Remove field error message
 */
function removeFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

/**
 * Initialize mobile navigation
 */
function initializeMobileNav() {
    // Legacy support for older navbar structure
    const toggler = document.querySelector('.navbar-toggler');
    const navMenu = document.querySelector('.navbar-nav');
    
    if (toggler && navMenu && !document.getElementById('mobileToggle')) {
        toggler.addEventListener('click', function() {
            navMenu.classList.toggle('show');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!toggler.contains(e.target) && !navMenu.contains(e.target)) {
                navMenu.classList.remove('show');
            }
        });
    }
}

/**
 * Auto-hide alert messages
 */
function autoHideAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // Add close button
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.className = 'btn-close';
        closeBtn.style.cssText = 'background: none; border: none; font-size: 1.5rem; opacity: 0.5; cursor: pointer; margin-left: auto; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;';
        
        closeBtn.addEventListener('click', function() {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        });
        
        alert.appendChild(closeBtn);
        
        // Auto-hide info alerts (instructions) after 20 seconds
        if (alert.classList.contains('alert-info')) {
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 500);
                }
            }, 20000); // 20 seconds for instructions
            return;
        }
        
        // Auto-hide success/error/warning alerts after 10 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 500);
            }
        }, 10000); // 10 seconds for other alerts
    });
}

/**
 * Show notification to user
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.style.cssText = 'position: fixed; top: 100px; right: 20px; z-index: 1050; min-width: 300px; animation: slideInRight 0.3s ease;';
    
    const iconMap = {
        success: 'fa-check-circle',
        danger: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    notification.innerHTML = `<i class="fas ${iconMap[type] || 'fa-info-circle'}"></i> ${message}`;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 5000);
}

/**
 * Validate form fields
 */
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!validateField(field)) {
            isValid = false;
        }
    });
    
    return isValid;
}

/**
 * Initialize date and time restrictions
 */
function initializeDateTimeValidation() {
    // Set minimum date for appointment date inputs
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (input.name === 'appointmentDate' || input.name === 'appointment_date' || input.name === 'date') {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            input.min = tomorrow.toISOString().split('T')[0];
            
            // Set maximum date to 3 months from now
            const maxDate = new Date();
            maxDate.setMonth(maxDate.getMonth() + 3);
            input.max = maxDate.toISOString().split('T')[0];
            
            // Add validation for Sundays
            input.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const dayOfWeek = selectedDate.getDay();
                
                if (dayOfWeek === 0) { // Sunday
                    showNotification('We are closed on Sundays. Please select another date.', 'warning');
                    this.value = '';
                    return;
                }
                
                if (selectedDate < tomorrow) {
                    showNotification('Please select a future date (at least 24 hours in advance).', 'warning');
                    this.value = '';
                    return;
                }
            });
        }
    });
}

/**
 * Validate business hours
 */
function validateBusinessHours(selectedDateTime) {
    const businessHours = {
        monday: {start: '09:00', end: '18:00'},
        tuesday: {start: '09:00', end: '18:00'},
        wednesday: {start: '09:00', end: '18:00'},
        thursday: {start: '09:00', end: '18:00'},
        friday: {start: '09:00', end: '18:00'},
        saturday: {start: '10:00', end: '16:00'},
        sunday: {closed: true}
    };
    
    const date = new Date(selectedDateTime);
    const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    const dayName = dayNames[date.getDay()];
    const timeString = date.toTimeString().substring(0, 5);
    
    if (businessHours[dayName].closed) {
        return false;
    }
    
    return timeString >= businessHours[dayName].start && timeString < businessHours[dayName].end;
}

/**
 * Enhanced form validation with better error messaging
 */
function validateFormFields(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    let firstInvalidField = null;
    
    requiredFields.forEach(field => {
        const fieldValid = validateField(field);
        if (!fieldValid && !firstInvalidField) {
            firstInvalidField = field;
        }
        isValid = isValid && fieldValid;
    });
    
    // Focus on first invalid field
    if (firstInvalidField) {
        firstInvalidField.focus();
        firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    return isValid;
}

/**
 * Add loading states for better UX
 */
function showLoadingState(element, isLoading = true) {
    if (isLoading) {
        element.classList.add('loading');
        element.disabled = true;
        if (element.tagName === 'SELECT') {
            element.innerHTML = '<option value="">Loading...</option>';
        } else if (element.tagName === 'BUTTON') {
            element.dataset.originalText = element.innerHTML;
            element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        }
    } else {
        element.classList.remove('loading');
        element.disabled = false;
        if (element.tagName === 'BUTTON' && element.dataset.originalText) {
            element.innerHTML = element.dataset.originalText;
        }
    }
}

/**
 * Form persistence for better UX
 */
function initializeFormPersistence() {
    const forms = document.querySelectorAll('form[data-persist="true"]');
    
    forms.forEach(form => {
        const formId = form.id || 'form_' + Math.random().toString(36).substr(2, 9);
        
        // Load saved data
        loadFormData(form, formId);
        
        // Save data on input
        form.addEventListener('input', function() {
            saveFormData(form, formId);
        });
        
        // Clear saved data on successful submission
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.classList.contains('error')) {
                clearFormData(formId);
            }
        });
    });
}

function saveFormData(form, formId) {
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        if (key !== 'csrf_token' && key !== 'password') {
            data[key] = value;
        }
    }
    
    localStorage.setItem('form_' + formId, JSON.stringify(data));
}

function loadFormData(form, formId) {
    const savedData = localStorage.getItem('form_' + formId);
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            Object.keys(data).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field && field.type !== 'password') {
                    field.value = data[key];
                }
            });
        } catch (e) {
            console.error('Error loading form data:', e);
        }
    }
}

function clearFormData(formId) {
    localStorage.removeItem('form_' + formId);
}

/**
 * Add CSS animations dynamically
 */
function addAnimationStyles() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        .focused .form-control {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.15);
        }
        
        .nav-link.active {
            background: rgba(5, 150, 105, 0.1) !important;
            color: var(--primary) !important;
        }
    `;
    document.head.appendChild(style);
}

// Initialize animations on load
addAnimationStyles();

/**
 * Enhanced contact form validation
 */
function initializeContactFormValidation() {
    const contactForm = document.getElementById('contactForm');
    if (!contactForm) return;
    
    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const name = document.getElementById('name');
        const email = document.getElementById('email');
        const subject = document.getElementById('subject');
        const message = document.getElementById('message');
        
        let isValid = true;
        
        // Clear previous errors
        clearValidationErrors();
        
        // Validate name
        if (!name.value.trim() || name.value.trim().length < 2) {
            showFieldError(name, 'Name must be at least 2 characters long');
            isValid = false;
        }
        
        // Validate email
        if (!email.value.trim()) {
            showFieldError(email, 'Email is required');
            isValid = false;
        } else if (!validateEmailAddress(email.value.trim())) {
            showFieldError(email, 'Please enter a valid email address');
            isValid = false;
        }
        
        // Validate subject
        if (!subject.value.trim() || subject.value.trim().length < 3) {
            showFieldError(subject, 'Subject must be at least 3 characters long');
            isValid = false;
        }
        
        // Validate message
        if (!message.value.trim() || message.value.trim().length < 10) {
            showFieldError(message, 'Message must be at least 10 characters long');
            isValid = false;
        }
        
        if (isValid) {
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            showLoadingState(submitBtn, true);
            
            // Submit form
            this.submit();
        } else {
            showNotification('Please correct the errors below', 'warning');
        }
    });
}

/**
 * Enhanced email validation
 */
function validateEmailAddress(email) {
    const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
    return emailRegex.test(email) && email.length <= 254;
}

/**
 * Clear validation errors
 */
function clearValidationErrors() {
    const errorMessages = document.querySelectorAll('.field-error');
    errorMessages.forEach(error => error.remove());
    
    const invalidFields = document.querySelectorAll('.form-control.error');
    invalidFields.forEach(field => {
        field.classList.remove('error');
        field.style.borderColor = '';
    });
}

/**
 * Validate appointment time slots
 */
function validateTimeSlot(date, time) {
    const selectedDateTime = new Date(`${date} ${time}`);
    const now = new Date();
    const businessHours = {
        0: null, // Sunday - closed
        1: { start: 9, end: 18 }, // Monday
        2: { start: 9, end: 18 }, // Tuesday
        3: { start: 9, end: 18 }, // Wednesday
        4: { start: 9, end: 18 }, // Thursday
        5: { start: 9, end: 18 }, // Friday
        6: { start: 10, end: 16 } // Saturday
    };
    
    const dayOfWeek = selectedDateTime.getDay();
    const hour = selectedDateTime.getHours();
    
    // Check if closed on this day
    if (!businessHours[dayOfWeek]) {
        return { valid: false, message: 'We are closed on Sundays' };
    }
    
    // Check business hours
    const dayHours = businessHours[dayOfWeek];
    if (hour < dayHours.start || hour >= dayHours.end) {
        return { 
            valid: false, 
            message: `Business hours are ${dayHours.start}:00 AM - ${dayHours.end}:00 PM` 
        };
    }
    
    // Check if at least 24 hours in advance
    const hoursInAdvance = (selectedDateTime - now) / (1000 * 60 * 60);
    if (hoursInAdvance < 24) {
        return { 
            valid: false, 
            message: 'Appointments must be booked at least 24 hours in advance' 
        };
    }
    
    return { valid: true };
}

/**
 * Handle service consistency checks
 */
function validateServiceConsistency() {
    const serviceCards = document.querySelectorAll('.service-card');
    const serviceOptions = document.querySelectorAll('#service option[value]');
    
    // Check if all displayed services are available in booking form
    serviceCards.forEach(card => {
        const serviceName = card.querySelector('h4')?.textContent.trim();
        const servicePrice = card.querySelector('.service-price')?.textContent;
        
        if (serviceName && servicePrice) {
            const matchingOption = Array.from(serviceOptions).find(option => 
                option.textContent.includes(serviceName)
            );
            
            if (!matchingOption) {
                console.warn(`Service "${serviceName}" displayed but not available for booking`);
            }
        }
    });
}

/**
 * Improve form accessibility
 */
function enhanceFormAccessibility() {
    // Add ARIA labels to form elements
    const formControls = document.querySelectorAll('.form-control');
    formControls.forEach(control => {
        const label = control.previousElementSibling;
        if (label && label.tagName === 'LABEL') {
            const labelText = label.textContent.trim();
            control.setAttribute('aria-label', labelText);
            
            // Add required indicator to screen readers
            if (control.hasAttribute('required')) {
                control.setAttribute('aria-required', 'true');
                label.setAttribute('aria-required', 'true');
            }
        }
    });
    
    // Add role and aria-live to alert containers
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.setAttribute('role', 'alert');
        alert.setAttribute('aria-live', 'polite');
    });
    
    // Improve button accessibility
    const buttons = document.querySelectorAll('button[type="submit"]');
    buttons.forEach(button => {
        if (!button.getAttribute('aria-label')) {
            const buttonText = button.textContent.trim();
            button.setAttribute('aria-label', `Submit ${buttonText}`);
        }
    });
}

/**
 * Add progress indicators for multi-step processes
 */
function initializeProgressIndicators() {
    const appointmentForm = document.getElementById('appointmentForm');
    if (!appointmentForm) return;
    
    const steps = [
        { field: 'service', label: 'Select Service' },
        { field: 'therapist', label: 'Choose Therapist' },
        { field: 'appointmentDate', label: 'Pick Date' },
        { field: 'appointmentTime', label: 'Select Time' }
    ];
    
    function updateProgress() {
        let completedSteps = 0;
        steps.forEach(step => {
            const field = document.getElementById(step.field);
            if (field && field.value) {
                completedSteps++;
            }
        });
        
        const progressPercentage = (completedSteps / steps.length) * 100;
        const progressBar = document.querySelector('.booking-progress');
        if (progressBar) {
            progressBar.style.width = `${progressPercentage}%`;
            progressBar.setAttribute('aria-valuenow', progressPercentage);
        }
    }
    
    // Add event listeners to form fields
    steps.forEach(step => {
        const field = document.getElementById(step.field);
        if (field) {
            field.addEventListener('change', updateProgress);
        }
    });
}

// Export functions for global use
window.showNotification = showNotification;
window.validateForm = validateForm;