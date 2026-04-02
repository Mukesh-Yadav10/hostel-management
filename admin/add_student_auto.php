<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = md5($_POST['password']);
    $phone = $_POST['phone'];
    $parent_phone = $_POST['parent_phone'];
    $address = $_POST['address'];
    $joining_date = $_POST['joining_date'];
    $preferred_room_type = $_POST['preferred_room_type']; // single, double, triple, dorm
    
    // Find available room based on preference
    $room_query = "SELECT room_no FROM rooms 
                   WHERE room_type = :room_type 
                   AND status = 'available' 
                   AND current_occupancy < capacity 
                   ORDER BY current_occupancy ASC 
                   LIMIT 1";
    $room_stmt = $db->prepare($room_query);
    $room_stmt->bindParam(':room_type', $preferred_room_type);
    $room_stmt->execute();
    $available_room = $room_stmt->fetch(PDO::FETCH_ASSOC);
    
    if($available_room) {
        $room_no = $available_room['room_no'];
        
        // Add student with assigned room
        $query = "INSERT INTO students (student_id, name, email, password, phone, parent_phone, address, room_no, joining_date) 
                  VALUES (:student_id, :name, :email, :password, :phone, :parent_phone, :address, :room_no, :joining_date)";
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':parent_phone', $parent_phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':room_no', $room_no);
        $stmt->bindParam(':joining_date', $joining_date);
        
        if($stmt->execute()) {
            // Update room occupancy
            $update_room = "UPDATE rooms SET current_occupancy = current_occupancy + 1 WHERE room_no = :room_no";
            $update_stmt = $db->prepare($update_room);
            $update_stmt->bindParam(':room_no', $room_no);
            $update_stmt->execute();
            
            // Check if room is now full
            $check_full = "UPDATE rooms SET status = 'occupied' WHERE room_no = :room_no AND current_occupancy >= capacity";
            $full_stmt = $db->prepare($check_full);
            $full_stmt->bindParam(':room_no', $room_no);
            $full_stmt->execute();
            
            header("Location: manage_students.php?success=Student added with room $room_no");
        } else {
            header("Location: add_student.php?error=Failed to add student");
        }
    } else {
        // No room available of preferred type
        header("Location: add_student.php?error=No rooms available of type: $preferred_room_type");
    }
}
?>