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
$current_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_room = isset($_GET['room']) ? $_GET['room'] : '';

// Get all rooms for filter
$rooms_query = "SELECT DISTINCT room_no FROM rooms ORDER BY room_no";
$rooms_stmt = $db->prepare($rooms_query);
$rooms_stmt->execute();
$rooms = $rooms_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all students with their attendance for selected date
$students_query = "SELECT s.*, 
                   a.id as attendance_id, 
                   a.status, 
                   a.check_in_time, 
                   a.check_out_time,
                   a.remarks 
                   FROM students s 
                   LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = :date 
                   WHERE s.status = 'active'";
                   
if($selected_room) {
    $students_query .= " AND s.room_no = :room_no";
}

$students_query .= " ORDER BY s.room_no, s.name";

$students_stmt = $db->prepare($students_query);
$students_stmt->bindParam(':date', $current_date);
if($selected_room) {
    $students_stmt->bindParam(':room_no', $selected_room);
}
$students_stmt->execute();
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle attendance marking
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_attendance'])) {
    $attendance_data = $_POST['attendance'];
    
    foreach($attendance_data as $student_id => $data) {
        $status = $data['status'];
        $check_in_time = !empty($data['check_in_time']) ? $data['check_in_time'] : null;
        $check_out_time = !empty($data['check_out_time']) ? $data['check_out_time'] : null;
        $remarks = !empty($data['remarks']) ? $data['remarks'] : null;
        
        // Check if attendance already exists
        $check_query = "SELECT id FROM attendance WHERE student_id = :student_id AND attendance_date = :date";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':student_id', $student_id);
        $check_stmt->bindParam(':date', $current_date);
        $check_stmt->execute();
        
        if($check_stmt->rowCount() > 0) {
            // Update existing attendance
            $update_query = "UPDATE attendance SET status = :status, check_in_time = :check_in_time, 
                             check_out_time = :check_out_time, remarks = :remarks 
                             WHERE student_id = :student_id AND attendance_date = :date";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':status', $status);
            $update_stmt->bindParam(':check_in_time', $check_in_time);
            $update_stmt->bindParam(':check_out_time', $check_out_time);
            $update_stmt->bindParam(':remarks', $remarks);
            $update_stmt->bindParam(':student_id', $student_id);
            $update_stmt->bindParam(':date', $current_date);
            $update_stmt->execute();
        } else {
            // Insert new attendance
            $insert_query = "INSERT INTO attendance (student_id, attendance_date, status, check_in_time, check_out_time, remarks) 
                            VALUES (:student_id, :date, :status, :check_in_time, :check_out_time, :remarks)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':student_id', $student_id);
            $insert_stmt->bindParam(':date', $current_date);
            $insert_stmt->bindParam(':status', $status);
            $insert_stmt->bindParam(':check_in_time', $check_in_time);
            $insert_stmt->bindParam(':check_out_time', $check_out_time);
            $insert_stmt->bindParam(':remarks', $remarks);
            $insert_stmt->execute();
        }
    }
    
    $success = "Attendance saved successfully for " . date('d-m-Y', strtotime($current_date));
}

// Handle bulk attendance marking
if(isset($_POST['bulk_mark'])) {
    $bulk_status = $_POST['bulk_status'];
    $bulk_check_in = $_POST['bulk_check_in'];
    $bulk_check_out = $_POST['bulk_check_out'];
    
    foreach($students as $student) {
        $check_query = "SELECT id FROM attendance WHERE student_id = :student_id AND attendance_date = :date";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':student_id', $student['id']);
        $check_stmt->bindParam(':date', $current_date);
        $check_stmt->execute();
        
        if($check_stmt->rowCount() > 0) {
            $update_query = "UPDATE attendance SET status = :status, check_in_time = :check_in_time, 
                             check_out_time = :check_out_time WHERE student_id = :student_id AND attendance_date = :date";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':status', $bulk_status);
            $update_stmt->bindParam(':check_in_time', $bulk_check_in);
            $update_stmt->bindParam(':check_out_time', $bulk_check_out);
            $update_stmt->bindParam(':student_id', $student['id']);
            $update_stmt->bindParam(':date', $current_date);
            $update_stmt->execute();
        } else {
            $insert_query = "INSERT INTO attendance (student_id, attendance_date, status, check_in_time, check_out_time) 
                            VALUES (:student_id, :date, :status, :check_in_time, :check_out_time)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':student_id', $student['id']);
            $insert_stmt->bindParam(':date', $current_date);
            $insert_stmt->bindParam(':status', $bulk_status);
            $insert_stmt->bindParam(':check_in_time', $bulk_check_in);
            $insert_stmt->bindParam(':check_out_time', $bulk_check_out);
            $insert_stmt->execute();
        }
    }
    
    $success = "Bulk attendance marked successfully!";
}

