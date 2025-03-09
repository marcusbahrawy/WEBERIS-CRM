<?php
// modules/service_agreements/renew.php - Renew an existing service agreement
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
$pageTitle = "Renew Service Agreement";

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

// Calculate renewal details
$today = new DateTime();
$startDate = new DateTime($agreement['start_date']);
$endDate = $agreement['end_date'] ? new DateTime($agreement['end_date']) : null;
$renewalDate = $agreement['renewal_date'] ? new DateTime($agreement['renewal_date']) : null;

$isExpired = $endDate && $today > $endDate;
$isPendingRenewal = $renewalDate && $today >= $renewalDate;

// Calculate suggested new dates
$newStartDate = new DateTime();
$newEndDate = null;
$newRenewalDate = null;

// Calculate new end date based on billing cycle
if ($agreement['billing_cycle'] !== 'one-time') {
    $newEndDate = clone $newStartDate;
    switch ($agreement['billing_cycle']) {
        case 'monthly':
            $newEndDate->modify('+1 month');
            break;
        case 'quarterly':
            $newEndDate->modify('+3 months');
            break;
        case 'biannually':
            $newEndDate->modify('+6 months');
            break;
        case 'annually':
        default:
            $newEndDate->modify('+1 year');
            break;
    }
    
    // Calculate new renewal date (usually same as end date)
    $newRenewalDate = clone $newEndDate;
} else {
    // For one-time agreements, end date is same as start date
    $newEndDate = clone $newStartDate;
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
        $newStatus = sanitizeInput($_POST['new_status']);
        $newStartDate = sanitizeInput($_POST['new_start_date']);
        $newEndDate = !empty($_POST['new_end_date']) ? sanitizeInput($_POST['new_end_date']) : null;
        $newRenewalDate = !empty($_POST['new_renewal_date']) ? sanitizeInput($_POST['new_renewal_date']) : null;
        $newPrice = !empty($_POST['new_price']) ? (float)$_POST['new_price'] : $agreement['price'];
        
        // Validate required fields
        if (empty($newStartDate)) {
            $error = "New start date is required.";
        } elseif (!empty($newEndDate) && !empty($newStartDate) && strtotime($newEndDate) < strtotime($newStartDate)) {
            $error = "New end date cannot be earlier than new start date.";
        } elseif (!empty($newRenewalDate) && !empty($newStartDate) && strtotime($newRenewalDate) < strtotime($newStartDate)) {
            $error = "New renewal date cannot be earlier than new start date.";
        } elseif ($newPrice <= 0) {
            $error = "Price must be greater than zero.";
        } else {
            // Update service agreement
            $stmt = $conn->prepare("UPDATE service_agreements SET 
                status = ?, 
                start_date = ?, 
                end_date = ?, 
                renewal_date = ?, 
                price = ? 
                WHERE id = ?");
            $stmt->bind_param('ssssdi', $newStatus, $newStartDate, $newEndDate, $newRenewalDate, $newPrice, $agreementId);
            
            if ($stmt->execute()) {
                $success = "Service Agreement renewed successfully.";
                
                // Redirect to the updated service agreement page
                header("Location: view.php?id=" . $agreementId . "&success=renewed");
                exit;
            } else {
                $error = "Error renewing service agreement: " . $conn->error;
            }
        }
    }
}

