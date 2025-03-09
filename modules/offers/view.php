<?php
// modules/offers/view.php - View offer details
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_offer')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$offerId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Get offer data with related information
$stmt = $conn->prepare("SELECT o.*, 
                      b.name as business_name, 
                      CONCAT(c.first_name, ' ', c.last_name) as contact_name,
                      c.email as contact_email,
                      c.phone as contact_phone,
                      l.title as lead_title,
                      u.name as created_by_name
                      FROM offers o
                      LEFT JOIN businesses b ON o.business_id = b.id
                      LEFT JOIN contacts c ON o.contact_id = c.id
                      LEFT JOIN leads l ON o.lead_id = l.id
                      LEFT JOIN users u ON o.created_by = u.id
                      WHERE o.id = ?");
$stmt->bind_param('i', $offerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$offer = $result->fetch_assoc();

// Get related projects
$stmt = $conn->prepare("SELECT id, name, status, start_date, end_date
                       FROM projects
                       WHERE offer_id = ?
                       ORDER BY created_at DESC");
$stmt->bind_param('i', $offerId);
$stmt->execute();
$projectsResult = $stmt->get_result();
$projects = [];
while ($project = $projectsResult->fetch_assoc()) {
    $projects[] = $project;
}

// Page title
$pageTitle = $offer['title'];
$pageActions = '';

// Check edit permission
if (checkPermission('edit_offer')) {
    $pageActions .= '<a href="edit.php?id=' . $offerId . '" class="btn btn-primary"><span class="material-icons">edit</span> Edit</a>';
}

// Check delete permission
if (checkPermission('delete_offer')) {
    $pageActions .= '<a href="delete.php?id=' . $offerId . '" class="btn btn-danger delete-item" data-confirm="Are you sure you want to delete this offer?"><span class="material-icons">delete</span> Delete</a>';
}

// Add project button if user has permission and offer is accepted
if (checkPermission('add_project') && $offer['status'] === 'accepted') {
    $pageActions .= '<a href="../projects/add.php?offer_id=' . $offerId . '" class="btn btn-secondary"><span class="material-icons">assignment</span> Create Project</a>';
}

// Include header
include '../../includes/header.php';

// Check for success message
$successMessage = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $successMessage = 'Offer created successfully.';
            break;
        case 'updated':
            $successMessage = 'Offer updated successfully.';
            break;
    }
}
?>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success"><?php echo $successMessage; ?></div>
<?php endif; ?>

<div class="offer-details">
    <div class="card">
        <div class="card-header">
            <h2>Offer Information</h2>
            <div class="card-header-actions">
                <a href="index.php" class="btn btn-text">
                    <span class="material-icons">arrow_back</span> Back to Offers
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item full-width">
                    <div class="detail-label">Title</div>
                    <div class="detail-value"><?php echo $offer['title']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Amount</div>
                    <div class="detail-value"><?php echo '' . formatCurrency($offer['amount'], 2); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <span class="status-badge status-<?php echo $offer['status']; ?>"><?php echo $offer['status']; ?></span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Valid Until</div>
                    <div class="detail-value">
                        <?php if ($offer['valid_until']): ?>
                            <?php 
                            $validUntil = new DateTime($offer['valid_until']);
                            $today = new DateTime();
                            $isExpired = $validUntil < $today && $offer['status'] !== 'accepted';
                            ?>
                            <span <?php echo $isExpired ? 'class="text-danger"' : ''; ?>>
                                <?php echo date('M j, Y', strtotime($offer['valid_until'])); ?>
                                <?php if ($isExpired): ?>
                                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">warning</span> Expired
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created Date</div>
                    <div class="detail-value"><?php echo date('M j, Y, g:i a', strtotime($offer['created_at'])); ?></div>
                </div>
                
                <div class="detail-item full-width">
                    <div class="detail-label">Description</div>
                    <div class="detail-value"><?php echo nl2br($offer['description'] ?? 'N/A'); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Business</div>
                    <div class="detail-value">
                        <?php if ($offer['business_id']): ?>
                            <a href="../businesses/view.php?id=<?php echo $offer['business_id']; ?>">
                                <?php echo $offer['business_name']; ?>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Contact</div>
                    <div class="detail-value">
                        <?php if ($offer['contact_id']): ?>
                            <a href="../contacts/view.php?id=<?php echo $offer['contact_id']; ?>">
                                <?php echo $offer['contact_name']; ?>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($offer['contact_email'])): ?>
                <div class="detail-item">
                    <div class="detail-label">Contact Email</div>
                    <div class="detail-value">
                        <a href="mailto:<?php echo $offer['contact_email']; ?>"><?php echo $offer['contact_email']; ?></a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($offer['contact_phone'])): ?>
                <div class="detail-item">
                    <div class="detail-label">Contact Phone</div>
                    <div class="detail-value">
                        <a href="tel:<?php echo $offer['contact_phone']; ?>"><?php echo $offer['contact_phone']; ?></a>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="detail-item">
                    <div class="detail-label">Associated Lead</div>
                    <div class="detail-value">
                        <?php if ($offer['lead_id']): ?>
                            <a href="../leads/view.php?id=<?php echo $offer['lead_id']; ?>">
                                <?php echo $offer['lead_title']; ?>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created By</div>
                    <div class="detail-value"><?php echo $offer['created_by_name']; ?></div>
                </div>
            </div>
            
            <div class="form-actions mt-xl">
                <?php if (checkPermission('edit_offer')): ?>
                    <a href="edit.php?id=<?php echo $offerId; ?>" class="btn btn-primary">
                        <span class="material-icons">edit</span> Edit Offer
                    </a>
                <?php endif; ?>
                
                <?php if (checkPermission('add_project') && $offer['status'] === 'accepted' && count($projects) === 0): ?>
                    <a href="../projects/add.php?offer_id=<?php echo $offerId; ?>" class="btn btn-secondary">
                        <span class="material-icons">assignment</span> Create Project
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Projects Section -->
    <div class="card">
        <div class="card-header">
            <h2>Projects</h2>
            <?php if (checkPermission('add_project') && $offer['status'] === 'accepted' && count($projects) === 0): ?>
                <div class="card-header-actions">
                    <a href="../projects/add.php?offer_id=<?php echo $offerId; ?>" class="btn btn-primary">
                        <span class="material-icons">add</span> Create Project
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card-body">
            <?php if (count($projects) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td><?php echo $project['name']; ?></td>
                                    <td><span class="status-badge status-<?php echo $project['status']; ?>"><?php echo $project['status']; ?></span></td>
                                    <td><?php echo $project['start_date'] ? date('M j, Y', strtotime($project['start_date'])) : 'N/A'; ?></td>
                                    <td><?php echo $project['end_date'] ? date('M j, Y', strtotime($project['end_date'])) : 'N/A'; ?></td>
                                    <td class="actions-cell">
                                        <a href="../projects/view.php?id=<?php echo $project['id']; ?>" class="btn btn-icon btn-text" title="View Details">
                                            <span class="material-icons">visibility</span>
                                        </a>
                                        
                                        <?php if (checkPermission('edit_project')): ?>
                                            <a href="../projects/edit.php?id=<?php echo $project['id']; ?>" class="btn btn-icon btn-text" title="Edit">
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
                        <span class="material-icons">assignment</span>
                    </div>
                    <h3>No projects found</h3>
                    <?php if ($offer['status'] === 'accepted'): ?>
                        <p>There are no projects created from this offer yet.</p>
                        <?php if (checkPermission('add_project')): ?>
                            <a href="../projects/add.php?offer_id=<?php echo $offerId; ?>" class="btn btn-primary">Create Project</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>Projects can only be created from accepted offers.</p>
                        <?php if (checkPermission('edit_offer')): ?>
                            <a href="edit.php?id=<?php echo $offerId; ?>" class="btn btn-primary">Update Offer Status</a>
                        <?php endif; ?>
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