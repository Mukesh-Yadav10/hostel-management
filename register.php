<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$database = new Database();
$db = $database->getConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = md5($_POST['password']);
    $phone = $_POST['phone'];
    $parent_phone = $_POST['parent_phone'];
    $address = $_POST['address'];
    
    // Check if student ID already exists
    $check_query = "SELECT * FROM students WHERE student_id = :student_id OR email = :email";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':student_id', $student_id);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();
    
    if($check_stmt->rowCount() > 0) {
        $error = "Student ID or Email already exists!";
    } else {
        $query = "INSERT INTO students (student_id, name, email, password, phone, parent_phone, address, joining_date) 
                  VALUES (:student_id, :name, :email, :password, :phone, :parent_phone, :address, CURDATE())";
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':parent_phone', $parent_phone);
        $stmt->bindParam(':address', $address);
        
        if($stmt->execute()) {
            $success = "Registration successful! You can now login.";
        } else {
            $error = "Registration failed! Please try again.";
        }
    }
}
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Student Registration</h4>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Student ID</label>
                            <input type="text" name="student_id" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Phone Number</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Parent/Guardian Phone</label>
                            <input type="text" name="parent_phone" class="form-control">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label>Address</label>
                            <textarea name="address" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>
                <div class="mt-3 text-center">
                    <a href="login.php">Already have an account? Login Here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>