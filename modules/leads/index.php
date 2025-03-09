<?php
// modules/leads/index.php - Leads listing page
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_lead')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Leads";
$pageActions = '';

// Check if user can add leads
if (checkPermission('add_lead')) {
    $pageActions .= '<a href="add.php" class="btn btn-primary"><span class="material-icons">add</span> Add Lead</a>';
}

// Business/Contact filter
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;
$contactId = isset($_GET['contact_id']) && is_numeric($_GET['contact_id']) ? (int)$_GET['contact_id'] : null;
$businessName = '';
$contactName = '';

$conn = connectDB();

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

if ($contactId) {
    $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM contacts WHERE id = ?");
    $stmt->bind_param('i', $contactId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $contactName = $result->fetch_assoc()['name'];
    } else {
        $contactId = null;
    }
}

// Search query
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Status filter
$statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Get all possible statuses
$statusesResult = $conn->query("SELECT DISTINCT status FROM leads ORDER BY status");
$statuses = [];
while ($status = $statusesResult->fetch_assoc()) {
    $statuses[] = $status['status'];
}

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total leads count
$countQuery = "SELECT COUNT(*) as total FROM leads l 
               LEFT JOIN businesses b ON l.business_id = b.id 
               LEFT JOIN contacts c ON l.contact_id = c.id
               LEFT JOIN users u ON l.assigned_to = u.id";
$countParams = [];
$countTypes = '';

$whereConditions = [];
if (!empty($search)) {
    $searchTerm = '%' . $conn->real_escape_string($search) . '%';
    $whereConditions[] = "(l.title LIKE ? OR l.description LIKE ? OR b.name LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes .= 'ssss';
}

if (!empty($statusFilter)) {
    $whereConditions[] = "l.status = ?";
    $countParams[] = $statusFilter;
    $countTypes .= 's';
}

if ($businessId) {
    $whereConditions[] = "l.business_id = ?";
    $countParams[] = $businessId;
    $countTypes .= 'i';
}

if ($contactId) {
    $whereConditions[] = "l.contact_id = ?";
    $countParams[] = $contactId;
    $countTypes .= 'i';
}

if (!empty($whereConditions)) {
    $countQuery .= " WHERE " . implode(' AND ', $whereConditions);
}

if (!empty($countParams)) {
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param($countTypes, ...$countParams);
    $stmt->execute();
    $totalResult = $stmt->get_result();
} else {
    $totalResult = $conn->query($countQuery);
}

$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $perPage);

// Get leads with pagination
$query = "SELECT l.*, b.name as business_name, CONCAT(c.first_name, ' ', c.last_name) as contact_name, 
          u.name as assigned_to_name
          FROM leads l 
          LEFT JOIN businesses b ON l.business_id = b.id 
          LEFT JOIN contacts c ON l.contact_id = c.id
          LEFT JOIN users u ON l.assigned_to = u.id";

$queryParams = [];
$queryTypes = '';

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(' AND ', $whereConditions);
}

$query .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
$queryParams = array_merge($countParams, [$perPage, $offset]);
$queryTypes .= 'ii';

