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

$error = '';
$success = '';

// Add new visitor
if(isset($_POST['add_visitor'])) {
    $visitor_name = trim($_POST['visitor_name']);
    $student_id = $_POST['student_id'];
    $visit_date = $_POST['visit_date'];
    $visit_time = $_POST['visit_time'];
    $purpose = trim($_POST['purpose']);
    $contact_no = trim($_POST['contact_no']);
    $check_in_time = $_POST['check_in_time'];
    $check_out_time = $_POST['check_out_time'];
    
    $query = "INSERT INTO visitors (visitor_name, student_id, visit_date, visit_time, purpose, contact_no, check_in_time, check_out_time) 
              VALUES (:visitor_name, :student_id, :visit_date, :visit_time, :purpose, :contact_no, :check_in_time, :check_out_time)";
    $stmt = $db->prepare($query);
    
    $stmt->bindParam(':visitor_name', $visitor_name);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':visit_date', $visit_date);
    $stmt->bindParam(':visit_time', $visit_time);
    $stmt->bindParam(':purpose', $purpose);
    $stmt->bindParam(':contact_no', $contact_no);
    $stmt->bindParam(':check_in_time', $check_in_time);
    $stmt->bindParam(':check_out_time', $check_out_time);
    
    if($stmt->execute()) {
        $success = "Visitor added successfully!";
    } else {
        $error = "Error adding visitor!";
    }
}

// Update visitor
if(isset($_POST['update_visitor'])) {
    $id = $_POST['visitor_id'];
    $visitor_name = trim($_POST['visitor_name']);
    $student_id = $_POST['student_id'];
    $visit_date = $_POST['visit_date'];
    $visit_time = $_POST['visit_time'];
    $purpose = trim($_POST['purpose']);
    $contact_no = trim($_POST['contact_no']);
    $check_in_time = $_POST['check_in_time'];
    $check_out_time = $_POST['check_out_time'];
    
    $query = "UPDATE visitors SET visitor_name = :visitor_name, student_id = :student_id, 
              visit_date = :visit_date, visit_time = :visit_time, purpose = :purpose, 
              contact_no = :contact_no, check_in_time = :check_in_time, check_out_time = :check_out_time 
              WHERE id = :id";
    $stmt = $db->prepare($query);
    
    $stmt->bindParam(':visitor_name', $visitor_name);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':visit_date', $visit_date);
    $stmt->bindParam(':visit_time', $visit_time);
    $stmt->bindParam(':purpose', $purpose);
    $stmt->bindParam(':contact_no', $contact_no);
    $stmt->bindParam(':check_in_time', $check_in_time);
    $stmt->bindParam(':check_out_time', $check_out_time);
    $stmt->bindParam(':id', $id);
    
    if($stmt->execute()) {
        $success = "Visitor updated successfully!";
    } else {
        $error = "Error updating visitor!";
    }
}

// Delete visitor
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $query = "DELETE FROM visitors WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    if($stmt->execute()) {
        $success = "Visitor deleted successfully!";
    } else {
        $error = "Error deleting visitor!";
    }
}

// Check-out visitor
if(isset($_GET['checkout'])) {
    $id = $_GET['checkout'];
    $check_out_time = date('H:i:s');
    $query = "UPDATE visitors SET check_out_time = :check_out_time WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':check_out_time', $check_out_time);
    $stmt->bindParam(':id', $id);
    if($stmt->execute()) {
        $success = "Visitor checked out successfully!";
    } else {
        $error = "Error checking out visitor!";
    }
}

// Get all students for dropdown
$students_query = "SELECT id, student_id, name, room_no FROM students WHERE status = 'active' ORDER BY name";
$students_stmt = $db->prepare($students_query);
$students_stmt->execute();
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all visitors with student details
$visitors_query = "SELECT v.*, s.name as student_name, s.student_id, s.room_no 
                   FROM visitors v 
                   JOIN students s ON v.student_id = s.id 
                   ORDER BY v.visit_date DESC, v.visit_time DESC";
