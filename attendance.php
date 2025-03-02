<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Handle bulk attendance marking (Admin only)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $role == "admin" && isset($_POST['mark_attendance'])) {
    $date = $_POST['date'];
    foreach ($_POST['status'] as $employee_id => $status) {
        $notes = isset($_POST['notes'][$employee_id]) ? $_POST['notes'][$employee_id] : '';
        $sql = "INSERT INTO attendance (employee_id, date, status, notes) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = ?, notes = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssss", $employee_id, $date, $status, $notes, $status, $notes);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch employees for dropdown/marking
$employees = $conn->query("SELECT id, employee_id, full_name FROM employees WHERE is_deleted = 0 ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

// Filter attendance
$filter_employee = isset($_GET['employee']) ? (int)$_GET['employee'] : ($role == "employee" ? $user_id : 0);
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($filter_employee) {
    $where .= " AND a.employee_id = ?";
    $params[] = $filter_employee;
    $types .= "i";
}
if ($filter_date) {
    $where .= " AND a.date = ?";
    $params[] = $filter_date;
    $types .= "s";
}
if ($role == "employee") {
    $where .= " AND a.employee_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

$sql = "SELECT a.*, e.full_name, e.employee_id AS emp_id 
        FROM attendance a 
        JOIN employees e ON a.employee_id = e.id 
        $where 
        ORDER BY a.date DESC 
        LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$total_sql = "SELECT COUNT(*) as total 
              FROM attendance a 
              JOIN employees e ON a.employee_id = e.id 
              $where";
$total_stmt = $conn->prepare($total_sql);
$total_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
$total_stmt->execute();
$total = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

// Monthly summary for employees
$month = date('Y-m');
$summary_sql = "SELECT status, COUNT(*) as count 
                FROM attendance 
                WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? 
                GROUP BY status";
$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("is", $user_id, $month);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
$summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];
while ($row = $summary_result->fetch_assoc()) {
    $summary[$row['status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance - Jigjiga University HRM</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .attendance-form {
            padding: 20px;
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            max-height: 400px;
            overflow-y: auto;
        }
        .attendance-table {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }
        .attendance-table th, .attendance-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }
        .attendance-table th {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--sidebar-text);
        }
        .dark-mode .attendance-table th {
            color: #ffffff;
        }
        .attendance-table tr.present { background: rgba(0, 255, 0, 0.1); }
        .attendance-table tr.absent { background: rgba(255, 0, 0, 0.1); }
        .attendance-table tr.late { background: rgba(255, 165, 0, 0.1); }
        .attendance-table tr.leave { background: rgba(128, 128, 128, 0.1); }
        .attendance-table tr:hover {
            background: var(--card-hover);
        }
        .summary-cards {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .summary-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            text-align: center;
            flex: 1;
            min-width: 150px;
            border: 1px solid var(--border-color);
        }
        .summary-card h4 {
            color: var(--primary);
            margin-bottom: 10px;
        }
        .summary-card p {
            font-size: 24px;
            font-weight: bold;
            color: var(--text-color);
        }
        #live-clock {
            font-size: 18px;
            color: var(--secondary-text-color);
            margin-bottom: 20px;
            text-align: center;
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
            <h2><?php echo $role == "employee" ? "My Attendance" : "Attendance Management"; ?></h2>
            <div id="live-clock"></div>

            <?php if ($role == "admin") { ?>
                <div class="attendance-form">
                    <h3>Mark Attendance for <?php echo date('F j, Y', strtotime($filter_date)); ?></h3>
                    <form method="POST">
                        <input type="hidden" name="mark_attendance" value="1">
                        <div class="input-group">
                            <input type="date" name="date" value="<?php echo $filter_date; ?>" onchange="this.form.submit()" required>
                        </div>
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $emp) { ?>
                                    <?php
                                    $existing = $conn->prepare("SELECT status, notes FROM attendance WHERE employee_id = ? AND date = ?");
                                    $existing->bind_param("is", $emp['id'], $filter_date);
                                    $existing->execute();
                                    $existing_result = $existing->get_result()->fetch_assoc();
                                    $current_status = $existing_result ? $existing_result['status'] : 'present';
                                    $current_notes = $existing_result ? $existing_result['notes'] : '';
                                    ?>
                                    <tr>
                                        <td><?php echo $emp['employee_id'] . ' - ' . $emp['full_name']; ?></td>
                                        <td>
                                            <select name="status[<?php echo $emp['id']; ?>]">
                                                <option value="present" <?php echo $current_status == 'present' ? 'selected' : ''; ?>>Present</option>
                                                <option value="absent" <?php echo $current_status == 'absent' ? 'selected' : ''; ?>>Absent</option>
                                                <option value="late" <?php echo $current_status == 'late' ? 'selected' : ''; ?>>Late</option>
                                                <option value="leave" <?php echo $current_status == 'leave' ? 'selected' : ''; ?>>Leave</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="notes[<?php echo $emp['id']; ?>]" value="<?php echo htmlspecialchars($current_notes); ?>" placeholder="Optional note">
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <button type="submit" class="form-btn">Save Attendance</button>
                    </form>
                </div>
            <?php } ?>

            <?php if ($role == "employee") { ?>
                <div class="summary-cards">
                    <div class="summary-card">
                        <h4>Present</h4>
                        <p><?php echo $summary['present']; ?></p>
                    </div>
                    <div class="summary-card">
                        <h4>Absent</h4>
                        <p><?php echo $summary['absent']; ?></p>
                    </div>
                    <div class="summary-card">
                        <h4>Late</h4>
                        <p><?php echo $summary['late']; ?></p>
                    </div>
                    <div class="summary-card">
                        <h4>Leave</h4>
                        <p><?php echo $summary['leave']; ?></p>
                    </div>
                </div>
            <?php } ?>

            <div class="search-filter">
                <?php if ($role != "employee") { ?>
                    <select id="filter-employee" onchange="filterAttendance()">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $emp) { ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $filter_employee == $emp['id'] ? 'selected' : ''; ?>><?php echo $emp['employee_id'] . ' - ' . $emp['full_name']; ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>
                <input type="date" id="filter-date" value="<?php echo $filter_date; ?>" onchange="filterAttendance()">
            </div>

            <table class="attendance-table" id="attendance-table">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Recorded At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()) { ?>
                        <tr class="<?php echo $row['status']; ?>">
                            <td><?php echo $row['emp_id']; ?></td>
                            <td><?php echo $row['full_name']; ?></td>
                            <td><?php echo $row['date']; ?></td>
                            <td><?php echo ucfirst($row['status']); ?></td>
                            <td><?php echo $row['notes'] ?: '-'; ?></td>
                            <td><?php echo $row['recorded_at']; ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <a href="?page=<?php echo $i; ?>&employee=<?php echo $filter_employee; ?>&date=<?php echo $filter_date; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php } ?>
            </div>
        </section>
    </div>

    <script src="js/script.js"></script>
    <script>
        function filterAttendance() {
            const employee = document.getElementById('filter-employee') ? document.getElementById('filter-employee').value : '<?php echo $filter_employee; ?>';
            const date = document.getElementById('filter-date').value;
            window.location.href = `?employee=${employee}&date=${date}`;
        }

        // Live clock
        function updateClock() {
            const now = new Date();
            document.getElementById('live-clock').textContent = now.toLocaleString();
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>