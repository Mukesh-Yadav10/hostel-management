<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to appropriate dashboard
if(isset($_SESSION['user_id'])) {
    if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
        header("Location: admin/dashboard.php");
        exit();
    } elseif(isset($_SESSION['role']) && $_SESSION['role'] == 'student') {
        header("Location: student/dashboard.php");
        exit();
    }
}

require_once 'config/database.php';
require_once 'includes/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate input
    if(empty($_POST['username']) || empty($_POST['password']) || empty($_POST['role'])) {
        $error = "Please fill in all fields!";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $username = trim($_POST['username']);
        $password = md5($_POST['password']);
        $role = $_POST['role'];
        
        if ($role == 'admin') {
            // Admin login
            $query = "SELECT id, username, email FROM admin WHERE username = :username AND password = :password";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['email'] = $admin['email'];
                $_SESSION['role'] = 'admin';
                $_SESSION['login_time'] = time();
                
                header("Location: admin/dashboard.php");
                exit();
            } else {
                $error = "Invalid admin credentials!";
            }
        } else {
            // Student login
            $query = "SELECT id, student_id, name, email, room_no, status FROM students 
                      WHERE (student_id = :username OR email = :username) 
                      AND password = :password 
                      AND status = 'active'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['user_id'] = $student['id'];
                $_SESSION['student_id'] = $student['student_id'];
                $_SESSION['username'] = $student['name'];
                $_SESSION['email'] = $student['email'];
                $_SESSION['room_no'] = $student['room_no'];
                $_SESSION['role'] = 'student';
                $_SESSION['login_time'] = time();
                
                header("Location: student/dashboard.php");
                exit();
            } else {
                $error = "Invalid student credentials! Your account might be inactive.";
            }
        }
    }
}
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-5">
        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-sign-in-alt"></i> Login to Hostel Management System</h4>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i> Username / Student ID
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               placeholder="Enter username or student ID"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               required>
                        <div class="invalid-feedback">
                            Please enter your username or student ID.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password"
                               required>
                        <div class="invalid-feedback">
                            Please enter your password.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">
                            <i class="fas fa-user-tag"></i> Login As
                        </label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : ''; ?>>
                                Student
                            </option>
                            <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>
                                Administrator
                            </option>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe">
                        <label class="form-check-label" for="rememberMe">Remember me</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="mb-2">New Student?</p>
                    <a href="register.php" class="btn btn-success">
                        <i class="fas fa-user-plus"></i> Register Here
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Client-side form validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    let username = document.getElementById('username').value.trim();
    let password = document.getElementById('password').value.trim();
    let isValid = true;
    
    if(username === '') {
        document.getElementById('username').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('username').classList.remove('is-invalid');
    }
    
    if(password === '') {
        document.getElementById('password').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('password').classList.remove('is-invalid');
    }
    
    if(!isValid) {
        e.preventDefault();
    }
});

// Clear validation on input
document.getElementById('username').addEventListener('input', function() {
    this.classList.remove('is-invalid');
});

document.getElementById('password').addEventListener('input', function() {
    this.classList.remove('is-invalid');
});

// Remember me functionality (optional - can be implemented with localStorage)
document.getElementById('rememberMe').addEventListener('change', function() {
    if(this.checked) {
        // Store login info in localStorage (not recommended for passwords, just for demo)
        let username = document.getElementById('username').value;
        if(username) {
            localStorage.setItem('saved_username', username);
        }
    } else {
        localStorage.removeItem('saved_username');
    }
});

// Load saved username if exists
window.addEventListener('load', function() {
    let savedUsername = localStorage.getItem('saved_username');
    if(savedUsername) {
        document.getElementById('username').value = savedUsername;
        document.getElementById('rememberMe').checked = true;
    }
});
</script>

<style>
.card {
    border-radius: 15px;
    overflow: hidden;
    animation: fadeInUp 0.5s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-bottom: none;
    padding: 20px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 12px;
    transition: transform 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
}

.btn-success {
    padding: 8px 20px;
    transition: transform 0.3s;
}

.btn-success:hover {
    transform: translateY(-2px);
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.alert {
    border-radius: 10px;
    animation: shake 0.5s ease;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}
</style>

<?php require_once 'includes/footer.php'; ?>