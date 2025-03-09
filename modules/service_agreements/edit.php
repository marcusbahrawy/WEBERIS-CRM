<?php
// modules/service_agreements/edit.php - Edit an existing service agreement
require_once '../../config.php';

// Check permissions
if (!checkPermission('edit_service_agreement')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$agreementId = (int)$_GET['id'];

// Page title
$pageTitle = "Edit Service Agreement";

// Database connection
$conn = connectDB();

// Get service agreement data
$stmt = $conn->prepare("SELECT * FROM service_agreements WHERE id = ?");
$stmt->bind_param('i', $agreementId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$agreement = $result->fetch_assoc();

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
        $businessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        
        // Validate required fields
        if (empty($title)) {
            $error = "Agreement title is required.";
        } elseif (empty($startDate)) {
            $error = "Start date is required.";
        } elseif (!$businessId) {
            $error = "Business is required.";
        } elseif ($price <= 0) {
            $error = "Price must be greater than zero.";
        } elseif (!empty($endDate) && !empty($startDate) && strtotime($endDate) < strtotime($startDate)) {
            $error = "End date cannot be earlier than start date.";
        } elseif (!empty($renewalDate) && !empty($startDate) && strtotime($renewalDate) < strtotime($startDate)) {
            $error = "Renewal date cannot be earlier than start date.";
        } else {
            // Update service agreement
            $stmt = $conn->prepare("UPDATE service_agreements SET 
                title = ?, 
                description = ?, 
                business_id = ?, 
                status = ?, 
                agreement_type = ?, 
                start_date = ?, 
                end_date = ?, 
                renewal_date = ?, 
                price = ?, 
                billing_cycle = ? 
                WHERE id = ?");
            $stmt->bind_param('ssisssssdsi', $title, $description, $businessId, $status, $agreementType, $startDate, $endDate, $renewalDate, $price, $billingCycle, $agreementId);
            
            if ($stmt->execute()) {
                $success = "Service Agreement updated successfully.";
                
                // Refresh agreement data
                $stmt = $conn->prepare("SELECT * FROM service_agreements WHERE id = ?");
                $stmt->bind_param('i', $agreementId);
                $stmt->execute();
                $result = $stmt->get_result();
                $agreement = $result->fetch_assoc();
            } else {
                $error = "Error updating service agreement: " . $conn->error;
            }
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Edit Service Agreement</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $agreementId; ?>" class="btn btn-text">
                <span class="material-icons">visibility</span> View Agreement
            </a>
            <a href="index.php" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Agreements
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
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo $agreement['title']; ?>" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="business_id">Business <span class="required">*</span></label>
                        <select id="business_id" name="business_id" class="form-control" required>
                            <option value="">-- Select Business --</option>
                            <?php foreach ($businesses as $business): ?>
                                <option value="<?php echo $business['id']; ?>" <?php echo ($agreement['business_id'] == $business['id']) ? 'selected' : ''; ?>>
                                    <?php echo $business['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4"><?php echo $agreement['description']; ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="active" <?php echo ($agreement['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo ($agreement['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="expired" <?php echo ($agreement['status'] === 'expired') ? 'selected' : ''; ?>>Expired</option>
                            <option value="canceled" <?php echo ($agreement['status'] === 'canceled') ? 'selected' : ''; ?>>Canceled</option>
                            <option value="pending_renewal" <?php echo ($agreement['status'] === 'pending_renewal') ? 'selected' : ''; ?>>Pending Renewal</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="agreement_type">Agreement Type <span class="required">*</span></label>
                        <select id="agreement_type" name="agreement_type" class="form-control" required>
                            <option value="standard" <?php echo ($agreement['agreement_type'] === 'standard') ? 'selected' : ''; ?>>Standard</option>
                            <option value="premium" <?php echo ($agreement['agreement_type'] === 'premium') ? 'selected' : ''; ?>>Premium</option>
                            <option value="custom" <?php echo ($agreement['agreement_type'] === 'custom') ? 'selected' : ''; ?>>Custom</option>
                            <option value="maintenance" <?php echo ($agreement['agreement_type'] === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="support" <?php echo ($agreement['agreement_type'] === 'support') ? 'selected' : ''; ?>>Support</option>
                            <option value="hosting" <?php echo ($agreement['agreement_type'] === 'hosting') ? 'selected' : ''; ?>>Hosting</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="start_date">Start Date <span class="required">*</span></label>
                        <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $agreement['start_date']; ?>" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $agreement['end_date']; ?>">
                        <span class="form-hint">Leave empty for ongoing agreements</span>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="renewal_date">Renewal Date</label>
                        <input type="date" id="renewal_date" name="renewal_date" class="form-control" value="<?php echo $agreement['renewal_date']; ?>">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="price">Price (<?php echo getSetting('currency_symbol', 'NOK'); ?>) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" class="form-control" step="0.01" min="0.01" value="<?php echo number_format((float)$agreement['price'], 2, '.', ''); ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="billing_cycle">Billing Cycle <span class="required">*</span></label>
                <select id="billing_cycle" name="billing_cycle" class="form-control" required>
                    <option value="monthly" <?php echo ($agreement['billing_cycle'] === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                    <option value="quarterly" <?php echo ($agreement['billing_cycle'] === 'quarterly') ? 'selected' : ''; ?>>Quarterly</option>
                    <option value="biannually" <?php echo ($agreement['billing_cycle'] === 'biannually') ? 'selected' : ''; ?>>Biannually</option>
                    <option value="annually" <?php echo ($agreement['billing_cycle'] === 'annually') ? 'selected' : ''; ?>>Annually</option>
                    <option value="one-time" <?php echo ($agreement['billing_cycle'] === 'one-time') ? 'selected' : ''; ?>>One-time</option>
                </select>
            </div>
            
            <div class="form-actions">
                <a href="view.php?id=<?php echo $agreementId; ?>" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Service Agreement</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Validate dates when form is submitted
        const form = document.querySelector('form');
        const startDateField = document.getElementById('start_date');
        const endDateField = document.getElementById('end_date');
        const renewalDateField = document.getElementById('renewal_date');
        const billingCycleSelect = document.getElementById('billing_cycle');
        
        form.addEventListener('submit', function(e) {
            // Validate end date is after start date
            if (endDateField.value && startDateField.value) {
                const startDate = new Date(startDateField.value);
                const endDate = new Date(endDateField.value);
                
                if (endDate < startDate) {
                    e.preventDefault();
                    alert('End date cannot be earlier than start date');
                    endDateField.focus();
                    return false;
                }
            }
            
            // Validate renewal date is after start date
            if (renewalDateField.value && startDateField.value) {
                const startDate = new Date(startDateField.value);
                const renewalDate = new Date(renewalDateField.value);
                
                if (renewalDate < startDate) {
                    e.preventDefault();
                    alert('Renewal date cannot be earlier than start date');
                    renewalDateField.focus();
                    return false;
                }
            }
            
            // Handle one-time billing cycle special cases
            if (billingCycleSelect.value === 'one-time') {
                // For one-time agreements, if end date is empty, set it to same as start date
                if (!endDateField.value) {
                    endDateField.value = startDateField.value;
                }
                
                // For one-time agreements, renewal date should be empty
                if (renewalDateField.value) {
                    renewalDateField.value = '';
                }
            }
        });
        
        // Update fields when billing cycle changes
        billingCycleSelect.addEventListener('change', function() {
            if (this.value === 'one-time') {
                // Suggest setting end date to match start date for one-time agreements
                if (confirm('For one-time agreements, would you like to set the end date to match the start date?')) {
                    endDateField.value = startDateField.value;
                }
                
                // Clear renewal date for one-time agreements
                renewalDateField.value = '';
            }
        });
        
        // Helper function to calculate suggested renewal date
        const calculateRenewalDate = function() {
            const startDate = new Date(startDateField.value);
            if (!startDate || isNaN(startDate.getTime())) return null;
            
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
                    return null; // No renewal for one-time
            }
            
            return renewalDate;
        };
        
        // Add button to recalculate renewal date
        const renewalDateContainer = renewalDateField.parentElement;
        const recalculateButton = document.createElement('button');
        recalculateButton.type = 'button';
        recalculateButton.className = 'btn btn-text mt-sm';
        recalculateButton.innerHTML = '<span class="material-icons">refresh</span> Recalculate based on billing cycle';
        recalculateButton.addEventListener('click', function() {
            const newRenewalDate = calculateRenewalDate();
            if (newRenewalDate) {
                const year = newRenewalDate.getFullYear();
                const month = String(newRenewalDate.getMonth() + 1).padStart(2, '0');
                const day = String(newRenewalDate.getDate()).padStart(2, '0');
                renewalDateField.value = `${year}-${month}-${day}`;
            } else {
                renewalDateField.value = '';
            }
        });
        
        // Only show recalculate button if not one-time billing
        if (billingCycleSelect.value !== 'one-time') {
            renewalDateContainer.appendChild(recalculateButton);
        }
        
        // Show/hide recalculate button based on billing cycle
        billingCycleSelect.addEventListener('change', function() {
            if (this.value === 'one-time') {
                recalculateButton.style.display = 'none';
            } else {
                recalculateButton.style.display = 'inline-flex';
            }
        });
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>