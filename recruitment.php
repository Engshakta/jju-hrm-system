<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Handle job posting (Admin only)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $role == "admin" && isset($_POST['post_job'])) {
    $title = $_POST['title'];
    $department = $_POST['department'];
    $description = $_POST['description'];
    $posted_by = $user_id;

    $sql = "INSERT INTO jobs (title, department, description, posted_by) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $title, $department, $description, $posted_by);
    $stmt->execute();
    $stmt->close();
}

// Handle job application (All roles can apply)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_job'])) {
    $job_id = $_POST['job_id'];
    $cover_letter = $_POST['cover_letter'];

    $sql = "INSERT INTO job_applications (job_id, employee_id, cover_letter) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $job_id, $user_id, $cover_letter);
    $stmt->execute();
    $stmt->close();
}

// Handle application review (Admin only)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $role == "admin" && isset($_POST['review_application'])) {
    $application_id = $_POST['application_id'];
    $status = $_POST['status'];

    $sql = "UPDATE job_applications SET status = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $status, $user_id, $application_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch jobs
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'open';

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($filter_status) {
    $where .= " AND j.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

$sql = "SELECT j.*, e.full_name AS poster_name 
        FROM jobs j 
        LEFT JOIN employees e ON j.posted_by = e.id 
        $where 
        ORDER BY j.posted_at DESC 
        LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$jobs_result = $stmt->get_result();

$total_sql = "SELECT COUNT(*) as total FROM jobs j $where";
$total_stmt = $conn->prepare($total_sql);
if ($types && count($params) > 2) {
    $total_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
}
$total_stmt->execute();
$total = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

// Fetch applications (Admin only)
$applications_sql = "SELECT ja.*, j.title, e.full_name AS applicant_name 
                     FROM job_applications ja 
                     JOIN jobs j ON ja.job_id = j.id 
                     JOIN employees e ON ja.employee_id = e.id 
                     ORDER BY ja.applied_at DESC";
$applications_result = $conn->query($applications_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Recruitment - Jigjiga University HRM</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .job-form, .application-form {
            padding: 20px;
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        .jobs-table, .applications-table {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }
        .jobs-table th, .jobs-table td, .applications-table th, .applications-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }
        .jobs-table th, .applications-table th {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--sidebar-text);
        }
        .dark-mode .jobs-table th, .dark-mode .applications-table th {
            color: #ffffff;
        }
        .jobs-table tr.open { background: rgba(0, 255, 0, 0.1); }
        .jobs-table tr.closed { background: rgba(255, 0, 0, 0.1); }
        .applications-table tr.pending { background: rgba(255, 215, 0, 0.1); }
        .applications-table tr.accepted { background: rgba(0, 255, 0, 0.1); }
        .applications-table tr.rejected { background: rgba(255, 0, 0, 0.1); }
        .jobs-table tr:hover, .applications-table tr:hover {
            background: var(--card-hover);
        }
        .job-actions select {
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
            <h2><?php echo $role == "admin" ? "Recruitment Management" : "Job Openings"; ?></h2>
            <?php if ($role == "admin") { ?>
    <div class="job-form">
        <h3>Post New Job</h3>
        <form method="POST">
            <input type="hidden" name="post_job" value="1">
            <div class="input-group">
                <label for="title">Job Title</label>
                <input type="text" id="title" name="title" placeholder="Enter job title" required>
            </div>
            <div class="input-group">
                <label for="department">Department</label>
                <input type="text" id="department" name="department" placeholder="Enter department" required>
            </div>
            <div class="input-group">
                <label for="description">Job Description</label>
                <textarea id="description" name="description" placeholder="Enter the job description here" required rows="4"></textarea>
            </div>
            <button type="submit" class="form-btn">Post Job</button>
        </form>
    </div>
<?php } ?>

            <div class="search-filter">
                <select id="filter-status" onchange="filterJobs()">
                    <option value="">All Statuses</option>
                    <option value="open" <?php echo $filter_status == 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="closed" <?php echo $filter_status == 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>

            <table class="jobs-table" id="jobs-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Department</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Posted By</th>
                        <th>Posted At</th>
                        <th>Apply</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $jobs_result->fetch_assoc()) { ?>
                        <tr class="<?php echo $row['status']; ?>">
                            <td><?php echo $row['title']; ?></td>
                            <td><?php echo $row['department']; ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo ucfirst($row['status']); ?></td>
                            <td><?php echo $row['poster_name'] ?: 'Unknown'; ?></td>
                            <td><?php echo $row['posted_at']; ?></td>
                            <td>
                                <?php if ($row['status'] == 'open') { ?>
                                    <form method="POST" class="application-form">
                                        <input type="hidden" name="apply_job" value="1">
                                        <input type="hidden" name="job_id" value="<?php echo $row['id']; ?>">
                                        <textarea name="cover_letter" placeholder="Cover Letter" required rows="2" style="width: 100%; padding: 10px; border-radius: 8px; border: 2px solid var(--border-color); background: rgba(255, 255, 255, 0.05); color: var(--text-color);"></textarea>
                                        <button type="submit" class="form-btn">Apply</button>
                                    </form>
                                <?php } else { ?>
                                    <span>Closed</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo $filter_status; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php } ?>
            </div>

            <?php if ($role == "admin" && $applications_result->num_rows > 0) { ?>
                <h3>Applications</h3>
                <table class="applications-table" id="applications-table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Applicant</th>
                            <th>Cover Letter</th>
                            <th>Status</th>
                            <th>Applied At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $applications_result->fetch_assoc()) { ?>
                            <tr class="<?php echo $row['status']; ?>">
                                <td><?php echo $row['title']; ?></td>
                                <td><?php echo $row['applicant_name']; ?></td>
                                <td><?php echo htmlspecialchars($row['cover_letter']); ?></td>
                                <td><?php echo ucfirst($row['status']); ?></td>
                                <td><?php echo $row['applied_at']; ?></td>
                                <td class="job-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="review_application" value="1">
                                        <input type="hidden" name="application_id" value="<?php echo $row['id']; ?>">
                                        <select name="status" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $row['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="accepted" <?php echo $row['status'] == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                            <option value="rejected" <?php echo $row['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </section>
    </div>

    <script src="js/script.js"></script>
    <script>
        function filterJobs() {
            const status = document.getElementById('filter-status').value;
            window.location.href = `?status=${status}`;
        }
    </script>
</body>
</html>