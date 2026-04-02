<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Handle room assignment
if(isset($_POST['assign_room'])) {
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
            
            // Check if room is now fully occupied
            $check_full = "UPDATE rooms SET status = 'occupied' WHERE room_no = :room_no AND current_occupancy >= capacity";
            $full_stmt = $db->prepare($check_full);
            $full_stmt->bindParam(':room_no', $room_no);
            $full_stmt->execute();
            
            echo "<div class='alert alert-success'>Room assigned successfully!</div>";
        } else {
            echo "<div class='alert alert-danger'>Error assigning room!</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>Room is not available or full!</div>";
    }
}

// Handle room change
if(isset($_POST['change_room'])) {
    $student_id = $_POST['student_id'];
    $old_room = $_POST['old_room'];
    $new_room = $_POST['new_room'];
    
    // Check if new room is available
    $check_query = "SELECT capacity, current_occupancy FROM rooms WHERE room_no = :room_no AND status = 'available'";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':room_no', $new_room);
    $check_stmt->execute();
    $room = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if($room && $room['current_occupancy'] < $room['capacity']) {
        // Update student's room
        $query = "UPDATE students SET room_no = :new_room WHERE id = :student_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':new_room', $new_room);
        $stmt->bindParam(':student_id', $student_id);
        
        if($stmt->execute()) {
            // Decrease old room occupancy
            $dec_old = "UPDATE rooms SET current_occupancy = current_occupancy - 1 WHERE room_no = :old_room";
            $dec_stmt = $db->prepare($dec_old);
            $dec_stmt->bindParam(':old_room', $old_room);
            $dec_stmt->execute();
            
            // Update old room status if needed
            $update_old = "UPDATE rooms SET status = 'available' WHERE room_no = :old_room AND current_occupancy < capacity";
            $update_old_stmt = $db->prepare($update_old);
            $update_old_stmt->bindParam(':old_room', $old_room);
            $update_old_stmt->execute();
            
            // Increase new room occupancy
            $inc_new = "UPDATE rooms SET current_occupancy = current_occupancy + 1 WHERE room_no = :new_room";
            $inc_stmt = $db->prepare($inc_new);
            $inc_stmt->bindParam(':new_room', $new_room);
            $inc_stmt->execute();
            
            // Check if new room is now full
            $check_full = "UPDATE rooms SET status = 'occupied' WHERE room_no = :new_room AND current_occupancy >= capacity";
            $full_stmt = $db->prepare($check_full);
            $full_stmt->bindParam(':new_room', $new_room);
            $full_stmt->execute();
            
            echo "<div class='alert alert-success'>Room changed successfully!</div>";
        } else {
            echo "<div class='alert alert-danger'>Error changing room!</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>New room is not available or full!</div>";
    }
}

// Handle student deletion
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Get student's room before deletion
    $get_room = "SELECT room_no FROM students WHERE id = :id";
    $room_stmt = $db->prepare($get_room);
    $room_stmt->bindParam(':id', $id);
    $room_stmt->execute();
    $student = $room_stmt->fetch(PDO::FETCH_ASSOC);
    
    if($student && $student['room_no']) {
        // Decrease room occupancy
        $dec_room = "UPDATE rooms SET current_occupancy = current_occupancy - 1 WHERE room_no = :room_no";
        $dec_stmt = $db->prepare($dec_room);
        $dec_stmt->bindParam(':room_no', $student['room_no']);
        $dec_stmt->execute();
        
        // Update room status
        $update_room = "UPDATE rooms SET status = 'available' WHERE room_no = :room_no AND current_occupancy < capacity";
        $update_stmt = $db->prepare($update_room);
        $update_stmt->bindParam(':room_no', $student['room_no']);
        $update_stmt->execute();
    }
    
    // Delete student
    $query = "DELETE FROM students WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    if($stmt->execute()) {
        echo "<div class='alert alert-success'>Student deleted successfully!</div>";
    }
}

// Get all students with their room info
$query = "SELECT s.*, r.room_type, r.rent_per_month 
          FROM students s 
          LEFT JOIN rooms r ON s.room_no = r.room_no 
          ORDER BY s.id DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available rooms for dropdown
$rooms_query = "SELECT room_no, room_type, capacity, current_occupancy, rent_per_month 
                FROM rooms 
                WHERE status = 'available' AND current_occupancy < capacity 
                ORDER BY room_no";
$rooms_stmt = $db->prepare($rooms_query);
$rooms_stmt->execute();
$available_rooms = $rooms_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-12">
        <h2>Manage Students</h2>
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="fas fa-plus"></i> Add New Student
        </button>
        <hr>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>Student List with Room Details</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Room No</th>
                                <th>Room Type</th>
                                <th>Rent/Month</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($students as $row): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td>
                                    <?php if($row['room_no']): ?>
                                        <span class="badge bg-info"><?php echo $row['room_no']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['room_type'] ?: '-'; ?></td>
                                <td><?php echo $row['rent_per_month'] ? '₹' . $row['rent_per_month'] : '-'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if(!$row['room_no']): ?>
                                        <button class="btn btn-sm btn-success" onclick="assignRoom(<?php echo $row['id']; ?>, '<?php echo $row['name']; ?>')">
                                            <i class="fas fa-bed"></i> Assign Room
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-warning" onclick="changeRoom(<?php echo $row['id']; ?>, '<?php echo $row['name']; ?>', '<?php echo $row['room_no']; ?>')">
                                            <i class="fas fa-exchange-alt"></i> Change Room
                                        </button>
                                    <?php endif; ?>
                                    <a href="edit_student.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure? This will free up the room.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Room Modal -->
