<?php
// modules/agreement_types/add.php - Add a new agreement type
require_once '../../config.php';

// Check permissions (use service_agreement permissions for now)
if (!checkPermission('edit_service_agreement')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Add Agreement Type";

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
        $label = sanitizeInput($_POST['label']);
        $description = sanitizeInput($_POST['description']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Validate required fields
        if (empty($name)) {
            $error = "Name is required.";
        } elseif (empty($label)) {
            $error = "Label is required.";
        } else {
            // Sanitize the name to ensure it's valid for database storage
            $name = preg_replace('/[^a-z0-9_]/', '', strtolower($name));
            
            if (empty($name)) {
                $error = "Name must contain at least one alphanumeric character.";
            } else {
                // Database connection
                $conn = connectDB();
                
                // Check if agreement type already exists
                $stmt = $conn->prepare("SELECT id FROM agreement_types WHERE name = ?");
                $stmt->bind_param('s', $name);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "An agreement type with this name already exists.";
                } else {
                    // Insert new agreement type
                    $stmt = $conn->prepare("INSERT INTO agreement_types (name, label, description, is_active) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('sssi', $name, $label, $description, $isActive);
                    
                    if ($stmt->execute()) {
                        $agreementTypeId = $conn->insert_id;
                        $success = "Agreement type added successfully.";
                        
                        // Redirect to the agreement types listing page
                        header("Location: index.php?success=created");
                        exit;
                    } else {
                        $error = "Error adding agreement type: " . $conn->error;
                    }
                }
                
                $conn->close();
            }
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Add New Agreement Type</h2>
        <div class="card-header-actions">
            <a href="index.php" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Agreement Types
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
                        <label for="name">Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" required>
                        <span class="form-hint">System name (lowercase letters, numbers and underscores only)</span>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="label">Label <span class="required">*</span></label>
                        <input type="text" id="label" name="label" class="form-control" required>
                        <span class="form-hint">Display name shown to users</span>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label class="checkbox-container">
                    <input type="checkbox" name="is_active" id="is_active" checked>
                    <span class="checkbox-label">Active</span>
                </label>
                <span class="form-hint">Inactive types won't appear in dropdown lists</span>
            </div>
            
            <div class="form-actions">
                <button type="reset" class="btn btn-text">Reset</button>
                <button type="submit" class="btn btn-primary">Add Agreement Type</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-generate name from label
        const labelInput = document.getElementById('label');
        const nameInput = document.getElementById('name');
        
        labelInput.addEventListener('input', function() {
            // Only auto-generate if name field is empty or hasn't been manually edited
            if (nameInput.dataset.manuallyEdited !== 'true') {
                const label = this.value;
                const name = label.toLowerCase()
                    .replace(/\s+/g, '_')  // Replace spaces with underscores
                    .replace(/[^a-z0-9_]/g, '');  // Remove non-alphanumeric characters
                nameInput.value = name;
            }
        });
        
        // Mark the name field as manually edited when user types in it
        nameInput.addEventListener('input', function() {
            nameInput.dataset.manuallyEdited = 'true';
        });
    });
</script>

<style>
.checkbox-container {
    display: flex;
    align-items: center;
    margin-bottom: var(--spacing-sm);
    cursor: pointer;
}

.checkbox-label {
    margin-left: var(--spacing-sm);
    font-weight: var(--font-weight-medium);
}
</style>

<?php
// Include footer
include '../../includes/footer.php';
?>