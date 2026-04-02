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

// Get current student details
$query = "SELECT * FROM students WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $parent_phone = trim($_POST['parent_phone']);
    $address = trim($_POST['address']);
    
    // Validation
    $errors = [];
    
    if(empty($name)) {
        $errors[] = "Name is required";
    }
    
    if(empty($email)) {
        $errors[] = "Email is required";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if(!empty($phone) && !preg_match("/^[0-9]{10}$/", $phone)) {
        $errors[] = "Phone number must be 10 digits";
    }
    
    if(!empty($parent_phone) && !preg_match("/^[0-9]{10}$/", $parent_phone)) {
        $errors[] = "Parent phone number must be 10 digits";
    }
    
    // Check if email already exists for another student
    if($email != $student['email']) {
        $check_email = "SELECT id FROM students WHERE email = :email AND id != :id";
        $check_stmt = $db->prepare($check_email);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->bindParam(':id', $student_id);
        $check_stmt->execute();
        
        if($check_stmt->rowCount() > 0) {
            $errors[] = "Email already exists for another student";
        }
    }
    
    if(empty($errors)) {
        $update_query = "UPDATE students SET name = :name, email = :email, phone = :phone, 
                         parent_phone = :parent_phone, address = :address WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':name', $name);
        $update_stmt->bindParam(':email', $email);
        $update_stmt->bindParam(':phone', $phone);
        $update_stmt->bindParam(':parent_phone', $parent_phone);
        $update_stmt->bindParam(':address', $address);
        $update_stmt->bindParam(':id', $student_id);
        
        if($update_stmt->execute()) {
            $success = "Profile updated successfully!";
            // Update session username
            $_SESSION['username'] = $name;
            $_SESSION['email'] = $email;
            
            // Refresh student data
            $stmt->execute();
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Error updating profile. Please try again.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Handle password change
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
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
    
    if(strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters long";
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
            $pass_success = "Password changed successfully!";
            // Clear password fields
            $_POST['current_password'] = '';
            $_POST['new_password'] = '';
            $_POST['confirm_password'] = '';
        } else {
            $pass_error = "Error changing password. Please try again.";
        }
    } else {
        $pass_error = implode("<br>", $errors);
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <h2>Edit Profile</h2>
        <p class="text-muted">Update your personal information and password</p>
        <hr>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Profile Information Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user-edit"></i> Personal Information</h5>
            </div>
            <div class="card-body">
                <?php if(isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="profileForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="student_id" class="form-label">Student ID</label>
                            <input type="text" class="form-control" id="student_id" 
                                   value="<?php echo htmlspecialchars($student['student_id']); ?>" readonly>
                            <small class="text-muted">Student ID cannot be changed</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($student['name']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($student['email']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($student['phone']); ?>" 
                                   pattern="[0-9]{10}" maxlength="10">
                            <small class="text-muted">10-digit mobile number</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="parent_phone" class="form-label">Parent/Guardian Phone</label>
                            <input type="tel" class="form-control" id="parent_phone" name="parent_phone" 
                                   value="<?php echo htmlspecialchars($student['parent_phone']); ?>" 
                                   pattern="[0-9]{10}" maxlength="10">
                            <small class="text-muted">Emergency contact number</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="room_no" class="form-label">Room Number</label>
                            <input type="text" class="form-control" id="room_no" 
                                   value="<?php echo $student['room_no'] ?: 'Not Assigned'; ?>" readonly>
                            <small class="text-muted">Contact admin for room change requests</small>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php 
                                echo htmlspecialchars($student['address']); 
                            ?></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="joining_date" class="form-label">Joining Date</label>
                            <input type="text" class="form-control" id="joining_date" 
                                   value="<?php echo date('d-m-Y', strtotime($student['joining_date'])); ?>" readonly>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <input type="text" class="form-control" id="status" 
                                   value="<?php echo ucfirst($student['status']); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password Form -->
        <div class="card">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fas fa-key"></i> Change Password</h5>
            </div>
            <div class="card-body">
                <?php if(isset($pass_success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $pass_success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($pass_error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $pass_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="passwordForm">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                        <div class="invalid-feedback">Please enter your current password</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="invalid-feedback">Password must be at least 6 characters</div>
                        <small class="text-muted">Password must be at least 6 characters long</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div class="invalid-feedback">Passwords do not match</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="show_password">
                            <label class="form-check-label" for="show_password">Show Password</label>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" name="change_password" class="btn btn-warning">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Profile Sidebar -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-user-circle"></i> Profile Picture</h5>
            </div>
            <div class="card-body text-center">
                <div class="profile-picture mb-3">
                    <?php
                    $avatar_url = "https://ui-avatars.com/api/?background=667eea&color=fff&size=150&name=" . urlencode($student['name']);
                    ?>
                    <img src="<?php echo $avatar_url; ?>" alt="Profile Picture" class="rounded-circle img-fluid" style="width: 150px; height: 150px; object-fit: cover;">
                </div>
                <h5><?php echo htmlspecialchars($student['name']); ?></h5>
                <p class="text-muted"><?php echo htmlspecialchars($student['student_id']); ?></p>
                
                <button class="btn btn-sm btn-outline-primary" onclick="uploadProfilePicture()">
                    <i class="fas fa-camera"></i> Change Photo
                </button>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Account Information</h5>
            </div>
            <div class="card-body">
                <div class="info-item mb-3">
                    <strong><i class="fas fa-calendar-alt"></i> Member Since:</strong><br>
                    <?php echo date('F d, Y', strtotime($student['joining_date'])); ?>
                </div>
                <div class="info-item mb-3">
                    <strong><i class="fas fa-clock"></i> Last Login:</strong><br>
                    <?php echo isset($_SESSION['login_time']) ? date('F d, Y H:i:s', $_SESSION['login_time']) : 'Today'; ?>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-shield-alt"></i> Account Status:</strong><br>
                    <span class="badge bg-<?php echo $student['status'] == 'active' ? 'success' : 'danger'; ?>">
                        <?php echo strtoupper($student['status']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Profile Picture Upload Modal -->
<div class="modal fade" id="profilePictureModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-camera"></i> Upload Profile Picture</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="upload_profile_picture.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <img id="previewImage" src="#" alt="Preview" style="max-width: 200px; display: none;" class="img-thumbnail">
                    </div>
                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">Choose Image</label>
                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*" required>
                        <small class="text-muted">Accepted formats: JPG, PNG, GIF (Max 2MB)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Profile form validation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    let phone = document.getElementById('phone').value;
    let parentPhone = document.getElementById('parent_phone').value;
    let email = document.getElementById('email').value;
    let isValid = true;
    
    // Validate phone
    if(phone && !/^\d{10}$/.test(phone)) {
        document.getElementById('phone').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('phone').classList.remove('is-invalid');
    }
    
    // Validate parent phone
    if(parentPhone && !/^\d{10}$/.test(parentPhone)) {
        document.getElementById('parent_phone').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('parent_phone').classList.remove('is-invalid');
    }
    
    // Validate email
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        document.getElementById('email').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('email').classList.remove('is-invalid');
    }
    
    if(!isValid) {
        e.preventDefault();
    }
});

// Password form validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    let currentPass = document.getElementById('current_password').value;
    let newPass = document.getElementById('new_password').value;
    let confirmPass = document.getElementById('confirm_password').value;
    let isValid = true;
    
    if(!currentPass) {
        document.getElementById('current_password').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('current_password').classList.remove('is-invalid');
    }
    
    if(newPass.length < 6) {
        document.getElementById('new_password').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('new_password').classList.remove('is-invalid');
    }
    
    if(newPass !== confirmPass) {
        document.getElementById('confirm_password').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('confirm_password').classList.remove('is-invalid');
    }
    
    if(!isValid) {
        e.preventDefault();
    }
});

// Show/hide password
document.getElementById('show_password').addEventListener('change', function() {
    let currentPass = document.getElementById('current_password');
    let newPass = document.getElementById('new_password');
    let confirmPass = document.getElementById('confirm_password');
    
    let type = this.checked ? 'text' : 'password';
    currentPass.type = type;
    newPass.type = type;
    confirmPass.type = type;
});

// Profile picture preview
document.getElementById('profile_picture').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('previewImage');
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
});

function uploadProfilePicture() {
    new bootstrap.Modal(document.getElementById('profilePictureModal')).show();
}

// Real-time phone validation
document.getElementById('phone').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
});

document.getElementById('parent_phone').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
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
    if(/[0-9]/.test(password)) strength++;
    if(/[^A-Za-z0-9]/.test(password)) strength++;
    return strength;
}

function updateStrengthMeter(strength) {
    let meter = document.getElementById('passwordStrength');
    if(!meter) {
        meter = document.createElement('div');
        meter.id = 'passwordStrength';
        meter.className = 'progress mt-2';
        meter.style.height = '5px';
        document.getElementById('new_password').parentNode.appendChild(meter);
    }
    
    let width = (strength / 5) * 100;
    let color = '';
    if(strength <= 2) color = 'danger';
    else if(strength <= 3) color = 'warning';
    else if(strength <= 4) color = 'info';
    else color = 'success';
    
    meter.innerHTML = `<div class="progress-bar bg-${color}" style="width: ${width}%"></div>`;
}
</script>

<style>
.profile-picture {
    position: relative;
    display: inline-block;
}

.info-item {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.info-item:last-child {
    border-bottom: none;
}

.card {
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn {
    transition: all 0.3s;
}

.btn:hover {
    transform: translateY(-2px);
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

@media (max-width: 768px) {
    .col-md-8, .col-md-4 {
        margin-bottom: 20px;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>