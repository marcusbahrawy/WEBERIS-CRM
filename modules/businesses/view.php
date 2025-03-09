<?php
// modules/businesses/view.php - View business details
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_business')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$businessId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Get business data
$stmt = $conn->prepare("SELECT b.*, u.name as created_by_name
                       FROM businesses b
                       LEFT JOIN users u ON b.created_by = u.id
                       WHERE b.id = ?");
$stmt->bind_param('i', $businessId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$business = $result->fetch_assoc();

// Get related contacts
$stmt = $conn->prepare("SELECT id, first_name, last_name, position, email, phone
                       FROM contacts
                       WHERE business_id = ?
                       ORDER BY first_name, last_name");
$stmt->bind_param('i', $businessId);
$stmt->execute();
$contactsResult = $stmt->get_result();
$contacts = [];
while ($contact = $contactsResult->fetch_assoc()) {
    $contacts[] = $contact;
}

// Get related leads
$stmt = $conn->prepare("SELECT id, title, status, value, created_at
                       FROM leads
                       WHERE business_id = ?
                       ORDER BY created_at DESC");
$stmt->bind_param('i', $businessId);
$stmt->execute();
$leadsResult = $stmt->get_result();
$leads = [];
while ($lead = $leadsResult->fetch_assoc()) {
    $leads[] = $lead;
}

// Get related projects
$stmt = $conn->prepare("SELECT id, name, status, start_date, end_date, budget
                       FROM projects
                       WHERE business_id = ?
                       ORDER BY start_date DESC");
$stmt->bind_param('i', $businessId);
$stmt->execute();
$projectsResult = $stmt->get_result();
$projects = [];
while ($project = $projectsResult->fetch_assoc()) {
    $projects[] = $project;
}

// Page title
$pageTitle = $business['name'];
$pageActions = '';

// Check edit permission
if (checkPermission('edit_business')) {
    $pageActions .= '<a href="edit.php?id=' . $businessId . '" class="btn btn-primary"><span class="material-icons">edit</span> Edit</a>';
}

// Check delete permission
if (checkPermission('delete_business')) {
    $pageActions .= '<a href="delete.php?id=' . $businessId . '" class="btn btn-danger delete-item" data-confirm="Are you sure you want to delete this business?"><span class="material-icons">delete</span> Delete</a>';
}

// Include header
include '../../includes/header.php';

// Check for success message
$successMessage = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $successMessage = 'Business created successfully.';
            break;
        case 'updated':
            $successMessage = 'Business updated successfully.';
            break;
    }
}
?>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success"><?php echo $successMessage; ?></div>
<?php endif; ?>

<div class="business-details">
    <div class="card">
        <div class="card-header">
            <h2>Business Information</h2>
            <div class="card-header-actions">
                <a href="index.php" class="btn btn-text">
                    <span class="material-icons">arrow_back</span> Back to Businesses
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Business Name</div>
                    <div class="detail-value"><?php echo $business['name']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Registration Number</div>
                    <div class="detail-value"><?php echo $business['registration_number'] ?? 'N/A'; ?></div>
                </div>
                
                <div class="detail-item full-width">
                    <div class="detail-label">Address</div>
                    <div class="detail-value"><?php echo $business['address'] ?? 'N/A'; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Phone</div>
                    <div class="detail-value"><?php echo $business['phone'] ?? 'N/A'; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?php echo $business['email'] ?? 'N/A'; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Website</div>
                    <div class="detail-value">
                        <?php if (!empty($business['website'])): ?>
                            <a href="<?php echo $business['website']; ?>" target="_blank"><?php echo $business['website']; ?></a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Industry</div>
                    <div class="detail-value"><?php echo $business['industry'] ?? 'N/A'; ?></div>
                </div>
                
                <div class="detail-item full-width">
                    <div class="detail-label">Description</div>
                    <div class="detail-value"><?php echo nl2br($business['description'] ?? 'N/A'); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created By</div>
                    <div class="detail-value"><?php echo $business['created_by_name']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created On</div>
                    <div class="detail-value"><?php echo date('M j, Y, g:i a', strtotime($business['created_at'])); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contacts Section -->
    <div class="card">
        <div class="card-header">
            <h2>Contacts</h2>
            <?php if (checkPermission('add_contact')): ?>
                <div class="card-header-actions">
                    <a href="../contacts/add.php?business_id=<?php echo $businessId; ?>" class="btn btn-primary">
                        <span class="material-icons">add</span> Add Contact
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card-body">
            <?php if (count($contacts) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td><?php echo $contact['first_name'] . ' ' . $contact['last_name']; ?></td>
                                    <td><?php echo $contact['position'] ?? 'N/A'; ?></td>
                                    <td><?php echo $contact['email'] ?? 'N/A'; ?></td>
                                    <td><?php echo $contact['phone'] ?? 'N/A'; ?></td>
                                    <td class="actions-cell">
                                        <a href="../contacts/view.php?id=<?php echo $contact['id']; ?>" class="btn btn-text" title="View Details">
                                            <span class="material-icons">visibility</span>
                                        </a>
                                        
                                        <?php if (checkPermission('edit_contact')): ?>
                                            <a href="../contacts/edit.php?id=<?php echo $contact['id']; ?>" class="btn btn-text" title="Edit">
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
                        <span class="material-icons">contacts</span>
                    </div>
                    <h3>No contacts found</h3>
                    <p>There are no contacts associated with this business.</p>
                    <?php if (checkPermission('add_contact')): ?>
                        <a href="../contacts/add.php?business_id=<?php echo $businessId; ?>" class="btn btn-primary">Add Contact</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Leads Section -->
    <div class="card">
        <div class="card-header">
            <h2>Leads</h2>
            <?php if (checkPermission('add_lead')): ?>
                <div class="card-header-actions">
                    <a href="../leads/add.php?business_id=<?php echo $businessId; ?>" class="btn btn-primary">
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
                    <p>There are no leads associated with this business.</p>
                    <?php if (checkPermission('add_lead')): ?>
                        <a href="../leads/add.php?business_id=<?php echo $businessId; ?>" class="btn btn-primary">Add Lead</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Projects Section -->
    <div class="card">
        <div class="card-header">
            <h2>Projects</h2>
            <?php if (checkPermission('add_project')): ?>
                <div class="card-header-actions">
                    <a href="../projects/add.php?business_id=<?php echo $businessId; ?>" class="btn btn-primary">
                        <span class="material-icons">add</span> Add Project
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
                                <th>Budget</th>
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
                                    <td><?php echo '$' . number_format($project['budget'], 2); ?></td>
                                    <td class="actions-cell">
                                        <a href="../projects/view.php?id=<?php echo $project['id']; ?>" class="btn btn-text" title="View Details">
                                            <span class="material-icons">visibility</span>
                                        </a>
                                        
                                        <?php if (checkPermission('edit_project')): ?>
                                            <a href="../projects/edit.php?id=<?php echo $project['id']; ?>" class="btn btn-text" title="Edit">
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
                    <p>There are no projects associated with this business.</p>
                    <?php if (checkPermission('add_project')): ?>
                        <a href="../projects/add.php?business_id=<?php echo $businessId; ?>" class="btn btn-primary">Add Project</a>
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