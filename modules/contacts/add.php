<?php
// modules/contacts/add.php - Add a new contact
require_once '../../config.php';

// Check permissions
if (!checkPermission('add_contact')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Add Contact";

// Get business_id from query parameter
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;
$businessName = '';

if ($businessId) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT name FROM businesses WHERE id = ?");
    $stmt->bind_param('i', $businessId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $businessName = $result->fetch_assoc()['name'];
    } else {
        $businessId = null;
    }
    $conn->close();
}

// Get all businesses for dropdown
$conn = connectDB();
$businessesResult = $conn->query("SELECT id, name FROM businesses ORDER BY name ASC");
$businesses = [];
if ($businessesResult->num_rows > 0) {
    while ($business = $businessesResult->fetch_assoc()) {
        $businesses[] = $business;
    }
}
$conn->close();

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
        $selectedBusinessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        $notes = sanitizeInput($_POST['notes']);
        
        // Validate required fields
        if (empty($firstName) || empty($lastName)) {
            $error = "First name and last name are required.";
        } else {
            // Database connection
            $conn = connectDB();
            
            // Insert new contact
            $stmt = $conn->prepare("INSERT INTO contacts (first_name, last_name, position, email, phone, business_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssssisi', $firstName, $lastName, $position, $email, $phone, $selectedBusinessId, $notes, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $contactId = $conn->insert_id;
                $success = "Contact added successfully.";
                
                // Redirect to the new contact page
                header("Location: view.php?id=" . $contactId . "&success=created");
                exit;
            } else {
                $error = "Error adding contact: " . $conn->error;
            }
            
            $conn->close();
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><?php echo $businessId ? "Add Contact for " . $businessName : "Add New Contact"; ?></h2>
        <div class="card-header-actions">
            <a href="index.php<?php echo $businessId ? '?business_id=' . $businessId : ''; ?>" class="btn btn-text">
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
                        <input type="text" id="first_name" name="first_name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control" required>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control">
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="position">Position</label>
                        <input type="text" id="position" name="position" class="form-control">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="business_id">Business</label>
                        <select id="business_id" name="business_id" class="form-control">
                            <option value="">-- No Business --</option>
                            <?php foreach ($businesses as $business): ?>
                                <option value="<?php echo $business['id']; ?>" <?php echo ($businessId == $business['id']) ? 'selected' : ''; ?>>
                                    <?php echo $business['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="5"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="reset" class="btn btn-text">Reset</button>
                <button type="submit" class="btn btn-primary">Add Contact</button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
?>