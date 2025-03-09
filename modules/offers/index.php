<?php
// modules/offers/index.php - Offers listing page
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_offer')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Offers";
$pageActions = '';

// Check if user can add offers
if (checkPermission('add_offer')) {
    $pageActions .= '<a href="add.php" class="btn btn-primary"><span class="material-icons">add</span> Add Offer</a>';
}

// Business/Contact/Lead filter
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;
$contactId = isset($_GET['contact_id']) && is_numeric($_GET['contact_id']) ? (int)$_GET['contact_id'] : null;
$leadId = isset($_GET['lead_id']) && is_numeric($_GET['lead_id']) ? (int)$_GET['lead_id'] : null;
$businessName = '';
$contactName = '';
$leadTitle = '';

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

if ($leadId) {
    $stmt = $conn->prepare("SELECT title FROM leads WHERE id = ?");
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $leadTitle = $result->fetch_assoc()['title'];
    } else {
        $leadId = null;
    }
}

// Search query
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Status filter
$statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Get all possible statuses
$statusesResult = $conn->query("SELECT DISTINCT status FROM offers ORDER BY status");
$statuses = [];
while ($status = $statusesResult->fetch_assoc()) {
    $statuses[] = $status['status'];
}

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total offers count
$countQuery = "SELECT COUNT(*) as total FROM offers o 
               LEFT JOIN businesses b ON o.business_id = b.id 
               LEFT JOIN contacts c ON o.contact_id = c.id
               LEFT JOIN leads l ON o.lead_id = l.id";
$countParams = [];
$countTypes = '';

$whereConditions = [];
if (!empty($search)) {
    $searchTerm = '%' . $conn->real_escape_string($search) . '%';
    $whereConditions[] = "(o.title LIKE ? OR o.description LIKE ? OR b.name LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes .= 'ssss';
}

if (!empty($statusFilter)) {
    $whereConditions[] = "o.status = ?";
    $countParams[] = $statusFilter;
    $countTypes .= 's';
}

if ($businessId) {
    $whereConditions[] = "o.business_id = ?";
    $countParams[] = $businessId;
    $countTypes .= 'i';
}

if ($contactId) {
    $whereConditions[] = "o.contact_id = ?";
    $countParams[] = $contactId;
    $countTypes .= 'i';
}

if ($leadId) {
    $whereConditions[] = "o.lead_id = ?";
    $countParams[] = $leadId;
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

// Get offers with pagination
$query = "SELECT o.*, b.name as business_name, CONCAT(c.first_name, ' ', c.last_name) as contact_name, 
          l.title as lead_title
          FROM offers o 
          LEFT JOIN businesses b ON o.business_id = b.id 
          LEFT JOIN contacts c ON o.contact_id = c.id
          LEFT JOIN leads l ON o.lead_id = l.id";

$queryParams = [];
$queryTypes = '';

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(' AND ', $whereConditions);
}

