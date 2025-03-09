<?php
// modules/tasks/index.php - Tasks listing page
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_task')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Tasks";
$pageActions = '';

// Check if user can add tasks
if (checkPermission('add_task')) {
    $pageActions .= '<a href="add.php" class="btn btn-primary"><span class="material-icons">add</span> Add Task</a>';
}

// Filter parameters
$assignedTo = isset($_GET['assigned_to']) && is_numeric($_GET['assigned_to']) ? (int)$_GET['assigned_to'] : null;
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;
$projectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;

// Get entity names if filters are applied
$conn = connectDB();
$businessName = '';
$projectName = '';
$assignedToName = '';

if ($businessId) {
    $stmt = $conn->prepare("SELECT name FROM businesses WHERE id = ?");
    $stmt->bind_param('i', $businessId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $businessName = $result->fetch_assoc()['name'];
    }
}

if ($projectId) {
    $stmt = $conn->prepare("SELECT name FROM projects WHERE id = ?");
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $projectName = $result->fetch_assoc()['name'];
    }
}

if ($assignedTo) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param('i', $assignedTo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $assignedToName = $result->fetch_assoc()['name'];
    }
}

// Search query
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Status filter
$statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Get all possible statuses
$statusesResult = $conn->query("SELECT DISTINCT status FROM tasks ORDER BY FIELD(status, 'pending', 'in_progress', 'completed', 'canceled')");
$statuses = [];
if ($statusesResult && $statusesResult->num_rows > 0) {
    while ($status = $statusesResult->fetch_assoc()) {
        $statuses[] = $status['status'];
    }
} else {
    // Default statuses if no tasks yet
    $statuses = ['pending', 'in_progress', 'completed', 'canceled'];
}

// Priority filter
$priorityFilter = isset($_GET['priority']) ? sanitizeInput($_GET['priority']) : '';
$priorities = ['low', 'medium', 'high', 'urgent'];

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total tasks count based on filters
$countQuery = "SELECT COUNT(*) as total FROM tasks t
               LEFT JOIN businesses b ON t.business_id = b.id
               LEFT JOIN projects p ON t.project_id = p.id
               LEFT JOIN users u ON t.assigned_to = u.id";

$countParams = [];
$countTypes = '';

$whereConditions = [];

// Apply filters to where conditions
if (!empty($search)) {
    $searchTerm = '%' . $conn->real_escape_string($search) . '%';
    $whereConditions[] = "(t.title LIKE ? OR t.description LIKE ? OR b.name LIKE ?)";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes .= 'sss';
}

if (!empty($statusFilter)) {
    $whereConditions[] = "t.status = ?";
    $countParams[] = $statusFilter;
    $countTypes .= 's';
}

if (!empty($priorityFilter)) {
    $whereConditions[] = "t.priority = ?";
    $countParams[] = $priorityFilter;
    $countTypes .= 's';
}

if ($businessId) {
    $whereConditions[] = "t.business_id = ?";
    $countParams[] = $businessId;
    $countTypes .= 'i';
}

if ($projectId) {
    $whereConditions[] = "t.project_id = ?";
    $countParams[] = $projectId;
    $countTypes .= 'i';
}

if ($assignedTo) {
    $whereConditions[] = "t.assigned_to = ?";
    $countParams[] = $assignedTo;
    $countTypes .= 'i';
} else {
    // If no specific assignment filter, show tasks assigned to current user or created by current user
    $whereConditions[] = "(t.assigned_to = ? OR t.created_by = ?)";
    $countParams[] = $_SESSION['user_id'];
    $countParams[] = $_SESSION['user_id'];
    $countTypes .= 'ii';
}

// Apply where conditions to count query
if (!empty($whereConditions)) {
    $countQuery .= " WHERE " . implode(' AND ', $whereConditions);
}

// Execute count query with parameters
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

// Get tasks with pagination
$query = "SELECT t.*, 
          b.name as business_name, 
          p.name as project_name,
          u.name as assigned_to_name,
          creator.name as created_by_name
          FROM tasks t
          LEFT JOIN businesses b ON t.business_id = b.id
          LEFT JOIN projects p ON t.project_id = p.id
          LEFT JOIN users u ON t.assigned_to = u.id
          LEFT JOIN users creator ON t.created_by = creator.id";

$queryParams = [];
$queryTypes = '';

// Apply where conditions to main query
if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(' AND ', $whereConditions);
}

