<?php
// modules/leads/delete.php - Delete a lead
require_once '../../config.php';

// Check permissions
if (!checkPermission('delete_lead')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$leadId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Check if lead exists
$stmt = $conn->prepare("SELECT title FROM leads WHERE id = ?");
$stmt->bind_param('i', $leadId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$lead = $result->fetch_assoc();

// Check if lead has related offers
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM offers WHERE lead_id = ?");
$stmt->bind_param('i', $leadId);
$stmt->execute();
$result = $stmt->get_result();
$offerCount = $result->fetch_assoc()['count'];

// Get business ID and contact ID if any (for redirection)
$stmt = $conn->prepare("SELECT business_id, contact_id FROM leads WHERE id = ?");
$stmt->bind_param('i', $leadId);
$stmt->execute();
$result = $stmt->get_result();
$redirectData = $result->fetch_assoc();
$businessId = $redirectData['business_id'];
$contactId = $redirectData['contact_id'];

// Handle confirmation
$error = '';
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update offers to remove the lead reference
            if ($offerCount > 0) {
                $stmt = $conn->prepare("UPDATE offers SET lead_id = NULL WHERE lead_id = ?");
                $stmt->bind_param('i', $leadId);
                $stmt->execute();
            }
            
            // Delete lead
            $stmt = $conn->prepare("DELETE FROM leads WHERE id = ?");
            $stmt->bind_param('i', $leadId);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Redirect based on source
            if (isset($_GET['redirect_to_business']) && $_GET['redirect_to_business'] === 'true' && $businessId) {
                header("Location: ../businesses/view.php?id=" . $businessId . "&tab=leads");
                exit;
            } elseif (isset($_GET['redirect_to_contact']) && $_GET['redirect_to_contact'] === 'true' && $contactId) {
                header("Location: ../contacts/view.php?id=" . $contactId . "&tab=leads");
                exit;
            } else {
                header("Location: index.php?success=deleted");
                exit;
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Error deleting lead: " . $e->getMessage();
        }
    }
}

// Page title
$pageTitle = "Delete Lead";

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Delete Lead</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $leadId; ?>" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Lead
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="confirm-delete">
            <p>Are you sure you want to delete the lead "<strong><?php echo $lead['title']; ?></strong>"?</p>
            
            <?php if ($offerCount > 0): ?>
                <p class="text-warning">
                    <span class="material-icons" style="vertical-align: middle; margin-right: 8px;">warning</span>
                    This lead has <?php echo $offerCount; ?> related offer<?php echo $offerCount > 1 ? 's' : ''; ?>. 
                    The offers will be kept, but they will no longer be associated with this lead.
                </p>
            <?php endif; ?>
            
            <p class="text-danger">This action cannot be undone.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="confirm" value="yes">
                
                <div class="form-actions">
                    <a href="view.php?id=<?php echo $leadId; ?>" class="btn btn-text">Cancel</a>
                    <button type="submit" class="btn btn-danger">Delete Lead</button>
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