$visitors_stmt = $db->prepare($visitors_query);
$visitors_stmt->execute();
$visitors = $visitors_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's visitors count
$today = date('Y-m-d');
$today_query = "SELECT COUNT(*) as count FROM visitors WHERE visit_date = :today";
$today_stmt = $db->prepare($today_query);
$today_stmt->bindParam(':today', $today);
$today_stmt->execute();
$today_count = $today_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get active visitors (checked in but not checked out)
$active_query = "SELECT COUNT(*) as count FROM visitors WHERE check_out_time IS NULL AND visit_date = :today";
$active_stmt = $db->prepare($active_query);
$active_stmt->bindParam(':today', $today);
$active_stmt->execute();
$active_count = $active_stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<div class="row">
    <div class="col-md-12">
        <h2>Manage Visitors</h2>
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addVisitorModal">
            <i class="fas fa-plus"></i> Add New Visitor
        </button>
        <hr>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="dashboard-stats bg-primary text-white">
            <h4><?php echo count($visitors); ?></h4>
            <p>Total Visitors</p>
            <i class="fas fa-users fa-2x"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-stats bg-success text-white">
            <h4><?php echo $today_count; ?></h4>
            <p>Today's Visitors</p>
            <i class="fas fa-calendar-day fa-2x"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-stats bg-warning text-white">
            <h4><?php echo $active_count; ?></h4>
            <p>Currently Inside</p>
            <i class="fas fa-clock fa-2x"></i>
        </div>
    </div>
</div>

