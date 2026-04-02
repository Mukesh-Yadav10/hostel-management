<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'];

$query = "SELECT v.*, s.name as student_name, s.student_id, s.room_no 
          FROM visitors v 
          JOIN students s ON v.student_id = s.id 
          WHERE v.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();

$visitor = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($visitor);
?>