// Add ordering and pagination
$query .= " ORDER BY 
    CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END, 
    CASE t.priority 
        WHEN 'urgent' THEN 1 
        WHEN 'high' THEN 2 
        WHEN 'medium' THEN 3 
        WHEN 'low' THEN 4 
    END,
    CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END,
    t.due_date ASC,
    t.created_at DESC 
    LIMIT ? OFFSET ?";

$queryParams = array_merge($countParams, [$perPage, $offset]);
$queryTypes .= 'ii';

// Execute main query with parameters
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
            if ($assignedTo && $assignedTo == $_SESSION['user_id']) {
                echo "My Tasks";
            } elseif ($assignedTo) {
                echo "Tasks assigned to " . $assignedToName;
            } elseif ($businessId) {
                echo "Tasks for " . $businessName;
            } elseif ($projectId) {
                echo "Tasks for project: " . $projectName;
            } else {
                echo "All Tasks";
            }
            ?>
        </h2>
        <div class="card-header-actions">
            <form action="" method="GET" class="search-form">
                <?php if ($businessId): ?>
                    <input type="hidden" name="business_id" value="<?php echo $businessId; ?>">
                <?php endif; ?>
                <?php if ($projectId): ?>
                    <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                <?php endif; ?>
                <?php if ($assignedTo): ?>
                    <input type="hidden" name="assigned_to" value="<?php echo $assignedTo; ?>">
                <?php endif; ?>
                <div class="search-input">
                    <span class="material-icons">search</span>
                    <input type="text" name="search" placeholder="Search tasks..." value="<?php echo $search; ?>">
                    <button type="submit" class="btn btn-text">Search</button>
                    <?php if (!empty($search) || !empty($statusFilter) || !empty($priorityFilter)): ?>
                        <a href="?<?php 
                            $params = [];
                            if ($businessId) $params[] = 'business_id=' . $businessId;
                            if ($projectId) $params[] = 'project_id=' . $projectId;
                            if ($assignedTo) $params[] = 'assigned_to=' . $assignedTo;
                            echo implode('&', $params);
                        ?>" class="btn btn-text">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
            <div class="alert alert-success">Task deleted successfully.</div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filters-container mb-lg">
            <div class="filter-group">
                <label class="filter-label">Status:</label>
                <div class="filter-options">
                    <a href="?<?php 
                        $params = [];
                        if (!empty($search)) $params[] = 'search=' . urlencode($search);
                        if (!empty($priorityFilter)) $params[] = 'priority=' . urlencode($priorityFilter);
                        if ($businessId) $params[] = 'business_id=' . $businessId;
                        if ($projectId) $params[] = 'project_id=' . $projectId;
                        if ($assignedTo) $params[] = 'assigned_to=' . $assignedTo;
                        echo implode('&', $params);
                    ?>" class="filter-option <?php echo empty($statusFilter) ? 'active' : ''; ?>">All</a>
                    
                    <?php foreach ($statuses as $status): ?>
                        <a href="?status=<?php echo urlencode($status); ?><?php 
                            if (!empty($search)) echo '&search=' . urlencode($search);
                            if (!empty($priorityFilter)) echo '&priority=' . urlencode($priorityFilter);
                            if ($businessId) echo '&business_id=' . $businessId;
                            if ($projectId) echo '&project_id=' . $projectId;
                            if ($assignedTo) echo '&assigned_to=' . $assignedTo;
                        ?>" class="filter-option <?php echo $statusFilter === $status ? 'active' : ''; ?>">
                            <?php echo ucfirst($status); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Priority:</label>
                <div class="filter-options">
                    <a href="?<?php 
                        $params = [];
                        if (!empty($search)) $params[] = 'search=' . urlencode($search);
                        if (!empty($statusFilter)) $params[] = 'status=' . urlencode($statusFilter);
                        if ($businessId) $params[] = 'business_id=' . $businessId;
                        if ($projectId) $params[] = 'project_id=' . $projectId;
                        if ($assignedTo) $params[] = 'assigned_to=' . $assignedTo;
                        echo implode('&', $params);
                    ?>" class="filter-option <?php echo empty($priorityFilter) ? 'active' : ''; ?>">All</a>
                    
                    <?php foreach ($priorities as $priority): ?>
                        <a href="?priority=<?php echo urlencode($priority); ?><?php 
                            if (!empty($search)) echo '&search=' . urlencode($search);
                            if (!empty($statusFilter)) echo '&status=' . urlencode($statusFilter);
                            if ($businessId) echo '&business_id=' . $businessId;
                            if ($projectId) echo '&project_id=' . $projectId;
                            if ($assignedTo) echo '&assigned_to=' . $assignedTo;
                        ?>" class="filter-option <?php echo $priorityFilter === $priority ? 'active' : ''; ?>">
                            <?php echo ucfirst($priority); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if (!$assignedTo): ?>
            <div class="filter-group">
                <a href="?assigned_to=<?php echo $_SESSION['user_id']; ?><?php 
                    if (!empty($search)) echo '&search=' . urlencode($search);
                    if (!empty($statusFilter)) echo '&status=' . urlencode($statusFilter);
                    if (!empty($priorityFilter)) echo '&priority=' . urlencode($priorityFilter);
                    if ($businessId) echo '&business_id=' . $businessId;
                    if ($projectId) echo '&project_id=' . $projectId;
                ?>" class="btn btn-outline">
                    <span class="material-icons">person</span> My Tasks
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($result->num_rows > 0): ?>
            <div class="task-list">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="task-card <?php echo $row['status']; ?>">
                        <div class="task-priority priority-<?php echo $row['priority']; ?>"></div>
                        <div class="task-content">
                            <div class="task-header">
                                <h3 class="task-title">
                                    <a href="view.php?id=<?php echo $row['id']; ?>"><?php echo $row['title']; ?></a>
                                </h3>
                                <div class="task-status">
                                    <span class="status-badge status-<?php echo $row['status']; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="task-meta">
                                <?php if ($row['business_id']): ?>
                                    <div class="meta-item">
                                        <span class="material-icons">business</span>
                                        <a href="../businesses/view.php?id=<?php echo $row['business_id']; ?>"><?php echo $row['business_name']; ?></a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($row['project_id']): ?>
                                    <div class="meta-item">
                                        <span class="material-icons">assignment</span>
                                        <a href="../projects/view.php?id=<?php echo $row['project_id']; ?>"><?php echo $row['project_name']; ?></a>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="meta-item">
                                    <span class="material-icons">person</span>
                                    <?php echo $row['assigned_to'] ? $row['assigned_to_name'] : 'Unassigned'; ?>
                                </div>
                                
                                <?php if ($row['due_date']): ?>
                                    <?php 
                                    $dueDate = new DateTime($row['due_date']);
                                    $today = new DateTime();
                                    $isOverdue = $row['status'] !== 'completed' && $dueDate < $today;
                                    $isToday = $dueDate->format('Y-m-d') === $today->format('Y-m-d');
                                    ?>
                                    <div class="meta-item <?php echo $isOverdue ? 'overdue' : ($isToday ? 'due-today' : ''); ?>">
                                        <span class="material-icons">event</span>
                                        <?php echo date('M j, Y', strtotime($row['due_date'])); ?>
                                        <?php if ($isOverdue): ?>
                                            <span class="due-label overdue">Overdue</span>
                                        <?php elseif ($isToday): ?>
                                            <span class="due-label due-today">Today</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($row['description'])): ?>
                                <div class="task-description">
                                    <?php echo nl2br(substr($row['description'], 0, 150) . (strlen($row['description']) > 150 ? '...' : '')); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="task-footer">
                                <div class="task-created">
                                    <small>Created <?php echo date('M j, Y', strtotime($row['created_at'])); ?> by <?php echo $row['created_by_name']; ?></small>
                                </div>
                                <div class="task-actions">
                                    <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-text btn-sm">View</a>
                                    
                                    <?php if (checkPermission('edit_task') && ($row['assigned_to'] == $_SESSION['user_id'] || $row['created_by'] == $_SESSION['user_id'])): ?>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-text btn-sm">Edit</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($row['status'] !== 'completed' && $row['assigned_to'] == $_SESSION['user_id']): ?>
                                        <a href="complete.php?id=<?php echo $row['id']; ?>" class="btn btn-text btn-sm complete-task">Complete</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?><?php 
                            if (!empty($search)) echo '&search=' . urlencode($search);
                            if (!empty($statusFilter)) echo '&status=' . urlencode($statusFilter);
                            if (!empty($priorityFilter)) echo '&priority=' . urlencode($priorityFilter);
                            if ($businessId) echo '&business_id=' . $businessId;
                            if ($projectId) echo '&project_id=' . $projectId;
                            if ($assignedTo) echo '&assigned_to=' . $assignedTo;
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
                        
                        if (!empty($search)) $url .= '&search=' . urlencode($search);
                        if (!empty($statusFilter)) $url .= '&status=' . urlencode($statusFilter);
                        if (!empty($priorityFilter)) $url .= '&priority=' . urlencode($priorityFilter);
                        if ($businessId) $url .= '&business_id=' . $businessId;
                        if ($projectId) $url .= '&project_id=' . $projectId;
                        if ($assignedTo) $url .= '&assigned_to=' . $assignedTo;
                        
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
                            if (!empty($priorityFilter)) echo '&priority=' . urlencode($priorityFilter);
                            if ($businessId) echo '&business_id=' . $businessId;
                            if ($projectId) echo '&project_id=' . $projectId;
                            if ($assignedTo) echo '&assigned_to=' . $assignedTo;
                        ?>" class="pagination-item">
                            <span class="material-icons">navigate_next</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-icons">check_box</span>
                </div>
                <h3>No tasks found</h3>
                <?php if (!empty($search) || !empty($statusFilter) || !empty($priorityFilter) || $businessId || $projectId || $assignedTo): ?>
                    <p>
                        No tasks matching your filters were found.
                    </p>
                    <a href="index.php" class="btn btn-primary">View All Tasks</a>
                <?php else: ?>
                    <p>Get started by creating your first task.</p>
                    <?php if (checkPermission('add_task')): ?>
                        <a href="add.php" class="btn btn-primary">Add Task</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Task-specific styles */
