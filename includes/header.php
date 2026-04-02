<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Your CSS styles here */
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .dashboard-stats {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        footer {
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 20px 0;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="<?php echo isset($_SESSION['role']) && $_SESSION['role'] == 'admin' ? 'dashboard.php' : '../index.php'; ?>">
                <i class="fas fa-hotel"></i> Hostel Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if($_SESSION['role'] == 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="manage_students.php">
                                    <i class="fas fa-users"></i> Students
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="manage_rooms.php">
                                    <i class="fas fa-bed"></i> Rooms
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="manage_fees.php">
                                    <i class="fas fa-rupee-sign"></i> Fees
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="manage_complaints.php">
                                    <i class="fas fa-comment-alt"></i> Complaints
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="../student/dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../student/view_fees.php">
                                    <i class="fas fa-rupee-sign"></i> Fees
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../student/edit_profile.php">
                                    <i class="fas fa-user-edit"></i> Profile
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../student/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">