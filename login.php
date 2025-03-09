<?php
// login.php - User login interface
require_once 'config.php';
startSecureSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            $error = "All fields are required.";
        } else {
            $conn = connectDB();
            
            // Get user by email
            $stmt = $conn->prepare("SELECT u.id, u.name, u.email, u.password, u.role_id, r.name as role_name 
                                    FROM users u 
                                    JOIN roles r ON u.role_id = r.id 
                                    WHERE u.email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Get user permissions
                    $permissions = [];
                    $permStmt = $conn->prepare("SELECT p.name FROM permissions p 
                                               JOIN role_permissions rp ON p.id = rp.permission_id 
                                               WHERE rp.role_id = ?");
                    $permStmt->bind_param("i", $user['role_id']);
                    $permStmt->execute();
                    $permResult = $permStmt->get_result();
                    
                    while ($perm = $permResult->fetch_assoc()) {
                        $permissions[] = $perm['name'];
                    }
                    
                    // Set session data
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['role_name'] = $user['role_name'];
                    $_SESSION['permissions'] = $permissions;
                    
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);
                    
                    header("Location: " . SITE_URL . "/dashboard.php");
                    exit;
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
            }
            
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/modern-styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-logo">
            <h1><?php echo APP_NAME; ?></h1>
        </div>
        
        <div class="login-form">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>