<?php
// modules/businesses/edit.php - Edit an existing business
require_once '../../config.php';

// Check permissions
if (!checkPermission('edit_business')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$businessId = (int)$_GET['id'];

// Page title
$pageTitle = "Edit Business";

// Database connection
$conn = connectDB();

// Get business data
$stmt = $conn->prepare("SELECT * FROM businesses WHERE id = ?");
$stmt->bind_param('i', $businessId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$business = $result->fetch_assoc();

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Get and sanitize form data
        $name = sanitizeInput($_POST['name']);
        $registrationNumber = sanitizeInput($_POST['registration_number']);
        $address = sanitizeInput($_POST['address']);
        $phone = sanitizeInput($_POST['phone']);
        $email = sanitizeInput($_POST['email']);
        $website = sanitizeInput($_POST['website']);
        $industry = sanitizeInput($_POST['industry']);
        $description = sanitizeInput($_POST['description']);
        
        // Validate required fields
        if (empty($name)) {
            $error = "Business name is required.";
        } else {
            // Check if business name already exists (excluding current business)
            $stmt = $conn->prepare("SELECT id FROM businesses WHERE name = ? AND id != ?");
            $stmt->bind_param('si', $name, $businessId);
            $stmt->execute();
            $checkResult = $stmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $error = "A business with this name already exists.";
            } else {
                // Update business
                $stmt = $conn->prepare("UPDATE businesses SET name = ?, registration_number = ?, address = ?, phone = ?, email = ?, website = ?, industry = ?, description = ? WHERE id = ?");
                $stmt->bind_param('ssssssssi', $name, $registrationNumber, $address, $phone, $email, $website, $industry, $description, $businessId);
                
                if ($stmt->execute()) {
                    $success = "Business updated successfully.";
                    
                    // Refresh business data
                    $stmt = $conn->prepare("SELECT * FROM businesses WHERE id = ?");
                    $stmt->bind_param('i', $businessId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $business = $result->fetch_assoc();
                } else {
                    $error = "Error updating business: " . $conn->error;
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
        <h2>Edit Business</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $businessId; ?>" class="btn btn-text">
                <span class="material-icons">visibility</span> View Business
            </a>
            <a href="index.php" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Businesses
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
                        <label for="name">Business Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo $business['name']; ?>" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="registration_number">Registration Number</label>
                        <input type="text" id="registration_number" name="registration_number" class="form-control" value="<?php echo $business['registration_number']; ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" class="form-control" rows="3"><?php echo $business['address']; ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?php echo $business['phone']; ?>">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo $business['email']; ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="website">Website</label>
                        <input type="url" id="website" name="website" class="form-control" value="<?php echo $business['website']; ?>">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="industry">Industry</label>
                        <input type="text" id="industry" name="industry" class="form-control" value="<?php echo $business['industry']; ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="5"><?php echo $business['description']; ?></textarea>
            </div>
            
            <div class="form-actions">
                <a href="view.php?id=<?php echo $businessId; ?>" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Business</button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>