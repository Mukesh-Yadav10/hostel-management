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
$error = '';
$success = '';

// Get student details
$student_query = "SELECT name, student_id, email FROM students WHERE id = :id";
$student_stmt = $db->prepare($student_query);
$student_stmt->bindParam(':id', $student_id);
$student_stmt->execute();
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

// Handle password change
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Verify current password
    $check_pass = "SELECT password FROM students WHERE id = :id";
    $pass_stmt = $db->prepare($check_pass);
    $pass_stmt->bindParam(':id', $student_id);
    $pass_stmt->execute();
    $db_password = $pass_stmt->fetch(PDO::FETCH_ASSOC);
    
    if(md5($current_password) != $db_password['password']) {
        $errors[] = "Current password is incorrect";
    }
    
    // Validate new password
    if(strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters long";
    }
    
    if(!preg_match('/[A-Z]/', $new_password)) {
        $errors[] = "New password must contain at least one uppercase letter";
    }
    
    if(!preg_match('/[a-z]/', $new_password)) {
        $errors[] = "New password must contain at least one lowercase letter";
    }
    
    if(!preg_match('/[0-9]/', $new_password)) {
        $errors[] = "New password must contain at least one number";
    }
    
    if(!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $errors[] = "New password must contain at least one special character";
    }
    
    if($new_password != $confirm_password) {
        $errors[] = "New password and confirm password do not match";
    }
    
    if(empty($errors)) {
        $new_password_md5 = md5($new_password);
        $update_pass = "UPDATE students SET password = :password WHERE id = :id";
        $update_stmt = $db->prepare($update_pass);
        $update_stmt->bindParam(':password', $new_password_md5);
        $update_stmt->bindParam(':id', $student_id);
        
        if($update_stmt->execute()) {
            $success = "Password changed successfully! Please remember your new password.";
            
            // Log the password change
            $log_query = "INSERT INTO password_change_logs (student_id, change_date, ip_address) 
                          VALUES (:student_id, NOW(), :ip)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':student_id', $student_id);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bindParam(':ip', $ip_address);
            $log_stmt->execute();
            
            // Clear password fields
            $_POST = array();
        } else {
            $error = "Error changing password. Please try again.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Password strength calculation function
function getPasswordStrength($password) {
    $strength = 0;
    if(strlen($password) >= 6) $strength++;
    if(strlen($password) >= 10) $strength++;
    if(preg_match('/[A-Z]/', $password)) $strength++;
    if(preg_match('/[a-z]/', $password)) $strength++;
    if(preg_match('/[0-9]/', $password)) $strength++;
    if(preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) $strength++;
    return $strength;
}
?>

<div class="row">
    <div class="col-md-12">
        <h2>Change Password</h2>
        <p class="text-muted">Keep your account secure by regularly updating your password</p>
        <hr>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fas fa-key"></i> Change Password</h5>
            </div>
            <div class="card-body">
                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Password Requirements:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Minimum 6 characters long</li>
                        <li>At least one uppercase letter (A-Z)</li>
                        <li>At least one lowercase letter (a-z)</li>
                        <li>At least one number (0-9)</li>
                        <li>At least one special character (!@#$%^&* etc.)</li>
                    </ul>
                </div>
                
                <form method="POST" action="" id="passwordForm">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Please enter your current password</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Password must meet the requirements</div>
                        <div class="mt-2">
                            <div class="progress" style="height: 5px;">
                                <div id="strengthBar" class="progress-bar" style="width: 0%"></div>
                            </div>
                            <small id="strengthText" class="text-muted"></small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Passwords do not match</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="show_all_passwords">
                            <label class="form-check-label" for="show_all_passwords">Show all passwords</label>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Important:</strong> After changing your password, you will be logged out and need to login again with your new password.
                    </div>
                    
                    <div class="text-end">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-warning" id="submitBtn">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Security Tips Card -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Password Security Tips</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-check-circle text-success"></i> Do's:</h6>
                        <ul>
                            <li>Use a mix of characters (uppercase, lowercase, numbers, symbols)</li>
                            <li>Make it at least 8-12 characters long</li>
                            <li>Use a unique password for this account</li>
                            <li>Change your password regularly (every 3-6 months)</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-times-circle text-danger"></i> Don'ts:</h6>
                        <ul>
                            <li>Don't use common words or phrases</li>
                            <li>Don't use personal information (name, birthdate)</li>
                            <li>Don't share your password with anyone</li>
                            <li>Don't use the same password for multiple accounts</li>
                        </ul>
                    </div>
                </div>
                
                <hr>
                
                <div class="alert alert-secondary">
                    <i class="fas fa-clock"></i> 
                    <strong>Last Password Change:</strong> 
                    <?php
                    $last_change = "SELECT MAX(change_date) as last_change FROM password_change_logs WHERE student_id = :student_id";
                    $last_stmt = $db->prepare($last_change);
                    $last_stmt->bindParam(':student_id', $student_id);
                    $last_stmt->execute();
                    $last = $last_stmt->fetch(PDO::FETCH_ASSOC);
                    echo $last['last_change'] ? date('F d, Y H:i:s', strtotime($last['last_change'])) : 'First time changing password';
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity Card -->
        <div class="card mt-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-history"></i> Recent Password Changes</h5>
            </div>
            <div class="card-body">
                <?php
                $history_query = "SELECT change_date, ip_address FROM password_change_logs 
                                  WHERE student_id = :student_id 
                                  ORDER BY change_date DESC LIMIT 5";
                $history_stmt = $db->prepare($history_query);
                $history_stmt->bindParam(':student_id', $student_id);
                $history_stmt->execute();
                $history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if(count($history) > 0):
                ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $entry): ?>
                            <tr>
                                <td><?php echo date('F d, Y H:i:s', strtotime($entry['change_date'])); ?></td>
                                <td><?php echo htmlspecialchars($entry['ip_address']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted text-center mb-0">No password change history found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const type = field.type === 'password' ? 'text' : 'password';
    field.type = type;
}

// Show all passwords
document.getElementById('show_all_passwords').addEventListener('change', function() {
    const fields = ['current_password', 'new_password', 'confirm_password'];
    const type = this.checked ? 'text' : 'password';
    fields.forEach(field => {
        document.getElementById(field).type = type;
    });
});

// Password strength meter
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strength = calculatePasswordStrength(password);
    updateStrengthMeter(strength);
});

