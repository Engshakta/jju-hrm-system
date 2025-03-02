<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
$role = $_SESSION['role'];
$dashboard_title = [
    'admin' => 'Admin Control Panel',
    'staff' => 'Staff Management Dashboard',
    'employee' => 'Employee Portal'
];
$welcome_message = [
    'admin' => 'Manage the entire HRM system.',
    'staff' => 'Handle employee records and requests.',
    'employee' => 'View your personal details and requests.'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $dashboard_title[$role]; ?> - Jigjiga University HRM</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="<?php echo $role; ?>-role">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="dashboard-header">
            <h1>Jigjiga University HRM</h1>
            <div class="user-info">
                <span>Welcome, <?php echo ucfirst($role); ?></span>
                <button id="theme-toggle" class="theme-toggle-btn" title="Toggle Theme">
                    <i class="fas fa-sun"></i>
                    <i class="fas fa-moon"></i>
                </button>
                <a href="logout.php" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>
        <section class="dashboard-overview">
            <h2><?php echo $dashboard_title[$role]; ?></h2>
            <p class="welcome-text"><?php echo $welcome_message[$role]; ?></p>
            <div class="stats-grid">
                <?php if ($role == "admin") { ?>
                    <div class="stat-card"><h3>Total Employees</h3><p class="stat-number">50</p><a href="employees.php">Manage Employees</a></div>
                    <div class="stat-card"><h3>Pending Leaves</h3><p class="stat-number">5</p><a href="leaves.php">Review Leaves</a></div>
                    <div class="stat-card"><h3>Open Jobs</h3><p class="stat-number">3</p><a href="recruitment.php">Manage Recruitment</a></div>
                    <div class="stat-card"><h3>Today’s Attendance</h3><p class="stat-number">N/A</p><a href="attendance.php">View Attendance</a></div>
                <?php } elseif ($role == "staff") { ?>
                    <div class="stat-card"><h3>Total Employees</h3><p class="stat-number">50</p><a href="employees.php">Manage Employees</a></div>
                    <div class="stat-card"><h3>Pending Leaves</h3><p class="stat-number">5</p><a href="leaves.php">Review Leaves</a></div>
                <?php } elseif ($role == "employee") { ?>
                    <div class="stat-card"><h3>My Attendance</h3><p class="stat-number">N/A</p><a href="attendance.php">View History</a></div>
                    <div class="stat-card"><h3>My Leave Balance</h3><p class="stat-number">10 Days</p><a href="leaves.php">Request Leave</a></div>
                <?php } ?>
            </div>
        </section>
    </div>
    <script src="js/script.js"></script>
</body>
</html>