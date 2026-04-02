<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';
require_once '../includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Add Room
if(isset($_POST['add_room'])) {
    $room_no = $_POST['room_no'];
    $room_type = $_POST['room_type'];
    $capacity = $_POST['capacity'];
    $rent_per_month = $_POST['rent_per_month'];
    $description = $_POST['description'];
    
    $query = "INSERT INTO rooms (room_no, room_type, capacity, rent_per_month, description) 
              VALUES (:room_no, :room_type, :capacity, :rent_per_month, :description)";
    $stmt = $db->prepare($query);
    
    $stmt->bindParam(':room_no', $room_no);
    $stmt->bindParam(':room_type', $room_type);
    $stmt->bindParam(':capacity', $capacity);
    $stmt->bindParam(':rent_per_month', $rent_per_month);
    $stmt->bindParam(':description', $description);
    
    if($stmt->execute()) {
        echo "<div class='alert alert-success'>Room added successfully!</div>";
    } else {
        echo "<div class='alert alert-danger'>Error adding room!</div>";
    }
}

// Delete Room
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $query = "DELETE FROM rooms WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    echo "<div class='alert alert-success'>Room deleted successfully!</div>";
}
?>

<div class="row">
    <div class="col-md-12">
        <h2>Manage Rooms</h2>
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addRoomModal">
            <i class="fas fa-plus"></i> Add New Room
        </button>
        <hr>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>Room List</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Room No</th>
                            <th>Type</th>
                            <th>Capacity</th>
                            <th>Occupancy</th>
                            <th>Rent/Month</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM rooms ORDER BY id DESC";
                        $stmt = $db->prepare($query);
                        $stmt->execute();
                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<tr>";
                            echo "<td>{$row['room_no']}</td>";
                            echo "<td>{$row['room_type']}</td>";
                            echo "<td>{$row['capacity']}</td>";
                            echo "<td>{$row['current_occupancy']}</td>";
                            echo "<td>₹{$row['rent_per_month']}</td>";
                            echo "<td><span class='badge bg-" . 
                                ($row['status'] == 'available' ? 'success' : 
                                ($row['status'] == 'occupied' ? 'warning' : 'danger')) . 
                                "'>{$row['status']}</span></td>";
                            echo "<td>
                                    <a href='edit_room.php?id={$row['id']}' class='btn btn-sm btn-warning'>Edit</a>
                                    <a href='?delete={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                                  </td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Room Number</label>
                        <input type="text" name="room_no" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Room Type</label>
                        <select name="room_type" class="form-control" required>
                            <option value="single">Single</option>
                            <option value="double">Double</option>
                            <option value="triple">Triple</option>
                            <option value="dorm">Dorm</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Capacity</label>
                        <input type="number" name="capacity" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Rent Per Month (₹)</label>
                        <input type="number" name="rent_per_month" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_room" class="btn btn-primary">Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>