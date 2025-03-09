<?php
// modules/users/add.php - Add a new user
require_once '../../config.php';

// Check if user is logged in and has admin rights
if (!isLoggedIn() || $_SESSION['role_name'] !== 'admin') {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Add User";

// Database connection
$conn = connectDB();

// Get all roles for dropdown
$rolesResult = $conn->query("SELECT id, name FROM roles ORDER BY name ASC");
$roles = [];
if ($rolesResult->num_rows > 0) {
    while ($role = $rolesResult->fetch_assoc()) {
        $roles[] = $role;
    }
}

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Get and sanitize form data
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $roleId = (int)$_POST['role_id'];
        
        // Validate required fields
        if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "Email address is already in use.";
            } else {
                // Check if role exists
                $stmt = $conn->prepare("SELECT id FROM roles WHERE id = ?");
                $stmt->bind_param('i', $roleId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $error = "Selected role does not exist.";
                } else {
                    // Hash password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new user
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role_id) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('sssi', $name, $email, $hashedPassword, $roleId);
                    
                    if ($stmt->execute()) {
                        $success = "User added successfully.";
                        // Redirect back to users tab in settings
                        header("Location: ../settings/index.php?tab=users&success=user_added");
                        exit;
                    } else {
                        $error = "Error adding user: " . $conn->error;
                    }
                }
            }
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Add New User</h2>
        <div class="card-header-actions">
            <a href="../settings/index.php?tab=users" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Users
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" data-validate="true">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="name">Full Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <span class="form-hint">Password must be at least 8 characters long</span>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="role_id">Role <span class="required">*</span></label>
                <select id="role_id" name="role_id" class="form-control" required>
                    <option value="">-- Select Role --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>"><?php echo $role['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <a href="../settings/index.php?tab=users" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-primary">Add User</button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>