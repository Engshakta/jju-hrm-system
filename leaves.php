<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Handle leave request (All roles can request)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_leave'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];

    $sql = "INSERT INTO leaves (employee_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $user_id, $start_date, $end_date, $reason);
    $stmt->execute();
    $stmt->close();
}

// Handle leave review (Admin only)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $role == "admin" && isset($_POST['review_leave'])) {
    $leave_id = $_POST['leave_id'];
    $status = $_POST['status'];

    $sql = "UPDATE leaves SET status = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $status, $user_id, $leave_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch employees for dropdown (Admin/Staff)
$employees = $conn->query("SELECT id, employee_id, full_name FROM employees WHERE is_deleted = 0 ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

// Filter leaves
$filter_employee = isset($_GET['employee']) ? (int)$_GET['employee'] : ($role == "employee" ? $user_id : 0);
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Base query
$sql = "SELECT l.*, e.full_name, e.employee_id AS emp_id, r.full_name AS reviewer_name 
        FROM leaves l 
        JOIN employees e ON l.employee_id = e.id 
        LEFT JOIN employees r ON l.reviewed_by = r.id";
$where = [];
$params = [];
$types = "";

if ($filter_employee) {
    $where[] = "l.employee_id = ?";
    $params[] = $filter_employee;
    $types .= "i";
}
if ($filter_status) {
    $where[] = "l.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($role == "employee") {
    $where[] = "l.employee_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY l.requested_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Total count
$total_sql = "SELECT COUNT(*) as total 
              FROM leaves l 
              JOIN employees e ON l.employee_id = e.id";
if (!empty($where)) {
    $total_sql .= " WHERE " . implode(" AND ", $where);
}
$total_stmt = $conn->prepare($total_sql);
if ($types && count($params) > 2) { // Only bind if there are filter params
    $total_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
}
$total_stmt->execute();
$total = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leaves - Jigjiga University HRM</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .leave-form {
            padding: 20px;
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        .leaves-table {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }
        .leaves-table th, .leaves-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }
        .leaves-table th {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--sidebar-text);
        }
        .dark-mode .leaves-table th {
            color: #ffffff;
        }
        .leaves-table tr.pending { background: rgba(255, 215, 0, 0.1); }
        .leaves-table tr.approved { background: rgba(0, 255, 0, 0.1); }
        .leaves-table tr.rejected { background: rgba(255, 0, 0, 0.1); }
        .leaves-table tr:hover {
            background: var(--card-hover);
        }
        .leave-actions select {
            padding: 6px 10px;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
            border: 1px solid var(--border-color);
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
            <h2><?php echo $role == "employee" ? "My Leaves" : "Leave Management"; ?></h2>

            <div class="leave-form">
                <h3>Request Leave</h3>
                <form method="POST">
                    <input type="hidden" name="request_leave" value="1">
                    <div class="input-group">
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="input-group">
                        <input type="date" name="end_date" required>
                    </div>
                    <div class="input-group">
                        <textarea name="reason" placeholder="Reason for leave" required rows="3" style="width: 100%; padding: 10px; border-radius: 8px; border: 2px solid var(--border-color); background: rgba(255, 255, 255, 0.05); color: var(--text-color);"></textarea>
                    </div>
                    <button type="submit" class="form-btn">Submit Request</button>
                </form>
            </div>

            <div class="search-filter">
                <?php if ($role != "employee") { ?>
                    <select id="filter-employee" onchange="filterLeaves()">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $emp) { ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $filter_employee == $emp['id'] ? 'selected' : ''; ?>><?php echo $emp['employee_id'] . ' - ' . $emp['full_name']; ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>
                <select id="filter-status" onchange="filterLeaves()">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $filter_status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>

            <table class="leaves-table" id="leaves-table">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Requested At</th>
                        <th>Reviewed By</th>
                        <?php if ($role == "admin") { ?><th>Action</th><?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()) { ?>
                        <tr class="<?php echo $row['status']; ?>">
                            <td><?php echo $row['emp_id']; ?></td>
                            <td><?php echo $row['full_name']; ?></td>
                            <td><?php echo $row['start_date']; ?></td>
                            <td><?php echo $row['end_date']; ?></td>
                            <td><?php echo htmlspecialchars($row['reason']); ?></td>
                            <td><?php echo ucfirst($row['status']); ?></td>
                            <td><?php echo $row['requested_at']; ?></td>
                            <td><?php echo $row['reviewer_name'] ?: '-'; ?></td>
                            <?php if ($role == "admin") { ?>
                                <td class="leave-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="review_leave" value="1">
                                        <input type="hidden" name="leave_id" value="<?php echo $row['id']; ?>">
                                        <select name="status" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $row['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo $row['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $row['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </form>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <a href="?page=<?php echo $i; ?>&employee=<?php echo $filter_employee; ?>&status=<?php echo $filter_status; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php } ?>
            </div>
        </section>
    </div>

    <script src="js/script.js"></script>
    <script>
        function filterLeaves() {
            const employee = document.getElementById('filter-employee') ? document.getElementById('filter-employee').value : '<?php echo $filter_employee; ?>';
            const status = document.getElementById('filter-status').value;
            window.location.href = `?employee=${employee}&status=${status}`;
        }
    </script>
</body>
</html>