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

// Get student details
$student_query = "SELECT * FROM students WHERE id = :id";
$student_stmt = $db->prepare($student_query);
$student_stmt->bindParam(':id', $student_id);
$student_stmt->execute();
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

// Get all fee records for this student
$fees_query = "SELECT * FROM fees WHERE student_id = :student_id ORDER BY month_year DESC";
$fees_stmt = $db->prepare($fees_query);
$fees_stmt->bindParam(':student_id', $student_id);
$fees_stmt->execute();
$fees = $fees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary
$total_paid = 0;
$total_due = 0;
$total_amount = 0;
$pending_count = 0;

foreach($fees as $fee) {
    $total_amount += $fee['total_amount'];
    $total_paid += $fee['paid_amount'];
    $total_due += $fee['due_amount'];
    if($fee['status'] != 'paid') {
        $pending_count++;
    }
}

// Get last payment date
$last_payment = null;
if(count($fees) > 0) {
    $last_payment_query = "SELECT MAX(payment_date) as last_date FROM fees WHERE student_id = :student_id AND payment_date IS NOT NULL";
    $last_stmt = $db->prepare($last_payment_query);
    $last_stmt->bindParam(':student_id', $student_id);
    $last_stmt->execute();
    $last_payment = $last_stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="row">
    <div class="col-md-12">
        <h2>Fee Management</h2>
        <p class="text-muted">Welcome, <?php echo htmlspecialchars($student['name']); ?>!</p>
        <hr>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="dashboard-stats bg-primary text-white">
            <h4>₹<?php echo number_format($total_amount, 2); ?></h4>
            <p>Total Fee Amount</p>
            <i class="fas fa-rupee-sign fa-2x"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-stats bg-success text-white">
            <h4>₹<?php echo number_format($total_paid, 2); ?></h4>
            <p>Total Paid</p>
            <i class="fas fa-check-circle fa-2x"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-stats bg-warning text-white">
            <h4>₹<?php echo number_format($total_due, 2); ?></h4>
            <p>Total Due</p>
            <i class="fas fa-clock fa-2x"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-stats bg-info text-white">
            <h4><?php echo $pending_count; ?></h4>
            <p>Pending Payments</p>
            <i class="fas fa-exclamation-triangle fa-2x"></i>
        </div>
    </div>
</div>

<!-- Fee Payment Status -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Fee Payment Status</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="progress mb-3" style="height: 30px;">
                            <?php 
                            $percentage = $total_amount > 0 ? ($total_paid / $total_amount) * 100 : 0;
                            $statusClass = $percentage == 100 ? 'bg-success' : ($percentage > 50 ? 'bg-warning' : 'bg-danger');
                            ?>
                            <div class="progress-bar <?php echo $statusClass; ?>" 
                                 role="progressbar" 
                                 style="width: <?php echo $percentage; ?>%" 
                                 aria-valuenow="<?php echo $percentage; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                <?php echo round($percentage, 2); ?>% Paid
                            </div>
                        </div>
                        <p class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            <?php if($percentage == 100): ?>
                                Congratulations! You have cleared all your fees.
                            <?php elseif($percentage > 0): ?>
                                You have paid <?php echo round($percentage, 2); ?>% of your total fees.
                                Remaining balance: ₹<?php echo number_format($total_due, 2); ?>
                            <?php else: ?>
                                No payments made yet. Total due: ₹<?php echo number_format($total_due, 2); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <?php if($total_due > 0): ?>
                            <button class="btn btn-success btn-lg" onclick="generatePaymentRequest()">
                                <i class="fas fa-money-bill-wave"></i> Pay Now
                            </button>
                        <?php else: ?>
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-check-circle"></i> All fees cleared!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fee History Table -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-history"></i> Fee Payment History</h5>
            </div>
            <div class="card-body">
                <?php if(count($fees) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered" id="feeTable">
                            <thead>
                                <tr>
                                    <th>Month/Year</th>
                                    <th>Total Amount</th>
                                    <th>Paid Amount</th>
                                    <th>Due Amount</th>
                                    <th>Payment Date</th>
                                    <th>Payment Mode</th>
                                    <th>Status</th>
                                    <th>Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($fees as $fee): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('F Y', strtotime($fee['month_year'] . '-01')); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $fee['month_year']; ?></small>
                                    </td>
                                    <td class="text-end">₹<?php echo number_format($fee['total_amount'], 2); ?></td>
                                    <td class="text-end text-success">
                                        <strong>₹<?php echo number_format($fee['paid_amount'], 2); ?></strong>
                                    </td>
                                    <td class="text-end">
                                        <?php if($fee['due_amount'] > 0): ?>
                                            <span class="text-danger fw-bold">₹<?php echo number_format($fee['due_amount'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-success">₹0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $fee['payment_date'] ? date('d-m-Y', strtotime($fee['payment_date'])) : '<span class="text-muted">Not Paid</span>'; ?>
                                    </td>
                                    <td>
                                        <?php if($fee['payment_mode']): ?>
                                            <span class="badge bg-<?php 
                                                echo $fee['payment_mode'] == 'cash' ? 'success' : 
                                                    ($fee['payment_mode'] == 'online' ? 'info' : 'warning'); 
                                            ?>">
                                                <i class="fas fa-<?php 
                                                    echo $fee['payment_mode'] == 'cash' ? 'money-bill' : 
                                                        ($fee['payment_mode'] == 'online' ? 'laptop' : 'receipt'); 
                                                ?>"></i>
                                                <?php echo ucfirst($fee['payment_mode']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        $statusIcon = '';
                                        switch($fee['status']) {
                                            case 'paid':
                                                $statusClass = 'success';
                                                $statusIcon = 'fa-check-circle';
                                                break;
                                            case 'partial':
                                                $statusClass = 'warning';
                                                $statusIcon = 'fa-exclamation-triangle';
                                                break;
                                            case 'pending':
                                                $statusClass = 'danger';
                                                $statusIcon = 'fa-times-circle';
                                                break;
                                            default:
                                                $statusClass = 'secondary';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <i class="fas <?php echo $statusIcon; ?>"></i>
                                            <?php echo ucfirst($fee['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if($fee['paid_amount'] > 0): ?>
                                            <button class="btn btn-sm btn-info" onclick="generateReceipt(<?php echo $fee['id']; ?>)">
                                                <i class="fas fa-download"></i> Receipt
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-dark">
                                <tr>
                                    <th class="text-end">Total:</th>
                                    <th class="text-end">₹<?php echo number_format($total_amount, 2); ?></th>
                                    <th class="text-end">₹<?php echo number_format($total_paid, 2); ?></th>
                                    <th class="text-end">₹<?php echo number_format($total_due, 2); ?></th>
                                    <th colspan="4"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <h5>No fee records found</h5>
                        <p>Your fee records will appear here once added by the admin.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Payment Request Modal -->
<div class="modal fade" id="paymentRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave"></i> Payment Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Please make the payment to the following account:
                </div>
                
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title">Bank Details:</h6>
                        <p class="mb-1"><strong>Account Name:</strong> Hostel Management System</p>
                        <p class="mb-1"><strong>Account Number:</strong> 1234567890</p>
                        <p class="mb-1"><strong>Bank Name:</strong> State Bank of India</p>
                        <p class="mb-1"><strong>IFSC Code:</strong> SBIN0012345</p>
                        <p class="mb-0"><strong>Branch:</strong> Main Branch, City Name</p>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title">UPI Details:</h6>
                        <p class="mb-1"><strong>UPI ID:</strong> hostel@okhdfcbank</p>
                        <p class="mb-0"><strong>QR Code:</strong> Scan to pay</p>
                        <div class="text-center mt-2">
                            <i class="fas fa-qrcode fa-4x text-dark"></i>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-clock"></i> After payment, please upload the transaction screenshot or contact the admin for confirmation.
                </div>
                
                <form action="upload_payment_proof.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label>Transaction ID:</label>
                        <input type="text" name="transaction_id" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Amount Paid:</label>
                        <input type="number" name="amount" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label>Payment Date:</label>
                        <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Upload Payment Screenshot:</label>
                        <input type="file" name="payment_proof" class="form-control" accept="image/*,.pdf" required>
                        <small class="text-muted">Accepted formats: JPG, PNG, PDF (Max 2MB)</small>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Submit Payment Proof</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-receipt"></i> Payment Receipt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <!-- Receipt content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function generatePaymentRequest() {
    new bootstrap.Modal(document.getElementById('paymentRequestModal')).show();
}

function generateReceipt(feeId) {
    // Fetch receipt details via AJAX
    fetch('get_receipt.php?id=' + feeId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('receiptContent').innerHTML = data;
            new bootstrap.Modal(document.getElementById('receiptModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading receipt');
        });
}

function printReceipt() {
    const receiptContent = document.getElementById('receiptContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Payment Receipt</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                @media print {
                    .no-print {
                        display: none;
                    }
                    body {
                        padding: 20px;
                    }
                }
            </style>
        </head>
        <body>
            ${receiptContent}
            <div class="text-center mt-4 no-print">
                <button class="btn btn-primary" onclick="window.print()">Print</button>
                <button class="btn btn-secondary" onclick="window.close()">Close</button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Add search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search by month/year...';
    searchInput.className = 'form-control mb-3';
    searchInput.style.width = '300px';
    
    const cardBody = document.querySelector('.card-body');
    if(cardBody && document.querySelector('#feeTable')) {
        cardBody.insertBefore(searchInput, cardBody.firstChild);
        
        searchInput.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('#feeTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    }
});

// Download receipt as PDF
function downloadReceipt(feeId) {
    window.location.href = 'download_receipt.php?id=' + feeId;
}
</script>

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

.dashboard-stats h4 {
    margin-bottom: 10px;
    font-weight: bold;
    font-size: 1.8rem;
}

.dashboard-stats p {
    margin-bottom: 0;
    opacity: 0.9;
}

.dashboard-stats i {
    position: absolute;
    right: 20px;
    bottom: 20px;
    opacity: 0.3;
    font-size: 3rem;
}

.progress {
    border-radius: 15px;
    overflow: hidden;
}

.progress-bar {
    transition: width 1s ease;
    font-weight: bold;
    line-height: 30px;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.badge {
    font-size: 0.85rem;
    padding: 5px 10px;
}

.btn-sm {
    margin: 2px;
}

.card {
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.text-end {
    text-align: right;
}

@media (max-width: 768px) {
    .dashboard-stats {
        margin-bottom: 15px;
    }
    
    .dashboard-stats h4 {
        font-size: 1.2rem;
    }
    
    .table {
        font-size: 0.9rem;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeIn 0.5s ease;
}
</style>

<?php require_once '../includes/footer.php'; ?>