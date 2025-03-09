<?php
// modules/contacts/delete.php - Delete a contact
require_once '../../config.php';

// Check permissions
if (!checkPermission('delete_contact')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$contactId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Check if contact exists
$stmt = $conn->prepare("SELECT first_name, last_name FROM contacts WHERE id = ?");
$stmt->bind_param('i', $contactId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$contact = $result->fetch_assoc();
$contactName = $contact['first_name'] . ' ' . $contact['last_name'];

// Get business ID if any (for redirection)
$businessId = 0;
$stmt = $conn->prepare("SELECT business_id FROM contacts WHERE id = ?");
$stmt->bind_param('i', $contactId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $businessId = $result->fetch_assoc()['business_id'];
}

// Handle confirmation
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Delete contact
        $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->bind_param('i', $contactId);
        
        if ($stmt->execute()) {
            // Redirect to contacts listing with success message
            if ($businessId && isset($_GET['redirect_to_business']) && $_GET['redirect_to_business'] === 'true') {
                header("Location: ../businesses/view.php?id=" . $businessId . "&tab=contacts");
            } else {
                header("Location: index.php?success=deleted");
            }
            exit;
        } else {
            $error = "Error deleting contact: " . $conn->error;
        }
    }
}

// Page title
$pageTitle = "Delete Contact";

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Delete Contact</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $contactId; ?>" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Contact
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="confirm-delete">
            <p>Are you sure you want to delete the contact "<strong><?php echo $contactName; ?></strong>"?</p>
            <p class="text-danger">This action cannot be undone. Any related leads and offers will be affected.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="confirm" value="yes">
                
                <div class="form-actions">
                    <a href="view.php?id=<?php echo $contactId; ?>" class="btn btn-text">Cancel</a>
                    <button type="submit" class="btn btn-danger">Delete Contact</button>
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