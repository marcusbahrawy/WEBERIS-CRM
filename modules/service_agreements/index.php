<?php
// modules/service_agreements/index.php - Service Agreements listing page
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_service_agreement')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Service Agreements";
$pageActions = '';

// Check if user can add service agreements
if (checkPermission('add_service_agreement')) {
    $pageActions .= '<a href="add.php" class="btn btn-primary"><span class="material-icons">add</span> Add Service Agreement</a>';
}

// Business filter
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;
$businessName = '';

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

// Create a map of agreement types for display
$agreementTypesMap = [];
$agreementTypesResult = $conn->query("SELECT name, label FROM agreement_types");
if ($agreementTypesResult->num_rows > 0) {
    while ($type = $agreementTypesResult->fetch_assoc()) {
        $agreementTypesMap[$type['name']] = $type['label'];
    }
}

// Search query
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Status filter
$statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Get all possible statuses
$statusesResult = $conn->query("SELECT DISTINCT status FROM service_agreements ORDER BY status");
$statuses = [];
if ($statusesResult && $statusesResult->num_rows > 0) {
    while ($status = $statusesResult->fetch_assoc()) {
        $statuses[] = $status['status'];
    }
} else {
    // Default statuses if no records yet
    $statuses = ['active', 'expired', 'canceled', 'pending_renewal'];
}

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total service agreements count
$countQuery = "SELECT COUNT(*) as total FROM service_agreements sa 
               LEFT JOIN businesses b ON sa.business_id = b.id";
$countParams = [];
$countTypes = '';

$whereConditions = [];
if (!empty($search)) {
    $searchTerm = '%' . $conn->real_escape_string($search) . '%';
    $whereConditions[] = "(sa.title LIKE ? OR sa.description LIKE ? OR b.name LIKE ?)";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes .= 'sss';
}

if (!empty($statusFilter)) {
    $whereConditions[] = "sa.status = ?";
    $countParams[] = $statusFilter;
    $countTypes .= 's';
}

if ($businessId) {
    $whereConditions[] = "sa.business_id = ?";
    $countParams[] = $businessId;
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

// Get service agreements with pagination
$query = "SELECT sa.*, b.name as business_name,
          u.name as created_by_name
          FROM service_agreements sa
          LEFT JOIN businesses b ON sa.business_id = b.id
          LEFT JOIN users u ON sa.created_by = u.id";

$queryParams = [];
$queryTypes = '';

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(' AND ', $whereConditions);
}