.filters-container {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
    border-bottom: 1px solid var(--grey-200);
    padding-bottom: var(--spacing-md);
}

.filter-group {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.filter-label {
    font-weight: var(--font-weight-medium);
    color: var(--grey-700);
}

.filter-options {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-xs);
}

.filter-option {
    padding: 4px 12px;
    border-radius: 16px;
    font-size: var(--font-size-sm);
    background-color: var(--grey-100);
    color: var(--grey-700);
    transition: all var(--transition-fast);
}

.filter-option:hover {
    background-color: var(--grey-200);
    text-decoration: none;
}

.filter-option.active {
    background-color: var(--primary-color);
    color: white;
}

.task-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.task-card {
    display: flex;
    background-color: white;
    border-radius: var(--border-radius-lg);
    box-shadow: var(--box-shadow);
    overflow: hidden;
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
    position: relative;
}

.task-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--box-shadow-lg);
}

.task-card.completed {
    opacity: 0.7;
}

.task-priority {
    width: 6px;
    flex-shrink: 0;
}

.priority-low {
    background-color: var(--info-color);
}

.priority-medium {
    background-color: var(--warning-color);
}

.priority-high {
    background-color: var(--secondary-color);
}

.priority-urgent {
    background-color: var(--danger-color);
}

.task-content {
    flex: 1;
    padding: var(--spacing-md);
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-sm);
}

