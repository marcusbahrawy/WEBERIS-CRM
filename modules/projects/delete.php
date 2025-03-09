<?php
// modules/projects/delete.php - Delete a project
require_once '../../config.php';

// Check permissions
if (!checkPermission('delete_project')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$projectId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Check if project exists
$stmt = $conn->prepare("SELECT name FROM projects WHERE id = ?");
$stmt->bind_param('i', $projectId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$project = $result->fetch_assoc();

// Get business ID and offer ID if any (for redirection)
$stmt = $conn->prepare("SELECT business_id, offer_id FROM projects WHERE id = ?");
$stmt->bind_param('i', $projectId);
$stmt->execute();
$result = $stmt->get_result();
$redirectData = $result->fetch_assoc();
$businessId = $redirectData['business_id'];
$offerId = $redirectData['offer_id'];

// Handle confirmation
$error = '';
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Delete project
        $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->bind_param('i', $projectId);
        
        if ($stmt->execute()) {
            // Redirect based on source
            if (isset($_GET['redirect_to_business']) && $_GET['redirect_to_business'] === 'true' && $businessId) {
                header("Location: ../businesses/view.php?id=" . $businessId . "&tab=projects");
                exit;
            } elseif (isset($_GET['redirect_to_offer']) && $_GET['redirect_to_offer'] === 'true' && $offerId) {
                header("Location: ../offers/view.php?id=" . $offerId);
                exit;
            } else {
                header("Location: index.php?success=deleted");
                exit;
            }
        } else {
            $error = "Error deleting project: " . $conn->error;
        }
    }
}

// Page title
$pageTitle = "Delete Project";

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Delete Project</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $projectId; ?>" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Project
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="confirm-delete">
            <p>Are you sure you want to delete the project "<strong><?php echo $project['name']; ?></strong>"?</p>
            <p class="text-danger">This action cannot be undone.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="confirm" value="yes">
                
                <div class="form-actions">
                    <a href="view.php?id=<?php echo $projectId; ?>" class="btn btn-text">Cancel</a>
                    <button type="submit" class="btn btn-danger">Delete Project</button>
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