$stmt = $conn->prepare($query);
if (!empty($queryParams)) {
    $stmt->bind_param($countTypes . $queryTypes, ...$queryParams);
}
$stmt->execute();
$result = $stmt->get_result();

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>
            <?php 
            if ($businessId) echo "Leads for " . $businessName;
            else if ($contactId) echo "Leads for " . $contactName;
            else echo "All Leads";
            ?>
        </h2>
        <div class="card-header-actions">
            <form action="" method="GET" class="search-form">
                <?php if ($businessId): ?>
                    <input type="hidden" name="business_id" value="<?php echo $businessId; ?>">
                <?php endif; ?>
                <?php if ($contactId): ?>
                    <input type="hidden" name="contact_id" value="<?php echo $contactId; ?>">
                <?php endif; ?>
                <div class="search-input">
                    <span class="material-icons">search</span>
                    <input type="text" name="search" placeholder="Search leads..." value="<?php echo $search; ?>">
                    <button type="submit" class="btn btn-text">Search</button>
                    <?php if (!empty($search) || !empty($statusFilter)): ?>
                        <a href="?<?php echo $businessId ? 'business_id=' . $businessId : ''; ?><?php echo $contactId ? 'contact_id=' . $contactId : ''; ?>" class="btn btn-text">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
            <div class="alert alert-success">Lead deleted successfully.</div>
        <?php endif; ?>
        
        <!-- Status filter tabs -->
        <div class="status-filters mb-lg">
            <a href="?<?php echo !empty($search) ? 'search=' . urlencode($search) : ''; ?><?php echo $businessId ? '&business_id=' . $businessId : ''; ?><?php echo $contactId ? '&contact_id=' . $contactId : ''; ?>" 
               class="btn <?php echo empty($statusFilter) ? 'btn-primary' : 'btn-text'; ?>">All</a>
            
            <?php foreach ($statuses as $status): ?>
                <a href="?status=<?php echo urlencode($status); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $businessId ? '&business_id=' . $businessId : ''; ?><?php echo $contactId ? '&contact_id=' . $contactId : ''; ?>" 
                   class="btn <?php echo $statusFilter === $status ? 'btn-primary' : 'btn-text'; ?>">
                    <?php echo ucfirst($status); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="data-table" data-sortable="true">
                    <thead>
                        <tr>
                            <th data-sortable="true">Title</th>
                            <th data-sortable="true">Business</th>
                            <th data-sortable="true">Contact</th>
                            <th data-sortable="true">Value</th>
                            <th data-sortable="true">Status</th>
                            <th data-sortable="true">Assigned To</th>
                            <th data-sortable="true">Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['title']; ?></td>
                                <td>
                                    <?php if ($row['business_id']): ?>
                                        <a href="../businesses/view.php?id=<?php echo $row['business_id']; ?>">
                                            <?php echo $row['business_name']; ?>
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['contact_id']): ?>
                                        <a href="../contacts/view.php?id=<?php echo $row['contact_id']; ?>">
                                            <?php echo $row['contact_name']; ?>
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?php echo '' . formatCurrency($row['value'], 2); ?></td>
                                <td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
                                <td><?php echo $row['assigned_to_name'] ?? 'Unassigned'; ?></td>
                                <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                                <td class="actions-cell">
                                    <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text" title="View Details">
                                        <span class="material-icons">visibility</span>
                                    </a>
                                    
                                    <?php if (checkPermission('edit_lead')): ?>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text" title="Edit">
                                            <span class="material-icons">edit</span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (checkPermission('delete_lead')): ?>
                                        <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text delete-item" 
                                           title="Delete" data-confirm="Are you sure you want to delete this lead?">
                                            <span class="material-icons">delete</span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo $businessId ? '&business_id=' . $businessId : ''; ?><?php echo $contactId ? '&contact_id=' . $contactId : ''; ?>" class="pagination-item">
                            <span class="material-icons">navigate_before</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $startPage + 4);
                    
                    if ($startPage > 1) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $activeClass = ($i === $page) ? 'active' : '';
                        $url = '?page=' . $i;
                        if (!empty($search)) {
                            $url .= '&search=' . urlencode($search);
                        }
                        if (!empty($statusFilter)) {
                            $url .= '&status=' . urlencode($statusFilter);
                        }
                        if ($businessId) {
                            $url .= '&business_id=' . $businessId;
                        }
                        if ($contactId) {
                            $url .= '&contact_id=' . $contactId;
                        }
                        echo "<a href='{$url}' class='pagination-item {$activeClass}'>{$i}</a>";
                    }
                    
                    if ($endPage < $totalPages) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo $businessId ? '&business_id=' . $businessId : ''; ?><?php echo $contactId ? '&contact_id=' . $contactId : ''; ?>" class="pagination-item">
                            <span class="material-icons">navigate_next</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-icons">lightbulb</span>
                </div>
                <h3>No leads found</h3>
                <?php if (!empty($search) || !empty($statusFilter) || $businessId || $contactId): ?>
                    <p>
                        <?php if (!empty($search)): ?>
                            No leads matching "<?php echo $search; ?>"
                        <?php endif; ?>
                        
                        <?php if (!empty($statusFilter)): ?>
                            with status "<?php echo $statusFilter; ?>"
                        <?php endif; ?>
                        
                        <?php if ($businessId): ?>
                            for business "<?php echo $businessName; ?>"
                        <?php endif; ?>
                        
                        <?php if ($contactId): ?>
                            for contact "<?php echo $contactName; ?>"
                        <?php endif; ?>
                        
                        were found.
                    </p>
                    <a href="index.php" class="btn btn-primary">View All Leads</a>
                <?php else: ?>
                    <p>Get started by adding your first lead.</p>
                    <?php if (checkPermission('add_lead')): ?>
                        <a href="add.php" class="btn btn-primary">Add Lead</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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