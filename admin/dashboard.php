<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];

// Total students
$query = "SELECT COUNT(*) as total FROM students WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_students'] = $result ? $result['total'] : 0;

// Total rooms
$query = "SELECT COUNT(*) as total FROM rooms";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_rooms'] = $result ? $result['total'] : 0;

// Available rooms
$query = "SELECT COUNT(*) as total FROM rooms WHERE status = 'available'";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['available_rooms'] = $result ? $result['total'] : 0;

// Pending complaints
$query = "SELECT COUNT(*) as total FROM complaints WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['pending_complaints'] = $result ? $result['total'] : 0;

// Total fees collected this month
$query = "SELECT SUM(paid_amount) as total FROM fees WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['monthly_fees'] = $result && $result['total'] ? $result['total'] : 0;

// Get recent students
$query = "SELECT * FROM students ORDER BY id DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent complaints
$query = "SELECT c.*, s.name as student_name FROM complaints c 
          JOIN students s ON c.student_id = s.id 
          ORDER BY c.id DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-12">
        <h2>Admin Dashboard</h2>
        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>!</p>
        <hr>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="dashboard-stats bg-primary text-white">
            <h3><?php echo $stats['total_students']; ?></h3>
            <p>Total Students</p>
            <i class="fas fa-users fa-2x"></i>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="dashboard-stats bg-success text-white">
            <h3><?php echo $stats['total_rooms']; ?></h3>
            <p>Total Rooms</p>
            <i class="fas fa-bed fa-2x"></i>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="dashboard-stats bg-info text-white">
            <h3><?php echo $stats['available_rooms']; ?></h3>
            <p>Available Rooms</p>
            <i class="fas fa-check-circle fa-2x"></i>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="dashboard-stats bg-warning text-white">
            <h3><?php echo $stats['pending_complaints']; ?></h3>
            <p>Pending Complaints</p>
            <i class="fas fa-exclamation-triangle fa-2x"></i>
        </div>
    </div>
    
    <div class="col-md-12 mb-4">
        <div class="dashboard-stats bg-secondary text-white">
            <h4>Monthly Fee Collection: ₹<?php echo number_format($stats['monthly_fees'], 2); ?></h4>
            <i class="fas fa-rupee-sign fa-2x"></i>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="manage_students.php" class="btn btn-primary">
                        <i class="fas fa-users"></i> Manage Students
                    </a>
                    <a href="manage_rooms.php" class="btn btn-success">
                        <i class="fas fa-bed"></i> Manage Rooms
                    </a>
                    <a href="manage_fees.php" class="btn btn-info">
                        <i class="fas fa-rupee-sign"></i> Manage Fees
                    </a>
                    <a href="manage_complaints.php" class="btn btn-warning">
                        <i class="fas fa-comment-alt"></i> Manage Complaints
                    </a>
                    <a href="manage_attendance.php" class="btn btn-secondary">
                        <i class="fas fa-calendar-check"></i> Manage Attendance
                    </a>
                    <a href="manage_visitors.php" class="btn btn-dark">
                        <i class="fas fa-user-friends"></i> Manage Visitors
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-user-graduate"></i> Recent Students</h5>
            </div>
            <div class="card-body">
                <?php if(count($recent_students) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Room No</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['room_no'] ?: 'Not Assigned'); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = $student['status'] == 'active' ? 'success' : 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo ucfirst($student['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> No students found.
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-end">
                <a href="manage_students.php" class="btn btn-sm btn-primary">View All Students</a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fas fa-comment-dots"></i> Recent Complaints</h5>
            </div>
            <div class="card-body">
                <?php if(count($recent_complaints) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_complaints as $complaint): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($complaint['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($complaint['complaint_title']); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($complaint['complaint_date'])); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch($complaint['status']) {
                                            case 'resolved':
                                                $statusClass = 'success';
                                                break;
                                            case 'pending':
                                                $statusClass = 'warning';
                                                break;
                                            case 'in_progress':
                                                $statusClass = 'info';
                                                break;
                                            case 'rejected':
                                                $statusClass = 'danger';
                                                break;
                                            default:
                                                $statusClass = 'secondary';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo ucfirst($complaint['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_complaint.php?id=<?php echo $complaint['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if($complaint['status'] == 'pending'): ?>
                                        <a href="resolve_complaint.php?id=<?php echo $complaint['id']; ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Resolve
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> No complaints found.
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-end">
                <a href="manage_complaints.php" class="btn btn-sm btn-primary">View All Complaints</a>
            </div>
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
    cursor: pointer;
}

.dashboard-stats:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.dashboard-stats h3, .dashboard-stats h4 {
    margin-bottom: 10px;
    font-weight: bold;
}

.dashboard-stats p {
    margin-bottom: 0;
    opacity: 0.8;
}

.dashboard-stats i {
    position: absolute;
    right: 20px;
    bottom: 20px;
    opacity: 0.3;
    font-size: 3rem;
}

.card {
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.btn {
    transition: all 0.3s;
}

.btn:hover {
    transform: translateY(-2px);
}

.d-grid .btn {
    margin-bottom: 10px;
    text-align: left;
    padding: 12px 15px;
}

.d-grid .btn i {
    margin-right: 10px;
    width: 20px;
}

@media (max-width: 768px) {
    .dashboard-stats {
        margin-bottom: 15px;
    }
    
    .dashboard-stats h3 {
        font-size: 1.5rem;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>