<?php
// modules/agreement_types/index.php - Agreement Types listing page
require_once '../../config.php';

// Check permissions (use service_agreement permissions for now)
if (!checkPermission('edit_service_agreement')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Agreement Types";
$pageActions = '';

// Add agreement type button
$pageActions .= '<a href="add.php" class="btn btn-primary"><span class="material-icons">add</span> Add Agreement Type</a>';

// Database connection
$conn = connectDB();

// Get all agreement types
$result = $conn->query("SELECT * FROM agreement_types ORDER BY name ASC");
$agreementTypes = [];
if ($result->num_rows > 0) {
    while ($type = $result->fetch_assoc()) {
        $agreementTypes[] = $type;
    }
}

// Handle active/inactive filter
$showInactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === '1';
$filterQuery = $showInactive ? '' : ' WHERE is_active = 1';

// Get agreement types with filter
$query = "SELECT * FROM agreement_types" . $filterQuery . " ORDER BY label ASC";
$result = $conn->query($query);

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Manage Agreement Types</h2>
        <div class="card-header-actions">
            <a href="<?php echo SITE_URL; ?>/modules/service_agreements/index.php" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Service Agreements
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (isset($_GET['success'])): ?>
            <?php if ($_GET['success'] === 'created'): ?>
                <div class="alert alert-success">Agreement type created successfully.</div>
            <?php elseif ($_GET['success'] === 'updated'): ?>
                <div class="alert alert-success">Agreement type updated successfully.</div>
            <?php elseif ($_GET['success'] === 'deleted'): ?>
                <div class="alert alert-success">Agreement type deleted successfully.</div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Filter toggle -->
        <div class="filter-toggle mb-lg">
            <a href="?<?php echo $showInactive ? '' : 'show_inactive=1'; ?>" class="btn btn-text">
                <?php echo $showInactive ? 'Hide inactive types' : 'Show inactive types'; ?>
            </a>
        </div>
        
        <?php if ($result && $result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Label</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['name']; ?></td>
                                <td><?php echo $row['label']; ?></td>
                                <td><?php echo substr($row['description'] ?? '', 0, 100) . (strlen($row['description'] ?? '') > 100 ? '...' : ''); ?></td>
                                <td>
                                    <?php if ($row['is_active']): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text" title="Edit">
                                        <span class="material-icons">edit</span>
                                    </a>
                                    
                                    <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text delete-item" 
                                       title="Delete" data-confirm="Are you sure you want to delete this agreement type?">
                                        <span class="material-icons">delete</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-icons">verified</span>
                </div>
                <h3>No agreement types found</h3>
                <p>Get started by adding your first agreement type.</p>
                <a href="add.php" class="btn btn-primary">Add Agreement Type</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.status-inactive {
    background-color: rgba(100, 116, 139, 0.15);
    color: var(--grey-700);
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