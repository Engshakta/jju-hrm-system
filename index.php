<?php
session_start();
include 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role']; // 'admin', 'staff', or 'employee'

    // Hardcoded users (username = password)
    $users = [
        'admin' => ['role' => 'admin', 'password' => 'admin', 'id' => 1],
        'staff' => ['role' => 'staff', 'password' => 'staff', 'id' => 2],
        'employee' => ['role' => 'employee', 'password' => 'employee', 'id' => 3]
    ];

    if (isset($users[$username]) && $users[$username]['password'] === $password && $users[$username]['role'] === $role) {
        $_SESSION['user_id'] = $users[$username]['id'];
        $_SESSION['role'] = $users[$username]['role'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid credentials or role mismatch";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Jigjiga University HRM</title>
    <link rel="stylesheet" href="/JJU/css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <h1>Jigjiga University HRM</h1>
                <p>Welcome! Please select your role and log in.</p>
            </div>
            <div class="role-toggle">
                <button class="role-btn active" data-role="admin" onclick="selectRole('admin')">Admin</button>
                <button class="role-btn" data-role="staff" onclick="selectRole('staff')">Staff</button>
                <button class="role-btn" data-role="employee" onclick="selectRole('employee')">Employee</button>
            </div>
            <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
            <form method="POST" id="login-form">
                <input type="hidden" name="role" id="role-input" value="admin">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Username" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="login-btn">Login</button>
            </form>
        </div>
    </div>
    <script>
        function selectRole(role) {
            document.getElementById('role-input').value = role;
            document.querySelectorAll('.role-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('data-role') === role) {
                    btn.classList.add('active');
                }
            });
        }
    </script>
</body>
</html>