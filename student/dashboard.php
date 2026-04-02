<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a student
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Get student details
$student_id = $_SESSION['user_id'];
$query = "SELECT * FROM students WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get fee details
$query = "SELECT * FROM fees WHERE student_id = :student_id ORDER BY id DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

// Get complaints
$query = "SELECT * FROM complaints WHERE student_id = :student_id ORDER BY id DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance for current month
$query = "SELECT COUNT(*) as total_present FROM attendance 
          WHERE student_id = :student_id 
          AND attendance_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
          AND status = 'present'";
$stmt = $db->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-12">
        <h2>Welcome, <?php echo htmlspecialchars($student['name']); ?>!</h2>
        <hr>
    </div>
    
    <div class="col-md-3">
        <div class="dashboard-stats bg-primary text-white">
            <h4><?php echo $student['room_no'] ?: 'Not Assigned'; ?></h4>
            <p>Room Number</p>
            <i class="fas fa-bed fa-2x"></i>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="dashboard-stats bg-success text-white">
            <h4><?php echo $fee ? '₹' . number_format($fee['due_amount'], 2) : 'No Dues'; ?></h4>
            <p>Pending Dues</p>
            <i class="fas fa-rupee-sign fa-2x"></i>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="dashboard-stats bg-info text-white">
            <h4><?php echo $attendance['total_present'] ?? 0; ?> / 30</h4>
            <p>Attendance (This Month)</p>
            <i class="fas fa-calendar-check fa-2x"></i>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="dashboard-stats bg-warning text-white">
            <h4><?php echo count($complaints); ?></h4>
            <p>Total Complaints</p>
            <i class="fas fa-exclamation-circle fa-2x"></i>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user-circle"></i> Personal Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th width="40%">Student ID:</th>
                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                    </tr>
                    <tr>
                        <th>Full Name:</th>
                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                    </tr>
                    <tr>
                        <th>Phone Number:</th>
                        <td><?php echo htmlspecialchars($student['phone'] ?: 'Not Provided'); ?></td>
                    </tr>
                    <tr>
                        <th>Parent/Guardian Phone:</th>
                        <td><?php echo htmlspecialchars($student['parent_phone'] ?: 'Not Provided'); ?></td>
                    </tr>
                    <tr>
                        <th>Room Number:</th>
                        <td><?php echo htmlspecialchars($student['room_no'] ?: 'Not Assigned'); ?></td>
                    </tr>
                    <tr>
                        <th>Joining Date:</th>
                        <td><?php echo date('d-m-Y', strtotime($student['joining_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Address:</th>
                        <td><?php echo htmlspecialchars($student['address'] ?: 'Not Provided'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-comment-alt"></i> Recent Complaints</h5>
                <button class="btn btn-sm btn-light float-end" data-bs-toggle="modal" data-bs-target="#complaintModal">
                    <i class="fas fa-plus"></i> New Complaint
                </button>
            </div>
            <div class="card-body">
                <?php if(count($complaints) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                 <tr>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                 </tr>
                            </thead>
                            <tbody>
                                <?php foreach($complaints as $complaint): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($complaint['complaint_title']); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($complaint['complaint_date'])); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        $statusText = '';
                                        switch($complaint['status']) {
                                            case 'resolved':
                                                $statusClass = 'success';
                                                $statusText = 'Resolved';
                                                break;
                                            case 'pending':
                                                $statusClass = 'warning';
                                                $statusText = 'Pending';
                                                break;
                                            case 'in_progress':
                                                $statusClass = 'info';
                                                $statusText = 'In Progress';
                                                break;
                                            case 'rejected':
                                                $statusClass = 'danger';
                                                $statusText = 'Rejected';
                                                break;
                                            default:
                                                $statusClass = 'secondary';
                                                $statusText = $complaint['status'];
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewComplaint(<?php echo $complaint['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> No complaints found. Click "New Complaint" to register one.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Quick Links</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <a href="view_fees.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-rupee-sign"></i> View Fee Details
                        </a>
                    </div>
                    <div class="col-md-6 mb-2">
                        <a href="view_attendance.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-calendar-check"></i> View Attendance
                        </a>
                    </div>
                    <div class="col-md-6 mb-2">
                        <a href="edit_profile.php" class="btn btn-outline-warning w-100">
                            <i class="fas fa-user-edit"></i> Edit Profile
                        </a>
                    </div>
                    <div class="col-md-6 mb-2">
                        <a href="change_password.php" class="btn btn-outline-danger w-100">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Complaint Modal -->
<div class="modal fade" id="complaintModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Register New Complaint</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="submit_complaint.php" onsubmit="return validateComplaint()">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="complaint_title" class="form-label">Complaint Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="complaint_title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="complaint_description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="complaint_description" name="description" rows="5" required></textarea>
                        <small class="text-muted">Please provide detailed description of your complaint.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Complaint</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Complaint Modal -->
<div class="modal fade" id="viewComplaintModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Complaint Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="complaintDetails">
                <!-- Complaint details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function validateComplaint() {
    let title = document.getElementById('complaint_title').value.trim();
    let description = document.getElementById('complaint_description').value.trim();
    
    if(title === '') {
        alert('Please enter complaint title');
        return false;
    }
    
    if(description === '') {
        alert('Please enter complaint description');
        return false;
    }
    
    if(description.length < 10) {
        alert('Please provide at least 10 characters in description');
        return false;
    }
    
    return true;
}

function viewComplaint(id) {
    // You can implement AJAX to fetch complaint details
    // For now, we'll show a simple alert
    alert('Complaint ID: ' + id + '\n\nYou can view detailed complaint information here.\nThis feature can be implemented with AJAX.');
    
    // Example AJAX implementation:
    /*
    fetch('get_complaint.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            let html = `
                <h6>Title: ${data.title}</h6>
                <p><strong>Date:</strong> ${data.date}</p>
                <p><strong>Status:</strong> <span class="badge bg-${data.status_class}">${data.status}</span></p>
                <p><strong>Description:</strong><br>${data.description}</p>
                ${data.resolution ? `<p><strong>Resolution:</strong><br>${data.resolution}</p>` : ''}
                ${data.resolved_date ? `<p><strong>Resolved Date:</strong> ${data.resolved_date}</p>` : ''}
            `;
            document.getElementById('complaintDetails').innerHTML = html;
            new bootstrap.Modal(document.getElementById('viewComplaintModal')).show();
        });
    */
}
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

.dashboard-stats i {
    position: absolute;
    right: 20px;
    bottom: 20px;
    opacity: 0.3;
}

.card {
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.table th {
    background-color: #f8f9fa;
}

.btn-outline-primary:hover, 
.btn-outline-success:hover, 
.btn-outline-warning:hover, 
.btn-outline-danger:hover {
    transform: translateY(-2px);
    transition: transform 0.3s;
}
</style>

<?php require_once '../includes/footer.php'; ?>