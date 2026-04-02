-- Create database
CREATE DATABASE IF NOT EXISTS hostel_management;
USE hostel_management;

-- Table 1: admin table
CREATE TABLE admin (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin
INSERT INTO admin (username, password, email) 
VALUES ('admin', MD5('admin123'), 'admin@hostel.com');

-- Table 2: students table
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(15),
    parent_phone VARCHAR(15),
    address TEXT,
    room_no VARCHAR(10),
    joining_date DATE,
    status ENUM('active', 'inactive', 'graduated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table 3: rooms table
CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_no VARCHAR(10) UNIQUE NOT NULL,
    room_type ENUM('single', 'double', 'triple', 'dorm') NOT NULL,
    capacity INT NOT NULL,
    current_occupancy INT DEFAULT 0,
    rent_per_month DECIMAL(10,2) NOT NULL,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    description TEXT
);

-- Table 4: fees table
CREATE TABLE fees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    month_year VARCHAR(20),
    total_amount DECIMAL(10,2),
    paid_amount DECIMAL(10,2) DEFAULT 0,
    due_amount DECIMAL(10,2),
    payment_date DATE,
    payment_mode ENUM('cash', 'online', 'cheque'),
    status ENUM('paid', 'pending', 'partial') DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_fee (student_id, month_year)
);

-- Table 5: complaints table
CREATE TABLE complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    complaint_title VARCHAR(200),
    complaint_description TEXT,
    complaint_date DATE,
    status ENUM('pending', 'in_progress', 'resolved', 'rejected') DEFAULT 'pending',
    resolution TEXT,
    resolved_date DATE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Table 6: attendance table
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    attendance_date DATE,
    status ENUM('present', 'absent', 'late') DEFAULT 'present',
    check_in_time TIME,
    check_out_time TIME,
    remarks TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (student_id, attendance_date)
);

-- Table 7: visitors table (additional table)
CREATE TABLE visitors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    visitor_name VARCHAR(100),
    student_id INT,
    visit_date DATE,
    visit_time TIME,
    purpose TEXT,
    contact_no VARCHAR(15),
    check_in_time TIME,
    check_out_time TIME,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);


-- Table for room change requests
CREATE TABLE IF NOT EXISTS room_change_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    current_room VARCHAR(10),
    preferred_room_type VARCHAR(20),
    reason TEXT,
    request_date DATETIME,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    admin_remarks TEXT,
    resolved_date DATETIME,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Create password change logs table
CREATE TABLE IF NOT EXISTS password_change_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    change_date DATETIME,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS payment_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    transaction_id VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE,
    proof_file VARCHAR(255),
    remarks TEXT,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    admin_remarks TEXT,
    request_date DATETIME,
    approved_date DATETIME,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);