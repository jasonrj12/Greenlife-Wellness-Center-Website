</main>

    <!-- Enhanced Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5>
                        <img src="images/logo.png" alt="GreenLife Wellness Center Logo" style="width: 26px; height: 26px; object-fit: contain; margin-right: 8px;">
                        GreenLife Wellness Center
                    </h5>
                    <p>Your trusted partner in natural healing and holistic wellness. Experience the difference of personalized care that nurtures mind, body, and spirit.</p>
                    <div class="social-links mt-3">
                        <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="col-md-2 mb-4">
                    <h6><i class="fas fa-spa me-2"></i>Our Services</h6>
                    <ul>
                        <li><a href="index.php#services">Yoga Therapy</a></li>
                        <li><a href="index.php#services">Massage Therapy</a></li>
                        <li><a href="index.php#services">Meditation</a></li>
                        <li><a href="index.php#services">Nutrition</a></li>
                        <li><a href="index.php#services">Aromatherapy</a></li>
                        <li><a href="index.php#services">Acupuncture</a></li>
                    </ul>
                </div>
                
                <div class="col-md-2 mb-4">
                    <h6><i class="fas fa-link me-2"></i>Quick Links</h6>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="index.php#about">About Us</a></li>
                        <li><a href="appointment_booking.php">Book Appointment</a></li>
                        <li><a href="contact_us.php">Contact Us</a></li>
                        <?php if (!isLoggedIn()): ?>
                        <li><a href="register.php">Register</a></li>
                        <li><a href="login.php">Login</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="col-md-4 mb-4">
                    <h6><i class="fas fa-map-marker-alt me-2"></i>Contact Info</h6>
                    <div class="contact-info">
                        <p><i class="fas fa-map-marker-alt"></i> <strong>Address:</strong><br>
                           123 Wellness Street, Colombo 07, Sri Lanka</p>
                        <p><i class="fas fa-phone"></i> <strong>Phone:</strong><br>
                           +94 11 234 5678</p>
                        <p><i class="fas fa-envelope"></i> <strong>Email:</strong><br>
                           info@greenlifewellness.com</p>
                        <p><i class="fas fa-clock"></i> <strong>Hours:</strong><br>
                           <strong>Monday - Saturday:</strong> 9:00 AM - 6:00 PM<br>
                           <strong>Sunday:</strong> Closed</p>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-12">
                    <p class="text-center mb-0">
                        &copy; <?php echo date('Y'); ?> GreenLife Wellness Center. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>