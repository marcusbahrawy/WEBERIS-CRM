<?php
// modules/users/edit.php - Edit an existing user
require_once '../../config.php';

// Check if user is logged in and has admin rights
if (!isLoggedIn() || $_SESSION['role_name'] !== 'admin') {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../settings/index.php?tab=users");
    exit;
}

$userId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Get user data
$stmt = $conn->prepare("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: ../settings/index.php?tab=users");
    exit;
}

$user = $result->fetch_assoc();

// Special protection for the master admin user
$isMasterAdmin = ($user['email'] === MASTER_ADMIN_EMAIL);

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
        $roleId = (int)$_POST['role_id'];
        $changePassword = isset($_POST['change_password']) && $_POST['change_password'] === 'yes';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate required fields
        if (empty($name) || empty($email)) {
            $error = "Name and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif ($changePassword && (empty($newPassword) || empty($confirmPassword))) {
            $error = "Both password fields are required when changing password.";
        } elseif ($changePassword && $newPassword !== $confirmPassword) {
            $error = "Passwords do not match.";
        } elseif ($changePassword && strlen($newPassword) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            // Check if email already exists (excluding current user)
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param('si', $email, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "Email address is already in use by another user.";
            } else {
                // Check if role exists
                $stmt = $conn->prepare("SELECT id FROM roles WHERE id = ?");
                $stmt->bind_param('i', $roleId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $error = "Selected role does not exist.";
                } else {
                    // Protect master admin from role change
                    if ($isMasterAdmin && $user['role_id'] != $roleId) {
                        $error = "Cannot change role for the master admin user.";
                    } else {
                        // Update user data
                        if ($changePassword) {
                            // Hash new password
                            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                            
                            // Update with new password
                            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, role_id = ? WHERE id = ?");
                            $stmt->bind_param('sssii', $name, $email, $hashedPassword, $roleId, $userId);
                        } else {
                            // Update without changing password
                            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role_id = ? WHERE id = ?");
                            $stmt->bind_param('ssii', $name, $email, $roleId, $userId);
                        }
                        
                        if ($stmt->execute()) {
                            $success = "User updated successfully.";
                            
                            // Refresh user data
                            $stmt = $conn->prepare("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
                            $stmt->bind_param('i', $userId);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $user = $result->fetch_assoc();
                        } else {
                            $error = "Error updating user: " . $conn->error;
                        }
                    }
                }
            }
        }
    }
}

// Page title
$pageTitle = "Edit User: " . $user['name'];

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Edit User: <?php echo $user['name']; ?></h2>
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
        
        <?php if ($isMasterAdmin): ?>
            <div class="alert alert-info">
                <strong>Note:</strong> This is the master administrator account. Some restrictions apply to editing this account.
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" data-validate="true">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="name">Full Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo $user['name']; ?>" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo $user['email']; ?>" required <?php echo $isMasterAdmin ? 'readonly' : ''; ?>>
                        <?php if ($isMasterAdmin): ?>
                            <span class="form-hint">The email for the master admin account cannot be changed.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="role_id">Role <span class="required">*</span></label>
                <select id="role_id" name="role_id" class="form-control" required <?php echo $isMasterAdmin ? 'disabled' : ''; ?>>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>" <?php echo ($user['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                            <?php echo $role['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isMasterAdmin): ?>
                    <input type="hidden" name="role_id" value="<?php echo $user['role_id']; ?>">
                    <span class="form-hint">The role for the master admin account cannot be changed.</span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="change_password" value="yes" id="change_password"> Change Password
                    </label>
                </div>
            </div>
            
            <div id="password_fields" style="display: none;">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="new_password">New Password <span class="required">*</span></label>
                            <input type="password" id="new_password" name="new_password" class="form-control">
                            <span class="form-hint">Password must be at least 8 characters long</span>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="../settings/index.php?tab=users" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password fields visibility
        const changePasswordCheckbox = document.getElementById('change_password');
        const passwordFields = document.getElementById('password_fields');
        
        if (changePasswordCheckbox && passwordFields) {
            changePasswordCheckbox.addEventListener('change', function() {
                passwordFields.style.display = this.checked ? 'block' : 'none';
                
                // Reset fields when hidden
                if (!this.checked) {
                    document.getElementById('new_password').value = '';
                    document.getElementById('confirm_password').value = '';
                }
            });
        }
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>