<?php
// modules/roles/delete.php - Delete a role
require_once '../../config.php';

// Check if user is logged in and has admin rights
if (!isLoggedIn() || $_SESSION['role_name'] !== 'admin') {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../settings/index.php?tab=roles");
    exit;
}

$roleId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Get role data
$stmt = $conn->prepare("SELECT name FROM roles WHERE id = ?");
$stmt->bind_param('i', $roleId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: ../settings/index.php?tab=roles");
    exit;
}

$role = $result->fetch_assoc();

// Special protection for the admin role
if ($role['name'] === 'admin') {
    $_SESSION['error_message'] = "The admin role cannot be deleted.";
    header("Location: ../settings/index.php?tab=roles");
    exit;
}

// Check if there are users assigned to this role
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role_id = ?");
$stmt->bind_param('i', $roleId);
$stmt->execute();
$result = $stmt->get_result();
$userCount = $result->fetch_assoc()['count'];

// Handle confirmation
$error = '';
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Check if there are users assigned to this role
        if ($userCount > 0) {
            $error = "Cannot delete role because it has users assigned to it. Please reassign these users to other roles first.";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Delete role permissions
                $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmt->bind_param('i', $roleId);
                $stmt->execute();
                
                // Delete role
                $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
                $stmt->bind_param('i', $roleId);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                // Redirect to roles tab with success message
                header("Location: ../settings/index.php?tab=roles&success=role_deleted");
                exit;
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $error = "Error deleting role: " . $e->getMessage();
            }
        }
    }
}

// Page title
$pageTitle = "Delete Role";

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Delete Role</h2>
        <div class="card-header-actions">
            <a href="../settings/index.php?tab=roles" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Roles
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="confirm-delete">
            <p>Are you sure you want to delete the role "<strong><?php echo $role['name']; ?></strong>"?</p>
            
            <?php if ($userCount > 0): ?>
                <div class="alert alert-warning">
                    <strong>Warning:</strong> This role has <?php echo $userCount; ?> user(s) assigned to it. You must reassign these users to other roles before deleting this role.
                </div>
                
                <div class="form-actions">
                    <a href="../settings/index.php?tab=roles" class="btn btn-primary">Back to Roles</a>
                </div>
            <?php else: ?>
                <p class="text-danger">This action cannot be undone. All permissions associated with this role will be deleted.</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="confirm" value="yes">
                    
                    <div class="form-actions">
                        <a href="../settings/index.php?tab=roles" class="btn btn-text">Cancel</a>
                        <button type="submit" class="btn btn-danger">Delete Role</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>