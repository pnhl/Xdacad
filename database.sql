-- Database schema for Work Schedule & Payroll Management System
-- Compatible with MySQL 5.7+ and InfinityFree hosting

CREATE DATABASE IF NOT EXISTS work_schedule_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE work_schedule_db;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    hourly_rate DECIMAL(12,2) DEFAULT 0.00,
    workplace_default VARCHAR(255) DEFAULT '',
    theme ENUM('light', 'dark') DEFAULT 'light',
    locale VARCHAR(10) DEFAULT 'vi-VN',
    role ENUM('user', 'admin') DEFAULT 'user',
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Shifts table
CREATE TABLE shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    planned_start DATETIME NOT NULL,
    planned_end DATETIME NOT NULL,
    workplace VARCHAR(255) NOT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('planned', 'in_progress', 'done', 'canceled') DEFAULT 'planned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, date),
    INDEX idx_status (status),
    INDEX idx_planned_start (planned_start)
) ENGINE=InnoDB;

-- Work sessions table (for tracking actual work time)
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    INDEX idx_shift_id (shift_id),
    INDEX idx_start_time (start_time)
) ENGINE=InnoDB;

-- Hourly rate history table
CREATE TABLE hourly_rate_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rate DECIMAL(12,2) NOT NULL,
    effective_from DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_effective (user_id, effective_from)
) ENGINE=InnoDB;

-- Audit logs table
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    meta JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Password reset tokens table
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;

-- Insert demo users
INSERT INTO users (name, email, password_hash, hourly_rate, workplace_default, role) VALUES 
('Admin User', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 150000.00, 'Văn phòng chính', 'admin'),
('Nguyễn Văn A', 'user1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 80000.00, 'Chi nhánh 1', 'user'),
('Trần Thị B', 'user2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 75000.00, 'Chi nhánh 2', 'user');

-- Insert initial hourly rate history
INSERT INTO hourly_rate_history (user_id, rate, effective_from) VALUES 
(1, 150000.00, '2024-01-01 00:00:00'),
(2, 80000.00, '2024-01-01 00:00:00'),
(3, 75000.00, '2024-01-01 00:00:00');

-- Insert demo shifts
INSERT INTO shifts (user_id, date, planned_start, planned_end, workplace, notes, status) VALUES 
(2, CURDATE(), CONCAT(CURDATE(), ' 08:00:00'), CONCAT(CURDATE(), ' 17:00:00'), 'Chi nhánh 1', 'Ca làm việc thường', 'planned'),
(3, CURDATE(), CONCAT(CURDATE(), ' 09:00:00'), CONCAT(CURDATE(), ' 18:00:00'), 'Chi nhánh 2', 'Ca làm việc chiều', 'planned');

-- Default password for demo accounts: "password"
-- You should change these passwords after setup
