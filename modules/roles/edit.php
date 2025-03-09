<?php
// modules/roles/edit.php - Edit an existing role
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
$stmt = $conn->prepare("SELECT * FROM roles WHERE id = ?");
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
$isAdminRole = ($role['name'] === 'admin');

// Get all permissions for checkboxes
$permissionsResult = $conn->query("SELECT id, name, description FROM permissions ORDER BY name ASC");
$permissions = [];
if ($permissionsResult->num_rows > 0) {
    // Group permissions by module
    while ($permission = $permissionsResult->fetch_assoc()) {
        $parts = explode('_', $permission['name']);
        $action = array_pop($parts);
        $module = implode('_', $parts);
        
        if (!isset($permissions[$module])) {
            $permissions[$module] = [];
        }
        
        $permissions[$module][] = $permission;
    }
}

// Get role's current permissions
$stmt = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
$stmt->bind_param('i', $roleId);
$stmt->execute();
$permissionsResult = $stmt->get_result();
$currentPermissions = [];

while ($row = $permissionsResult->fetch_assoc()) {
    $currentPermissions[] = $row['permission_id'];
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
        $description = sanitizeInput($_POST['description']);
        $selectedPermissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
        
        // Validate required fields
        if (empty($name)) {
            $error = "Role name is required.";
        } else {
            // Check if role name already exists (excluding current role)
            $stmt = $conn->prepare("SELECT id FROM roles WHERE name = ? AND id != ?");
            $stmt->bind_param('si', $name, $roleId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "A role with this name already exists.";
            } else {
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Protect admin role name from being changed
                    if ($isAdminRole && $name !== 'admin') {
                        $error = "Cannot change the name of the admin role.";
                        throw new Exception("Cannot change admin role name");
                    }
                    
                    // Update role
                    $stmt = $conn->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
                    $stmt->bind_param('ssi', $name, $description, $roleId);
                    $stmt->execute();
                    
                    // Delete current role permissions
                    $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                    $stmt->bind_param('i', $roleId);
                    $stmt->execute();
                    
                    // Insert new role permissions
                    if (!empty($selectedPermissions)) {
                        $stmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                        
                        foreach ($selectedPermissions as $permissionId) {
                            $permissionId = (int)$permissionId;
                            $stmt->bind_param('ii', $roleId, $permissionId);
                            $stmt->execute();
                        }
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    $success = "Role updated successfully.";
                    
                    // Refresh current permissions
                    $stmt = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
                    $stmt->bind_param('i', $roleId);
                    $stmt->execute();
                    $permissionsResult = $stmt->get_result();
                    $currentPermissions = [];
                    
                    while ($row = $permissionsResult->fetch_assoc()) {
                        $currentPermissions[] = $row['permission_id'];
                    }
                } catch (Exception $e) {
                    // Rollback on error
                    $conn->rollback();
                    if (empty($error)) {
                        $error = "Error updating role: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Page title
$pageTitle = "Edit Role: " . $role['name'];

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Edit Role: <?php echo $role['name']; ?></h2>
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
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($isAdminRole): ?>
            <div class="alert alert-info">
                <strong>Note:</strong> This is the admin role. Some restrictions apply to editing this role.
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" data-validate="true">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="name">Role Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo $role['name']; ?>" required <?php echo $isAdminRole ? 'readonly' : ''; ?>>
                        <?php if ($isAdminRole): ?>
                            <span class="form-hint">The admin role name cannot be changed.</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input type="text" id="description" name="description" class="form-control" value="<?php echo $role['description']; ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Permissions</label>
                <div class="permissions-container">
                    <?php foreach ($permissions as $module => $modulePermissions): ?>
                        <div class="permission-module">
                            <h4>
                                <?php echo ucfirst(str_replace('_', ' ', $module)); ?>
                                <button type="button" class="btn btn-text btn-sm select-all-btn" data-module="<?php echo $module; ?>">
                                    Select All
                                </button>
                            </h4>
                            
                            <div class="permission-checkboxes">
                                <?php foreach ($modulePermissions as $permission): ?>
                                    <div class="permission-checkbox">
                                        <label>
                                            <input type="checkbox" name="permissions[]" value="<?php echo $permission['id']; ?>" 
                                                   data-module="<?php echo $module; ?>"
                                                   <?php echo in_array($permission['id'], $currentPermissions) ? 'checked' : ''; ?>
                                                   <?php echo ($isAdminRole) ? 'checked disabled' : ''; ?>>
                                            <?php 
                                            $actionName = str_replace($module . '_', '', $permission['name']);
                                            echo ucfirst(str_replace('_', ' ', $actionName));
                                            ?>
                                            <span class="permission-description"><?php echo $permission['description']; ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($isAdminRole): ?>
                    <!-- Hidden inputs to submit all permissions for admin role -->
                    <?php foreach ($permissions as $module => $modulePermissions): ?>
                        <?php foreach ($modulePermissions as $permission): ?>
                            <input type="hidden" name="permissions[]" value="<?php echo $permission['id']; ?>">
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <span class="form-hint">The admin role always has all permissions.</span>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <a href="../settings/index.php?tab=roles" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Role</button>
            </div>
        </form>
    </div>
</div>

<style>
.permissions-container {
    margin-top: var(--spacing-sm);
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid var(--grey-200);
    border-radius: var(--border-radius-md);
    padding: var(--spacing-md);
}

.permission-module {
    margin-bottom: var(--spacing-md);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--grey-200);
}

.permission-module h4 {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--spacing-sm);
}

.permission-checkboxes {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: var(--spacing-sm);
}

.permission-checkbox {
    display: block;
    margin-bottom: var(--spacing-xs);
}

.permission-description {
    display: block;
    font-size: var(--font-size-xs);
    color: var(--text-secondary);
    margin-left: 20px;
}

.btn-sm {
    padding: var(--spacing-xs) var(--spacing-sm);
    font-size: var(--font-size-sm);
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle "Select All" buttons
        const selectAllButtons = document.querySelectorAll('.select-all-btn');
        selectAllButtons.forEach(button => {
            button.addEventListener('click', function() {
                const module = this.dataset.module;
                const checkboxes = document.querySelectorAll(`input[data-module="${module}"]:not(:disabled)`);
                
                // Check if all checkboxes are already checked
                const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
                
                // Toggle all checkboxes
                checkboxes.forEach(checkbox => {
                    checkbox.checked = !allChecked;
                });
                
                // Update button text
                this.textContent = allChecked ? 'Select All' : 'Deselect All';
            });
        });
        
        // Initialize button text based on initial state
        selectAllButtons.forEach(button => {
            const module = button.dataset.module;
            const checkboxes = document.querySelectorAll(`input[data-module="${module}"]:not(:disabled)`);
            const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
            
            button.textContent = allChecked ? 'Deselect All' : 'Select All';
        });
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>