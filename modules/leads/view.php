<?php
// modules/leads/view.php - View lead details
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_lead')) {
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

// Get lead data with related information
$stmt = $conn->prepare("SELECT l.*, 
                      b.name as business_name, 
                      CONCAT(c.first_name, ' ', c.last_name) as contact_name,
                      c.email as contact_email,
                      c.phone as contact_phone,
                      u1.name as assigned_to_name,
                      u2.name as created_by_name
                      FROM leads l
                      LEFT JOIN businesses b ON l.business_id = b.id
                      LEFT JOIN contacts c ON l.contact_id = c.id
                      LEFT JOIN users u1 ON l.assigned_to = u1.id
                      LEFT JOIN users u2 ON l.created_by = u2.id
                      WHERE l.id = ?");
$stmt->bind_param('i', $leadId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$lead = $result->fetch_assoc();

// Get related offers
$stmt = $conn->prepare("SELECT id, title, amount, status, valid_until
                       FROM offers
                       WHERE lead_id = ?
                       ORDER BY created_at DESC");
$stmt->bind_param('i', $leadId);
$stmt->execute();
$offersResult = $stmt->get_result();
$offers = [];
while ($offer = $offersResult->fetch_assoc()) {
    $offers[] = $offer;
}

// Page title
$pageTitle = $lead['title'];
$pageActions = '';

// Check edit permission
if (checkPermission('edit_lead')) {
    $pageActions .= '<a href="edit.php?id=' . $leadId . '" class="btn btn-primary"><span class="material-icons">edit</span> Edit</a>';
}

// Check delete permission
if (checkPermission('delete_lead')) {
    $pageActions .= '<a href="delete.php?id=' . $leadId . '" class="btn btn-danger delete-item" data-confirm="Are you sure you want to delete this lead?"><span class="material-icons">delete</span> Delete</a>';
}

// Add offer button if user has permission
if (checkPermission('add_offer')) {
    $pageActions .= '<a href="../offers/add.php?lead_id=' . $leadId . '" class="btn btn-secondary"><span class="material-icons">description</span> Create Offer</a>';
}

// Include header
include '../../includes/header.php';

// Check for success message
$successMessage = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $successMessage = 'Lead created successfully.';
            break;
        case 'updated':
            $successMessage = 'Lead updated successfully.';
            break;
    }
}
?>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success"><?php echo $successMessage; ?></div>
<?php endif; ?>

<div class="lead-details">
    <div class="card">
        <div class="card-header">
            <h2>Lead Information</h2>
            <div class="card-header-actions">
                <a href="index.php" class="btn btn-text">
                    <span class="material-icons">arrow_back</span> Back to Leads
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item full-width">
                    <div class="detail-label">Title</div>
                    <div class="detail-value"><?php echo $lead['title']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <span class="status-badge status-<?php echo $lead['status']; ?>"><?php echo $lead['status']; ?></span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Value</div>
                    <div class="detail-value"><?php echo '' . formatCurrency($lead['value'], 2); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Source</div>
                    <div class="detail-value"><?php echo $lead['source'] ?? 'N/A'; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created Date</div>
                    <div class="detail-value"><?php echo date('M j, Y, g:i a', strtotime($lead['created_at'])); ?></div>
                </div>
                
                <div class="detail-item full-width">
                    <div class="detail-label">Description</div>
                    <div class="detail-value"><?php echo nl2br($lead['description'] ?? 'N/A'); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Business</div>
                    <div class="detail-value">
                        <?php if ($lead['business_id']): ?>
                            <a href="../businesses/view.php?id=<?php echo $lead['business_id']; ?>">
                                <?php echo $lead['business_name']; ?>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Contact</div>
                    <div class="detail-value">
                        <?php if ($lead['contact_id']): ?>
                            <a href="../contacts/view.php?id=<?php echo $lead['contact_id']; ?>">
                                <?php echo $lead['contact_name']; ?>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($lead['contact_email'])): ?>
                <div class="detail-item">
                    <div class="detail-label">Contact Email</div>
                    <div class="detail-value">
                        <a href="mailto:<?php echo $lead['contact_email']; ?>"><?php echo $lead['contact_email']; ?></a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($lead['contact_phone'])): ?>
                <div class="detail-item">
                    <div class="detail-label">Contact Phone</div>
                    <div class="detail-value">
                        <a href="tel:<?php echo $lead['contact_phone']; ?>"><?php echo $lead['contact_phone']; ?></a>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="detail-item">
                    <div class="detail-label">Assigned To</div>
                    <div class="detail-value"><?php echo $lead['assigned_to_name'] ?? 'Unassigned'; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created By</div>
                    <div class="detail-value"><?php echo $lead['created_by_name']; ?></div>
                </div>
            </div>
            
            <div class="form-actions mt-xl">
                <?php if (checkPermission('edit_lead')): ?>
                    <a href="edit.php?id=<?php echo $leadId; ?>" class="btn btn-primary">
                        <span class="material-icons">edit</span> Edit Lead
                    </a>
                <?php endif; ?>
                
                <?php if (checkPermission('add_offer') && $lead['status'] != 'lost'): ?>
                    <a href="../offers/add.php?lead_id=<?php echo $leadId; ?>" class="btn btn-secondary">
                        <span class="material-icons">description</span> Create Offer
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Offers Section -->
    <div class="card">
        <div class="card-header">
            <h2>Offers</h2>
            <?php if (checkPermission('add_offer') && $lead['status'] != 'lost'): ?>
                <div class="card-header-actions">
                    <a href="../offers/add.php?lead_id=<?php echo $leadId; ?>" class="btn btn-primary">
                        <span class="material-icons">add</span> Add Offer
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card-body">
            <?php if (count($offers) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Valid Until</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($offers as $offer): ?>
                                <tr>
                                    <td><?php echo $offer['title']; ?></td>
                                    <td><?php echo '' . formatCurrency($offer['amount'], 2); ?></td>
                                    <td><span class="status-badge status-<?php echo $offer['status']; ?>"><?php echo $offer['status']; ?></span></td>
                                    <td><?php echo $offer['valid_until'] ? date('M j, Y', strtotime($offer['valid_until'])) : 'N/A'; ?></td>
                                    <td class="actions-cell">
                                        <a href="../offers/view.php?id=<?php echo $offer['id']; ?>" class="btn btn-icon btn-text" title="View Details">
                                            <span class="material-icons">visibility</span>
                                        </a>
                                        
                                        <?php if (checkPermission('edit_offer')): ?>
                                            <a href="../offers/edit.php?id=<?php echo $offer['id']; ?>" class="btn btn-icon btn-text" title="Edit">
                                                <span class="material-icons">edit</span>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (checkPermission('add_project') && $offer['status'] === 'accepted'): ?>
                                            <a href="../projects/add.php?offer_id=<?php echo $offer['id']; ?>" class="btn btn-icon btn-text" title="Create Project">
                                                <span class="material-icons">assignment</span>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="material-icons">description</span>
                    </div>
                    <h3>No offers found</h3>
                    <p>There are no offers associated with this lead.</p>
                    <?php if (checkPermission('add_offer') && $lead['status'] != 'lost'): ?>
                        <a href="../offers/add.php?lead_id=<?php echo $leadId; ?>" class="btn btn-primary">Add Offer</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

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