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

// Resolve complaint
if(isset($_POST['resolve_complaint'])) {
    $complaint_id = $_POST['complaint_id'];
    $resolution = $_POST['resolution'];
    $status = $_POST['status'];
    $resolved_date = date('Y-m-d');
    
    $query = "UPDATE complaints SET status = :status, resolution = :resolution, resolved_date = :resolved_date WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':resolution', $resolution);
    $stmt->bindParam(':resolved_date', $resolved_date);
    $stmt->bindParam(':id', $complaint_id);
    
    if($stmt->execute()) {
        $success = "Complaint updated successfully!";
    } else {
        $error = "Error updating complaint!";
    }
}

// Delete complaint
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $query = "DELETE FROM complaints WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    if($stmt->execute()) {
        $success = "Complaint deleted successfully!";
    } else {
        $error = "Error deleting complaint!";
    }
}

// Get all complaints with student details
$query = "SELECT c.*, s.name, s.student_id, s.room_no, s.phone 
          FROM complaints c 
          JOIN students s ON c.student_id = s.id 
          ORDER BY 
            CASE c.status 
                WHEN 'pending' THEN 1
                WHEN 'in_progress' THEN 2
                WHEN 'resolved' THEN 3
                WHEN 'rejected' THEN 4
            END,
            c.complaint_date DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];
$status_query = "SELECT status, COUNT(*) as count FROM complaints GROUP BY status";
$status_stmt = $db->prepare($status_query);
$status_stmt->execute();
$status_counts = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($status_counts as $count) {
    $stats[$count['status']] = $count['count'];
}
?>

<div class="row">
    <div class="col-md-12">
        <h2>Manage Complaints</h2>
        <hr>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="dashboard-stats bg-danger text-white">
            <h3><?php echo $stats['pending'] ?? 0; ?></h3>
            <p>Pending Complaints</p>
            <i class="fas fa-clock fa-2x"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-stats bg-warning text-white">
            <h3><?php echo $stats['in_progress'] ?? 0; ?></h3>
            <p>In Progress</p>
            <i class="fas fa-spinner fa-2x"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-stats bg-success text-white">
            <h3><?php echo $stats['resolved'] ?? 0; ?></h3>
            <p>Resolved</p>
            <i class="fas fa-check-circle fa-2x"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-stats bg-secondary text-white">
            <h3><?php echo $stats['rejected'] ?? 0; ?></h3>
            <p>Rejected</p>
            <i class="fas fa-times-circle fa-2x"></i>
        </div>
    </div>
</div>

<!-- Complaints Table -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>All Complaints</h5>
            </div>
            <div class="card-body">
                <?php if(isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="complaintsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Room No</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Resolution</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($complaints as $complaint): ?>
                            <tr>
                                <td><?php echo $complaint['id']; ?></td>
                                <td><?php echo htmlspecialchars($complaint['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($complaint['name']); ?></td>
                                <td><?php echo $complaint['room_no'] ?: 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($complaint['complaint_title']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewDescription('<?php echo addslashes($complaint['complaint_description']); ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                                <td><?php echo date('d-m-Y', strtotime($complaint['complaint_date'])); ?></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusIcon = '';
                                    switch($complaint['status']) {
                                        case 'pending':
                                            $statusClass = 'danger';
                                            $statusIcon = 'fa-clock';
                                            break;
                                        case 'in_progress':
                                            $statusClass = 'warning';
                                            $statusIcon = 'fa-spinner';
                                            break;
                                        case 'resolved':
                                            $statusClass = 'success';
                                            $statusIcon = 'fa-check-circle';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'secondary';
                                            $statusIcon = 'fa-times-circle';
                                            break;
                                        default:
                                            $statusClass = 'secondary';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <i class="fas <?php echo $statusIcon; ?>"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($complaint['resolution']): ?>
                                        <button class="btn btn-sm btn-info" onclick="viewResolution('<?php echo addslashes($complaint['resolution']); ?>')">
                                            <i class="fas fa-file-alt"></i> View
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">Not resolved yet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($complaint['status'] != 'resolved' && $complaint['status'] != 'rejected'): ?>
                                        <button class="btn btn-sm btn-success mb-1" onclick="resolveComplaint(<?php echo $complaint['id']; ?>, '<?php echo addslashes($complaint['complaint_title']); ?>')">
                                            <i class="fas fa-check"></i> Resolve
                                        </button>
                                        <button class="btn btn-sm btn-danger mb-1" onclick="rejectComplaint(<?php echo $complaint['id']; ?>, '<?php echo addslashes($complaint['complaint_title']); ?>')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $complaint['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this complaint?')">
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

<!-- Resolve Complaint Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Resolve Complaint</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="resolve_complaint_id" name="complaint_id">
                    <div class="mb-3">
                        <label>Complaint Title:</label>
                        <input type="text" id="resolve_complaint_title" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Status:</label>
                        <select name="status" class="form-control" required>
                            <option value="resolved">Resolved</option>
                            <option value="in_progress">In Progress</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Resolution Details:</label>
                        <textarea name="resolution" class="form-control" rows="4" required placeholder="Enter resolution details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="resolve_complaint" class="btn btn-success">Submit Resolution</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Description Modal -->
<div class="modal fade" id="descriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-file-alt"></i> Complaint Description</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="complaintDescription"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- View Resolution Modal -->
<div class="modal fade" id="resolutionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-file-alt"></i> Resolution Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="complaintResolution"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function resolveComplaint(id, title) {
    document.getElementById('resolve_complaint_id').value = id;
    document.getElementById('resolve_complaint_title').value = title;
    new bootstrap.Modal(document.getElementById('resolveModal')).show();
}

function rejectComplaint(id, title) {
    document.getElementById('resolve_complaint_id').value = id;
    document.getElementById('resolve_complaint_title').value = title;
    document.querySelector('select[name="status"]').value = 'rejected';
    new bootstrap.Modal(document.getElementById('resolveModal')).show();
}

function viewDescription(description) {
    document.getElementById('complaintDescription').innerText = description;
    new bootstrap.Modal(document.getElementById('descriptionModal')).show();
}

function viewResolution(resolution) {
    document.getElementById('complaintResolution').innerText = resolution;
    new bootstrap.Modal(document.getElementById('resolutionModal')).show();
}

// Add search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search complaints...';
    searchInput.className = 'form-control mb-3';
    searchInput.style.width = '300px';
    
    const cardBody = document.querySelector('.card-body');
    cardBody.insertBefore(searchInput, cardBody.firstChild);
    
    searchInput.addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const rows = document.querySelectorAll('#complaintsTable tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchText) ? '' : 'none';
        });
    });
});
</script>

<style>
.badge {
    font-size: 0.85rem;
    padding: 5px 10px;
}

.btn-sm {
    margin: 2px;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.dashboard-stats {
    transition: transform 0.3s;
    cursor: pointer;
}

.dashboard-stats:hover {
    transform: translateY(-5px);
}

.modal-content {
    border-radius: 10px;
}

textarea {
    resize: vertical;
}
</style>

<?php require_once '../includes/footer.php'; ?>