.task-title {
    margin: 0;
    font-size: var(--font-size-lg);
}

.task-meta {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-sm);
    font-size: var(--font-size-sm);
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
    color: var(--grey-600);
}

.meta-item .material-icons {
    font-size: 16px;
}

.meta-item.overdue {
    color: var(--danger-color);
}

.meta-item.due-today {
    color: var(--warning-color);
}

.due-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: var(--font-weight-medium);
    color: white;
    margin-left: 4px;
}

.due-label.overdue {
    background-color: var(--danger-color);
}

.due-label.due-today {
    background-color: var(--warning-color);
}

.task-description {
    margin-bottom: var(--spacing-md);
    color: var(--grey-700);
    font-size: var(--font-size-sm);
}

.task-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid var(--grey-200);
    padding-top: var(--spacing-sm);
    margin-top: var(--spacing-sm);
}

.task-created {
    color: var(--grey-500);
    font-size: var(--font-size-xs);
}

.task-actions {
    display: flex;
    gap: var(--spacing-xs);
}

.btn-sm {
    padding: 3px 8px;
    font-size: var(--font-size-xs);
}

.status-pending {
    background-color: rgba(58, 191, 248, 0.15);
    color: #085783;
}

.status-in_progress {
    background-color: rgba(251, 189, 35, 0.15);
    color: #946000;
}

.status-completed {
    background-color: rgba(46, 196, 182, 0.15);
    color: #0d6962;
}

.status-canceled {
    background-color: rgba(230, 57, 70, 0.15);
    color: #a61a24;
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Setup complete task confirmation
        const completeLinks = document.querySelectorAll('.complete-task');
        completeLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Mark this task as completed?')) {
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