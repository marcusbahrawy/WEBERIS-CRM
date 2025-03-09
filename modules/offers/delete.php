<?php
// modules/offers/delete.php - Delete an offer
require_once '../../config.php';

// Check permissions
if (!checkPermission('delete_offer')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$offerId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Check if offer exists
$stmt = $conn->prepare("SELECT title FROM offers WHERE id = ?");
$stmt->bind_param('i', $offerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$offer = $result->fetch_assoc();

// Check if offer has related projects
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM projects WHERE offer_id = ?");
$stmt->bind_param('i', $offerId);
$stmt->execute();
$result = $stmt->get_result();
$projectCount = $result->fetch_assoc()['count'];

// Get business ID, contact ID and lead ID if any (for redirection)
$stmt = $conn->prepare("SELECT business_id, contact_id, lead_id FROM offers WHERE id = ?");
$stmt->bind_param('i', $offerId);
$stmt->execute();
$result = $stmt->get_result();
$redirectData = $result->fetch_assoc();
$businessId = $redirectData['business_id'];
$contactId = $redirectData['contact_id'];
$leadId = $redirectData['lead_id'];

// Handle confirmation
$error = '';
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Cannot delete if has projects
        if ($projectCount > 0) {
            $error = "Cannot delete this offer because it has associated projects. Please delete the projects first.";
        } else {
            // Delete offer
            $stmt = $conn->prepare("DELETE FROM offers WHERE id = ?");
            $stmt->bind_param('i', $offerId);
            
            if ($stmt->execute()) {
                // Redirect based on source
                if (isset($_GET['redirect_to_business']) && $_GET['redirect_to_business'] === 'true' && $businessId) {
                    header("Location: ../businesses/view.php?id=" . $businessId . "&tab=offers");
                    exit;
                } elseif (isset($_GET['redirect_to_contact']) && $_GET['redirect_to_contact'] === 'true' && $contactId) {
                    header("Location: ../contacts/view.php?id=" . $contactId . "&tab=offers");
                    exit;
                } elseif (isset($_GET['redirect_to_lead']) && $_GET['redirect_to_lead'] === 'true' && $leadId) {
                    header("Location: ../leads/view.php?id=" . $leadId . "&tab=offers");
                    exit;
                } else {
                    header("Location: index.php?success=deleted");
                    exit;
                }
            } else {
                $error = "Error deleting offer: " . $conn->error;
            }
        }
    }
}

// Page title
$pageTitle = "Delete Offer";

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Delete Offer</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $offerId; ?>" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Offer
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="confirm-delete">
            <p>Are you sure you want to delete the offer "<strong><?php echo $offer['title']; ?></strong>"?</p>
            
            <?php if ($projectCount > 0): ?>
                <p class="text-danger">
                    <span class="material-icons" style="vertical-align: middle; margin-right: 8px;">error</span>
                    This offer cannot be deleted because it has <?php echo $projectCount; ?> associated project<?php echo $projectCount > 1 ? 's' : ''; ?>. 
                    Please delete the projects first.
                </p>
            <?php else: ?>
                <p class="text-danger">This action cannot be undone.</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="confirm" value="yes">
                    
                    <div class="form-actions">
                        <a href="view.php?id=<?php echo $offerId; ?>" class="btn btn-text">Cancel</a>
                        <button type="submit" class="btn btn-danger">Delete Offer</button>
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