<?php
// modules/settings/index.php - System settings administration page
require_once '../../config.php';

// Check if user is logged in and has admin rights
if (!isLoggedIn() || $_SESSION['role_name'] !== 'admin') {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "System Settings";

// Get current tab from query parameter
$currentTab = isset($_GET['tab']) ? sanitizeInput($_GET['tab']) : 'general';

// Handle form submission for general settings
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $updateCount = 0;
        
        // Create an array with editable settings
        $editableSettings = [
            'currency_symbol', 
            'currency_position', 
            'decimal_separator', 
            'thousands_separator',
            'app_name',
            'company_name',
            'company_email',
            'date_format',
            'time_format'
        ];
        
        // Update each submitted setting
        foreach ($editableSettings as $setting) {
            if (isset($_POST[$setting])) {
                $value = sanitizeInput($_POST[$setting]);
                
                // Special validation for specific settings
                if ($setting === 'currency_position' && !in_array($value, ['before', 'after'])) {
                    $error = "Invalid currency position value.";
                    break;
                }
                
                if (saveSetting($setting, $value)) {
                    $updateCount++;
                }
            }
        }
        
        if (empty($error) && $updateCount > 0) {
            $success = "Settings updated successfully.";
        } elseif (empty($error)) {
            $error = "No settings were changed.";
        }
    }
}

// Get all settings
$settings = getAllSettings(false);

