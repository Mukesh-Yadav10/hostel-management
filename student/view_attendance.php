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

// Get current month and year
$current_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$current_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Get attendance for selected month
$attendance_query = "SELECT * FROM attendance 
                     WHERE student_id = :student_id 
                     AND MONTH(attendance_date) = :month 
                     AND YEAR(attendance_date) = :year 
                     ORDER BY attendance_date ASC";
$attendance_stmt = $db->prepare($attendance_query);
$attendance_stmt->bindParam(':student_id', $student_id);
$attendance_stmt->bindParam(':month', $current_month);
$attendance_stmt->bindParam(':year', $current_year);
$attendance_stmt->execute();
$attendance_records = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_days = date('t', strtotime("$current_year-$current_month-01"));
$present_count = 0;
$absent_count = 0;
$late_count = 0;
$attendance_percentage = 0;

foreach($attendance_records as $record) {
    switch($record['status']) {
        case 'present':
            $present_count++;
            break;
        case 'absent':
            $absent_count++;
            break;
        case 'late':
            $late_count++;
            break;
    }
}

$attendance_percentage = $total_days > 0 ? ($present_count / $total_days) * 100 : 0;

// Get monthly summary for last 6 months
$summary_query = "SELECT 
                    MONTH(attendance_date) as month,
                    YEAR(attendance_date) as year,
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
                    COUNT(*) as total
                  FROM attendance 
                  WHERE student_id = :student_id 
                  AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                  GROUP BY YEAR(attendance_date), MONTH(attendance_date)
                  ORDER BY year DESC, month DESC";
$summary_stmt = $db->prepare($summary_query);
$summary_stmt->bindParam(':student_id', $student_id);
$summary_stmt->execute();
$monthly_summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's attendance status
$today = date('Y-m-d');
$today_query = "SELECT * FROM attendance WHERE student_id = :student_id AND attendance_date = :today";
$today_stmt = $db->prepare($today_query);
$today_stmt->bindParam(':student_id', $student_id);
$today_stmt->bindParam(':today', $today);
$today_stmt->execute();
$today_attendance = $today_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-12">
        <h2>Attendance Management</h2>
        <p class="text-muted">Track your attendance records</p>
        <hr>
    </div>
</div>

<!-- Attendance Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="dashboard-stats bg-primary text-white">
            <h4><?php echo $attendance_percentage; ?>%</h4>
            <p>Attendance Rate</p>
            <i class="fas fa-chart-line fa-2x"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-stats bg-success text-white">
            <h4><?php echo $present_count; ?></h4>
            <p>Present Days</p>
            <i class="fas fa-check-circle fa-2x"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-stats bg-danger text-white">
            <h4><?php echo $absent_count; ?></h4>
            <p>Absent Days</p>
            <i class="fas fa-times-circle fa-2x"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-stats bg-warning text-white">
            <h4><?php echo $late_count; ?></h4>
            <p>Late Arrivals</p>
            <i class="fas fa-clock fa-2x"></i>
        </div>
    </div>
</div>

