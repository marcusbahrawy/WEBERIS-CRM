<?php
// modules/agreement_types/delete.php - Delete an agreement type
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

// Check if agreement type exists
$stmt = $conn->prepare("SELECT name, label FROM agreement_types WHERE id = ?");
$stmt->bind_param('i', $typeId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$agreementType = $result->fetch_assoc();

// Check if this type is in use
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM service_agreements WHERE agreement_type = ?");
$stmt->bind_param('s', $agreementType['name']);
$stmt->execute();
$usageResult = $stmt->get_result();
$usageCount = $usageResult->fetch_assoc()['count'];

// Handle confirmation
$error = '';
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Check if we can delete this type
        if ($usageCount > 0) {
            $error = "Cannot delete this agreement type because it is being used by " . $usageCount . " service agreement(s).";
        } else {
            // Delete agreement type
            $stmt = $conn->prepare("DELETE FROM agreement_types WHERE id = ?");
            $stmt->bind_param('i', $typeId);
            
            if ($stmt->execute()) {
                // Redirect to agreement types listing with success message
                header("Location: index.php?success=deleted");
                exit;
            } else {
                $error = "Error deleting agreement type: " . $conn->error;
            }
        }
    }
}

// Page title
$pageTitle = "Delete Agreement Type";

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Delete Agreement Type</h2>
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
        
        <div class="confirm-delete">
            <p>Are you sure you want to delete the agreement type "<strong><?php echo $agreementType['label']; ?></strong>"?</p>
            
            <?php if ($usageCount > 0): ?>
                <div class="alert alert-warning">
                    <strong>Warning:</strong> This agreement type is currently being used by <?php echo $usageCount; ?> service agreement(s). 
                    You cannot delete it until all associated service agreements are updated to use a different type.
                </div>
                <div class="form-actions">
                    <a href="index.php" class="btn btn-primary">Return to Agreement Types</a>
                </div>
            <?php else: ?>
                <p class="text-danger">This action cannot be undone.</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="confirm" value="yes">
                    
                    <div class="form-actions">
                        <a href="index.php" class="btn btn-text">Cancel</a>
                        <button type="submit" class="btn btn-danger">Delete Agreement Type</button>
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