// Format dates for form
$formattedNewStartDate = $newStartDate->format('Y-m-d');
$formattedNewEndDate = $newEndDate ? $newEndDate->format('Y-m-d') : '';
$formattedNewRenewalDate = $newRenewalDate ? $newRenewalDate->format('Y-m-d') : '';

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Renew Service Agreement</h2>
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
        
        <div class="renewal-info mb-xl">
            <h3>Current Agreement Details</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Title</div>
                    <div class="detail-value"><?php echo $agreement['title']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <span class="status-badge status-<?php echo $agreement['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $agreement['status'])); ?>
                        </span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Start Date</div>
                    <div class="detail-value"><?php echo date('M j, Y', strtotime($agreement['start_date'])); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">End Date</div>
                    <div class="detail-value">
                        <?php echo $agreement['end_date'] ? date('M j, Y', strtotime($agreement['end_date'])) : 'Ongoing'; ?>
                        <?php if ($isExpired): ?>
                            <span class="text-danger">(Expired)</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Renewal Date</div>
                    <div class="detail-value">
                        <?php echo $agreement['renewal_date'] ? date('M j, Y', strtotime($agreement['renewal_date'])) : 'N/A'; ?>
                        <?php if ($isPendingRenewal && !$isExpired): ?>
                            <span class="text-warning">(Due for renewal)</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Current Price</div>
                    <div class="detail-value"><?php echo '' . formatCurrency($agreement['price']); ?> / <?php echo ucfirst($agreement['billing_cycle']); ?></div>
                </div>
            </div>
        </div>
        
        <form method="POST" action="" data-validate="true">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <h3>Renewal Details</h3>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="new_status">New Status <span class="required">*</span></label>
                        <select id="new_status" name="new_status" class="form-control" required>
                            <option value="active" selected>Active</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="new_price">New Price (<?php echo getSetting('currency_symbol', 'NOK'); ?>) <span class="required">*</span></label>
                        <input type="number" id="new_price" name="new_price" class="form-control" step="0.01" min="0.01" value="<?php echo number_format((float)$agreement['price'], 2, '.', ''); ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="new_start_date">New Start Date <span class="required">*</span></label>
                        <input type="date" id="new_start_date" name="new_start_date" class="form-control" value="<?php echo $formattedNewStartDate; ?>" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="new_end_date">New End Date</label>
                        <input type="date" id="new_end_date" name="new_end_date" class="form-control" value="<?php echo $formattedNewEndDate; ?>">
                        <span class="form-hint">Leave empty for ongoing agreements</span>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="new_renewal_date">New Renewal Date</label>
                <input type="date" id="new_renewal_date" name="new_renewal_date" class="form-control" value="<?php echo $formattedNewRenewalDate; ?>">
                <span class="form-hint">When should this agreement be reviewed for renewal again?</span>
            </div>
            
            <div class="form-actions">
                <a href="view.php?id=<?php echo $agreementId; ?>" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-success">Renew Agreement</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Renewal specific styles */
.renewal-info {
    background-color: var(--grey-50);
    border-radius: var(--border-radius-md);
    padding: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.renewal-info h3 {
    margin-top: 0;
    margin-bottom: var(--spacing-md);
    color: var(--grey-700);
    font-size: var(--font-size-lg);
    border-bottom: 1px solid var(--grey-200);
    padding-bottom: var(--spacing-sm);
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Calculate suggested dates based on billing cycle
        const updateDates = function() {
            const startDate = new Date(document.getElementById('new_start_date').value);
            if (!startDate || isNaN(startDate.getTime())) return;
            
            const billingCycle = "<?php echo $agreement['billing_cycle']; ?>";
            let endDate = new Date(startDate);
            
            // Calculate end date based on billing cycle
            switch (billingCycle) {
                case 'monthly':
                    endDate.setMonth(endDate.getMonth() + 1);
                    break;
                case 'quarterly':
                    endDate.setMonth(endDate.getMonth() + 3);
                    break;
                case 'biannually':
                    endDate.setMonth(endDate.getMonth() + 6);
                    break;
                case 'annually':
                    endDate.setFullYear(endDate.getFullYear() + 1);
                    break;
                case 'one-time':
                    // For one-time, end date is same as start date
                    document.getElementById('new_end_date').value = document.getElementById('new_start_date').value;
                    document.getElementById('new_renewal_date').value = '';
                    return;
            }
            
            // Format and set the end date
            const year = endDate.getFullYear();
            const month = String(endDate.getMonth() + 1).padStart(2, '0');
            const day = String(endDate.getDate()).padStart(2, '0');
            document.getElementById('new_end_date').value = `${year}-${month}-${day}`;
            
            // Set renewal date to same as end date
            document.getElementById('new_renewal_date').value = `${year}-${month}-${day}`;
        };
        
        // Update dates when start date changes
        document.getElementById('new_start_date').addEventListener('change', updateDates);
        
        // Add helper buttons
        const addButtonAfter = function(element, text, clickHandler) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-text mt-sm';
            button.textContent = text;
            button.addEventListener('click', clickHandler);
            element.parentNode.appendChild(button);
            return button;
        };
        
        // Add recalculate button for dates
        addButtonAfter(
            document.getElementById('new_start_date'),
            'Calculate suggested dates based on billing cycle',
            updateDates
        );
        
        // Add button to keep current price
        const priceField = document.getElementById('new_price');
        const currentPrice = <?php echo $agreement['price']; ?>;
        if (priceField.value != currentPrice) {
            addButtonAfter(
                priceField,
                'Reset to current price',
                function() { priceField.value = currentPrice.toFixed(2); }
            );
        }
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>