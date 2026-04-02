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

// Add new fee record
if(isset($_POST['add_fee'])) {
    $student_id = $_POST['student_id'];
    $month_year = $_POST['month_year'];
    $total_amount = $_POST['total_amount'];
    $paid_amount = $_POST['paid_amount'];
    $due_amount = $total_amount - $paid_amount;
    $payment_date = $_POST['payment_date'];
    $payment_mode = $_POST['payment_mode'];
    $status = ($due_amount == 0) ? 'paid' : (($paid_amount > 0) ? 'partial' : 'pending');
    
    // Check if fee already exists for this student and month
    $check_query = "SELECT id FROM fees WHERE student_id = :student_id AND month_year = :month_year";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':student_id', $student_id);
    $check_stmt->bindParam(':month_year', $month_year);
    $check_stmt->execute();
    
    if($check_stmt->rowCount() > 0) {
        $error = "Fee record already exists for this student and month!";
    } else {
        $query = "INSERT INTO fees (student_id, month_year, total_amount, paid_amount, due_amount, payment_date, payment_mode, status) 
                  VALUES (:student_id, :month_year, :total_amount, :paid_amount, :due_amount, :payment_date, :payment_mode, :status)";
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':month_year', $month_year);
        $stmt->bindParam(':total_amount', $total_amount);
        $stmt->bindParam(':paid_amount', $paid_amount);
        $stmt->bindParam(':due_amount', $due_amount);
        $stmt->bindParam(':payment_date', $payment_date);
        $stmt->bindParam(':payment_mode', $payment_mode);
        $stmt->bindParam(':status', $status);
        
        if($stmt->execute()) {
            $success = "Fee record added successfully!";
        } else {
            $error = "Error adding fee record!";
        }
    }
}

// Update fee payment
if(isset($_POST['update_payment'])) {
    $fee_id = $_POST['fee_id'];
    $additional_payment = $_POST['additional_payment'];
    
    // Get current fee details
    $query = "SELECT * FROM fees WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $fee_id);
    $stmt->execute();
    $fee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $new_paid = $fee['paid_amount'] + $additional_payment;
    $new_due = $fee['total_amount'] - $new_paid;
    $status = ($new_due == 0) ? 'paid' : (($new_paid > 0) ? 'partial' : 'pending');
    
    // Update fee record
    $update_query = "UPDATE fees SET paid_amount = :paid_amount, due_amount = :due_amount, 
                     status = :status, payment_date = :payment_date, payment_mode = :payment_mode 
                     WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':paid_amount', $new_paid);
    $update_stmt->bindParam(':due_amount', $new_due);
    $update_stmt->bindParam(':status', $status);
    $update_stmt->bindParam(':payment_date', date('Y-m-d'));
    $update_stmt->bindParam(':payment_mode', $_POST['payment_mode']);
    $update_stmt->bindParam(':id', $fee_id);
    
    if($update_stmt->execute()) {
        $success = "Payment updated successfully!";
    } else {
        $error = "Error updating payment!";
    }
}

// Delete fee record
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $query = "DELETE FROM fees WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    if($stmt->execute()) {
        $success = "Fee record deleted successfully!";
    } else {
        $error = "Error deleting fee record!";
    }
}

// Get all students for dropdown
$students_query = "SELECT id, student_id, name, room_no FROM students WHERE status = 'active' ORDER BY name";
$students_stmt = $db->prepare($students_query);
$students_stmt->execute();
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all fee records with student details
$fees_query = "SELECT f.*, s.name, s.student_id, s.room_no 
               FROM fees f 
               JOIN students s ON f.student_id = s.id 
               ORDER BY f.id DESC";
$fees_stmt = $db->prepare($fees_query);
$fees_stmt->execute();
$fees = $fees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary
$total_collected = 0;
$total_due = 0;
foreach($fees as $fee) {
    $total_collected += $fee['paid_amount'];
    $total_due += $fee['due_amount'];
}
?>

<div class="row">
    <div class="col-md-12">
        <h2>Manage Fees</h2>
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addFeeModal">
            <i class="fas fa-plus"></i> Add Fee Record
        </button>
        <hr>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="dashboard-stats bg-success text-white">
            <h4>₹<?php echo number_format($total_collected, 2); ?></h4>
            <p>Total Fees Collected</p>
            <i class="fas fa-rupee-sign fa-2x"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-stats bg-warning text-white">
            <h4>₹<?php echo number_format($total_due, 2); ?></h4>
            <p>Total Pending Dues</p>
            <i class="fas fa-clock fa-2x"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-stats bg-info text-white">
            <h4><?php echo count($fees); ?></h4>
            <p>Total Transactions</p>
            <i class="fas fa-receipt fa-2x"></i>
        </div>
    </div>
