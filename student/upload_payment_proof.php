<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$student_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get student details
$student_query = "SELECT * FROM students WHERE id = :id";
$student_stmt = $db->prepare($student_query);
$student_stmt->bindParam(':id', $student_id);
$student_stmt->execute();
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

// Handle payment proof upload
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $transaction_id = trim($_POST['transaction_id']);
    $amount = $_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $remarks = trim($_POST['remarks']);
    
    // Validation
    $errors = [];
    
    if(empty($transaction_id)) {
        $errors[] = "Transaction ID is required";
    }
    
    if(empty($amount) || $amount <= 0) {
        $errors[] = "Valid amount is required";
    }
    
    if(empty($payment_date)) {
        $errors[] = "Payment date is required";
    }
    
    // Handle file upload
    if(isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        $filename = $_FILES['payment_proof']['name'];
        $fileext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $filesize = $_FILES['payment_proof']['size'];
        
        if(!in_array($fileext, $allowed)) {
            $errors[] = "Invalid file type. Allowed: JPG, JPEG, PNG, GIF, PDF";
        }
        
        if($filesize > 2 * 1024 * 1024) { // 2MB limit
            $errors[] = "File size too large. Maximum 2MB allowed";
        }
        
        if(empty($errors)) {
            // Create upload directory if not exists
            $upload_dir = "../uploads/payment_proofs/";
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $new_filename = "payment_" . $student_id . "_" . time() . "_" . rand(1000, 9999) . "." . $fileext;
            $upload_path = $upload_dir . $new_filename;
            
            if(move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_path)) {
                // Save payment request to database
                $query = "INSERT INTO payment_requests (student_id, transaction_id, amount, payment_date, 
                          proof_file, remarks, status, request_date) 
                          VALUES (:student_id, :transaction_id, :amount, :payment_date, 
                          :proof_file, :remarks, 'pending', NOW())";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':student_id', $student_id);
                $stmt->bindParam(':transaction_id', $transaction_id);
                $stmt->bindParam(':amount', $amount);
                $stmt->bindParam(':payment_date', $payment_date);
                $stmt->bindParam(':proof_file', $new_filename);
                $stmt->bindParam(':remarks', $remarks);
                
                if($stmt->execute()) {
                    $success = "Payment proof uploaded successfully! Admin will verify and update your fee status.";
                    
                    // Send notification to admin (optional - can be implemented)
                    // You can add email notification or admin alert here
                    
                    // Redirect after 3 seconds
                    header("refresh:3;url=view_fees.php");
                } else {
                    $errors[] = "Failed to save payment request. Please try again.";
                    // Delete uploaded file if database insertion fails
                    if(file_exists($upload_path)) {
                        unlink($upload_path);
                    }
                }
            } else {
                $errors[] = "Failed to upload file. Please try again.";
            }
        }
    } else {
        $errors[] = "Please select a payment proof file to upload";
    }
    
    if(!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

// Get recent payment requests
$requests_query = "SELECT * FROM payment_requests 
                   WHERE student_id = :student_id 
                   ORDER BY request_date DESC LIMIT 5";
$requests_stmt = $db->prepare($requests_query);
$requests_stmt->bindParam(':student_id', $student_id);
$requests_stmt->execute();
$payment_requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Payment Proof - Hostel Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        .drop-zone {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #667eea;
            background: #f0f0ff;
        }
        .preview-image {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 10px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        .status-pending {
            background-color: #ffc107;
            color: #000;
        }
        .status-approved {
            background-color: #28a745;
            color: #fff;
        }
        .status-rejected {
            background-color: #dc3545;
            color: #fff;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card {
            animation: fadeIn 0.5s ease;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <a href="dashboard.php" class="btn btn-secondary mb-3">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-upload"></i> Upload Payment Proof</h4>
                    </div>
                    <div class="card-body">
                        <?php if($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Payment Instructions:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Make payment to the bank account or UPI ID provided</li>
                                <li>Take a screenshot or photo of the payment confirmation</li>
                                <li>Upload the proof using the form below</li>
                                <li>Admin will verify and update your fee status within 24 hours</li>
                            </ul>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="mb-3">
                                <label for="transaction_id" class="form-label">Transaction ID / Reference Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                       placeholder="Enter transaction ID from bank/UPI" required>
                                <small class="text-muted">Example: SBIN1234567890 or UPI-1234567890</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="amount" class="form-label">Amount Paid (₹) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           step="0.01" min="1" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="remarks" class="form-label">Additional Remarks (Optional)</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="2" 
                                          placeholder="Any additional information about the payment..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Payment Proof <span class="text-danger">*</span></label>
                                <div class="drop-zone" id="dropZone">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                                    <p class="mb-1">Drag & drop your file here or click to browse</p>
                                    <small class="text-muted">Supported formats: JPG, JPEG, PNG, GIF, PDF (Max 2MB)</small>
                                    <input type="file" id="payment_proof" name="payment_proof" style="display: none;" accept=".jpg,.jpeg,.png,.gif,.pdf">
                                </div>
                                <div id="filePreview" class="text-center mt-2"></div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                                <i class="fas fa-paper-plane"></i> Submit Payment Proof
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Recent Payment Requests</h5>
                    </div>
                    <div class="card-body">
                        <?php if(count($payment_requests) > 0): ?>
                            <div class="list-group">
                                <?php foreach($payment_requests as $request): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>₹<?php echo number_format($request['amount'], 2); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo date('d-m-Y', strtotime($request['request_date'])); ?></small>
                                            </div>
                                            <div>
                                                <span class="status-badge status-<?php echo $request['status']; ?>">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small><strong>Transaction ID:</strong> <?php echo htmlspecialchars($request['transaction_id']); ?></small>
                                            <?php if($request['remarks']): ?>
                                                <br><small><strong>Remarks:</strong> <?php echo htmlspecialchars($request['remarks']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-2"></i>
                                <p>No payment requests found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-university"></i> Bank Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="bank-details">
                            <p><strong>Account Name:</strong> Hostel Management System</p>
                            <p><strong>Account Number:</strong> 1234567890</p>
                            <p><strong>Bank Name:</strong> State Bank of India</p>
                            <p><strong>IFSC Code:</strong> SBIN0012345</p>
                            <p><strong>Branch:</strong> Main Branch, City Name</p>
                            <hr>
                            <p><strong>UPI ID:</strong> hostel@okhdfcbank</p>
                            <div class="text-center mt-3">
                                <i class="fas fa-qrcode fa-4x text-dark"></i>
                                <p><small>Scan QR code to pay via UPI</small></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Drag and drop functionality
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('payment_proof');
    const filePreview = document.getElementById('filePreview');
    
    dropZone.addEventListener('click', () => fileInput.click());
    
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    
    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if(files.length > 0) {
            fileInput.files = files;
            handleFilePreview(files[0]);
        }
    });
    
    fileInput.addEventListener('change', (e) => {
        if(e.target.files.length > 0) {
            handleFilePreview(e.target.files[0]);
        }
    });
    
    function handleFilePreview(file) {
        const fileType = file.type;
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        
        if(fileType.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                filePreview.innerHTML = `
                    <img src="${e.target.result}" class="preview-image" alt="Preview">
                    <p class="mt-2 mb-0"><small>${fileName} (${fileSize} MB)</small></p>
                `;
            };
            reader.readAsDataURL(file);
        } else if(fileType === 'application/pdf') {
            filePreview.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-file-pdf fa-2x"></i>
                    <p class="mb-0">${fileName} (${fileSize} MB)</p>
                </div>
            `;
        } else {
            filePreview.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-file fa-2x"></i>
                    <p class="mb-0">${fileName} (${fileSize} MB)</p>
                </div>
            `;
        }
    }
    
    // Form validation
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        const transactionId = document.getElementById('transaction_id').value.trim();
        const amount = document.getElementById('amount').value;
        const paymentDate = document.getElementById('payment_date').value;
        const file = fileInput.files[0];
        
        if(!transactionId) {
            e.preventDefault();
            alert('Please enter Transaction ID');
            return false;
        }
        
        if(!amount || amount <= 0) {
            e.preventDefault();
            alert('Please enter valid amount');
            return false;
        }
        
        if(!paymentDate) {
            e.preventDefault();
            alert('Please select payment date');
            return false;
        }
        
        if(!file) {
            e.preventDefault();
            alert('Please select a payment proof file');
            return false;
        }
        
        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    });
    
    // Transaction ID validation
    document.getElementById('transaction_id').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>