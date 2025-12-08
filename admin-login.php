<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

// if already logged in → send admin to dashboard
if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin-dashboard.php' : 'missions.php');
}

// handles login, e verify ang admin credentials and start session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) { // Collects email and password
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        // checks if ang user exists ug is an admin.
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_admin = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
        
        // f valid, starts session and redirects to admin dashboard.
            redirect('admin-dashboard.php');
        } else {
            $error = 'Invalid email or password. Only admins can login here.';
        }
        // Handle registration → validate inputs and create new admin account
    } elseif (isset($_POST['register'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $fullName = trim($_POST['full_name']);
        
        if (empty($email) || empty($password) || empty($fullName)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Email already registered.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, is_admin) VALUES (?, ?, ?, 1)");
                
                if ($stmt->execute([$email, $hashedPassword, $fullName])) {
                    $success = 'Admin registration successful! Please login.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickServe - Admin Portal</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="logo">
            <h1>🍃 QuickServe</h1>
            <p>Admin Portal</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="form-toggle">
            <button class="toggle-btn active" onclick="showLogin()">Login</button>
            <button class="toggle-btn" onclick="showRegister()">Register</button>
        </div>
        
        <form id="loginForm" method="POST" class="auth-form">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required placeholder="Enter your email">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter your password">
            </div>
            
            <button type="submit" name="login" class="btn btn-primary">Login as Admin</button>
            <a href="#" class="forgot-password">Forgot Password?</a>
        </form>
        
        <form id="registerForm" method="POST" class="auth-form" style="display: none;">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" required placeholder="Enter your full name">
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required placeholder="Enter your email">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="At least 6 characters">
            </div>
            
            <button type="submit" name="register" class="btn btn-primary">Register as Admin</button>
        </form>
        
        <div class="auth-footer">
            <p>Are you a student? <a href="index.php">Login as Student</a></p>
        </div>
    </div>
    
    <script src="js/main.js"></script>
</body>
</html>
