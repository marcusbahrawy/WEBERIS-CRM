<?php
// modules/service_agreements/add.php - Add a new service agreement
require_once '../../config.php';

// Check permissions
if (!checkPermission('add_service_agreement')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Add Service Agreement";

// Get business_id from query parameter
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;
$businessName = '';

$conn = connectDB();

// Check if business exists and get name
if ($businessId) {
    $stmt = $conn->prepare("SELECT name FROM businesses WHERE id = ?");
    $stmt->bind_param('i', $businessId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $businessName = $result->fetch_assoc()['name'];
    } else {
        $businessId = null;
    }
}

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
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $status = sanitizeInput($_POST['status']);
        $agreementType = sanitizeInput($_POST['agreement_type']);
        $startDate = sanitizeInput($_POST['start_date']);
        $endDate = !empty($_POST['end_date']) ? sanitizeInput($_POST['end_date']) : null;
        $renewalDate = !empty($_POST['renewal_date']) ? sanitizeInput($_POST['renewal_date']) : null;
        $price = !empty($_POST['price']) ? (float)$_POST['price'] : 0;
        $billingCycle = sanitizeInput($_POST['billing_cycle']);
        $selectedBusinessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        
        // Validate required fields
        if (empty($title)) {
            $error = "Agreement title is required.";
        } elseif (empty($startDate)) {
            $error = "Start date is required.";
        } elseif (empty($selectedBusinessId)) {
            $error = "Business is required.";
        } elseif ($price <= 0) {
            $error = "Price must be greater than zero.";
        } elseif (!empty($endDate) && !empty($startDate) && strtotime($endDate) < strtotime($startDate)) {
            $error = "End date cannot be earlier than start date.";
        } elseif (!empty($renewalDate) && !empty($startDate) && strtotime($renewalDate) < strtotime($startDate)) {
            $error = "Renewal date cannot be earlier than start date.";
        } else {
            // Insert new service agreement
            $stmt = $conn->prepare("INSERT INTO service_agreements 
                (title, description, business_id, status, agreement_type, start_date, end_date, renewal_date, price, billing_cycle, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssisssssdsi', $title, $description, $selectedBusinessId, $status, $agreementType, $startDate, $endDate, $renewalDate, $price, $billingCycle, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $agreementId = $conn->insert_id;
                $success = "Service Agreement added successfully.";
                
                // Redirect to the new service agreement page
                header("Location: view.php?id=" . $agreementId . "&success=created");
                exit;
            } else {
                $error = "Error adding service agreement: " . $conn->error;
            }
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><?php echo $businessId ? "Add Service Agreement for " . $businessName : "Add New Service Agreement"; ?></h2>
        <div class="card-header-actions">
            <a href="index.php<?php echo $businessId ? '?business_id=' . $businessId : ''; ?>" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Service Agreements
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
                        <label for="title">Agreement Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="business_id">Business <span class="required">*</span></label>
                        <select id="business_id" name="business_id" class="form-control" required>
                            <option value="">-- Select Business --</option>
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
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="active" selected>Active</option>
                            <option value="pending">Pending</option>
                            <option value="expired">Expired</option>
                            <option value="canceled">Canceled</option>
                            <option value="pending_renewal">Pending Renewal</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="agreement_type">Agreement Type <span class="required">*</span></label>
                        <select id="agreement_type" name="agreement_type" class="form-control" required>
                            <option value="standard" selected>Standard</option>
                            <option value="premium">Premium</option>
                            <option value="custom">Custom</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="support">Support</option>
                            <option value="hosting">Hosting</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="start_date">Start Date <span class="required">*</span></label>
                        <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control">
                        <span class="form-hint">Leave empty for ongoing agreements</span>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="renewal_date">Renewal Date</label>
                        <input type="date" id="renewal_date" name="renewal_date" class="form-control">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="price">Price (<?php echo getSetting('currency_symbol', 'NOK'); ?>) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" class="form-control" step="0.01" min="0.01" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="billing_cycle">Billing Cycle <span class="required">*</span></label>
                <select id="billing_cycle" name="billing_cycle" class="form-control" required>
                    <option value="monthly" selected>Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="biannually">Biannually</option>
                    <option value="annually">Annually</option>
                    <option value="one-time">One-time</option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="reset" class="btn btn-text">Reset</button>
                <button type="submit" class="btn btn-primary">Create Service Agreement</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set default renewal date based on billing cycle and start date
        const startDateField = document.getElementById('start_date');
        const endDateField = document.getElementById('end_date');
        const renewalDateField = document.getElementById('renewal_date');
        const billingCycleSelect = document.getElementById('billing_cycle');
        
        const updateRenewalDate = function() {
            if (startDateField.value) {
                const startDate = new Date(startDateField.value);
                const billingCycle = billingCycleSelect.value;
                let renewalDate = new Date(startDate);
                
                switch (billingCycle) {
                    case 'monthly':
                        renewalDate.setMonth(renewalDate.getMonth() + 1);
                        break;
                    case 'quarterly':
                        renewalDate.setMonth(renewalDate.getMonth() + 3);
                        break;
                    case 'biannually':
                        renewalDate.setMonth(renewalDate.getMonth() + 6);
                        break;
                    case 'annually':
                        renewalDate.setFullYear(renewalDate.getFullYear() + 1);
                        break;
                    case 'one-time':
                        renewalDateField.value = ''; // No renewal for one-time
                        return; // Exit the function
                }
                
                const year = renewalDate.getFullYear();
                const month = String(renewalDate.getMonth() + 1).padStart(2, '0');
                const day = String(renewalDate.getDate()).padStart(2, '0');
                renewalDateField.value = `${year}-${month}-${day}`;
            }
        };
        
        // Set default end date (1 year from start)
        const updateEndDate = function() {
            if (startDateField.value) {
                const startDate = new Date(startDateField.value);
                const billingCycle = billingCycleSelect.value;
                
                // For one-time agreements, set end date same as start date
                if (billingCycle === 'one-time') {
                    endDateField.value = startDateField.value;
                    return;
                }
                
                // Otherwise set to 1 year later by default
                let endDate = new Date(startDate);
                endDate.setFullYear(endDate.getFullYear() + 1);
                
                const year = endDate.getFullYear();
                const month = String(endDate.getMonth() + 1).padStart(2, '0');
                const day = String(endDate.getDate()).padStart(2, '0');
                endDateField.value = `${year}-${month}-${day}`;
            }
        };
        
        // Initial setup
        updateEndDate();
        updateRenewalDate();
        
        // Update dates when inputs change
        startDateField.addEventListener('change', function() {
            updateEndDate();
            updateRenewalDate();
        });
        
        billingCycleSelect.addEventListener('change', function() {
            updateRenewalDate();
            
            // Adjust end date for one-time agreements
            if (this.value === 'one-time' && startDateField.value) {
                endDateField.value = startDateField.value;
            } else if (endDateField.value === startDateField.value) {
                // If changing from one-time, update end date
                updateEndDate();
            }
        });
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>