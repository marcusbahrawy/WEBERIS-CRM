<?php
// modules/contacts/view.php - View contact details
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_contact')) {
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

// Get contact data
$stmt = $conn->prepare("SELECT c.*, b.name as business_name, u.name as created_by_name
                       FROM contacts c
                       LEFT JOIN businesses b ON c.business_id = b.id
                       LEFT JOIN users u ON c.created_by = u.id
                       WHERE c.id = ?");
$stmt->bind_param('i', $contactId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$contact = $result->fetch_assoc();

// Get related leads
$stmt = $conn->prepare("SELECT id, title, status, value, created_at
                       FROM leads
                       WHERE contact_id = ?
                       ORDER BY created_at DESC");
$stmt->bind_param('i', $contactId);
$stmt->execute();
$leadsResult = $stmt->get_result();
$leads = [];
while ($lead = $leadsResult->fetch_assoc()) {
    $leads[] = $lead;
}

// Get related offers
$stmt = $conn->prepare("SELECT id, title, amount, status, valid_until
                       FROM offers
                       WHERE contact_id = ?
                       ORDER BY created_at DESC");
$stmt->bind_param('i', $contactId);
$stmt->execute();
$offersResult = $stmt->get_result();
$offers = [];
while ($offer = $offersResult->fetch_assoc()) {
    $offers[] = $offer;
}

// Page title
$pageTitle = $contact['first_name'] . ' ' . $contact['last_name'];
$pageActions = '';

// Check edit permission
if (checkPermission('edit_contact')) {
    $pageActions .= '<a href="edit.php?id=' . $contactId . '" class="btn btn-primary"><span class="material-icons">edit</span> Edit</a>';
}

// Check delete permission
if (checkPermission('delete_contact')) {
    $pageActions .= '<a href="delete.php?id=' . $contactId . '" class="btn btn-danger delete-item" data-confirm="Are you sure you want to delete this contact?"><span class="material-icons">delete</span> Delete</a>';
}

// Include header
include '../../includes/header.php';

// Check for success message
$successMessage = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $successMessage = 'Contact created successfully.';
            break;
        case 'updated':
            $successMessage = 'Contact updated successfully.';
            break;
    }
}
?>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success"><?php echo $successMessage; ?></div>
<?php endif; ?>

<div class="contact-details">
    <div class="card">
        <div class="card-header">
            <h2>Contact Information</h2>
            <div class="card-header-actions">
                <a href="index.php" class="btn btn-text">
                    <span class="material-icons">arrow_back</span> Back to Contacts
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <div class="contact-header">
    <div class="contact-avatar">
        <?php echo strtoupper(substr($contact['first_name'], 0, 1) . substr($contact['last_name'], 0, 1)); ?>
    </div>
    <div class="contact-info">
        <h1 class="contact-name"><?php echo $contact['first_name'] . ' ' . $contact['last_name']; ?></h1>
        <?php if (!empty($contact['position'])): ?>
            <div class="contact-position"><?php echo $contact['position']; ?></div>
        <?php endif; ?>
        <?php if ($contact['business_id']): ?>
            <div class="contact-company">
                <span class="material-icons">business</span>
                <a href="../businesses/view.php?id=<?php echo $contact['business_id']; ?>">
                    <?php echo $contact['business_name']; ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">First Name</div>
                    <div class="detail-value"><?php echo $contact['first_name']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Last Name</div>
                    <div class="detail-value"><?php echo $contact['last_name']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Email</div>
                    <div class="detail-value">
                        <?php if (!empty($contact['email'])): ?>
                            <a href="mailto:<?php echo $contact['email']; ?>"><?php echo $contact['email']; ?></a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Phone</div>
                    <div class="detail-value">
                        <?php if (!empty($contact['phone'])): ?>
                            <a href="tel:<?php echo $contact['phone']; ?>"><?php echo $contact['phone']; ?></a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Position</div>
                    <div class="detail-value"><?php echo $contact['position'] ?? 'N/A'; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Business</div>
                    <div class="detail-value">
                        <?php if ($contact['business_id']): ?>
                            <a href="../businesses/view.php?id=<?php echo $contact['business_id']; ?>">
                                <?php echo $contact['business_name']; ?>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item full-width">
                    <div class="detail-label">Notes</div>
                    <div class="detail-value"><?php echo nl2br($contact['notes'] ?? 'N/A'); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created By</div>
                    <div class="detail-value"><?php echo $contact['created_by_name']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created On</div>
                    <div class="detail-value"><?php echo date('M j, Y, g:i a', strtotime($contact['created_at'])); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Leads Section -->
    <div class="card">
        <div class="card-header">
            <h2>Leads</h2>
            <?php if (checkPermission('add_lead')): ?>
                <div class="card-header-actions">
                    <a href="../leads/add.php?contact_id=<?php echo $contactId; ?>" class="btn btn-primary">
                        <span class="material-icons">add</span> Add Lead
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card-body">
            <?php if (count($leads) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Value</th>
                                <th>Created On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leads as $lead): ?>
                                <tr>
                                    <td><?php echo $lead['title']; ?></td>
                                    <td><span class="status-badge status-<?php echo $lead['status']; ?>"><?php echo $lead['status']; ?></span></td>
                                    <td><?php echo '$' . number_format($lead['value'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($lead['created_at'])); ?></td>
                                    <td class="actions-cell">
                                        <a href="../leads/view.php?id=<?php echo $lead['id']; ?>" class="btn btn-text" title="View Details">
                                            <span class="material-icons">visibility</span>
                                        </a>
                                        
                                        <?php if (checkPermission('edit_lead')): ?>
                                            <a href="../leads/edit.php?id=<?php echo $lead['id']; ?>" class="btn btn-text" title="Edit">
                                                <span class="material-icons">edit</span>
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
                        <span class="material-icons">lightbulb</span>
                    </div>
                    <h3>No leads found</h3>
                    <p>There are no leads associated with this contact.</p>
                    <?php if (checkPermission('add_lead')): ?>
                        <a href="../leads/add.php?contact_id=<?php echo $contactId; ?>" class="btn btn-primary">Add Lead</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Offers Section -->
    <div class="card">
        <div class="card-header">
            <h2>Offers</h2>
            <?php if (checkPermission('add_offer')): ?>
                <div class="card-header-actions">
                    <a href="../offers/add.php?contact_id=<?php echo $contactId; ?>" class="btn btn-primary">
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
                                    <td><?php echo '$' . number_format($offer['amount'], 2); ?></td>
                                    <td><span class="status-badge status-<?php echo $offer['status']; ?>"><?php echo $offer['status']; ?></span></td>
                                    <td><?php echo $offer['valid_until'] ? date('M j, Y', strtotime($offer['valid_until'])) : 'N/A'; ?></td>
                                    <td class="actions-cell">
                                        <a href="../offers/view.php?id=<?php echo $offer['id']; ?>" class="btn btn-text" title="View Details">
                                            <span class="material-icons">visibility</span>
                                        </a>
                                        
                                        <?php if (checkPermission('edit_offer')): ?>
                                            <a href="../offers/edit.php?id=<?php echo $offer['id']; ?>" class="btn btn-text" title="Edit">
                                                <span class="material-icons">edit</span>
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
                    <p>There are no offers associated with this contact.</p>
                    <?php if (checkPermission('add_offer')): ?>
                        <a href="../offers/add.php?contact_id=<?php echo $contactId; ?>" class="btn btn-primary">Add Offer</a>
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