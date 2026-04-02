<?php
require_once 'includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-8 text-center">
        <div class="card">
            <div class="card-body">
                <h1 class="display-4">Welcome to Hostel Management System</h1>
                <p class="lead">Efficiently manage hostel operations, student records, room allocation, fee collection, and complaints.</p>
                <hr class="my-4">
                <p>Login to access your dashboard and manage hostel activities.</p>
                <div class="mt-4">
                    <a href="login.php" class="btn btn-primary btn-lg mx-2">Login</a>
                    <a href="register.php" class="btn btn-success btn-lg mx-2">Register</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-5">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-users fa-3x text-primary mb-3"></i>
                <h5>Student Management</h5>
                <p>Manage student registrations, profiles, and room allocations.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-bed fa-3x text-success mb-3"></i>
                <h5>Room Management</h5>
                <p>Track room availability, occupancy, and maintenance status.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-rupee-sign fa-3x text-info mb-3"></i>
                <h5>Fee Management</h5>
                <p>Manage fee collection, due payments, and financial records.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>