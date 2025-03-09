<?php
// modules/service_agreements/view.php - View service agreement details
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_service_agreement')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$agreementId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Get service agreement data with related information
$stmt = $conn->prepare("SELECT sa.*, 
                      b.name as business_name,
                      u.name as created_by_name
                      FROM service_agreements sa
                      LEFT JOIN businesses b ON sa.business_id = b.id
                      LEFT JOIN users u ON sa.created_by = u.id
                      WHERE sa.id = ?");
$stmt->bind_param('i', $agreementId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$agreement = $result->fetch_assoc();

// Get agreement type label
$agreementTypeLabel = ucfirst(str_replace('_', ' ', $agreement['agreement_type']));
$stmt = $conn->prepare("SELECT label FROM agreement_types WHERE name = ?");
$stmt->bind_param('s', $agreement['agreement_type']);
$stmt->execute();
$typeResult = $stmt->get_result();
if ($typeResult->num_rows > 0) {
    $agreementTypeLabel = $typeResult->fetch_assoc()['label'];
}

// Calculate next invoice date and status information
$today = new DateTime();
$startDate = new DateTime($agreement['start_date']);
$endDate = $agreement['end_date'] ? new DateTime($agreement['end_date']) : null;
$renewalDate = $agreement['renewal_date'] ? new DateTime($agreement['renewal_date']) : null;

$isExpired = $endDate && $today > $endDate;
$isActive = $agreement['status'] === 'active';
$isPendingRenewal = $renewalDate && $today >= $renewalDate;
$isCanceled = $agreement['status'] === 'canceled';

$nextInvoiceDate = null;
if ($isActive && !$isExpired && !$isCanceled) {
    $nextInvoiceDate = clone $startDate;
    $billingCycle = $agreement['billing_cycle'];
    
    // Calculate next invoice date based on billing cycle
    switch ($billingCycle) {
        case 'monthly':
            $interval = 'P1M'; // 1 month
            break;
        case 'quarterly':
            $interval = 'P3M'; // 3 months
            break;
        case 'biannually':
            $interval = 'P6M'; // 6 months
            break;
        case 'annually':
            $interval = 'P1Y'; // 1 year
            break;
        case 'one-time':
            $nextInvoiceDate = null;
            break;
        default:
            $interval = 'P1M'; // Default to monthly
    }
    
    if ($nextInvoiceDate && $interval) {
        // Find the next invoice date after today
        while ($nextInvoiceDate <= $today) {
            $nextInvoiceDate->add(new DateInterval($interval));
        }
    }
}

// Page title
$pageTitle = $agreement['title'];
$pageActions = '';

// Check edit permission
if (checkPermission('edit_service_agreement')) {
    $pageActions .= '<a href="edit.php?id=' . $agreementId . '" class="btn btn-primary"><span class="material-icons">edit</span> Edit</a>';
}

// Check delete permission
if (checkPermission('delete_service_agreement')) {
    $pageActions .= '<a href="delete.php?id=' . $agreementId . '" class="btn btn-danger delete-item" data-confirm="Are you sure you want to delete this service agreement?"><span class="material-icons">delete</span> Delete</a>';
}

// Generate renew button if pending renewal or expired
if (($isPendingRenewal || $isExpired) && checkPermission('edit_service_agreement')) {
    $pageActions .= '<a href="renew.php?id=' . $agreementId . '" class="btn btn-success"><span class="material-icons">autorenew</span> Renew Agreement</a>';
}

// Include header
include '../../includes/header.php';

// Check for success message
$successMessage = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $successMessage = 'Service Agreement created successfully.';
            break;
        case 'updated':
            $successMessage = 'Service Agreement updated successfully.';
            break;
        case 'renewed':
            $successMessage = 'Service Agreement renewed successfully.';
            break;
    }
}
?>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success"><?php echo $successMessage; ?></div>
<?php endif; ?>