$query .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
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
            if ($businessId) echo "Offers for " . $businessName;
            else if ($contactId) echo "Offers for " . $contactName;
            else if ($leadId) echo "Offers for Lead: " . $leadTitle;
            else echo "All Offers";
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
                <?php if ($leadId): ?>
                    <input type="hidden" name="lead_id" value="<?php echo $leadId; ?>">
                <?php endif; ?>
                <div class="search-input">
                    <span class="material-icons">search</span>
                    <input type="text" name="search" placeholder="Search offers..." value="<?php echo $search; ?>">
                    <button type="submit" class="btn btn-text">Search</button>
                    <?php if (!empty($search) || !empty($statusFilter)): ?>
                        <a href="?<?php 
                            $params = [];
                            if ($businessId) $params[] = 'business_id=' . $businessId;
                            if ($contactId) $params[] = 'contact_id=' . $contactId;
                            if ($leadId) $params[] = 'lead_id=' . $leadId;
                            echo implode('&', $params);
                        ?>" class="btn btn-text">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
            <div class="alert alert-success">Offer deleted successfully.</div>
        <?php endif; ?>
        
        <!-- Status filter tabs -->
        <div class="status-filters mb-lg">
            <a href="?<?php 
                $params = [];
                if (!empty($search)) $params[] = 'search=' . urlencode($search);
                if ($businessId) $params[] = 'business_id=' . $businessId;
                if ($contactId) $params[] = 'contact_id=' . $contactId;
                if ($leadId) $params[] = 'lead_id=' . $leadId;
                echo implode('&', $params);
            ?>" class="btn <?php echo empty($statusFilter) ? 'btn-primary' : 'btn-text'; ?>">All</a>
            
            <?php foreach ($statuses as $status): ?>
                <a href="?status=<?php echo urlencode($status); ?><?php 
                    if (!empty($search)) echo '&search=' . urlencode($search);
                    if ($businessId) echo '&business_id=' . $businessId;
                    if ($contactId) echo '&contact_id=' . $contactId;
                    if ($leadId) echo '&lead_id=' . $leadId;
                ?>" class="btn <?php echo $statusFilter === $status ? 'btn-primary' : 'btn-text'; ?>">
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
                            <th data-sortable="true">Lead</th>
                            <th data-sortable="true">Amount</th>
                            <th data-sortable="true">Status</th>
                            <th data-sortable="true">Valid Until</th>
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
                                <td>
                                    <?php if ($row['lead_id']): ?>
                                        <a href="../leads/view.php?id=<?php echo $row['lead_id']; ?>">
                                            <?php echo $row['lead_title']; ?>
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?php echo '' . formatCurrency($row['amount'], 2); ?></td>
                                <td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
                                <td><?php echo $row['valid_until'] ? date('M j, Y', strtotime($row['valid_until'])) : 'N/A'; ?></td>
                                <td class="actions-cell">
                                    <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text" title="View Details">
                                        <span class="material-icons">visibility</span>
                                    </a>
                                    
                                    <?php if (checkPermission('edit_offer')): ?>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text" title="Edit">
                                            <span class="material-icons">edit</span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (checkPermission('delete_offer')): ?>
                                        <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text delete-item" 
                                           title="Delete" data-confirm="Are you sure you want to delete this offer?">
                                            <span class="material-icons">delete</span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (checkPermission('add_project') && $row['status'] === 'accepted'): ?>
                                        <a href="../projects/add.php?offer_id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text" title="Create Project">
                                            <span class="material-icons">assignment</span>
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
                        <a href="?page=<?php echo ($page - 1); ?><?php 
                            if (!empty($search)) echo '&search=' . urlencode($search);
                            if (!empty($statusFilter)) echo '&status=' . urlencode($statusFilter);
                            if ($businessId) echo '&business_id=' . $businessId;
                            if ($contactId) echo '&contact_id=' . $contactId;
                            if ($leadId) echo '&lead_id=' . $leadId;
                        ?>" class="pagination-item">
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
                        if ($leadId) {
                            $url .= '&lead_id=' . $leadId;
                        }
                        echo "<a href='{$url}' class='pagination-item {$activeClass}'>{$i}</a>";
                    }
                    
                    if ($endPage < $totalPages) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo ($page + 1); ?><?php 
                            if (!empty($search)) echo '&search=' . urlencode($search);
                            if (!empty($statusFilter)) echo '&status=' . urlencode($statusFilter);
                            if ($businessId) echo '&business_id=' . $businessId;
                            if ($contactId) echo '&contact_id=' . $contactId;
                            if ($leadId) echo '&lead_id=' . $leadId;
                        ?>" class="pagination-item">
                            <span class="material-icons">navigate_next</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-icons">description</span>
                </div>
                <h3>No offers found</h3>
                <?php if (!empty($search) || !empty($statusFilter) || $businessId || $contactId || $leadId): ?>
                    <p>
                        <?php if (!empty($search)): ?>
                            No offers matching "<?php echo $search; ?>"
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
                        
                        <?php if ($leadId): ?>
                            for lead "<?php echo $leadTitle; ?>"
                        <?php endif; ?>
                        
                        were found.
                    </p>
                    <a href="index.php" class="btn btn-primary">View All Offers</a>
                <?php else: ?>
                    <p>Get started by creating your first offer.</p>
                    <?php if (checkPermission('add_offer')): ?>
                        <a href="add.php" class="btn btn-primary">Add Offer</a>
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