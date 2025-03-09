<?php
// modules/service_agreements/delete.php - Delete a service agreement
require_once '../../config.php';

// Check permissions
if (!checkPermission('delete_service_agreement')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$agreementId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Check if service agreement exists
$stmt = $conn->prepare("SELECT title, business_id FROM service_agreements WHERE id = ?");
$stmt->bind_param('i', $agreementId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$agreement = $result->fetch_assoc();
$businessId = $agreement['business_id'];

// Handle confirmation
$error = '';
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Delete service agreement
        $stmt = $conn->prepare("DELETE FROM service_agreements WHERE id = ?");
        $stmt->bind_param('i', $agreementId);
        
        if ($stmt->execute()) {
            // Redirect based on source
            if (isset($_GET['redirect_to_business']) && $_GET['redirect_to_business'] === 'true' && $businessId) {
                header("Location: ../businesses/view.php?id=" . $businessId . "&tab=service_agreements");
                exit;
            } else {
                header("Location: index.php?success=deleted");
                exit;
            }
        } else {
            $error = "Error deleting service agreement: " . $conn->error;
        }
    }
}

// Page title
$pageTitle = "Delete Service Agreement";

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Delete Service Agreement</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $agreementId; ?>" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Service Agreement
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="confirm-delete">
            <p>Are you sure you want to delete the service agreement "<strong><?php echo $agreement['title']; ?></strong>"?</p>
            <p class="text-danger">This action cannot be undone.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="confirm" value="yes">
                
                <div class="form-actions">
                    <a href="view.php?id=<?php echo $agreementId; ?>" class="btn btn-text">Cancel</a>
                    <button type="submit" class="btn btn-danger">Delete Service Agreement</button>
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