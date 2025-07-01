<?php
/**
 * Dashboard Home - GreenLife Wellness Center
 * Personalized home page for logged-in users
 */

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get user information
$user = getCurrentUser();
$userId = $_SESSION['user_id'];

// Get user's recent appointments
try {
    $conn = getDbConnection();
    
    // Get upcoming appointments
    $stmt = $conn->prepare("
        SELECT a.*, s.service_name, s.price, t.name as therapist_name 
        FROM appointments a 
        JOIN services s ON a.service_id = s.service_id 
        JOIN therapists t ON a.therapist_id = t.therapist_id 
        WHERE a.user_id = ? AND a.appointment_date >= CURDATE() 
        ORDER BY a.appointment_date ASC, a.appointment_time ASC 
        LIMIT 3
    ");
    $stmt->execute([$userId]);
    $upcomingAppointments = $stmt->fetchAll();
    
    // Get total appointments count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalAppointments = $stmt->fetch()['total'];
    
    // Get favorite service (most booked)
    $stmt = $conn->prepare("
        SELECT s.service_name, COUNT(*) as booking_count 
        FROM appointments a 
        JOIN services s ON a.service_id = s.service_id 
        WHERE a.user_id = ? 
        GROUP BY a.service_id 
        ORDER BY booking_count DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $favoriteService = $stmt->fetch();
    
} catch (Exception $e) {
    error_log("Error fetching user dashboard data: " . $e->getMessage());
    $upcomingAppointments = [];
    $totalAppointments = 0;
    $favoriteService = null;
}

$pageTitle = "Dashboard";
require_once 'includes/header.php';
?>

    <!-- Welcome Section -->
    <section class="py-5" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold text-white mb-3">
                        Welcome back, <?php echo htmlspecialchars($user['name']); ?>! 
                    </h1>
                    <p class="lead text-white mb-4">
                        Continue your wellness journey with us. Check your upcoming appointments and explore our services.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="appointment_booking.php" class="btn btn-light btn-lg">
                        <i class="fas fa-calendar-plus me-2"></i>Book New Appointment
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Stats Dashboard -->
    <section class="py-4">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="text-success mb-2">
                                <i class="fas fa-calendar-check fa-2x"></i>
                            </div>
                            <h3 class="text-success mb-0"><?php echo $totalAppointments; ?></h3>
                            <small class="text-muted">Total Appointments</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="text-success mb-2">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <h3 class="text-success mb-0"><?php echo count($upcomingAppointments); ?></h3>
                            <small class="text-muted">Upcoming Appointments</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="text-success mb-2">
                                <i class="fas fa-heart fa-2x"></i>
                            </div>
                            <h5 class="text-success mb-0"><?php echo $favoriteService ? $favoriteService['service_name'] : 'None yet'; ?></h5>
                            <small class="text-muted">Favorite Service</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Upcoming Appointments -->
    <?php if (!empty($upcomingAppointments)): ?>
    <section class="py-4">
        <div class="container">
            <h3 class="mb-4">Your Upcoming Appointments</h3>
            <div class="row g-4">
                <?php foreach ($upcomingAppointments as $appointment): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title text-success"><?php echo htmlspecialchars($appointment['service_name']); ?></h5>
                                <?php
                                // Display status badge based on actual appointment status
                                $statusClass = '';
                                $statusText = '';
                                
                                switch(strtolower($appointment['status'])) {
                                    case 'pending':
                                        $statusClass = 'bg-warning text-dark';
                                        $statusText = 'Pending';
                                        break;
                                    case 'confirmed':
                                        $statusClass = 'bg-success';
                                        $statusText = 'Confirmed';
                                        break;
                                    case 'completed':
                                        $statusClass = 'bg-primary';
                                        $statusText = 'Completed';
                                        break;
                                    case 'canceled':
                                        $statusClass = 'bg-danger';
                                        $statusText = 'Canceled';
                                        break;
                                    default:
                                        $statusClass = 'bg-secondary';
                                        $statusText = ucfirst($appointment['status']);
                                }
                                ?>
                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            </div>
                            <p class="card-text">
                                <i class="fas fa-user-md me-2"></i><?php echo htmlspecialchars($appointment['therapist_name']); ?><br>
                                <i class="fas fa-calendar me-2"></i><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?><br>
                                <i class="fas fa-clock me-2"></i><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?><br>
                                <i class="fas fa-tag me-2"></i>Rs. <?php echo number_format($appointment['price']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="view_appointments.php" class="btn btn-outline-success">View All Appointments</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Services Section (same as public but with personalized buttons) -->
    <section id="services" class="py-5" style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);">
        <div class="container">
            <div class="section-header">
                <h2 class="display-5 fw-bold">Our Wellness Services</h2>
                <p class="lead">Book your next wellness session with just a click</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="service-card h-100">
                        <div class="service-icon">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <h4>Yoga Therapy</h4>
                        <p>Personalized yoga sessions designed to improve flexibility, reduce stress, and enhance overall well-being.</p>
                        <div class="service-price">Rs. 5,000</div>
                        <a href="appointment_booking.php?service=1" class="btn btn-success">Book Session</a>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="service-card h-100">
                        <div class="service-icon">
                            <i class="fas fa-spa"></i>
                        </div>
                        <h4>Massage Therapy</h4>
                        <p>Therapeutic massage treatments to relieve muscle tension, improve circulation, and promote relaxation.</p>
                        <div class="service-price">Rs. 7,500</div>
                        <a href="appointment_booking.php?service=2" class="btn btn-success">Book Session</a>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="service-card h-100">
                        <div class="service-icon">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h4>Meditation Counseling</h4>
                        <p>Learn mindfulness techniques and meditation practices to achieve mental clarity and emotional balance.</p>
                        <div class="service-price">Rs. 4,000</div>
                        <a href="appointment_booking.php?service=3" class="btn btn-success">Book Session</a>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="service-card h-100">
                        <div class="service-icon">
                            <i class="fas fa-apple-alt"></i>
                        </div>
                        <h4>Nutrition Consultation</h4>
                        <p>Personalized dietary planning and nutritional guidance to support your health goals.</p>
                        <div class="service-price">Rs. 6,000</div>
                        <a href="appointment_booking.php?service=4" class="btn btn-success">Book Session</a>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="service-card h-100">
                        <div class="service-icon">
                            <i class="fas fa-seedling"></i>
                        </div>
                        <h4>Aromatherapy</h4>
                        <p>Essential oil treatments that promote healing, relaxation, and emotional well-being.</p>
                        <div class="service-price">Rs. 3,500</div>
                        <a href="appointment_booking.php?service=5" class="btn btn-success">Book Session</a>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="service-card h-100">
                        <div class="service-icon">
                            <i class="fas fa-hand-holding-medical"></i>
                        </div>
                        <h4>Acupuncture</h4>
                        <p>Traditional acupuncture treatments for pain relief, stress reduction, and overall health improvement.</p>
                        <div class="service-price">Rs. 8,000</div>
                        <a href="appointment_booking.php?service=6" class="btn btn-success">Book Session</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Therapists Section (same as public) -->
    <section id="therapists" class="py-5">
        <div class="container">
            <div class="section-header">
                <h2 class="display-5 fw-bold">Meet Our Expert Therapists</h2>
                <p class="lead">Experienced professionals dedicated to your wellness journey</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="therapist-card text-center">
                        <div class="therapist-image">
                            <img src="images/therapist-1.jpg" alt="Dr. Samara Perera" class="img-fluid rounded-circle">
                        </div>
                        <h5 class="mt-3">Dr. Samara Perera</h5>
                        <p class="text-success">Yoga & Meditation Specialist</p>
                        <p class="small text-muted">10+ years experience in holistic healing and mindfulness practices.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="therapist-card text-center">
                        <div class="therapist-image">
                            <img src="images/therapist-2.jpg" alt="Dr. Nimal Silva" class="img-fluid rounded-circle">
                        </div>
                        <h5 class="mt-3">Dr. Nimal Silva</h5>
                        <p class="text-success">Massage Therapy Expert</p>
                        <p class="small text-muted">Certified in multiple massage techniques with 8+ years experience.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="therapist-card text-center">
                        <div class="therapist-image">
                            <img src="images/therapist-3.jpg" alt="Dr. Priya Fernando" class="img-fluid rounded-circle">
                        </div>
                        <h5 class="mt-3">Dr. Priya Fernando</h5>
                        <p class="text-success">Nutrition & Wellness Coach</p>
                        <p class="small text-muted">Licensed nutritionist with expertise in holistic dietary planning.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="therapist-card text-center">
                        <div class="therapist-image">
                            <img src="images/therapist-4.jpg" alt="Dr. Kamal Rajapakse" class="img-fluid rounded-circle">
                        </div>
                        <h5 class="mt-3">Dr. Kamal Rajapakse</h5>
                        <p class="text-success">Acupuncture Specialist</p>
                        <p class="small text-muted">Traditional medicine practitioner with 15+ years experience.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>