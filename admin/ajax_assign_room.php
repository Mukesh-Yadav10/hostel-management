<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $room_no = $_POST['room_no'];
    
    // Check if room is available
    $check_query = "SELECT capacity, current_occupancy FROM rooms WHERE room_no = :room_no AND status = 'available'";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':room_no', $room_no);
    $check_stmt->execute();
    $room = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if($room && $room['current_occupancy'] < $room['capacity']) {
        // Assign room to student
        $query = "UPDATE students SET room_no = :room_no WHERE id = :student_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':room_no', $room_no);
        $stmt->bindParam(':student_id', $student_id);
        
        if($stmt->execute()) {
            // Update room occupancy
            $update_room = "UPDATE rooms SET current_occupancy = current_occupancy + 1 WHERE room_no = :room_no";
            $update_stmt = $db->prepare($update_room);
            $update_stmt->bindParam(':room_no', $room_no);
            $update_stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Room assigned successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to assign room']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Room is not available or full']);
    }
}
?>