function calculatePasswordStrength(password) {
    let strength = 0;
    if(password.length >= 6) strength++;
    if(password.length >= 10) strength++;
    if(/[A-Z]/.test(password)) strength++;
    if(/[a-z]/.test(password)) strength++;
    if(/[0-9]/.test(password)) strength++;
    if(/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
    return strength;
}

function updateStrengthMeter(strength) {
    const bar = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    const maxStrength = 6;
    const percentage = (strength / maxStrength) * 100;
    
    bar.style.width = percentage + '%';
    
    let color = '';
    let message = '';
    
    if(strength <= 2) {
        color = 'danger';
        message = 'Weak Password';
    } else if(strength <= 3) {
        color = 'warning';
        message = 'Fair Password';
    } else if(strength <= 4) {
        color = 'info';
        message = 'Good Password';
    } else {
        color = 'success';
        message = 'Strong Password';
    }
    
    bar.className = 'progress-bar bg-' + color;
    text.innerHTML = message + ' (' + strength + '/' + maxStrength + ')';
}

// Form validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    let currentPass = document.getElementById('current_password').value;
    let newPass = document.getElementById('new_password').value;
    let confirmPass = document.getElementById('confirm_password').value;
    let isValid = true;
    
    // Check current password
    if(!currentPass) {
        document.getElementById('current_password').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('current_password').classList.remove('is-invalid');
    }
    
    // Check new password strength
    const strength = calculatePasswordStrength(newPass);
    if(strength < 3) {
        document.getElementById('new_password').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('new_password').classList.remove('is-invalid');
    }
    
    // Check password match
    if(newPass !== confirmPass) {
        document.getElementById('confirm_password').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('confirm_password').classList.remove('is-invalid');
    }
    
    if(!isValid) {
        e.preventDefault();
    } else {
        // Show confirmation dialog
        if(!confirm('Are you sure you want to change your password? You will be logged out after the change.')) {
            e.preventDefault();
        }
    }
});

// Real-time password match check
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = this.value;
    
    if(newPass !== confirmPass) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

// Prevent paste in confirm password
document.getElementById('confirm_password').addEventListener('paste', function(e) {
    e.preventDefault();
    alert('Please type your password instead of pasting for security reasons.');
});
</script>

<style>
.card {
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    animation: fadeIn 0.5s ease;
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

.btn {
    transition: all 0.3s;
}

.btn:hover {
    transform: translateY(-2px);
}

.progress {
    border-radius: 10px;
    overflow: hidden;
}

.form-control:focus {
    border-color: #ffc107;
    box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
}

.input-group .btn {
    border-left: none;
}

.invalid-feedback {
    display: block;
}

.alert {
    border-radius: 10px;
}

ul {
    padding-left: 20px;
}

@media (max-width: 768px) {
    .col-md-8 {
        padding: 0 15px;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>