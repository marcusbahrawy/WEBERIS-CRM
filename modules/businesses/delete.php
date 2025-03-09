<?php
// modules/businesses/delete.php - Delete a business
require_once '../../config.php';

// Check permissions
if (!checkPermission('delete_business')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$businessId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Check if business exists
$stmt = $conn->prepare("SELECT name FROM businesses WHERE id = ?");
$stmt->bind_param('i', $businessId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$business = $result->fetch_assoc();

// Handle confirmation
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Delete business
        $stmt = $conn->prepare("DELETE FROM businesses WHERE id = ?");
        $stmt->bind_param('i', $businessId);
        
        if ($stmt->execute()) {
            // Redirect to businesses listing with success message
            header("Location: index.php?success=deleted");
            exit;
        } else {
            $error = "Error deleting business: " . $conn->error;
        }
    }
}

// Page title
$pageTitle = "Delete Business";

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Delete Business</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $businessId; ?>" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Business
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="confirm-delete">
            <p>Are you sure you want to delete the business "<strong><?php echo $business['name']; ?></strong>"?</p>
            <p class="text-danger">This action cannot be undone. All related contacts, leads, and projects will be affected.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="confirm" value="yes">
                
                <div class="form-actions">
                    <a href="view.php?id=<?php echo $businessId; ?>" class="btn btn-text">Cancel</a>
                    <button type="submit" class="btn btn-danger">Delete Business</button>
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