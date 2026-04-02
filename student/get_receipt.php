<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    http_response_code(403);
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$fee_id = $_GET['id'];

// Get fee details with student info
$query = "SELECT f.*, s.name, s.student_id, s.room_no, s.phone, s.email 
          FROM fees f 
          JOIN students s ON f.student_id = s.id 
          WHERE f.id = :id AND s.id = :student_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $fee_id);
$stmt->bindParam(':student_id', $_SESSION['user_id']);
$stmt->execute();

$receipt = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$receipt) {
    echo "<div class='alert alert-danger'>Receipt not found!</div>";
    exit();
}
?>

<div class="receipt-container">
    <div class="text-center mb-4">
        <h2>HOSTEL MANAGEMENT SYSTEM</h2>
        <p>123 College Road, City Name - 123456</p>
        <p>Phone: +91 1234567890 | Email: hostel@example.com</p>
        <h4 class="mt-3">PAYMENT RECEIPT</h4>
        <hr>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <p><strong>Receipt No:</strong> HMS/<?php echo date('Y') . '/' . str_pad($receipt['id'], 5, '0', STR_PAD_LEFT); ?></p>
            <p><strong>Date:</strong> <?php echo date('d-m-Y', strtotime($receipt['payment_date'])); ?></p>
        </div>
        <div class="col-md-6 text-end">
            <p><strong>Student ID:</strong> <?php echo htmlspecialchars($receipt['student_id']); ?></p>
            <p><strong>Student Name:</strong> <?php echo htmlspecialchars($receipt['name']); ?></p>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <p><strong>Room Number:</strong> <?php echo $receipt['room_no'] ?: 'Not Assigned'; ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($receipt['phone']); ?></p>
        </div>
        <div class="col-md-6 text-end">
            <p><strong>Email:</strong> <?php echo htmlspecialchars($receipt['email']); ?></p>
        </div>
    </div>
    
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Description</th>
                <th class="text-end">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Hostel Fee for <?php echo date('F Y', strtotime($receipt['month_year'] . '-01')); ?></td>
                <td class="text-end"><?php echo number_format($receipt['total_amount'], 2); ?></td>
            </tr>
            <?php if($receipt['paid_amount'] > 0): ?>
            <tr>
                <td>Payment Received</td>
                <td class="text-end text-success">- <?php echo number_format($receipt['paid_amount'], 2); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="table-primary">
                <th>Total Amount</th>
                <th class="text-end">₹<?php echo number_format($receipt['total_amount'], 2); ?></th>
            </tr>
            <tr class="table-success">
                <th>Paid Amount</th>
                <th class="text-end">₹<?php echo number_format($receipt['paid_amount'], 2); ?></th>
            </tr>
            <?php if($receipt['due_amount'] > 0): ?>
            <tr class="table-warning">
                <th>Due Amount</th>
                <th class="text-end">₹<?php echo number_format($receipt['due_amount'], 2); ?></th>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <p><strong>Payment Mode:</strong> <?php echo ucfirst($receipt['payment_mode']); ?></p>
            <p><strong>Transaction Status:</strong> 
                <span class="badge bg-<?php echo $receipt['status'] == 'paid' ? 'success' : 'warning'; ?>">
                    <?php echo strtoupper($receipt['status']); ?>
                </span>
            </p>
        </div>
        <div class="col-md-6 text-end">
            <p><strong>Authorized Signature</strong></p>
            <p>_____________________</p>
            <p>(Hostel Administrator)</p>
        </div>
    </div>
    
    <div class="alert alert-info mt-3 text-center">
        <i class="fas fa-info-circle"></i> This is a computer generated receipt. No signature required.
    </div>
</div>

<style>
.receipt-container {
    padding: 20px;
    font-family: Arial, sans-serif;
}

@media print {
    .btn {
        display: none;
    }
    body {
        padding: 0;
        margin: 0;
    }
    .receipt-container {
        padding: 0;
    }
}
</style>