<div class="service-agreement-details">
    <div class="card">
        <div class="card-header">
            <h2>Service Agreement Details</h2>
            <div class="card-header-actions">
                <a href="index.php" class="btn btn-text">
                    <span class="material-icons">arrow_back</span> Back to Agreements
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <div class="agreement-header mb-xl">
                <h2 class="agreement-title"><?php echo $agreement['title']; ?></h2>
                <div class="agreement-status">
                    <span class="status-badge status-<?php echo $agreement['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $agreement['status'])); ?>
                    </span>
                    
                    <?php if ($isExpired): ?>
                        <span class="status-badge status-expired">
                            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">warning</span>
                            Expired
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($isPendingRenewal && !$isExpired): ?>
                        <span class="status-badge status-pending_renewal">
                            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">autorenew</span>
                            Pending Renewal
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($isActive && !$isExpired && !$isCanceled): ?>
                <div class="agreement-info-box mb-xl">
                    <div class="info-header">
                        <span class="material-icons">info</span> Agreement Information
                    </div>
                    <div class="info-content">
                        <div class="info-row">
                            <div class="info-label">Agreement Type:</div>
                            <div class="info-value"><?php echo $agreementTypeLabel; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Price:</div>
                            <div class="info-value"><?php echo '' . formatCurrency($agreement['price']); ?> / <?php echo ucfirst($agreement['billing_cycle']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Next Invoice Date:</div>
                            <div class="info-value">
                                <?php if ($nextInvoiceDate): ?>
                                    <?php echo $nextInvoiceDate->format('M j, Y'); ?>
                                    <?php 
                                    $daysUntilInvoice = $today->diff($nextInvoiceDate)->days;
                                    if ($daysUntilInvoice <= 14) {
                                        echo ' <span class="text-warning">(' . $daysUntilInvoice . ' days)</span>';
                                    } else {
                                        echo ' (' . $daysUntilInvoice . ' days)';
                                    }
                                    ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($renewalDate): ?>
                        <div class="info-row">
                            <div class="info-label">Renewal Date:</div>
                            <div class="info-value">
                                <?php echo $renewalDate->format('M j, Y'); ?>
                                <?php 
                                $daysUntilRenewal = $today->diff($renewalDate)->days;
                                if ($renewalDate < $today) {
                                    echo ' <span class="text-danger">(Overdue)</span>';
                                } elseif ($daysUntilRenewal <= 30) {
                                    echo ' <span class="text-warning">(' . $daysUntilRenewal . ' days left)</span>';
                                } else {
                                    echo ' (' . $daysUntilRenewal . ' days left)';
                                }
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="detail-grid">
                <div class="detail-item full-width">
                    <div class="detail-label">Description</div>
                    <div class="detail-value"><?php echo nl2br($agreement['description'] ?? 'N/A'); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Business</div>
                    <div class="detail-value">
                        <?php if ($agreement['business_id']): ?>
                            <a href="../businesses/view.php?id=<?php echo $agreement['business_id']; ?>">
                                <?php echo $agreement['business_name']; ?>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Agreement Type</div>
                    <div class="detail-value"><?php echo $agreementTypeLabel; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Billing</div>
                    <div class="detail-value"><?php echo '' . formatCurrency($agreement['price']); ?> / <?php echo ucfirst($agreement['billing_cycle']); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Start Date</div>
                    <div class="detail-value"><?php echo date('M j, Y', strtotime($agreement['start_date'])); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">End Date</div>
                    <div class="detail-value">
                        <?php if ($agreement['end_date']): ?>
                            <?php 
                            $endDate = new DateTime($agreement['end_date']);
                            $isExpired = $endDate < $today;
                            ?>
                            <span <?php echo $isExpired ? 'class="text-danger"' : ''; ?>>
                                <?php echo date('M j, Y', strtotime($agreement['end_date'])); ?>
                                <?php if ($isExpired): ?>
                                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">warning</span> Expired
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            Ongoing
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Renewal Date</div>
                    <div class="detail-value">
                        <?php if ($agreement['renewal_date']): ?>
                            <?php 
                            $renewalDate = new DateTime($agreement['renewal_date']);
                            $isPastRenewal = $renewalDate < $today;
                            $isRenewalSoon = $renewalDate > $today && $today->diff($renewalDate)->days <= 30;
                            ?>
                            <span class="<?php echo $isPastRenewal ? 'text-danger' : ($isRenewalSoon ? 'text-warning' : ''); ?>">
                                <?php echo date('M j, Y', strtotime($agreement['renewal_date'])); ?>
                                <?php if ($isPastRenewal): ?>
                                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">warning</span> Overdue
                                <?php elseif ($isRenewalSoon): ?>
                                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">schedule</span> Coming soon
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created By</div>
                    <div class="detail-value"><?php echo $agreement['created_by_name']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created On</div>
                    <div class="detail-value"><?php echo date('M j, Y, g:i a', strtotime($agreement['created_at'])); ?></div>
                </div>
            </div>
            
            <div class="form-actions mt-xl">
                <?php if (checkPermission('edit_service_agreement')): ?>
                    <a href="edit.php?id=<?php echo $agreementId; ?>" class="btn btn-primary">
                        <span class="material-icons">edit</span> Edit Agreement
                    </a>
                <?php endif; ?>
                
                <?php if (($isPendingRenewal || $isExpired) && checkPermission('edit_service_agreement')): ?>
                    <a href="renew.php?id=<?php echo $agreementId; ?>" class="btn btn-success">
                        <span class="material-icons">autorenew</span> Renew Agreement
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Service Agreement specific styles */
.agreement-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--grey-200);
    padding-bottom: var(--spacing-md);
}

.agreement-title {
    font-size: var(--font-size-2xl);
    margin: 0;
}

.agreement-info-box {
    background-color: var(--grey-50);
    border-radius: var(--border-radius-md);
    border-left: 4px solid var(--primary-color);
    padding: var(--spacing-md);
}

.info-header {
    font-weight: var(--font-weight-semibold);
    display: flex;
    align-items: center;
    margin-bottom: var(--spacing-sm);
    color: var(--primary-color);
}

.info-header .material-icons {
    margin-right: var(--spacing-xs);
}

.info-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-sm);
}

.info-row {
    display: flex;
    flex-direction: column;
    margin-bottom: var(--spacing-xs);
}

.info-label {
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
}

.info-value {
    font-weight: var(--font-weight-medium);
}

.status-active {
    background-color: rgba(46, 196, 182, 0.15);
    color: #0d6962;
}

.status-pending {
    background-color: rgba(251, 189, 35, 0.15);
    color: #946000;
}

.status-expired, 
.status-canceled {
    background-color: rgba(230, 57, 70, 0.15);
    color: #a61a24;
}

.status-pending_renewal {
    background-color: rgba(67, 97, 238, 0.15);
    color: var(--primary-dark);
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Setup delete confirmation
        const deleteLinks = document.querySelectorAll('.delete-item');
        deleteLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm(this.dataset.confirm)) {
                    e.preventDefault();
                }
            });
        });
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>