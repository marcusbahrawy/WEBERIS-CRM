<?php
// modules/contacts/index.php - Contacts listing page
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_contact')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Contacts";
$pageActions = '';

// Check if user can add contacts
if (checkPermission('add_contact')) {
    $pageActions .= '<a href="add.php" class="btn btn-primary"><span class="material-icons">add</span> Add Contact</a>';
}

// Business filter
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;
$businessName = '';

if ($businessId) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT name FROM businesses WHERE id = ?");
    $stmt->bind_param('i', $businessId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $businessName = $result->fetch_assoc()['name'];
    } else {
        $businessId = null;
    }
    $conn->close();
}

// Search query
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Database connection
$conn = connectDB();

// Get total contacts count
$countQuery = "SELECT COUNT(*) as total FROM contacts c LEFT JOIN businesses b ON c.business_id = b.id";
$countParams = [];
$countTypes = '';

$whereConditions = [];
if (!empty($search)) {
    $searchTerm = '%' . $conn->real_escape_string($search) . '%';
    $whereConditions[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes .= 'ssss';
}

if ($businessId) {
    $whereConditions[] = "c.business_id = ?";
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

// Get contacts with pagination
$query = "SELECT c.*, b.name as business_name 
          FROM contacts c 
          LEFT JOIN businesses b ON c.business_id = b.id";

$queryParams = [];
$queryTypes = '';

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(' AND ', $whereConditions);
}

$query .= " ORDER BY c.first_name, c.last_name ASC LIMIT ? OFFSET ?";
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
        <h2><?php echo $businessId ? "Contacts for " . $businessName : "All Contacts"; ?></h2>
        <div class="card-header-actions">
            <form action="" method="GET" class="search-form">
                <?php if ($businessId): ?>
                    <input type="hidden" name="business_id" value="<?php echo $businessId; ?>">
                <?php endif; ?>
                <div class="search-input">
                    <span class="material-icons">search</span>
                    <input type="text" name="search" placeholder="Search contacts..." value="<?php echo $search; ?>">
                    <button type="submit" class="btn btn-text">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="?<?php echo $businessId ? 'business_id=' . $businessId : ''; ?>" class="btn btn-text">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
            <div class="alert alert-success">Contact deleted successfully.</div>
        <?php endif; ?>
        
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="data-table" data-sortable="true">
                    <thead>
                        <tr>
                            <th data-sortable="true">Name</th>
                            <th data-sortable="true">Position</th>
                            <th data-sortable="true">Email</th>
                            <th data-sortable="true">Phone</th>
                            <th data-sortable="true">Business</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                                <td><?php echo $row['position'] ?? 'N/A'; ?></td>
                                <td><?php echo $row['email'] ?? 'N/A'; ?></td>
                                <td><?php echo $row['phone'] ?? 'N/A'; ?></td>
                                <td>
                                    <?php if ($row['business_id']): ?>
                                        <a href="../businesses/view.php?id=<?php echo $row['business_id']; ?>">
                                            <?php echo $row['business_name']; ?>
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-text" title="View Details">
                                        <span class="material-icons">visibility</span>
                                    </a>
                                    
                                    <?php if (checkPermission('edit_contact')): ?>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-text" title="Edit">
                                            <span class="material-icons">edit</span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (checkPermission('delete_contact')): ?>
                                        <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-text delete-item" 
                                           title="Delete" data-confirm="Are you sure you want to delete this contact?">
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
                        <a href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $businessId ? '&business_id=' . $businessId : ''; ?>" class="pagination-item">
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
                        <a href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $businessId ? '&business_id=' . $businessId : ''; ?>" class="pagination-item">
                            <span class="material-icons">navigate_next</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-icons">contacts</span>
                </div>
                <h3>No contacts found</h3>
                <?php if (!empty($search) || $businessId): ?>
                    <p>
                        <?php if (!empty($search)): ?>
                            No contacts matching "<?php echo $search; ?>"<?php echo $businessId ? ' for ' . $businessName : ''; ?> were found.
                        <?php else: ?>
                            No contacts found for <?php echo $businessName; ?>.
                        <?php endif; ?>
                    </p>
                    <a href="index.php" class="btn btn-primary">View All Contacts</a>
                <?php else: ?>
                    <p>Get started by adding your first contact.</p>
                    <?php if (checkPermission('add_contact')): ?>
                        <a href="add.php" class="btn btn-primary">Add Contact</a>
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