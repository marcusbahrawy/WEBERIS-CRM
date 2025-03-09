<?php
// modules/agreement_types/edit.php - Edit an existing agreement type
require_once '../../config.php';

// Check permissions
if (!checkPermission('edit_service_agreement')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$typeId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Get agreement type data
$stmt = $conn->prepare("SELECT * FROM agreement_types WHERE id = ?");
$stmt->bind_param('i', $typeId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$agreementType = $result->fetch_assoc();

// Page title
$pageTitle = "Edit Agreement Type";

// Check if this type is in use
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM service_agreements WHERE agreement_type = ?");
$stmt->bind_param('s', $agreementType['name']);
$stmt->execute();
$usageResult = $stmt->get_result();
$usageCount = $usageResult->fetch_assoc()['count'];

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Get and sanitize form data
        $originalName = $agreementType['name'];
        $label = sanitizeInput($_POST['label']);
        $description = sanitizeInput($_POST['description']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Only allow name modification if the type is not in use
        $name = $originalName;
        if ($usageCount == 0) {
            $name = sanitizeInput($_POST['name']);
            // Sanitize the name to ensure it's valid for database storage
            $name = preg_replace('/[^a-z0-9_]/', '', strtolower($name));
        }
        
        // Validate required fields
        if (empty($name)) {
            $error = "Name is required.";
        } elseif (empty($label)) {
            $error = "Label is required.";
        } else {
            // Check if agreement type name already exists (excluding current type)
            $stmt = $conn->prepare("SELECT id FROM agreement_types WHERE name = ? AND id != ?");
            $stmt->bind_param('si', $name, $typeId);
            $stmt->execute();
            $checkResult = $stmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $error = "An agreement type with this name already exists.";
            } else {
                // Prepare update statement
                $updateQuery = "UPDATE agreement_types SET label = ?, description = ?, is_active = ?";
                $paramTypes = "ssi";
                $params = [$label, $description, $isActive];
                
                // Only update name if the type is not in use
                if ($usageCount == 0) {
                    $updateQuery .= ", name = ?";
                    $paramTypes .= "s";
                    $params[] = $name;
                }
                
                $updateQuery .= " WHERE id = ?";
                $paramTypes .= "i";
                $params[] = $typeId;
                
                // Update agreement type
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param($paramTypes, ...$params);
                
                if ($stmt->execute()) {
                    $success = "Agreement type updated successfully.";
                    
                    // If name was changed and successful, update service_agreements table
                    if ($usageCount == 0 && $name !== $originalName) {
                        $stmt = $conn->prepare("UPDATE service_agreements SET agreement_type = ? WHERE agreement_type = ?");
                        $stmt->bind_param('ss', $name, $originalName);
                        $stmt->execute();
                    }
                    
                    // Refresh agreement type data
                    $stmt = $conn->prepare("SELECT * FROM agreement_types WHERE id = ?");
                    $stmt->bind_param('i', $typeId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $agreementType = $result->fetch_assoc();
                    
                    // Redirect to the agreement types listing page
                    header("Location: index.php?success=updated");
                    exit;
                } else {
                    $error = "Error updating agreement type: " . $conn->error;
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
        <h2>Edit Agreement Type</h2>
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
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo $agreementType['name']; ?>" <?php echo ($usageCount > 0) ? 'readonly' : 'required'; ?>>
                        <span class="form-hint">
                            System name (lowercase letters, numbers and underscores only)
                            <?php if ($usageCount > 0): ?>
                                <br><strong>This type is in use by <?php echo $usageCount; ?> service agreement(s) and cannot be renamed.</strong>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="label">Label <span class="required">*</span></label>
                        <input type="text" id="label" name="label" class="form-control" value="<?php echo $agreementType['label']; ?>" required>
                        <span class="form-hint">Display name shown to users</span>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"><?php echo $agreementType['description']; ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="checkbox-container">
                    <input type="checkbox" name="is_active" id="is_active" <?php echo $agreementType['is_active'] ? 'checked' : ''; ?>>
                    <span class="checkbox-label">Active</span>
                </label>
                <span class="form-hint">Inactive types won't appear in dropdown lists</span>
            </div>
            
            <div class="form-actions">
                <a href="index.php" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Agreement Type</button>
            </div>
        </form>
    </div>
</div>

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
$conn->close();
?>