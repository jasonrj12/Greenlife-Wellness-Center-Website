<?php
/**
 * Public Home Page - GreenLife Wellness Center
 * For non-logged-in users
 */
$pageTitle = "GreenLife Wellness Center - Your Path to Natural Healing";
require_once 'includes/header.php';
?>

    <!-- Hero Section -->
    <section id="home" class="hero-section position-relative" style="min-height: 100vh; background: linear-gradient(45deg, #0d2818 0%, #1a4c36 25%, #263d2f 50%, #0f3d2c 75%, #1b2f23 100%); overflow: hidden; margin-top: -80px; padding-top: 80px;">
        <!-- Diagonal stripes pattern -->
        <div class="position-absolute w-100 h-100" style="z-index: 1; background-image: repeating-linear-gradient(45deg, rgba(255,255,255,0.02) 0px, rgba(255,255,255,0.02) 2px, transparent 2px, transparent 40px); animation: slidePattern 15s linear infinite; top: 0;"></div>
        
        <!-- Organic blob shapes -->
        <div class="position-absolute w-100 h-100" style="z-index: 1; top: 0;">
            <div class="position-absolute" style="top: 15%; right: 20%; width: 300px; height: 200px; background: rgba(255,255,255,0.03); border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%; animation: morphBlob 8s ease-in-out infinite;"></div>
            <div class="position-absolute" style="bottom: 25%; left: 15%; width: 250px; height: 180px; background: rgba(255,255,255,0.04); border-radius: 40% 60% 70% 30% / 40% 70% 30% 60%; animation: morphBlob 10s ease-in-out infinite reverse;"></div>
            <div class="position-absolute" style="top: 40%; left: 60%; width: 150px; height: 150px; background: rgba(255,255,255,0.02); border-radius: 50% 30% 80% 20% / 60% 80% 20% 40%; animation: morphBlob 6s ease-in-out infinite;"></div>
        </div>
        
        <div class="container position-relative" style="z-index: 2;">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6" style="margin-top: 90px;">
                    <h1 class="display-4 fw-bold mb-4" style="font-family: 'Poppins', 'Inter', sans-serif; color: #ffffff; text-shadow: 0 2px 10px rgba(0,0,0,0.3); line-height: 1.2;">
                        Your Journey to <span style="color: #4ade80; font-weight: 700; background: linear-gradient(135deg, #4ade80, #22d3ee); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Natural Wellness</span> Starts Here
                    </h1>
                    <p class="lead mb-4" style="font-family: 'Inter', 'Segoe UI', sans-serif; color: #e2e8f0; font-size: 1.25rem; line-height: 1.6; font-weight: 400; text-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                        Experience holistic healing through our comprehensive wellness services. 
                        From yoga therapy to nutrition counseling, we're here to guide you towards optimal health.
                    </p>
                    <div class="d-flex justify-content-start gap-3 mb-5">
                        <a href="register.php" class="btn btn-light px-5 py-3 rounded-2 shadow-sm" id="startJourneyBtn" style="transition: all 0.3s; font-weight: 600; border: 2px solid transparent; background: linear-gradient(45deg, #fff, #f8f9fa); font-size: 1rem;">
                            <i class="fas fa-user-plus me-2"></i>Start Your Journey
                        </a>
                        <a href="#services" class="btn btn-outline-light px-5 py-3 rounded-2" id="exploreServicesBtn" style="transition: all 0.3s; font-weight: 600; border: 2px solid rgba(255,255,255,0.6); backdrop-filter: blur(10px); background: rgba(255,255,255,0.1); font-size: 1rem;">
                            <i class="fas fa-spa me-2"></i>Explore Services
                        </a>
                    </div>
                    
                    <!-- Stats Section -->
                    <div class="row g-4 mt-4">
                        <div class="col-4">
                            <div class="text-center">
                                <h3 class="fw-bold mb-1 text-white" style="font-size: 2rem;">500+</h3>
                                <p class="mb-0 text-white" style="font-size: 0.9rem;">Happy Clients</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <h3 class="fw-bold mb-1 text-white" style="font-size: 2rem;">6</h3>
                                <p class="mb-0 text-white" style="font-size: 0.9rem;">Wellness Services</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <h3 class="fw-bold mb-1 text-white" style="font-size: 2rem;">4+</h3>
                                <p class="mb-0 text-white" style="font-size: 0.9rem;">Expert Therapists</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image">
                        <img src="images/hero-wellness.jpg" alt="Wellness Center" class="img-fluid rounded-3 shadow-lg">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <style>
        @keyframes slidePattern {
            0% { transform: translateX(0); }
            100% { transform: translateX(40px); }
        }
        
        @keyframes morphBlob {
            0%, 100% { 
                border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
                transform: rotate(0deg) scale(1);
            }
            50% { 
                border-radius: 30% 70% 60% 40% / 50% 60% 40% 50%;
                transform: rotate(180deg) scale(1.1);
            }
        }
        
        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        #startJourneyBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            background: linear-gradient(45deg, #00b894, #00cec9);
            color: black;
        }
        
        #exploreServicesBtn:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            border-color: rgba(255,255,255,0.8);
        }
    </style>

    <!-- Services Section -->
    <section id="services" class="py-5" style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);">
        <div class="container">
            <div class="section-header">
                <h2 class="display-5 fw-bold">Our Wellness Services</h2>
                <p class="lead">Comprehensive holistic treatments for mind, body, and spirit</p>
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
                        <a href="register.php" class="btn btn-success">Register to Book</a>
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
                        <a href="register.php" class="btn btn-success">Register to Book</a>
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
                        <a href="register.php" class="btn btn-success">Register to Book</a>
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
                        <a href="register.php" class="btn btn-success">Register to Book</a>
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
                        <a href="register.php" class="btn btn-success">Register to Book</a>
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
                        <a href="register.php" class="btn btn-success">Register to Book</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Therapists Section -->
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
                        <p class="text-muted">Traditional medicine practitioner with 15+ years experience.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5" style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="section-header text-start">
                        <h2 class="display-5 fw-bold mb-4">About GreenLife Wellness Center</h2>
                        <p class="lead">
                            Founded with a vision to promote natural healing and holistic wellness, 
                            GreenLife Wellness Center has been serving the community for over a decade.
                        </p>
                    </div>
                    <p>
                        Our center combines traditional healing practices with modern wellness approaches 
                        to provide comprehensive care for mind, body, and spirit. We believe that true 
                        wellness comes from addressing the whole person, not just symptoms.
                    </p>
                    
                    <div class="row mt-4">
                        <div class="col-6">
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Certified Therapists</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Personalized Treatment</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Natural Methods</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Holistic Approach</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image">
                        <img src="images/about-center.webp" alt="Wellness Center" class="img-fluid rounded-3 shadow">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 cta-section">
        <div class="container text-center">
            <h2 class="display-5 fw-bold mb-4">Ready to Start Your Wellness Journey?</h2>
            <p class="lead mb-4">Join hundreds of satisfied clients who have transformed their lives through our services.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="contact_us.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-phone me-2"></i>Contact Us
                </a>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>