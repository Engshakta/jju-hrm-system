<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Default date range: current month
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$filter_dept = isset($_GET['department']) && $role != 'employee' ? $_GET['department'] : ($role == 'employee' ? null : '');

// Fetch departments for filter (admin/staff only)
$dept_sql = "SELECT DISTINCT department FROM employees WHERE is_deleted = 0";
$departments = $conn->query($dept_sql)->fetch_all(MYSQLI_ASSOC);

// Attendance Report
$att_where = "WHERE a.date BETWEEN ? AND ?";
$att_params = [$start_date, $end_date];
$att_types = "ss";

if ($filter_dept) {
    $att_where .= " AND e.department = ?";
    $att_params[] = $filter_dept;
    $att_types .= "s";
}
if ($role == "employee") {
    $att_where .= " AND a.employee_id = ?";
    $att_params[] = $user_id;
    $att_types .= "i";
} elseif ($role == "staff") {
    $att_where .= " AND e.department = (SELECT department FROM employees WHERE id = ?)";
    $att_params[] = $user_id;
    $att_types .= "i";
}

$att_summary_sql = "SELECT a.status, COUNT(*) as total 
                    FROM attendance a 
                    JOIN employees e ON a.employee_id = e.id 
                    $att_where 
                    GROUP BY a.status";
$att_stmt = $conn->prepare($att_summary_sql);
$att_stmt->bind_param($att_types, ...$att_params);
$att_stmt->execute();
$att_summary_result = $att_stmt->get_result();
$att_summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];
while ($row = $att_summary_result->fetch_assoc()) {
    $att_summary[$row['status']] = $row['total'];
}

$att_details_sql = "SELECT e.full_name, a.date, a.status 
                    FROM attendance a 
                    JOIN employees e ON a.employee_id = e.id 
                    $att_where 
                    ORDER BY a.date DESC 
                    LIMIT 10";
$att_details_stmt = $conn->prepare($att_details_sql);
$att_details_stmt->bind_param($att_types, ...$att_params);
$att_details_stmt->execute();
$att_details_result = $att_details_stmt->get_result();

// Leave Report
$leave_where = "WHERE l.start_date BETWEEN ? AND ?";
$leave_params = [$start_date, $end_date];
$leave_types = "ss";

if ($filter_dept) {
    $leave_where .= " AND e.department = ?";
    $leave_params[] = $filter_dept;
    $leave_types .= "s";
}
if ($role == "employee") {
    $leave_where .= " AND l.employee_id = ?";
    $leave_params[] = $user_id;
    $leave_types .= "i";
} elseif ($role == "staff") {
    $leave_where .= " AND e.department = (SELECT department FROM employees WHERE id = ?)";
    $leave_params[] = $user_id;
    $leave_types .= "i";
}

$leave_summary_sql = "SELECT l.status, COUNT(*) as total 
                      FROM leaves l 
                      JOIN employees e ON l.employee_id = e.id 
                      $leave_where 
                      GROUP BY l.status";
$leave_stmt = $conn->prepare($leave_summary_sql);
$leave_stmt->bind_param($leave_types, ...$leave_params);
$leave_stmt->execute();
$leave_summary_result = $leave_stmt->get_result();
$leave_summary = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
while ($row = $leave_summary_result->fetch_assoc()) {
    $leave_summary[$row['status']] = $row['total'];
}

$leave_details_sql = "SELECT e.full_name, l.start_date, l.end_date, l.reason, l.status 
                      FROM leaves l 
                      JOIN employees e ON l.employee_id = e.id 
                      $leave_where 
                      ORDER BY l.start_date DESC 
                      LIMIT 10";
$leave_details_stmt = $conn->prepare($leave_details_sql);
$leave_details_stmt->bind_param($leave_types, ...$leave_params);
$leave_details_stmt->execute();
$leave_details_result = $leave_details_stmt->get_result();