// Get statistics for current month
$current_month = date('m');
$current_year = date('Y');
$stats_query = "SELECT 
                COUNT(DISTINCT student_id) as total_students,
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late
                FROM attendance 
                WHERE MONTH(attendance_date) = :month AND YEAR(attendance_date) = :year";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':month', $current_month);
$stats_stmt->bindParam(':year', $current_year);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-12">
        <h2>Manage Attendance</h2>
        <hr>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="dashboard-stats bg-primary text-white">
            <h4><?php echo $stats['total_students'] ?? 0; ?></h4>
            <p>Total Students</p>
            <i class="fas fa-users fa-2x"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-stats bg-success text-white">
            <h4><?php echo $stats['present'] ?? 0; ?></h4>
            <p>Present Today</p>
            <i class="fas fa-check-circle fa-2x"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-stats bg-danger text-white">
            <h4><?php echo $stats['absent'] ?? 0; ?></h4>
            <p>Absent Today</p>
            <i class="fas fa-times-circle fa-2x"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-stats bg-warning text-white">
            <h4><?php echo $stats['late'] ?? 0; ?></h4>
            <p>Late Arrivals</p>
            <i class="fas fa-clock fa-2x"></i>
        </div>
    </div>
</div>

<!-- Date and Filter Controls -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row">
                    <div class="col-md-3">
                        <label>Select Date:</label>
                        <input type="date" name="date" class="form-control" value="<?php echo $current_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Filter by Room:</label>
                        <select name="room" class="form-control">
                            <option value="">All Rooms</option>
                            <?php foreach($rooms as $room): ?>
                                <option value="<?php echo $room['room_no']; ?>" <?php echo $selected_room == $room['room_no'] ? 'selected' : ''; ?>>
                                    Room <?php echo $room['room_no']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary form-control">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-2">
                        <label>&nbsp;</label>
                        <a href="manage_attendance.php" class="btn btn-secondary form-control">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                    <div class="col-md-2">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-info form-control" data-bs-toggle="modal" data-bs-target="#bulkModal">
                            <i class="fas fa-check-double"></i> Bulk Mark
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Table -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-check"></i> Attendance for <?php echo date('d-m-Y', strtotime($current_date)); ?></h5>
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
                
                <form method="POST">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered" id="attendanceTable">
                            <thead>
                                <tr class="table-dark">
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Room No</th>
                                    <th>Status</th>
                                    <th>Check In Time</th>
                                    <th>Check Out Time</th>
                                    <th>Remarks</th>
                                 </tr>
                            </thead>
                            <tbody>
                                <?php if(count($students) > 0): ?>
                                    <?php foreach($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo $student['room_no'] ?: 'N/A'; ?></td>
                                        <td>
                                            <select name="attendance[<?php echo $student['id']; ?>][status]" class="form-control status-select">
                                                <option value="present" <?php echo $student['status'] == 'present' ? 'selected' : ''; ?>>Present</option>
                                                <option value="absent" <?php echo $student['status'] == 'absent' ? 'selected' : ''; ?>>Absent</option>
                                                <option value="late" <?php echo $student['status'] == 'late' ? 'selected' : ''; ?>>Late</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="time" name="attendance[<?php echo $student['id']; ?>][check_in_time]" 
                                                   class="form-control" value="<?php echo $student['check_in_time']; ?>">
                                        </td>
                                        <td>
                                            <input type="time" name="attendance[<?php echo $student['id']; ?>][check_out_time]" 
                                                   class="form-control" value="<?php echo $student['check_out_time']; ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="attendance[<?php echo $student['id']; ?>][remarks]" 
                                                   class="form-control" placeholder="Optional remarks" 
                                                   value="<?php echo htmlspecialchars($student['remarks']); ?>">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No students found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if(count($students) > 0): ?>
                    <div class="text-end mt-3">
                        <button type="submit" name="save_attendance" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i> Save Attendance
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Attendance Modal -->
<div class="modal fade" id="bulkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-check-double"></i> Bulk Mark Attendance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> This will mark attendance for ALL students with the same status.
                    </div>
                    <div class="mb-3">
                        <label>Bulk Status:</label>
                        <select name="bulk_status" class="form-control" required>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="late">Late</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Check In Time (Optional):</label>
                        <input type="time" name="bulk_check_in" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Check Out Time (Optional):</label>
                        <input type="time" name="bulk_check_out" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="bulk_mark" class="btn btn-info">Mark Attendance</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

.status-select {
    width: 120px;
}

.table th {
    background-color: #f8f9fa;
}

.btn {
    transition: all 0.3s;
}

.btn:hover {
    transform: translateY(-2px);
}
</style>

<?php require_once '../includes/footer.php'; ?>