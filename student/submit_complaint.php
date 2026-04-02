<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $student_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $complaint_date = date('Y-m-d');
    
    $query = "INSERT INTO complaints (student_id, complaint_title, complaint_description, complaint_date, status) 
              VALUES (:student_id, :title, :description, :date, 'pending')";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':date', $complaint_date);
    
    if($stmt->execute()) {
        $_SESSION['success'] = "Complaint submitted successfully!";
    } else {
        $_SESSION['error'] = "Failed to submit complaint. Please try again.";
    }
    
    header("Location: dashboard.php");
    exit();
}