<div class="modal fade" id="assignRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-bed"></i> Assign Room to Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Student Name:</label>
                        <input type="text" id="assign_student_name" class="form-control" readonly>
                        <input type="hidden" id="assign_student_id" name="student_id">
                    </div>
                    <div class="mb-3">
                        <label>Select Room:</label>
                        <select name="room_no" class="form-control" required onchange="showRoomDetails(this.value)">
                            <option value="">Choose a room...</option>
                            <?php foreach($available_rooms as $room): ?>
                            <option value="<?php echo $room['room_no']; ?>" 
                                    data-type="<?php echo $room['room_type']; ?>"
                                    data-capacity="<?php echo $room['capacity']; ?>"
                                    data-occupancy="<?php echo $room['current_occupancy']; ?>"
                                    data-rent="<?php echo $room['rent_per_month']; ?>">
                                Room <?php echo $room['room_no']; ?> - <?php echo ucfirst($room['room_type']); ?> 
                                (<?php echo $room['current_occupancy']; ?>/<?php echo $room['capacity']; ?> occupants)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="roomDetails" class="alert alert-info" style="display:none;">
                        <strong>Room Details:</strong><br>
                        Type: <span id="room_type"></span><br>
                        Capacity: <span id="room_capacity"></span><br>
                        Current Occupancy: <span id="room_occupancy"></span><br>
                        Rent: ₹<span id="room_rent"></span>/month
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_room" class="btn btn-success">Assign Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Room Modal -->
<div class="modal fade" id="changeRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="fas fa-exchange-alt"></i> Change Room</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Student Name:</label>
                        <input type="text" id="change_student_name" class="form-control" readonly>
                        <input type="hidden" id="change_student_id" name="student_id">
                        <input type="hidden" id="old_room" name="old_room">
                    </div>
                    <div class="mb-3">
                        <label>Current Room:</label>
                        <input type="text" id="current_room" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Select New Room:</label>
                        <select name="new_room" class="form-control" required onchange="showNewRoomDetails(this.value)">
                            <option value="">Choose a new room...</option>
                            <?php foreach($available_rooms as $room): ?>
                            <option value="<?php echo $room['room_no']; ?>"
                                    data-type="<?php echo $room['room_type']; ?>"
                                    data-capacity="<?php echo $room['capacity']; ?>"
                                    data-occupancy="<?php echo $room['current_occupancy']; ?>"
                                    data-rent="<?php echo $room['rent_per_month']; ?>">
                                Room <?php echo $room['room_no']; ?> - <?php echo ucfirst($room['room_type']); ?> 
                                (<?php echo $room['current_occupancy']; ?>/<?php echo $room['capacity']; ?> occupants)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="newRoomDetails" class="alert alert-info" style="display:none;">
                        <strong>New Room Details:</strong><br>
                        Type: <span id="new_room_type"></span><br>
                        Capacity: <span id="new_room_capacity"></span><br>
                        Current Occupancy: <span id="new_room_occupancy"></span><br>
                        Rent: ₹<span id="new_room_rent"></span>/month
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="change_room" class="btn btn-warning">Change Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function assignRoom(id, name) {
    document.getElementById('assign_student_id').value = id;
    document.getElementById('assign_student_name').value = name;
    new bootstrap.Modal(document.getElementById('assignRoomModal')).show();
}

function changeRoom(id, name, currentRoom) {
    document.getElementById('change_student_id').value = id;
    document.getElementById('change_student_name').value = name;
    document.getElementById('old_room').value = currentRoom;
    document.getElementById('current_room').value = currentRoom;
    new bootstrap.Modal(document.getElementById('changeRoomModal')).show();
}

function showRoomDetails(roomNo) {
    if(roomNo) {
        let select = document.querySelector('select[name="room_no"]');
        let selectedOption = select.options[select.selectedIndex];
        
        document.getElementById('room_type').innerText = selectedOption.dataset.type;
        document.getElementById('room_capacity').innerText = selectedOption.dataset.capacity;
        document.getElementById('room_occupancy').innerText = selectedOption.dataset.occupancy;
        document.getElementById('room_rent').innerText = selectedOption.dataset.rent;
        document.getElementById('roomDetails').style.display = 'block';
    } else {
        document.getElementById('roomDetails').style.display = 'none';
    }
}

function showNewRoomDetails(roomNo) {
    if(roomNo) {
        let select = document.querySelector('select[name="new_room"]');
        let selectedOption = select.options[select.selectedIndex];
        
        document.getElementById('new_room_type').innerText = selectedOption.dataset.type;
        document.getElementById('new_room_capacity').innerText = selectedOption.dataset.capacity;
        document.getElementById('new_room_occupancy').innerText = selectedOption.dataset.occupancy;
        document.getElementById('new_room_rent').innerText = selectedOption.dataset.rent;
        document.getElementById('newRoomDetails').style.display = 'block';
    } else {
        document.getElementById('newRoomDetails').style.display = 'none';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>