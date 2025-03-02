<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
$role = $_SESSION['role'];
?>
<?php if ($role == "admin") { ?>
    <li><a href="reports.php">Reports</a></li>
<?php } elseif ($role == "staff") { ?>
    <li><a href="reports.php">Reports</a></li>
<?php } elseif ($role == "employee") { ?>
    <li><a href="reports.php">My Reports</a></li>
<?php } ?>
<div class="sidebar">
    <div class="sidebar-header">
        <h3><?php echo ucfirst($role); ?> Menu</h3>
    </div>
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <?php if ($role == "admin") { ?>
            <li><a href="employees.php">Employees</a></li>
            <li><a href="attendance.php">Attendance</a></li>
            <li><a href="leaves.php">Leaves</a></li>
            <li><a href="recruitment.php">Recruitment</a></li>
            <li><a href="reports.php">Reports</a></li>
        <?php } elseif ($role == "staff") { ?>
            <li><a href="employees.php">Employees</a></li>
            <li><a href="attendance.php">Attendance</a></li>
            <li><a href="leaves.php">Leaves</a></li>
            <li><a href="recruitment.php">Job Openings</a></li>
        <?php } elseif ($role == "employee") { ?>
            <li><a href="attendance.php">My Attendance</a></li>
            <li><a href="leaves.php">My Leaves</a></li>
            <li><a href="recruitment.php">Job Openings</a></li>
        <?php } ?>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</div>