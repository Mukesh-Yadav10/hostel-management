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

// Approve payment request
if(isset($_GET['approve'])) {
    $id = $_GET['approve'];
    
    // Get payment request details
    $query = "SELECT * FROM payment_requests WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($request) {
        // Update payment request status
        $update_query = "UPDATE payment_requests SET status = 'approved', approved_date = NOW() WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $id);
        $update_stmt->execute();
        
        // Update fee record for the student
        $fee_query = "SELECT * FROM fees WHERE student_id = :student_id AND status != 'paid' ORDER BY id DESC LIMIT 1";
        $fee_stmt = $db->prepare($fee_query);
        $fee_stmt->bindParam(':student_id', $request['student_id']);
        $fee_stmt->execute();
        $fee = $fee_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($fee) {
            $new_paid = $fee['paid_amount'] + $request['amount'];
            $new_due = $fee['total_amount'] - $new_paid;
            $status = ($new_due == 0) ? 'paid' : 'partial';
            
            $update_fee = "UPDATE fees SET paid_amount = :paid, due_amount = :due, status = :status, 
                           payment_date = :date, payment_mode = 'online' WHERE id = :id";
            $update_fee_stmt = $db->prepare($update_fee);
            $update_fee_stmt->bindParam(':paid', $new_paid);
            $update_fee_stmt->bindParam(':due', $new_due);
            $update_fee_stmt->bindParam(':status', $status);
            $update_fee_stmt->bindParam(':date', date('Y-m-d'));
            $update_fee_stmt->bindParam(':id', $fee['id']);
            $update_fee_stmt->execute();
        }
        
        $success = "Payment request approved and fee updated!";
    }
}

// Reject payment request
if(isset($_GET['reject'])) {
    $id = $_GET['reject'];
    $query = "UPDATE payment_requests SET status = 'rejected' WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $success = "Payment request rejected!";
}

// Get all payment requests
$query = "SELECT pr.*, s.name, s.student_id, s.room_no 
          FROM payment_requests pr 
          JOIN students s ON pr.student_id = s.id 
          ORDER BY pr.request_date DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-12">
        <h2>Manage Payment Requests</h2>
        <hr>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-money-bill-wave"></i> Payment Verification Requests</h5>
            </div>
            <div class="card-body">
                <?php if(isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student</th>
                                <th>Transaction ID</th>
                                <th>Amount</th>
                                <th>Payment Date</th>
                                <th>Proof</th>
                                <th>Remarks</th>
                                <th>Status</th>
                                <th>Request Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($requests as $request): ?>
                            <tr>
                                <td><?php echo $request['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($request['name']); ?><br>
                                    <small><?php echo $request['student_id']; ?> (Room <?php echo $request['room_no']; ?>)</small>
                                </td>
                                <td><?php echo htmlspecialchars($request['transaction_id']); ?></td>
                                <td class="text-success">₹<?php echo number_format($request['amount'], 2); ?></td>
                                <td><?php echo date('d-m-Y', strtotime($request['payment_date'])); ?></td>
                                <td>
                                    <a href="../uploads/payment_proofs/<?php echo $request['proof_file']; ?>" target="_blank" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                 </td>
                                <td><?php echo htmlspecialchars($request['remarks']); ?></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    switch($request['status']) {
                                        case 'pending':
                                            $statusClass = 'warning';
                                            break;
                                        case 'approved':
                                            $statusClass = 'success';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                 </td>
                                <td><?php echo date('d-m-Y H:i', strtotime($request['request_date'])); ?></td>
                                <td>
                                    <?php if($request['status'] == 'pending'): ?>
                                        <a href="?approve=<?php echo $request['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve this payment request?')">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                        <a href="?reject=<?php echo $request['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject this payment request?')">
                                            <i class="fas fa-times"></i> Reject
                                        </a>
                                    <?php endif; ?>
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

<?php require_once '../includes/footer.php'; ?>