// Recruitment Report (Admin Only)
if ($role == "admin") {
    $recruit_summary_sql = "SELECT j.status, COUNT(*) as total 
                            FROM jobs j 
                            WHERE j.posted_at BETWEEN ? AND ? 
                            GROUP BY j.status";
    $recruit_stmt = $conn->prepare($recruit_summary_sql);
    $recruit_stmt->bind_param("ss", $start_date, $end_date);
    $recruit_stmt->execute();
    $recruit_summary_result = $recruit_stmt->get_result();
    $recruit_summary = ['open' => 0, 'closed' => 0];
    while ($row = $recruit_summary_result->fetch_assoc()) {
        $recruit_summary[$row['status']] = $row['total'];
    }

    $app_summary_sql = "SELECT ja.status, COUNT(*) as total 
                        FROM job_applications ja 
                        JOIN jobs j ON ja.job_id = j.id 
                        WHERE ja.applied_at BETWEEN ? AND ? 
                        GROUP BY ja.status";
    $app_stmt = $conn->prepare($app_summary_sql);
    $app_stmt->bind_param("ss", $start_date, $end_date);
    $app_stmt->execute();
    $app_summary_result = $app_stmt->get_result();
    $app_summary = ['pending' => 0, 'accepted' => 0, 'rejected' => 0];
    while ($row = $app_summary_result->fetch_assoc()) {
        $app_summary[$row['status']] = $row['total'];
    }

    $recruit_details_sql = "SELECT j.title, COUNT(ja.id) as applications 
                            FROM jobs j 
                            LEFT JOIN job_applications ja ON j.id = ja.job_id 
                            WHERE j.posted_at BETWEEN ? AND ? 
                            GROUP BY j.id, j.title 
                            ORDER BY j.posted_at DESC 
                            LIMIT 10";
    $recruit_details_stmt = $conn->prepare($recruit_details_sql);
    $recruit_details_stmt->bind_param("ss", $start_date, $end_date);
    $recruit_details_stmt->execute();
    $recruit_details_result = $recruit_details_stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Jigjiga University HRM</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .reports-summary {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        .reports-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }
        .reports-table th,
        .reports-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }
        .reports-table th {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--sidebar-text);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 14px;
        }
        .dark-mode .reports-table th {
            color: #ffffff;
        }
        .reports-table tr:hover {
            background: var(--card-hover);
        }
    </style>
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
            <h2><?php echo $role == 'employee' ? 'My Reports' : 'Reports'; ?></h2>

            <div class="search-filter">
                <input type="date" id="start-date" value="<?php echo $start_date; ?>" onchange="filterReports()">
                <input type="date" id="end-date" value="<?php echo $end_date; ?>" onchange="filterReports()">
                <?php if ($role != 'employee') { ?>
                    <select id="filter-department" onchange="filterReports()">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept) { ?>
                            <option value="<?php echo $dept['department']; ?>" <?php echo $filter_dept == $dept['department'] ? 'selected' : ''; ?>><?php echo $dept['department']; ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>
            </div>

            <!-- Attendance Report -->
            <h3>Attendance Report</h3>
            <div class="reports-summary">
                <div class="summary-card">
                    <h4>Present</h4>
                    <p><?php echo $att_summary['present']; ?></p>
                </div>
                <div class="summary-card">
                    <h4>Absent</h4>
                    <p><?php echo $att_summary['absent']; ?></p>
                </div>
                <div class="summary-card">
                    <h4>Late</h4>
                    <p><?php echo $att_summary['late']; ?></p>
                </div>
                <div class="summary-card">
                    <h4>Leave</h4>
                    <p><?php echo $att_summary['leave']; ?></p>
                </div>
            </div>
            <table class="reports-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $att_details_result->fetch_assoc()) { ?>
                        <tr class="<?php echo $row['status']; ?>">
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo $row['date']; ?></td>
                            <td><?php echo ucfirst($row['status']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <!-- Leave Report -->
            <h3>Leave Report</h3>
            <div class="reports-summary">
                <div class="summary-card">
                    <h4>Pending</h4>
                    <p><?php echo $leave_summary['pending']; ?></p>
                </div>
                <div class="summary-card">
                    <h4>Approved</h4>
                    <p><?php echo $leave_summary['approved']; ?></p>
                </div>
                <div class="summary-card">
                    <h4>Rejected</h4>
                    <p><?php echo $leave_summary['rejected']; ?></p>
                </div>
            </div>
            <table class="reports-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $leave_details_result->fetch_assoc()) { ?>
                        <tr class="<?php echo $row['status']; ?>">
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo $row['start_date']; ?></td>
                            <td><?php echo $row['end_date']; ?></td>
                            <td><?php echo htmlspecialchars($row['reason']); ?></td>
                            <td><?php echo ucfirst($row['status']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <!-- Recruitment Report (Admin Only) -->
            <?php if ($role == "admin") { ?>
                <h3>Recruitment Report</h3>
                <div class="reports-summary">
                    <div class="summary-card">
                        <h4>Open Jobs</h4>
                        <p><?php echo $recruit_summary['open']; ?></p>
                    </div>
                    <div class="summary-card">
                        <h4>Closed Jobs</h4>
                        <p><?php echo $recruit_summary['closed']; ?></p>
                    </div>
                    <div class="summary-card">
                        <h4>Pending Apps</h4>
                        <p><?php echo $app_summary['pending']; ?></p>
                    </div>
                    <div class="summary-card">
                        <h4>Accepted Apps</h4>
                        <p><?php echo $app_summary['accepted']; ?></p>
                    </div>
                    <div class="summary-card">
                        <h4>Rejected Apps</h4>
                        <p><?php echo $app_summary['rejected']; ?></p>
                    </div>
                </div>
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Applications</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recruit_details_result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo $row['applications']; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </section>
    </div>

    <script src="js/script.js"></script>
    <script>
        function filterReports() {
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            const dept = document.getElementById('filter-department') ? document.getElementById('filter-department').value : '';
            window.location.href = `?start_date=${startDate}&end_date=${endDate}&department=${dept}`;
        }
    </script>
</body>
</html>