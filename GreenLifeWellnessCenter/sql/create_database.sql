-- GreenLife Wellness Center Database Schema
-- Complete database structure with all required tables and data

-- Create database
CREATE DATABASE IF NOT EXISTS greenlife_wellness;
USE greenlife_wellness;

-- Users table with enhanced fields
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'therapist', 'client') DEFAULT 'client',
    phone_number VARCHAR(15),
    address TEXT,
    profile_picture VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login DATETIME,
    reset_token VARCHAR(255),
    reset_expires DATETIME,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Services table with comprehensive fields
CREATE TABLE services (
    service_id INT PRIMARY KEY AUTO_INCREMENT,
    service_name VARCHAR(100) NOT NULL,
    description TEXT,
    duration INT DEFAULT 60, -- Duration in minutes
    price DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Therapists table with detailed information
CREATE TABLE therapists (
    therapist_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100),
    experience_years INT DEFAULT 0,
    qualification TEXT,
    bio TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Appointments table with enhanced tracking
CREATE TABLE appointments (
    appointment_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    therapist_id INT NOT NULL,
    service_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'canceled') DEFAULT 'pending',
    notes TEXT,
    client_notes TEXT,
    therapist_notes TEXT,
    admin_notes TEXT,
    reminder_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (therapist_id) REFERENCES therapists(therapist_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE CASCADE,
    UNIQUE KEY unique_appointment (therapist_id, appointment_date, appointment_time)
);

-- Inquiries table with priority system
CREATE TABLE inquiries (
    inquiry_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    name VARCHAR(100),
    email VARCHAR(100),
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    admin_response TEXT,
    responded_by INT,
    responded_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (responded_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Wellness Journal for client tracking
CREATE TABLE wellness_journal (
    journal_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    mood_rating INT CHECK (mood_rating BETWEEN 1 AND 10),
    symptoms TEXT,
    medications TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Therapist availability
CREATE TABLE therapist_availability (
    availability_id INT PRIMARY KEY AUTO_INCREMENT,
    therapist_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    start_time TIME,
    end_time TIME,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (therapist_id) REFERENCES therapists(therapist_id) ON DELETE CASCADE
);

-- Notifications table for user alerts
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('system', 'appointment', 'inquiry', 'promotion') DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Admin logs for tracking administrative actions
CREATE TABLE admin_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(200) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Email logs for tracking email communications
CREATE TABLE email_logs (
    email_id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System settings for configuration
CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (name, email, password, role, phone_number, address) VALUES 
('Administrator', 'admin@greenlife.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '+94112345678', '123 Wellness Street, Colombo 07');

-- Insert sample services
INSERT INTO services (service_name, description, duration, price) VALUES 
('Yoga Therapy', 'Personalized yoga sessions designed to improve flexibility, reduce stress, and enhance overall well-being through ancient practices and modern techniques.', 60, 5000.00),
('Massage Therapy', 'Therapeutic massage treatments to relieve muscle tension, improve circulation, and promote deep relaxation using various massage techniques.', 90, 7500.00),
('Meditation Counseling', 'Learn mindfulness techniques and meditation practices to achieve mental clarity, emotional balance, and inner peace.', 45, 4000.00),
('Nutrition Consultation', 'Personalized dietary planning and nutritional guidance to support your health goals with comprehensive meal planning.', 60, 6000.00),
('Aromatherapy', 'Essential oil treatments that promote healing, relaxation, and emotional well-being through carefully selected aromatic compounds.', 75, 3500.00),
('Acupuncture', 'Traditional acupuncture treatments for pain relief, stress reduction, and overall health improvement using sterile needles.', 60, 8000.00);

-- Insert sample therapist users
INSERT INTO users (name, email, password, role, phone_number, address) VALUES 
('Dr. Samara Perera', 'samara@greenlife.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'therapist', '+94112345680', '456 Healing Avenue, Colombo 05'),
('Dr. Nimal Silva', 'nimal@greenlife.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'therapist', '+94112345681', '789 Therapy Lane, Colombo 03'),
('Dr. Priya Fernando', 'priya@greenlife.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'therapist', '+94112345682', '321 Nutrition Street, Colombo 04'),
('Dr. Kamal Rajapakse', 'kamal@greenlife.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'therapist', '+94112345683', '654 Acupuncture Road, Colombo 02');

-- Insert therapist records
INSERT INTO therapists (user_id, name, specialization, experience_years, qualification, bio) VALUES 
((SELECT user_id FROM users WHERE email = 'samara@greenlife.com'), 'Dr. Samara Perera', 'Yoga & Meditation Specialist', 10, 'Certified Yoga Instructor, Master in Mindfulness', 'Dr. Samara has over 10 years of experience in holistic healing and mindfulness practices.'),
((SELECT user_id FROM users WHERE email = 'nimal@greenlife.com'), 'Dr. Nimal Silva', 'Massage Therapy Expert', 8, 'Licensed Massage Therapist, Sports Medicine Certificate', 'Dr. Nimal is certified in multiple massage techniques with 8+ years of experience helping clients achieve pain relief and relaxation.'),
((SELECT user_id FROM users WHERE email = 'priya@greenlife.com'), 'Dr. Priya Fernando', 'Nutrition & Wellness Coach', 12, 'Licensed Nutritionist, PhD in Dietetics', 'Dr. Priya is a licensed nutritionist with expertise in holistic dietary planning and has helped hundreds of clients achieve their health goals.'),
((SELECT user_id FROM users WHERE email = 'kamal@greenlife.com'), 'Dr. Kamal Rajapakse', 'Acupuncture Specialist', 15, 'Licensed Acupuncturist, Traditional Medicine Practitioner', 'Dr. Kamal is a traditional medicine practitioner with 15+ years of experience in acupuncture and holistic healing methods.');

-- Insert sample client for testing
INSERT INTO users (name, email, password, role, phone_number, address) VALUES 
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', '+94771234567', '123 Client Street, Colombo 01'),
('Jane Smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', '+94771234568', '456 Client Avenue, Colombo 06');

-- Insert default therapist availability (9 AM to 6 PM, Monday to Saturday)
INSERT INTO therapist_availability (therapist_id, day_of_week, start_time, end_time) 
SELECT t.therapist_id, 'Monday', '09:00:00', '18:00:00' FROM therapists t
UNION ALL
SELECT t.therapist_id, 'Tuesday', '09:00:00', '18:00:00' FROM therapists t
UNION ALL
SELECT t.therapist_id, 'Wednesday', '09:00:00', '18:00:00' FROM therapists t
UNION ALL
SELECT t.therapist_id, 'Thursday', '09:00:00', '18:00:00' FROM therapists t
UNION ALL
SELECT t.therapist_id, 'Friday', '09:00:00', '18:00:00' FROM therapists t
UNION ALL
SELECT t.therapist_id, 'Saturday', '09:00:00', '18:00:00' FROM therapists t;

-- Insert sample appointments for testing
INSERT INTO appointments (user_id, therapist_id, service_id, appointment_date, appointment_time, status, notes) VALUES 
((SELECT user_id FROM users WHERE email = 'john@example.com'), 1, 1, '2025-06-20', '10:00:00', 'confirmed', 'First time yoga session, beginner level'),
((SELECT user_id FROM users WHERE email = 'jane@example.com'), 2, 2, '2025-06-21', '14:00:00', 'pending', 'Suffering from lower back pain');

-- Insert sample inquiries
INSERT INTO inquiries (user_id, subject, message, priority) VALUES 
((SELECT user_id FROM users WHERE email = 'john@example.com'), 'Question about yoga classes', 'I am interested in joining yoga classes. Do you offer beginner-friendly sessions?', 'medium'),
(NULL, 'General Information', 'What are your operating hours and location?', 'low');

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value) VALUES 
('site_name', 'GreenLife Wellness Center'),
('business_hours', '9:00 AM - 6:00 PM'),
('contact_email', 'info@greenlife.com'),
('contact_phone', '+94112345678'),
('max_appointments_per_user', '3'),
('booking_advance_hours', '24');

-- Create indexes for better performance
CREATE INDEX idx_appointments_date ON appointments(appointment_date);
CREATE INDEX idx_appointments_therapist ON appointments(therapist_id);
CREATE INDEX idx_appointments_user ON appointments(user_id);
CREATE INDEX idx_appointments_status ON appointments(status);
CREATE INDEX idx_inquiries_status ON inquiries(status);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);

-- Default password for all demo accounts is 'password' (bcrypt hashed)
-- Login credentials:
-- Admin: admin@greenlife.com / password
-- Therapists: samara@greenlife.com, nimal@greenlife.com, priya@greenlife.com, kamal@greenlife.com / password
-- Clients: john@example.com, jane@example.com / password