<!-- Visitors Table -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-user-friends"></i> Visitor Records</h5>
            </div>
            <div class="card-body">
                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="visitorsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Visitor Name</th>
                                <th>Contact No</th>
                                <th>Student</th>
                                <th>Room No</th>
                                <th>Visit Date</th>
                                <th>Visit Time</th>
                                <th>Purpose</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($visitors as $visitor): ?>
                            <tr>
                                <td><?php echo $visitor['id']; ?></td>
                                <td><?php echo htmlspecialchars($visitor['visitor_name']); ?></td>
                                <td><?php echo htmlspecialchars($visitor['contact_no']); ?></td>
                                <td><?php echo htmlspecialchars($visitor['student_name']); ?><br>
                                    <small><?php echo $visitor['student_id']; ?></small>
                                </td>
                                <td><?php echo $visitor['room_no']; ?></td>
                                <td><?php echo date('d-m-Y', strtotime($visitor['visit_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($visitor['visit_time'])); ?></td>
                                <td><?php echo htmlspecialchars($visitor['purpose']); ?></td>
                                <td><?php echo $visitor['check_in_time'] ? date('h:i A', strtotime($visitor['check_in_time'])) : '-'; ?></td>
                                <td><?php echo $visitor['check_out_time'] ? date('h:i A', strtotime($visitor['check_out_time'])) : '-'; ?></td>
                                <td>
                                    <?php if($visitor['check_out_time']): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewVisitor(<?php echo $visitor['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="editVisitor(<?php echo htmlspecialchars(json_encode($visitor)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if(!$visitor['check_out_time']): ?>
                                        <a href="?checkout=<?php echo $visitor['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Check out this visitor?')">
                                            <i class="fas fa-sign-out-alt"></i> Check Out
                                        </a>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $visitor['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i>
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

<!-- Add Visitor Modal -->
<div class="modal fade" id="addVisitorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New Visitor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Visitor Name <span class="text-danger">*</span></label>
                            <input type="text" name="visitor_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Contact Number <span class="text-danger">*</span></label>
                            <input type="tel" name="contact_no" class="form-control" pattern="[0-9]{10}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Select Student <span class="text-danger">*</span></label>
                            <select name="student_id" class="form-control" required>
                                <option value="">Select Student</option>
                                <?php foreach($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo $student['student_id'] . ' - ' . $student['name'] . ' (Room ' . $student['room_no'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Purpose of Visit <span class="text-danger">*</span></label>
                            <input type="text" name="purpose" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Visit Date <span class="text-danger">*</span></label>
                            <input type="date" name="visit_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Visit Time <span class="text-danger">*</span></label>
                            <input type="time" name="visit_time" class="form-control" value="<?php echo date('H:i'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Check In Time</label>
                            <input type="time" name="check_in_time" class="form-control" value="<?php echo date('H:i'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Check Out Time</label>
                            <input type="time" name="check_out_time" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_visitor" class="btn btn-primary">Add Visitor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Visitor Modal -->
<div class="modal fade" id="editVisitorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Visitor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="visitor_id" id="edit_visitor_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Visitor Name <span class="text-danger">*</span></label>
                            <input type="text" name="visitor_name" id="edit_visitor_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Contact Number <span class="text-danger">*</span></label>
                            <input type="tel" name="contact_no" id="edit_contact_no" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Select Student <span class="text-danger">*</span></label>
                            <select name="student_id" id="edit_student_id" class="form-control" required>
                                <option value="">Select Student</option>
                                <?php foreach($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo $student['student_id'] . ' - ' . $student['name'] . ' (Room ' . $student['room_no'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Purpose of Visit <span class="text-danger">*</span></label>
                            <input type="text" name="purpose" id="edit_purpose" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Visit Date <span class="text-danger">*</span></label>
                            <input type="date" name="visit_date" id="edit_visit_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Visit Time <span class="text-danger">*</span></label>
                            <input type="time" name="visit_time" id="edit_visit_time" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Check In Time</label>
                            <input type="time" name="check_in_time" id="edit_check_in" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Check Out Time</label>
                            <input type="time" name="check_out_time" id="edit_check_out" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_visitor" class="btn btn-warning">Update Visitor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Visitor Modal -->
<div class="modal fade" id="viewVisitorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-user-circle"></i> Visitor Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="visitorDetails">
                <!-- Visitor details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewVisitor(id) {
    // Fetch visitor details via AJAX
    fetch('get_visitor.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            let html = `
                <table class="table table-bordered">
                    <tr><th>Visitor Name:</th><td>${data.visitor_name}</td></tr>
                    <tr><th>Contact Number:</th><td>${data.contact_no}</td></tr>
                    <tr><th>Student Name:</th><td>${data.student_name}</td></tr>
                    <tr><th>Student ID:</th><td>${data.student_id}</td></tr>
                    <tr><th>Room Number:</th><td>${data.room_no}</td></tr>
                    <tr><th>Purpose:</th><td>${data.purpose}</td></tr>
                    <tr><th>Visit Date:</th><td>${data.visit_date}</td></tr>
                    <tr><th>Visit Time:</th><td>${data.visit_time}</td></tr>
                    <tr><th>Check In Time:</th><td>${data.check_in_time || 'Not checked in'}</td></tr>
                    <tr><th>Check Out Time:</th><td>${data.check_out_time || 'Not checked out'}</td></tr>
                    <tr><th>Status:</th><td>${data.check_out_time ? '<span class="badge bg-success">Completed</span>' : '<span class="badge bg-warning">Active</span>'}</td></tr>
                </table>
            `;
            document.getElementById('visitorDetails').innerHTML = html;
            new bootstrap.Modal(document.getElementById('viewVisitorModal')).show();
        });
}

function editVisitor(visitor) {
    document.getElementById('edit_visitor_id').value = visitor.id;
    document.getElementById('edit_visitor_name').value = visitor.visitor_name;
    document.getElementById('edit_contact_no').value = visitor.contact_no;
    document.getElementById('edit_student_id').value = visitor.student_id;
    document.getElementById('edit_purpose').value = visitor.purpose;
    document.getElementById('edit_visit_date').value = visitor.visit_date;
    document.getElementById('edit_visit_time').value = visitor.visit_time;
    document.getElementById('edit_check_in').value = visitor.check_in_time || '';
    document.getElementById('edit_check_out').value = visitor.check_out_time || '';
    
    new bootstrap.Modal(document.getElementById('editVisitorModal')).show();
}

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search visitors...';
    searchInput.className = 'form-control mb-3';
    searchInput.style.width = '300px';
    
    const cardBody = document.querySelector('.card-body');
    cardBody.insertBefore(searchInput, cardBody.firstChild);
    
    searchInput.addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const rows = document.querySelectorAll('#visitorsTable tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchText) ? '' : 'none';
        });
    });
});
</script>

<style>
.dashboard-stats {
    border-radius: 10px;
    padding: 20px;
    position: relative;
    overflow: hidden;
    transition: transform 0.3s;
}

.dashboard-stats:hover {
    transform: translateY(-5px);
}

.dashboard-stats h4 {
    font-size: 2rem;
    margin-bottom: 10px;
}

.dashboard-stats i {
    position: absolute;
    right: 20px;
    bottom: 20px;
    opacity: 0.3;
}

.btn {
    transition: all 0.3s;
}

.btn:hover {
    transform: translateY(-2px);
}

.table th {
    background-color: #f8f9fa;
}
</style>

<?php require_once '../includes/footer.php'; ?>