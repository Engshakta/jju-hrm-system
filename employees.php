<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header("Location: index.php");
    exit();
}

// Handle CRUD Operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_employee']) && $_SESSION['role'] == 'admin') {
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $role = $_POST['role'];
        $department = $_POST['department'];
        $salary = floatval($_POST['salary']);
        $joining_date = $_POST['joining_date'];
        $status = $_POST['status'];

        $last_id = $conn->query("SELECT MAX(id) as max_id FROM employees")->fetch_assoc()['max_id'] + 1;
        $employee_id = 'EMP' . str_pad($last_id, 3, '0', STR_PAD_LEFT);

        $profile_picture = '';
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $profile_picture = $target_dir . basename($_FILES["profile_picture"]["name"]);
            if (!move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $profile_picture)) {
                die("Error: Failed to upload profile picture.");
            }
        }

        $sql = "INSERT INTO employees (employee_id, full_name, email, phone, role, department, salary, joining_date, status, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssdsss", $employee_id, $full_name, $email, $phone, $role, $department, $salary, $joining_date, $status, $profile_picture);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['update_employee']) && $_SESSION['role'] == 'admin') {
        $id = $_POST['id'];
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $role = $_POST['role'];
        $department = $_POST['department'];
        $salary = $_POST['salary'];
        $joining_date = $_POST['joining_date'];
        $status = $_POST['status'];

        $sql = "UPDATE employees SET full_name=?, email=?, phone=?, role=?, department=?, salary=?, joining_date=?, status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssdsi", $full_name, $email, $phone, $role, $department, $salary, $joining_date, $status, $id);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['delete_employee']) && $_SESSION['role'] == 'admin') {
        $id = $_POST['id'];
        $sql = "UPDATE employees SET is_deleted=1 WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

// Search and Filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_department = isset($_GET['department']) ? $_GET['department'] : '';
$filter_role = isset($_GET['role']) ? $_GET['role'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'active';

$per_page = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

$where = "WHERE is_deleted=0 AND status LIKE ?";
$params = ["%$filter_status%"];
$types = "s";

if ($search) {
    $where .= " AND (full_name LIKE ? OR employee_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}
if ($filter_department) {
    $where .= " AND department=?";
    $params[] = $filter_department;
    $types .= "s";
}
if ($filter_role) {
    $where .= " AND role=?";
    $params[] = $filter_role;
    $types .= "s";
}

$sql = "SELECT * FROM employees $where LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$total_sql = "SELECT COUNT(*) as total FROM employees $where";
$total_stmt = $conn->prepare($total_sql);
$total_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
$total_stmt->execute();
$total = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

$departments = $conn->query("SELECT DISTINCT department FROM employees WHERE is_deleted=0")->fetch_all(MYSQLI_ASSOC);
$roles = $conn->query("SELECT DISTINCT role FROM employees WHERE is_deleted=0")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employees - Jigjiga University HRM</title>
    <link rel="stylesheet" href="/JJU/css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="<?php echo $_SESSION['role']; ?>-role">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="dashboard-header">
            <h1>Jigjiga University HRM</h1>
            <div class="user-info">
                <span>Welcome, <?php echo ucfirst($_SESSION['role']); ?></span>
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
            <h2>Employee Management</h2>

            <?php if ($_SESSION['role'] == 'admin') { ?>
                <div class="form-container">
                    <h3>Add New Employee</h3>
                    <form method="POST" enctype="multipart/form-data" id="add-employee-form">
                        <input type="hidden" name="add_employee" value="1">
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" name="full_name" placeholder="Full Name" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" placeholder="Email" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-phone"></i>
                            <input type="text" name="phone" placeholder="Phone" pattern="[0-9]{10}" title="10-digit phone number">
                        </div>
                        <div class="input-group">
                            <i class="fas fa-briefcase"></i>
                            <input type="text" name="role" placeholder="Role" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-building"></i>
                            <input type="text" name="department" placeholder="Department" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-dollar-sign"></i>
                            <input type="number" name="salary" placeholder="Salary" step="0.01" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-calendar-alt"></i>
                            <input type="date" name="joining_date" required>
                        </div>
                        <div class="input-group">
                            <select name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <input type="file" name="profile_picture" accept="image/*">
                        </div>
                        <button type="submit" class="form-btn">Add Employee</button>
                    </form>
                </div>
            <?php } ?>

            <div class="search-filter">
                <input type="text" id="search" placeholder="Search by name or ID..." value="<?php echo htmlspecialchars($search); ?>" onkeyup="filterEmployees()">
                <select id="filter-department" onchange="filterEmployees()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept) { ?>
                        <option value="<?php echo $dept['department']; ?>" <?php echo $filter_department == $dept['department'] ? 'selected' : ''; ?>><?php echo $dept['department']; ?></option>
                    <?php } ?>
                </select>
                <select id="filter-role" onchange="filterEmployees()">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as $r) { ?>
                        <option value="<?php echo $r['role']; ?>" <?php echo $filter_role == $r['role'] ? 'selected' : ''; ?>><?php echo $r['role']; ?></option>
                    <?php } ?>
                </select>
                <select id="filter-status" onchange="filterEmployees()">
                    <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <table id="employee-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Salary</th>
                        <th>Joining Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()) { ?>
                        <tr data-id="<?php echo $row['id']; ?>">
                            <td><?php echo $row['employee_id']; ?></td>
                            <td><img src="<?php echo $row['profile_picture'] ?: 'uploads/default.jpg'; ?>" alt="Profile" class="profile-img"></td>
                            <td><?php echo $row['full_name']; ?></td>
                            <td><?php echo $row['email']; ?></td>
                            <td><?php echo $row['phone']; ?></td>
                            <td><?php echo $row['role']; ?></td>
                            <td><?php echo $row['department']; ?></td>
                            <td><?php echo number_format($row['salary'], 2); ?></td>
                            <td><?php echo $row['joining_date']; ?></td>
                            <td><?php echo ucfirst($row['status']); ?></td>
                            <td>
                                <?php if ($_SESSION['role'] == 'admin') { ?>
                                    <button class="edit-btn" onclick="editEmployee(<?php echo $row['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                    <button class="delete-btn" onclick="confirmDelete(<?php echo $row['id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($filter_department); ?>&role=<?php echo urlencode($filter_role); ?>&status=<?php echo urlencode($filter_status); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php } ?>
            </div>
        </section>

        <div id="delete-modal" class="modal">
            <div class="modal-content">
                <h3>Confirm Deletion</h3>
                <p>Are you sure you want to delete this employee? This will archive them.</p>
                <form method="POST" id="delete-form">
                    <input type="hidden" name="delete_employee" value="1">
                    <input type="hidden" name="id" id="delete-id">
                    <button type="submit" class="modal-btn confirm-btn">Yes, Delete</button>
                    <button type="button" class="modal-btn cancel-btn" onclick="closeModal()">Cancel</button>
                </form>
            </div>
        </div>

        <div id="edit-modal" class="modal">
            <div class="modal-content">
                <h3>Edit Employee</h3>
                <form method="POST" id="edit-form">
                    <input type="hidden" name="update_employee" value="1">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="full_name" id="edit-full_name" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" id="edit-email" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-phone"></i>
                        <input type="text" name="phone" id="edit-phone" pattern="[0-9]{10}">
                    </div>
                    <div class="input-group">
                        <i class="fas fa-briefcase"></i>
                        <input type="text" name="role" id="edit-role" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-building"></i>
                        <input type="text" name="department" id="edit-department" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-dollar-sign"></i>
                        <input type="number" name="salary" id="edit-salary" step="0.01" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" name="joining_date" id="edit-joining_date" required>
                    </div>
                    <div class="input-group">
                        <select name="status" id="edit-status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="modal-btn confirm-btn">Update</button>
                    <button type="button" class="modal-btn cancel-btn" onclick="closeModal()">Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let debounceTimer;

        function filterEmployees() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const search = document.getElementById('search').value;
                const department = document.getElementById('filter-department').value;
                const role = document.getElementById('filter-role').value;
                const status = document.getElementById('filter-status').value;
                window.location.href = `?search=${encodeURIComponent(search)}&department=${encodeURIComponent(department)}&role=${encodeURIComponent(role)}&status=${encodeURIComponent(status)}`;
            }, 500);
        }

        function editEmployee(id) {
            const row = document.querySelector(`tr[data-id='${id}']`);
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-full_name').value = row.cells[2].textContent;
            document.getElementById('edit-email').value = row.cells[3].textContent;
            document.getElementById('edit-phone').value = row.cells[4].textContent;
            document.getElementById('edit-role').value = row.cells[5].textContent;
            document.getElementById('edit-department').value = row.cells[6].textContent;
            document.getElementById('edit-salary').value = row.cells[7].textContent.replace(/,/g, '');
            document.getElementById('edit-joining_date').value = row.cells[8].textContent;
            document.getElementById('edit-status').value = row.cells[9].textContent.toLowerCase();
            document.getElementById('edit-modal').style.display = 'block';
        }

        function confirmDelete(id) {
            document.getElementById('delete-id').value = id;
            document.getElementById('delete-modal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('delete-modal').style.display = 'none';
            document.getElementById('edit-modal').style.display = 'none';
        }

        document.getElementById('add-employee-form').addEventListener('submit', function(e) {
            const email = document.querySelector('input[name="email"]').value;
            const phone = document.querySelector('input[name="phone"]').value;
            if (!/^\S+@\S+\.\S+$/.test(email)) {
                alert('Please enter a valid email address.');
                e.preventDefault();
            }
            if (phone && !/^\d{10}$/.test(phone)) {
                alert('Phone number must be 10 digits.');
                e.preventDefault();
            }
        });
    </script>
    <script src="js/script.js"></script>
</body>
</html>