</div>

<!-- Fee Records Table -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>Fee Records</h5>
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
                    <table class="table table-striped table-bordered" id="feesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Room No</th>
                                <th>Month/Year</th>
                                <th>Total Amount</th>
                                <th>Paid Amount</th>
                                <th>Due Amount</th>
                                <th>Payment Date</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($fees as $fee): ?>
                            <tr>
                                <td><?php echo $fee['id']; ?></td>
                                <td><?php echo htmlspecialchars($fee['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($fee['name']); ?></td>
                                <td><?php echo $fee['room_no'] ?: 'N/A'; ?></td>
                                <td><?php echo $fee['month_year']; ?></td>
                                <td>₹<?php echo number_format($fee['total_amount'], 2); ?></td>
                                <td>₹<?php echo number_format($fee['paid_amount'], 2); ?></td>
                                <td>
                                    <?php if($fee['due_amount'] > 0): ?>
                                        <span class="text-danger">₹<?php echo number_format($fee['due_amount'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-success">₹0</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $fee['payment_date'] ? date('d-m-Y', strtotime($fee['payment_date'])) : '-'; ?></td>
                                <td>
                                    <?php
                                    $modeClass = '';
                                    switch($fee['payment_mode']) {
                                        case 'cash':
                                            $modeClass = 'success';
                                            break;
                                        case 'online':
                                            $modeClass = 'info';
                                            break;
                                        case 'cheque':
                                            $modeClass = 'warning';
                                            break;
                                        default:
                                            $modeClass = 'secondary';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $modeClass; ?>">
                                        <?php echo ucfirst($fee['payment_mode'] ?: 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    switch($fee['status']) {
                                        case 'paid':
                                            $statusClass = 'success';
                                            break;
                                        case 'partial':
                                            $statusClass = 'warning';
                                            break;
                                        case 'pending':
                                            $statusClass = 'danger';
                                            break;
                                        default:
                                            $statusClass = 'secondary';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo ucfirst($fee['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($fee['due_amount'] > 0): ?>
                                        <button class="btn btn-sm btn-success" onclick="makePayment(<?php echo $fee['id']; ?>, '<?php echo $fee['name']; ?>', <?php echo $fee['due_amount']; ?>)">
                                            <i class="fas fa-money-bill"></i> Pay
                                        </button>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $fee['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
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

<!-- Add Fee Modal -->
<div class="modal fade" id="addFeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Fee Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Select Student <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-control" required onchange="getStudentRoom(this.value)">
                            <option value="">Select Student</option>
                            <?php foreach($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>" data-room="<?php echo $student['room_no']; ?>">
                                <?php echo $student['student_id'] . ' - ' . $student['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Room Number</label>
                        <input type="text" id="room_no" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Month/Year <span class="text-danger">*</span></label>
                        <input type="month" name="month_year" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Total Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" name="total_amount" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label>Paid Amount (₹)</label>
                        <input type="number" name="paid_amount" class="form-control" step="0.01" value="0">
                    </div>
                    <div class="mb-3">
                        <label>Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label>Payment Mode</label>
                        <select name="payment_mode" class="form-control">
                            <option value="cash">Cash</option>
                            <option value="online">Online</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_fee" class="btn btn-primary">Add Fee Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave"></i> Make Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="payment_fee_id" name="fee_id">
                    <div class="mb-3">
                        <label>Student Name:</label>
                        <input type="text" id="payment_student_name" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Due Amount:</label>
                        <input type="text" id="payment_due_amount" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Payment Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" name="additional_payment" id="payment_amount" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label>Payment Mode</label>
                        <select name="payment_mode" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="online">Online</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_payment" class="btn btn-success">Make Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function getStudentRoom(studentId) {
    let select = document.querySelector('select[name="student_id"]');
    let selectedOption = select.options[select.selectedIndex];
    let roomNo = selectedOption.getAttribute('data-room');
    document.getElementById('room_no').value = roomNo || 'Not Assigned';
}

function makePayment(feeId, studentName, dueAmount) {
    document.getElementById('payment_fee_id').value = feeId;
    document.getElementById('payment_student_name').value = studentName;
    document.getElementById('payment_due_amount').value = '₹' + dueAmount.toFixed(2);
    document.getElementById('payment_amount').value = dueAmount;
    document.getElementById('payment_amount').max = dueAmount;
    
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

// Validate payment amount
document.getElementById('payment_amount').addEventListener('input', function() {
    let max = parseFloat(this.max);
    let value = parseFloat(this.value);
    if(value > max) {
        this.value = max;
        alert('Payment amount cannot exceed due amount!');
    }
    if(value < 0) {
        this.value = 0;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>