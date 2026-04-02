<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/header.php';

$database = new Database();
$db = $database->getConnection();

$student_id = $_SESSION['user_id'];

// Get current student details
$query = "SELECT * FROM students WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle room change request
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $preferred_room_type = $_POST['preferred_room_type'];
    $reason = $_POST['reason'];
    
    // Insert room change request
    $query = "INSERT INTO room_change_requests (student_id, current_room, preferred_room_type, reason, request_date, status) 
              VALUES (:student_id, :current_room, :preferred_room_type, :reason, NOW(), 'pending')";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':current_room', $student['room_no']);
    $stmt->bindParam(':preferred_room_type', $preferred_room_type);
    $stmt->bindParam(':reason', $reason);
    
    if($stmt->execute()) {
        $success = "Room change request submitted successfully!";
    } else {
        $error = "Failed to submit request!";
    }
}

// Get available rooms for dropdown
$rooms_query = "SELECT room_no, room_type, capacity, current_occupancy, rent_per_month 
                FROM rooms 
                WHERE status = 'available' AND current_occupancy < capacity 
                ORDER BY room_type";
$rooms_stmt = $db->prepare($rooms_query);
$rooms_stmt->execute();
$available_rooms = $rooms_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-warning text-white">
                <h4><i class="fas fa-exchange-alt"></i> Request Room Change</h4>
            </div>
            <div class="card-body">
                <?php if(isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <strong>Current Room:</strong> <?php echo $student['room_no'] ?: 'Not Assigned'; ?>
                </div>
                
                <form method="POST">
                    <div class="mb-3">
                        <label>Preferred Room Type:</label>
                        <select name="preferred_room_type" class="form-control" required>
                            <option value="">Select room type...</option>
                            <option value="single">Single Room</option>
                            <option value="double">Double Room</option>
                            <option value="triple">Triple Room</option>
                            <option value="dorm">Dormitory</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label>Reason for Room Change:</label>
                        <textarea name="reason" class="form-control" rows="4" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100">
                        Submit Request
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5>Available Rooms</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Room No</th>
                                <th>Type</th>
                                <th>Capacity</th>
                                <th>Available</th>
                                <th>Rent/Month</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($available_rooms as $room): ?>
                            <tr>
                                <td><?php echo $room['room_no']; ?></td>
                                <td><?php echo ucfirst($room['room_type']); ?></td>
                                <td><?php echo $room['capacity']; ?></td>
                                <td><?php echo $room['capacity'] - $room['current_occupancy']; ?></td>
                                <td>₹<?php echo $room['rent_per_month']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>