$query .= " ORDER BY sa.start_date DESC, sa.created_at DESC LIMIT ? OFFSET ?";
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
            echo $businessId ? "Service Agreements for " . $businessName : "All Service Agreements";
            ?>
        </h2>
        <div class="card-header-actions">
            <form action="" method="GET" class="search-form">
                <?php if ($businessId): ?>
                    <input type="hidden" name="business_id" value="<?php echo $businessId; ?>">
                <?php endif; ?>
                <div class="search-input">
                    <span class="material-icons">search</span>
                    <input type="text" name="search" placeholder="Search agreements..." value="<?php echo $search; ?>">
                    <button type="submit" class="btn btn-text">Search</button>
                    <?php if (!empty($search) || !empty($statusFilter)): ?>
                        <a href="?<?php echo $businessId ? 'business_id=' . $businessId : ''; ?>" class="btn btn-text">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
            <?php if (checkPermission('edit_service_agreement')): ?>
                <a href="../agreement_types/index.php" class="btn btn-text">
                    <span class="material-icons">settings</span> Manage Types
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
            <div class="alert alert-success">Service Agreement deleted successfully.</div>
        <?php endif; ?>
        
        <!-- Status filter tabs -->
        <div class="status-filters mb-lg">
            <a href="?<?php 
                $params = [];
                if (!empty($search)) $params[] = 'search=' . urlencode($search);
                if ($businessId) $params[] = 'business_id=' . $businessId;
                echo implode('&', $params);
            ?>" class="btn <?php echo empty($statusFilter) ? 'btn-primary' : 'btn-text'; ?>">All</a>
            
            <?php foreach ($statuses as $status): ?>
                <a href="?status=<?php echo urlencode($status); ?><?php 
                    if (!empty($search)) echo '&search=' . urlencode($search);
                    if ($businessId) echo '&business_id=' . $businessId;
                ?>" class="btn <?php echo $statusFilter === $status ? 'btn-primary' : 'btn-text'; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php if ($result && $result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="data-table" data-sortable="true">
                    <thead>
                        <tr>
                            <th data-sortable="true">Title</th>
                            <th data-sortable="true">Business</th>
                            <th data-sortable="true">Status</th>
                            <th data-sortable="true">Agreement Type</th>
                            <th data-sortable="true">Start Date</th>
                            <th data-sortable="true">End Date</th>
                            <th data-sortable="true">Renewal Date</th>
                            <th data-sortable="true">Price</th>
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
                                    <span class="status-badge status-<?php echo $row['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo isset($agreementTypesMap[$row['agreement_type']]) ? $agreementTypesMap[$row['agreement_type']] : ucfirst(str_replace('_', ' ', $row['agreement_type'])); ?></td>
                                <td><?php echo date('M j, Y', strtotime($row['start_date'])); ?></td>
                                <td><?php echo $row['end_date'] ? date('M j, Y', strtotime($row['end_date'])) : 'Ongoing'; ?></td>
                                <td>
                                    <?php if ($row['renewal_date']): ?>
                                        <?php 
                                        $renewalDate = new DateTime($row['renewal_date']);
                                        $today = new DateTime();
                                        $daysUntilRenewal = $today->diff($renewalDate)->days;
                                        $isRenewalSoon = $renewalDate > $today && $daysUntilRenewal <= 30;
                                        ?>
                                        <span <?php echo $isRenewalSoon ? 'class="text-warning"' : ''; ?>>
                                            <?php echo date('M j, Y', strtotime($row['renewal_date'])); ?>
                                            <?php if ($isRenewalSoon): ?>
                                                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">warning</span>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?php echo '' . formatCurrency($row['price']); ?> / <?php echo ucfirst($row['billing_cycle']); ?></td>
                                <td class="actions-cell">
                                    <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text" title="View Details">
                                        <span class="material-icons">visibility</span>
                                    </a>
                                    
                                    <?php if (checkPermission('edit_service_agreement')): ?>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text" title="Edit">
                                            <span class="material-icons">edit</span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (checkPermission('delete_service_agreement')): ?>
                                        <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text delete-item" 
                                           title="Delete" data-confirm="Are you sure you want to delete this service agreement?">
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
                        <a href="?page=<?php echo ($page - 1); ?><?php 
                            if (!empty($search)) echo '&search=' . urlencode($search);
                            if (!empty($statusFilter)) echo '&status=' . urlencode($statusFilter);
                            if ($businessId) echo '&business_id=' . $businessId;
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
                        ?>" class="pagination-item">
                            <span class="material-icons">navigate_next</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-icons">verified</span>
                </div>
                <h3>No service agreements found</h3>
                <?php if (!empty($search) || !empty($statusFilter) || $businessId): ?>
                    <p>
                        <?php if (!empty($search)): ?>
                            No service agreements matching "<?php echo $search; ?>"
                        <?php endif; ?>
                        
                        <?php if (!empty($statusFilter)): ?>
                            with status "<?php echo $statusFilter; ?>"
                        <?php endif; ?>
                        
                        <?php if ($businessId): ?>
                            for business "<?php echo $businessName; ?>"
                        <?php endif; ?>
                        
                        were found.
                    </p>
                    <a href="index.php" class="btn btn-primary">View All Service Agreements</a>
                <?php else: ?>
                    <p>Get started by creating your first service agreement.</p>
                    <?php if (checkPermission('add_service_agreement')): ?>
                        <a href="add.php" class="btn btn-primary">Add Service Agreement</a>
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