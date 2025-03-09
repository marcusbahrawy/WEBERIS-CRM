<?php
// modules/roles/add.php - Add a new role
require_once '../../config.php';

// Check if user is logged in and has admin rights
if (!isLoggedIn() || $_SESSION['role_name'] !== 'admin') {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Add Role";

// Database connection
$conn = connectDB();

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
            // Check if role name already exists
            $stmt = $conn->prepare("SELECT id FROM roles WHERE name = ?");
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "A role with this name already exists.";
            } else {
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Insert new role
                    $stmt = $conn->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
                    $stmt->bind_param('ss', $name, $description);
                    $stmt->execute();
                    
                    $roleId = $conn->insert_id;
                    
                    // Insert role permissions
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
                    
                    // Redirect back to roles tab in settings
                    header("Location: ../settings/index.php?tab=roles&success=role_added");
                    exit;
                } catch (Exception $e) {
                    // Rollback on error
                    $conn->rollback();
                    $error = "Error adding role: " . $e->getMessage();
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
        <h2>Add New Role</h2>
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
        
        <form method="POST" action="" data-validate="true">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="name">Role Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input type="text" id="description" name="description" class="form-control">
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
                                            <input type="checkbox" name="permissions[]" value="<?php echo $permission['id']; ?>" data-module="<?php echo $module; ?>">
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
            </div>
            
            <div class="form-actions">
                <a href="../settings/index.php?tab=roles" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-primary">Add Role</button>
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
                const checkboxes = document.querySelectorAll(`input[data-module="${module}"]`);
                
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
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>