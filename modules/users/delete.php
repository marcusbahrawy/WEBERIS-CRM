<?php
// modules/users/delete.php - Delete a user
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
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
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
if ($user['email'] === MASTER_ADMIN_EMAIL) {
    $_SESSION['error_message'] = "The master administrator account cannot be deleted.";
    header("Location: ../settings/index.php?tab=users");
    exit;
}

// Handle confirmation
$error = '';
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Check if user is trying to delete themselves
        if ($userId === (int)$_SESSION['user_id']) {
            $error = "You cannot delete your own account.";
        } else {
            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $userId);
            
            if ($stmt->execute()) {
                // Redirect to users tab with success message
                header("Location: ../settings/index.php?tab=users&success=user_deleted");
                exit;
            } else {
                $error = "Error deleting user: " . $conn->error;
            }
        }
    }
}

// Page title
$pageTitle = "Delete User";

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Delete User</h2>
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
        
        <div class="confirm-delete">
            <p>Are you sure you want to delete the user "<strong><?php echo $user['name']; ?></strong>" with email "<strong><?php echo $user['email']; ?></strong>"?</p>
            <p class="text-danger">This action cannot be undone. All data associated with this user will remain in the system but will no longer be linked to a user account.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="confirm" value="yes">
                
                <div class="form-actions">
                    <a href="../settings/index.php?tab=users" class="btn btn-text">Cancel</a>
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>