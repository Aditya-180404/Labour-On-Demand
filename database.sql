-- Database: `labour_on_demand`

CREATE DATABASE IF NOT EXISTS `labour_on_demand`;
USE `labour_on_demand`;

-- Users Table (Customers)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(15),
    `pin_code` VARCHAR(6),
    `address_details` TEXT,
    `location` VARCHAR(100),
    `profile_image` VARCHAR(255) DEFAULT 'default.png',
    `otp` VARCHAR(6) DEFAULT NULL,
    `otp_expires_at` DATETIME DEFAULT NULL,
    `profile_image_public_id` VARCHAR(255) DEFAULT NULL,
    `user_uid` VARCHAR(10) UNIQUE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Service Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) UNIQUE NOT NULL,
    `icon` VARCHAR(50) DEFAULT 'fa-tools' -- FontAwesome icon class
);

-- Workers Table
CREATE TABLE IF NOT EXISTS `workers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `profile_image` VARCHAR(255) DEFAULT 'default.png',
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(15),
    `service_category_id` INT,
    `bio` TEXT,
    `hourly_rate` DECIMAL(10, 2),
    `latitude` DECIMAL(10, 8),
    `longitude` DECIMAL(11, 8),
    `is_available` BOOLEAN DEFAULT 1,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `pin_code` TEXT COMMENT 'Comma-separated PIN codes (e.g., 110001,110002,110003)',
    `address` TEXT,
    `adhar_card` VARCHAR(12),
    `aadhar_photo` VARCHAR(255),
    `pan_photo` VARCHAR(255),
    `signature_photo` VARCHAR(255) DEFAULT NULL,
    `pending_signature_photo` VARCHAR(255) DEFAULT NULL,
    `previous_work_images` TEXT DEFAULT NULL,
    `working_location` VARCHAR(100),
    `otp` VARCHAR(6) DEFAULT NULL,
    `otp_expires_at` DATETIME DEFAULT NULL,
    `profile_image_public_id` VARCHAR(255) DEFAULT NULL,
    `aadhar_photo_public_id` VARCHAR(255) DEFAULT NULL,
    `pan_photo_public_id` VARCHAR(255) DEFAULT NULL,
    `signature_photo_public_id` VARCHAR(255) DEFAULT NULL,
    `previous_work_public_ids` TEXT DEFAULT NULL,
    `pending_profile_image_public_id` VARCHAR(255) DEFAULT NULL,
    `pending_aadhar_photo_public_id` VARCHAR(255) DEFAULT NULL,
    `pending_pan_photo_public_id` VARCHAR(255) DEFAULT NULL,
    `pending_signature_photo_public_id` VARCHAR(255) DEFAULT NULL,
    `doc_update_status` ENUM('approved', 'pending', 'rejected') DEFAULT NULL,
    `worker_uid` VARCHAR(8) UNIQUE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`service_category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
);

-- Bookings Table
CREATE TABLE IF NOT EXISTS `bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `worker_id` INT NOT NULL,
    `service_date` DATE NOT NULL,
    `service_time` TIME NOT NULL,
    `service_end_time` TIME,
    `address` TEXT NOT NULL,
    `status` ENUM('pending', 'accepted', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    `amount_paid` DECIMAL(10, 2),
    `completion_time` DATETIME,
    `booking_latitude` DECIMAL(10, 8),
    `booking_longitude` DECIMAL(11, 8),
    `work_proof_images` TEXT DEFAULT NULL,
    `work_done_public_ids` TEXT DEFAULT NULL,
    `completion_otp` VARCHAR(6) DEFAULT NULL,
    `completion_otp_expires_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`worker_id`) REFERENCES `workers`(`id`) ON DELETE CASCADE
);

-- Reviews Table
CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `worker_id` INT NOT NULL,
    `rating` INT CHECK (rating >= 1 AND rating <= 5),
    `comment` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`worker_id`) REFERENCES `workers`(`id`) ON DELETE CASCADE
);

-- Admin Table
CREATE TABLE IF NOT EXISTS `admin` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL
);

-- Insert Default Admin (Password: password)
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT IGNORE INTO `admin` (`username`, `password`) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert Sample Categories
INSERT IGNORE INTO `categories` (`name`, `icon`) VALUES 
('Plumber', 'fa-faucet'),
('Electrician', 'fa-bolt'),
('Cleaner', 'fa-broom'),
('Painter', 'fa-paint-roller'),
('Carpenter', 'fa-hammer'),
('Helper', 'fa-hands-helping');

-- Worker Photo/Document Change History
CREATE TABLE IF NOT EXISTS `worker_photo_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `worker_id` INT NOT NULL,
    `photo_type` ENUM('profile', 'aadhar', 'pan', 'signature') NOT NULL,
    `photo_path` VARCHAR(255) NOT NULL,
    `photo_public_id` VARCHAR(255) DEFAULT NULL,
    `replaced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`worker_id`) REFERENCES `workers`(`id`) ON DELETE CASCADE
);

-- Feedbacks Table
CREATE TABLE IF NOT EXISTS `feedbacks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `worker_id` INT DEFAULT NULL,
    `sender_role` ENUM('user', 'worker', 'guest') DEFAULT 'guest',
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('pending', 'read', 'replied') DEFAULT 'pending',
    `admin_reply` TEXT DEFAULT NULL,
    `replied_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`worker_id`) REFERENCES `workers`(`id`) ON DELETE CASCADE
);

-- Rate Limiting Table
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL UNIQUE,
    `request_count` INT DEFAULT 1,
    `first_request_at` INT NOT NULL,
    `blocked_until` INT DEFAULT 0,
    INDEX (`ip_address`),
    INDEX (`blocked_until`)
);
