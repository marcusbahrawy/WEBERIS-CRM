<?php
// modules/contacts/edit.php - Edit an existing contact
require_once '../../config.php';

// Check permissions
if (!checkPermission('edit_contact')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$contactId = (int)$_GET['id'];

// Page title
$pageTitle = "Edit Contact";

// Database connection
$conn = connectDB();

// Get contact data
$stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ?");
$stmt->bind_param('i', $contactId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$contact = $result->fetch_assoc();

// Get all businesses for dropdown
$businessesResult = $conn->query("SELECT id, name FROM businesses ORDER BY name ASC");
$businesses = [];
if ($businessesResult->num_rows > 0) {
    while ($business = $businessesResult->fetch_assoc()) {
        $businesses[] = $business;
    }
}

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Get and sanitize form data
        $firstName = sanitizeInput($_POST['first_name']);
        $lastName = sanitizeInput($_POST['last_name']);
        $position = sanitizeInput($_POST['position']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $businessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        $notes = sanitizeInput($_POST['notes']);
        
        // Validate required fields
        if (empty($firstName) || empty($lastName)) {
            $error = "First name and last name are required.";
        } else {
            // Update contact
            $stmt = $conn->prepare("UPDATE contacts SET first_name = ?, last_name = ?, position = ?, email = ?, phone = ?, business_id = ?, notes = ? WHERE id = ?");
            $stmt->bind_param('sssssisi', $firstName, $lastName, $position, $email, $phone, $businessId, $notes, $contactId);
            
            if ($stmt->execute()) {
                $success = "Contact updated successfully.";
                
                // Refresh contact data
                $stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ?");
                $stmt->bind_param('i', $contactId);
                $stmt->execute();
                $result = $stmt->get_result();
                $contact = $result->fetch_assoc();
            } else {
                $error = "Error updating contact: " . $conn->error;
            }
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Edit Contact</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $contactId; ?>" class="btn btn-text">
                <span class="material-icons">visibility</span> View Contact
            </a>
            <a href="index.php" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Contacts
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
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo $contact['first_name']; ?>" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo $contact['last_name']; ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo $contact['email']; ?>">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?php echo $contact['phone']; ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="position">Position</label>
                        <input type="text" id="position" name="position" class="form-control" value="<?php echo $contact['position']; ?>">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="business_id">Business</label>
                        <select id="business_id" name="business_id" class="form-control">
                            <option value="">-- No Business --</option>
                            <?php foreach ($businesses as $business): ?>
                                <option value="<?php echo $business['id']; ?>" <?php echo ($contact['business_id'] == $business['id']) ? 'selected' : ''; ?>>
                                    <?php echo $business['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="5"><?php echo $contact['notes']; ?></textarea>
            </div>
            
            <div class="form-actions">
                <a href="view.php?id=<?php echo $contactId; ?>" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Contact</button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>