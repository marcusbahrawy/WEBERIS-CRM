<?php
// modules/businesses/index.php - Businesses listing page
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_business')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Businesses";
$pageActions = '';

// Check if user can add businesses
if (checkPermission('add_business')) {
    $pageActions .= '<a href="add.php" class="btn btn-primary"><span class="material-icons">add</span> Add Business</a>';
}

// Search query
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Database connection
$conn = connectDB();

// Get total businesses count
$countQuery = "SELECT COUNT(*) as total FROM businesses";

if (!empty($search)) {
    $searchTerm = '%' . $conn->real_escape_string($search) . '%';
    $countQuery .= " WHERE name LIKE ? OR registration_number LIKE ? OR email LIKE ?";
    
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $totalResult = $stmt->get_result();
} else {
    $totalResult = $conn->query($countQuery);
}

$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $perPage);

// Get businesses with pagination
$query = "SELECT * FROM businesses";

if (!empty($search)) {
    $query .= " WHERE name LIKE ? OR registration_number LIKE ? OR email LIKE ?";
    $query .= " ORDER BY name ASC LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssii', $searchTerm, $searchTerm, $searchTerm, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $query .= " ORDER BY name ASC LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
}

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Manage Businesses</h2>
        <div class="card-header-actions">
            <form action="" method="GET" class="search-form">
                <div class="search-input">
                    <span class="material-icons">search</span>
                    <input type="text" name="search" placeholder="Search businesses..." value="<?php echo $search; ?>">
                    <button type="submit" class="btn btn-text">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="index.php" class="btn btn-text">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="data-table" data-sortable="true">
                    <thead>
                        <tr>
                            <th data-sortable="true">Name</th>
                            <th data-sortable="true">Registration Number</th>
                            <th data-sortable="true">Email</th>
                            <th data-sortable="true">Phone</th>
                            <th data-sortable="true">Industry</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['name']; ?></td>
                                <td><?php echo $row['registration_number'] ?? 'N/A'; ?></td>
                                <td><?php echo $row['email'] ?? 'N/A'; ?></td>
                                <td><?php echo $row['phone'] ?? 'N/A'; ?></td>
                                <td><?php echo $row['industry'] ?? 'N/A'; ?></td>
                                <td class="actions-cell">
    <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text" title="View Details">
        <span class="material-icons">visibility</span>
    </a>
    
    <?php if (checkPermission('edit_business')): ?>
        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text" title="Edit">
            <span class="material-icons">edit</span>
        </a>
    <?php endif; ?>
    
    <?php if (checkPermission('delete_business')): ?>
        <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-text delete-item" 
           title="Delete" data-confirm="Are you sure you want to delete this business?">
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
                        <a href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="pagination-item">
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
                        echo "<a href='?page={$i}" . (!empty($search) ? '&search=' . urlencode($search) : '') . "' class='pagination-item {$activeClass}'>{$i}</a>";
                    }
                    
                    if ($endPage < $totalPages) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="pagination-item">
                            <span class="material-icons">navigate_next</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-icons">business</span>
                </div>
                <h3>No businesses found</h3>
                <?php if (!empty($search)): ?>
                    <p>No businesses matching "<?php echo $search; ?>" were found.</p>
                    <a href="index.php" class="btn btn-primary">Clear Search</a>
                <?php else: ?>
                    <p>Get started by adding your first business.</p>
                    <?php if (checkPermission('add_business')): ?>
                        <a href="add.php" class="btn btn-primary">Add Business</a>
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
?>