<!-- Today's Attendance Status -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-day"></i> Today's Attendance</h5>
            </div>
            <div class="card-body">
                <?php if($today_attendance): ?>
                    <div class="alert alert-<?php echo $today_attendance['status'] == 'present' ? 'success' : ($today_attendance['status'] == 'late' ? 'warning' : 'danger'); ?>">
                        <i class="fas fa-<?php echo $today_attendance['status'] == 'present' ? 'check-circle' : ($today_attendance['status'] == 'late' ? 'clock' : 'times-circle'); ?>"></i>
                        <strong>Status: <?php echo strtoupper($today_attendance['status']); ?></strong>
                        <?php if($today_attendance['check_in_time']): ?>
                            <br>Check-in Time: <?php echo date('h:i A', strtotime($today_attendance['check_in_time'])); ?>
                        <?php endif; ?>
                        <?php if($today_attendance['check_out_time']): ?>
                            <br>Check-out Time: <?php echo date('h:i A', strtotime($today_attendance['check_out_time'])); ?>
                        <?php endif; ?>
                        <?php if($today_attendance['remarks']): ?>
                            <br>Remarks: <?php echo htmlspecialchars($today_attendance['remarks']); ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary">
                        <i class="fas fa-info-circle"></i> No attendance recorded for today.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Month Selector and Calendar View -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Attendance Calendar</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <label>Select Month:</label>
                            <select name="month" class="form-control" onchange="this.form.submit()">
                                <?php for($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $current_month == $m ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Select Year:</label>
                            <select name="year" class="form-control" onchange="this.form.submit()">
                                <?php for($y = date('Y')-2; $y <= date('Y'); $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $current_year == $y ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label>
                            <div>
                                <a href="view_attendance.php?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>" class="btn btn-secondary">
                                    <i class="fas fa-calendar-day"></i> Current Month
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Calendar View -->
                <div class="attendance-calendar">
                    <?php
                    $first_day = date('N', strtotime("$current_year-$current_month-01"));
                    $days_in_month = date('t', strtotime("$current_year-$current_month-01"));
                    
                    // Create associative array for quick lookup
                    $attendance_map = [];
                    foreach($attendance_records as $record) {
                        $day = date('j', strtotime($record['attendance_date']));
                        $attendance_map[$day] = $record;
                    }
                    ?>
                    
                    <table class="table table-bordered calendar-table">
                        <thead>
                            <tr class="bg-light">
                                <th>Mon</th>
                                <th>Tue</th>
                                <th>Wed</th>
                                <th>Thu</th>
                                <th>Fri</th>
                                <th>Sat</th>
                                <th>Sun</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $day_counter = 1;
                            $current_day = 1;
                            
                            // Calculate number of rows needed
                            $total_cells = ceil(($first_day + $days_in_month) / 7) * 7;
                            
                            for($i = 1; $i <= $total_cells; $i++):
                                if($i % 7 == 1) echo "<tr>";
                                
                                if($i >= $first_day && $current_day <= $days_in_month):
                                    $day_num = $current_day;
                                    $attendance = isset($attendance_map[$day_num]) ? $attendance_map[$day_num] : null;
                                    $status = $attendance ? $attendance['status'] : 'not_recorded';
                                    $date = date('Y-m-d', strtotime("$current_year-$current_month-$day_num"));
                                    $is_today = ($date == date('Y-m-d'));
                                    ?>
                                    <td class="calendar-day <?php echo $status; ?> <?php echo $is_today ? 'today' : ''; ?>">
                                        <div class="day-number"><?php echo $day_num; ?></div>
                                        <div class="day-status">
                                            <?php if($status == 'present'): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                                <span class="badge bg-success">Present</span>
                                                <?php if($attendance['check_in_time']): ?>
                                                    <small><?php echo date('h:i A', strtotime($attendance['check_in_time'])); ?></small>
                                                <?php endif; ?>
                                            <?php elseif($status == 'absent'): ?>
                                                <i class="fas fa-times-circle text-danger"></i>
                                                <span class="badge bg-danger">Absent</span>
                                            <?php elseif($status == 'late'): ?>
                                                <i class="fas fa-clock text-warning"></i>
                                                <span class="badge bg-warning">Late</span>
                                                <?php if($attendance['check_in_time']): ?>
                                                    <small><?php echo date('h:i A', strtotime($attendance['check_in_time'])); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <i class="fas fa-minus-circle text-secondary"></i>
                                                <span class="badge bg-secondary">Not Recorded</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if($attendance && $attendance['remarks']): ?>
                                            <div class="remarks-tooltip" title="<?php echo htmlspecialchars($attendance['remarks']); ?>">
                                                <i class="fas fa-comment-dots text-info"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php
                                    $current_day++;
                                else:
                                    ?>
                                    <td class="calendar-day empty"></td>
                                    <?php
                                endif;
                                
                                if($i % 7 == 0) echo "</tr>";
                            endfor;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Summary Table -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Monthly Attendance Summary</h5>
            </div>
            <div class="card-body">
                <?php if(count($monthly_summary) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Present</th>
                                    <th>Absent</th>
                                    <th>Late</th>
                                    <th>Total Days</th>
                                    <th>Percentage</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($monthly_summary as $summary): 
                                    $month_name = date('F', mktime(0, 0, 0, $summary['month'], 1));
                                    $total_days_month = date('t', strtotime("{$summary['year']}-{$summary['month']}-01"));
                                    $percentage = ($summary['present'] / $total_days_month) * 100;
                                    $status_color = $percentage >= 75 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');
                                ?>
                                <tr>
                                    <td><strong><?php echo $month_name . ' ' . $summary['year']; ?></strong></td>
                                    <td class="text-success"><?php echo $summary['present']; ?></td>
                                    <td class="text-danger"><?php echo $summary['absent']; ?></td>
                                    <td class="text-warning"><?php echo $summary['late']; ?></td>
                                    <td><?php echo $total_days_month; ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $status_color; ?>" 
                                                 style="width: <?php echo $percentage; ?>%">
                                                <?php echo round($percentage, 2); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_color; ?>">
                                            <?php echo $percentage >= 75 ? 'Good' : ($percentage >= 50 ? 'Average' : 'Poor'); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <h5>No attendance records found</h5>
                        <p>Your attendance records will appear here once marked by the admin.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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
}

.dashboard-stats h4 {
    font-size: 2rem;
    margin-bottom: 10px;
    font-weight: bold;
}

.dashboard-stats i {
    position: absolute;
    right: 20px;
    bottom: 20px;
    opacity: 0.3;
}

.calendar-table {
    text-align: center;
    background: white;
}

.calendar-day {
    height: 100px;
    vertical-align: top;
    position: relative;
    transition: all 0.3s;
}

.calendar-day:hover {
    transform: scale(1.02);
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    z-index: 1;
}

.calendar-day.present {
    background-color: #d4edda;
}

.calendar-day.absent {
    background-color: #f8d7da;
}

.calendar-day.late {
    background-color: #fff3cd;
}

.calendar-day.today {
    border: 2px solid #007bff;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.2);
}

.day-number {
    font-weight: bold;
    font-size: 1.1rem;
    margin-bottom: 5px;
}

.day-status {
    font-size: 0.75rem;
    margin-top: 5px;
}

.day-status i {
    font-size: 1rem;
}

.remarks-tooltip {
    position: absolute;
    bottom: 5px;
    right: 5px;
    cursor: help;
}

.progress {
    border-radius: 10px;
}

.progress-bar {
    line-height: 20px;
    font-size: 0.75rem;
}

@media (max-width: 768px) {
    .calendar-day {
        height: auto;
        min-height: 60px;
        font-size: 0.8rem;
    }
    
    .day-status {
        display: none;
    }
    
    .dashboard-stats h4 {
        font-size: 1.2rem;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>