// Database connection
$conn = connectDB();

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>System Settings</h2>
        <div class="card-header-actions">
            <a href="<?php echo SITE_URL; ?>/dashboard.php" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Dashboard
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
        
        <?php if (isset($_GET['success'])): ?>
            <?php
            $successMessage = '';
            switch ($_GET['success']) {
                case 'user_added':
                    $successMessage = 'User added successfully.';
                    break;
                case 'user_deleted':
                    $successMessage = 'User deleted successfully.';
                    break;
                case 'role_added':
                    $successMessage = 'Role added successfully.';
                    break;
                case 'role_deleted':
                    $successMessage = 'Role deleted successfully.';
                    break;
            }
            
            if (!empty($successMessage)):
            ?>
                <div class="alert alert-success"><?php echo $successMessage; ?></div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error_message']; ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <!-- Tabs navigation -->
        <div class="settings-tabs mb-lg">
            <a href="?tab=general" class="btn <?php echo $currentTab === 'general' ? 'btn-primary' : 'btn-text'; ?>">General</a>
            <a href="?tab=users" class="btn <?php echo $currentTab === 'users' ? 'btn-primary' : 'btn-text'; ?>">Users</a>
            <a href="?tab=roles" class="btn <?php echo $currentTab === 'roles' ? 'btn-primary' : 'btn-text'; ?>">Roles</a>
        </div>
        
        <?php if ($currentTab === 'general'): ?>
        <!-- General Settings Tab -->
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <!-- General settings -->
            <div class="settings-section">
                <h3>General</h3>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="app_name">Application Name</label>
                            <input type="text" id="app_name" name="app_name" class="form-control" 
                                   value="<?php echo $settings['app_name']['value'] ?? APP_NAME; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="company_name">Company Name</label>
                            <input type="text" id="company_name" name="company_name" class="form-control"
                                   value="<?php echo $settings['company_name']['value'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="company_email">Company Email</label>
                            <input type="email" id="company_email" name="company_email" class="form-control"
                                   value="<?php echo $settings['company_email']['value'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Currency settings -->
            <div class="settings-section">
                <h3>Currency Settings</h3>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="currency_symbol">Currency Symbol</label>
                            <input type="text" id="currency_symbol" name="currency_symbol" class="form-control"
                                   value="<?php echo $settings['currency_symbol']['value'] ?? 'NOK'; ?>">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="currency_position">Currency Position</label>
                            <select id="currency_position" name="currency_position" class="form-control">
                                <option value="before" <?php echo ($settings['currency_position']['value'] ?? 'after') === 'before' ? 'selected' : ''; ?>>
                                    Before (<?php echo $settings['currency_symbol']['value'] ?? 'NOK'; ?> 100)
                                </option>
                                <option value="after" <?php echo ($settings['currency_position']['value'] ?? 'after') === 'after' ? 'selected' : ''; ?>>
                                    After (100 <?php echo $settings['currency_symbol']['value'] ?? 'NOK'; ?>)
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="decimal_separator">Decimal Separator</label>
                            <select id="decimal_separator" name="decimal_separator" class="form-control">
                                <option value="." <?php echo ($settings['decimal_separator']['value'] ?? ',') === '.' ? 'selected' : ''; ?>>
                                    Period (.)
                                </option>
                                <option value="," <?php echo ($settings['decimal_separator']['value'] ?? ',') === ',' ? 'selected' : ''; ?>>
                                    Comma (,)
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="thousands_separator">Thousands Separator</label>
                            <select id="thousands_separator" name="thousands_separator" class="form-control">
                                <option value=" " <?php echo ($settings['thousands_separator']['value'] ?? ' ') === ' ' ? 'selected' : ''; ?>>
                                    Space ( )
                                </option>
                                <option value="," <?php echo ($settings['thousands_separator']['value'] ?? ' ') === ',' ? 'selected' : ''; ?>>
                                    Comma (,)
                                </option>
                                <option value="." <?php echo ($settings['thousands_separator']['value'] ?? ' ') === '.' ? 'selected' : ''; ?>>
                                    Period (.)
                                </option>
                                <option value="" <?php echo ($settings['thousands_separator']['value'] ?? ' ') === '' ? 'selected' : ''; ?>>
                                    None
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="currency-preview">
                        <label>Preview:</label>
                        <div class="preview-box" id="currencyPreview">
                            <?php echo formatCurrency(1234.56); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Date and time settings -->
            <div class="settings-section">
                <h3>Date & Time Settings</h3>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="date_format">Date Format</label>
                            <select id="date_format" name="date_format" class="form-control">
                                <option value="d.m.Y" <?php echo ($settings['date_format']['value'] ?? 'd.m.Y') === 'd.m.Y' ? 'selected' : ''; ?>>
                                    31.12.2025 (DD.MM.YYYY)
                                </option>
                                <option value="d/m/Y" <?php echo ($settings['date_format']['value'] ?? 'd.m.Y') === 'd/m/Y' ? 'selected' : ''; ?>>
                                    31/12/2025 (DD/MM/YYYY)
                                </option>
                                <option value="m/d/Y" <?php echo ($settings['date_format']['value'] ?? 'd.m.Y') === 'm/d/Y' ? 'selected' : ''; ?>>
                                    12/31/2025 (MM/DD/YYYY)
                                </option>
                                <option value="Y-m-d" <?php echo ($settings['date_format']['value'] ?? 'd.m.Y') === 'Y-m-d' ? 'selected' : ''; ?>>
                                    2025-12-31 (YYYY-MM-DD)
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="time_format">Time Format</label>
                            <select id="time_format" name="time_format" class="form-control">
                                <option value="H:i" <?php echo ($settings['time_format']['value'] ?? 'H:i') === 'H:i' ? 'selected' : ''; ?>>
                                    14:30 (24 hour)
                                </option>
                                <option value="h:i A" <?php echo ($settings['time_format']['value'] ?? 'H:i') === 'h:i A' ? 'selected' : ''; ?>>
                                    02:30 PM (12 hour)
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="date-preview">
                        <label>Preview:</label>
                        <div class="preview-box" id="dateTimePreview">
                            <?php echo formatDateTime(date('Y-m-d H:i:s')); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="reset" class="btn btn-text">Reset</button>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
        
        <?php elseif ($currentTab === 'users'): ?>
        <!-- Users Tab -->
        <div class="settings-section">
            <h3>User Management</h3>
            
            <?php
            // Get all users
            $usersQuery = "SELECT u.id, u.name, u.email, r.name as role_name, u.created_at 
                          FROM users u 
                          JOIN roles r ON u.role_id = r.id 
                          ORDER BY u.name ASC";
            $usersResult = $conn->query($usersQuery);
            $users = [];
            
            if ($usersResult->num_rows > 0) {
                while ($user = $usersResult->fetch_assoc()) {
                    $users[] = $user;
                }
            }
            ?>
            
            <div class="mb-md">
                <a href="../users/add.php" class="btn btn-primary">
                    <span class="material-icons">add</span> Add User
                </a>
            </div>
            
            <?php if (count($users) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['name']; ?></td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td><?php echo $user['role_name']; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="actions-cell">
                                        <a href="../users/edit.php?id=<?php echo $user['id']; ?>" class="btn btn-icon btn-text" title="Edit User">
                                            <span class="material-icons">edit</span>
                                        </a>
                                        <?php if ($user['email'] !== MASTER_ADMIN_EMAIL): ?>
                                            <a href="../users/delete.php?id=<?php echo $user['id']; ?>" class="btn btn-icon btn-text delete-item" 
                                               title="Delete User" data-confirm="Are you sure you want to delete this user?">
                                                <span class="material-icons">delete</span>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="material-icons">people</span>
                    </div>
                    <h3>No users found</h3>
                    <p>Add users to provide access to the system.</p>
                    <a href="../users/add.php" class="btn btn-primary">Add User</a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php elseif ($currentTab === 'roles'): ?>
        <!-- Roles Tab -->
        <div class="settings-section">
            <h3>Role Management</h3>
            
            <?php
            // Get all roles
            $rolesQuery = "SELECT r.id, r.name, r.description, 
                          (SELECT COUNT(*) FROM users WHERE role_id = r.id) as user_count 
                          FROM roles r ORDER BY r.name ASC";
            $rolesResult = $conn->query($rolesQuery);
            $roles = [];
            
            if ($rolesResult->num_rows > 0) {
                while ($role = $rolesResult->fetch_assoc()) {
                    $roles[] = $role;
                }
            }
            ?>
            
            <div class="mb-md">
                <a href="../roles/add.php" class="btn btn-primary">
                    <span class="material-icons">add</span> Add Role
                </a>
            </div>
            
            <?php if (count($roles) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Role Name</th>
                                <th>Description</th>
                                <th>Users</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td><?php echo $role['name']; ?></td>
                                    <td><?php echo $role['description']; ?></td>
                                    <td><?php echo $role['user_count']; ?></td>
                                    <td class="actions-cell">
                                        <a href="../roles/edit.php?id=<?php echo $role['id']; ?>" class="btn btn-icon btn-text" title="Edit Role">
                                            <span class="material-icons">edit</span>
                                        </a>
                                        <?php if ($role['name'] !== 'admin' && $role['user_count'] == 0): ?>
                                            <a href="../roles/delete.php?id=<?php echo $role['id']; ?>" class="btn btn-icon btn-text delete-item" 
                                               title="Delete Role" data-confirm="Are you sure you want to delete this role?">
                                                <span class="material-icons">delete</span>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="material-icons">admin_panel_settings</span>
                    </div>
                    <h3>No roles found</h3>
                    <p>Add roles to define permissions for users.</p>
                    <a href="../roles/add.php" class="btn btn-primary">Add Role</a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.settings-section {
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-lg);
    border-bottom: 1px solid var(--grey-200);
}

.settings-section h3 {
    margin-bottom: var(--spacing-md);
    color: var(--grey-800);
    font-size: var(--font-size-xl);
}

.preview-box {
    background-color: var(--grey-50);
    padding: var(--spacing-md);
    border-radius: var(--border-radius-md);
    margin-top: var(--spacing-xs);
    font-weight: var(--font-weight-medium);
}

.settings-tabs {
    display: flex;
    gap: var(--spacing-sm);
    border-bottom: 1px solid var(--grey-200);
    padding-bottom: var(--spacing-md);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update currency preview when settings change
    const currencySymbolInput = document.getElementById('currency_symbol');
    const currencyPositionSelect = document.getElementById('currency_position');
    const decimalSeparatorSelect = document.getElementById('decimal_separator');
    const thousandsSeparatorSelect = document.getElementById('thousands_separator');
    const currencyPreview = document.getElementById('currencyPreview');
    
    if (currencySymbolInput && currencyPositionSelect && decimalSeparatorSelect && thousandsSeparatorSelect && currencyPreview) {
        function updateCurrencyPreview() {
            const symbol = currencySymbolInput.value || 'NOK';
            const position = currencyPositionSelect.value;
            const decimalSep = decimalSeparatorSelect.value;
            const thousandsSep = thousandsSeparatorSelect.value;
            
            // Format example amount
            let formattedAmount = '1234.56'.replace('.', decimalSep);
            
            // Add thousands separator
            if (thousandsSep !== '') {
                const parts = formattedAmount.split(decimalSep);
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep);
                formattedAmount = parts.join(decimalSep);
            }
            
            // Place currency symbol
            if (position === 'before') {
                currencyPreview.textContent = symbol + ' ' + formattedAmount;
            } else {
                currencyPreview.textContent = formattedAmount + ' ' + symbol;
            }
        }
        
        // Update preview when settings change
        currencySymbolInput.addEventListener('input', updateCurrencyPreview);
        currencyPositionSelect.addEventListener('change', updateCurrencyPreview);
        decimalSeparatorSelect.addEventListener('change', updateCurrencyPreview);
        thousandsSeparatorSelect.addEventListener('change', updateCurrencyPreview);
        
        // Initialize preview
        updateCurrencyPreview();
    }
    
    // Update date and time preview when settings change
    const dateFormatSelect = document.getElementById('date_format');
    const timeFormatSelect = document.getElementById('time_format');
    const dateTimePreview = document.getElementById('dateTimePreview');
    
    if (dateFormatSelect && timeFormatSelect && dateTimePreview) {
        function updateDateTimePreview() {
            const now = new Date();
            let dateStr = '';
            
            switch (dateFormatSelect.value) {
                case 'd.m.Y':
                    dateStr = now.getDate() + '.' + (now.getMonth() + 1) + '.' + now.getFullYear();
                    break;
                case 'd/m/Y':
                    dateStr = now.getDate() + '/' + (now.getMonth() + 1) + '/' + now.getFullYear();
                    break;
                case 'm/d/Y':
                    dateStr = (now.getMonth() + 1) + '/' + now.getDate() + '/' + now.getFullYear();
                    break;
                case 'Y-m-d':
                    dateStr = now.getFullYear() + '-' + (now.getMonth() + 1) + '-' + now.getDate();
                    break;
                default:
                    dateStr = now.getDate() + '.' + (now.getMonth() + 1) + '.' + now.getFullYear();
            }
            
            let timeStr = '';
            if (timeFormatSelect.value === 'H:i') {
                timeStr = now.getHours() + ':' + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes();
            } else {
                const hours = now.getHours() % 12 || 12;
                timeStr = hours + ':' + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes() + ' ' + (now.getHours() >= 12 ? 'PM' : 'AM');
            }
            
            dateTimePreview.textContent = dateStr + ' ' + timeStr;
        }
        
        // Add event listeners
        dateFormatSelect.addEventListener('change', updateDateTimePreview);
        timeFormatSelect.addEventListener('change', updateDateTimePreview);
        
        // Initialize preview
        updateDateTimePreview();
    }

    // Setup delete confirmation
    const deleteLinks = document.querySelectorAll('.delete-item');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>