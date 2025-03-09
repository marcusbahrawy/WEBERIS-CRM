<?php
// modules/time/index.php - Time tracking list page
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_time')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Time Tracking";
$pageActions = '';

// Check if user can add time entries
if (checkPermission('add_time')) {
    $pageActions .= '<a href="add.php" class="btn btn-primary"><span class="material-icons">add</span> Add Time Entry</a>';
}

// Add timer button
$pageActions .= '<a href="timer.php" class="btn btn-secondary"><span class="material-icons">timer</span> Start Timer</a>';

// Filter parameters
$userId = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$projectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;
$taskId = isset($_GET['task_id']) && is_numeric($_GET['task_id']) ? (int)$_GET['task_id'] : null;

// Date range filter
$startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-d');

// Billable filter
$billableFilter = isset($_GET['billable']) ? sanitizeInput($_GET['billable']) : '';

// Database connection
$conn = connectDB();

// Get entity names if filters are applied
$userName = '';
$projectName = '';
$businessName = '';
$taskTitle = '';

if ($userId) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $userName = $result->fetch_assoc()['name'];
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

if ($businessId) {
    $stmt = $conn->prepare("SELECT name FROM businesses WHERE id = ?");
    $stmt->bind_param('i', $businessId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $businessName = $result->fetch_assoc()['name'];
    }
}

if ($taskId) {
    $stmt = $conn->prepare("SELECT title FROM tasks WHERE id = ?");
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $taskTitle = $result->fetch_assoc()['title'];
    }
}

// Search query
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total time entries count based on filters
$countQuery = "SELECT COUNT(*) as total FROM time_entries t
               LEFT JOIN users u ON t.user_id = u.id
               LEFT JOIN projects p ON t.project_id = p.id
               LEFT JOIN businesses b ON t.business_id = b.id
               LEFT JOIN tasks task ON t.task_id = task.id";

$countParams = [];
$countTypes = '';

$whereConditions = [];

// Apply date range filter
$whereConditions[] = "DATE(t.start_time) BETWEEN ? AND ?";
$countParams[] = $startDate;
$countParams[] = $endDate;
$countTypes .= 'ss';

// Apply filters to where conditions
if (!empty($search)) {
    $searchTerm = '%' . $conn->real_escape_string($search) . '%';
    $whereConditions[] = "(t.description LIKE ? OR u.name LIKE ? OR p.name LIKE ? OR b.name LIKE ? OR task.title LIKE ?)";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes .= 'sssss';
}

if (!empty($billableFilter)) {
    $whereConditions[] = "t.is_billable = ?";
    $countParams[] = ($billableFilter === 'yes') ? 1 : 0;
    $countTypes .= 'i';
}

if ($userId) {
    $whereConditions[] = "t.user_id = ?";
    $countParams[] = $userId;
    $countTypes .= 'i';
} else if (!checkPermission('view_time_reports')) {
    // If not an admin or manager, show only user's own time entries
    $whereConditions[] = "t.user_id = ?";
    $countParams[] = $_SESSION['user_id'];
    $countTypes .= 'i';
}

if ($projectId) {
    $whereConditions[] = "t.project_id = ?";
    $countParams[] = $projectId;
    $countTypes .= 'i';
}

if ($businessId) {
    $whereConditions[] = "t.business_id = ?";
    $countParams[] = $businessId;
    $countTypes .= 'i';
}

if ($taskId) {
    $whereConditions[] = "t.task_id = ?";
    $countParams[] = $taskId;
    $countTypes .= 'i';
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

// Get time entries with pagination
$query = "SELECT t.*, 
          u.name as user_name, 
          p.name as project_name,
          b.name as business_name,
          task.title as task_title
          FROM time_entries t
          LEFT JOIN users u ON t.user_id = u.id
          LEFT JOIN projects p ON t.project_id = p.id
          LEFT JOIN businesses b ON t.business_id = b.id
          LEFT JOIN tasks task ON t.task_id = task.id";

$queryParams = [];
$queryTypes = '';

// Apply where conditions to main query
if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(' AND ', $whereConditions);
}

// Add ordering and pagination
$query .= " ORDER BY t.start_time DESC
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

// Get all users for filter dropdown
$usersQuery = "SELECT id, name FROM users ORDER BY name ASC";
$usersResult = $conn->query($usersQuery);
$users = [];
if ($usersResult->num_rows > 0) {
    while ($user = $usersResult->fetch_assoc()) {
        $users[] = $user;
    }
}

// Calculate total time and billable time
$totalSeconds = 0;
$billableSeconds = 0;
$timeEntries = [];

// Process time entries and calculate totals
if ($result->num_rows > 0) {
    while ($entry = $result->fetch_assoc()) {
        // Calculate duration if end_time exists and duration is not set
        if ($entry['end_time'] && !$entry['duration']) {
            $start = new DateTime($entry['start_time']);
            $end = new DateTime($entry['end_time']);
            $duration = $end->getTimestamp() - $start->getTimestamp();
            $entry['duration'] = $duration;
        }
        
        // Add to totals
        if ($entry['duration'] > 0) {
            $totalSeconds += $entry['duration'];
            
            if ($entry['is_billable']) {
                $billableSeconds += $entry['duration'];
            }
        }
        
        $timeEntries[] = $entry;
    }
}

// Format total time
function formatTime($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    return sprintf("%02d:%02d", $hours, $minutes);
}

// Calculate billable percentage
$billablePercentage = ($totalSeconds > 0) ? round(($billableSeconds / $totalSeconds) * 100) : 0;

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>
            <?php 
            if ($taskId) {
                echo "Time for Task: " . $taskTitle;
            } elseif ($projectId) {
                echo "Time for Project: " . $projectName;
            } elseif ($businessId) {
                echo "Time for " . $businessName;
            } elseif ($userId) {
                echo "Time for " . $userName;
            } else {
                echo "Time Tracking";
            }
            ?>
        </h2>
        <div class="card-header-actions">
            <form action="" method="GET" class="search-form">
                <?php if ($userId): ?>
                    <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                <?php endif; ?>
                <?php if ($projectId): ?>
                    <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                <?php endif; ?>
                <?php if ($businessId): ?>
                    <input type="hidden" name="business_id" value="<?php echo $businessId; ?>">
                <?php endif; ?>
                <?php if ($taskId): ?>
                    <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                <?php endif; ?>
                <div class="search-input">
                    <span class="material-icons">search</span>
                    <input type="text" name="search" placeholder="Search time entries..." value="<?php echo $search; ?>">
                    <button type="submit" class="btn btn-text">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="?<?php 
                            $params = [];
                            if ($userId) $params[] = 'user_id=' . $userId;
                            if ($projectId) $params[] = 'project_id=' . $projectId;
                            if ($businessId) $params[] = 'business_id=' . $businessId;
                            if ($taskId) $params[] = 'task_id=' . $taskId;
                            echo implode('&', $params);
                        ?>" class="btn btn-text">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Time summary and filters -->
        <div class="time-summary">
            <div class="summary-stats">
                <div class="stat-card">
                    <span class="stat-label">Total Time</span>
                    <span class="stat-value"><?php echo formatTime($totalSeconds); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Billable Time</span>
                    <span class="stat-value"><?php echo formatTime($billableSeconds); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Billable Percentage</span>
                    <span class="stat-value"><?php echo $billablePercentage; ?>%</span>
                </div>
            </div>
            
            <div class="time-filters">
                <form action="" method="GET" class="filter-form">
                    <?php if ($userId): ?>
                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                    <?php endif; ?>
                    <?php if ($projectId): ?>
                        <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                    <?php endif; ?>
                    <?php if ($businessId): ?>
                        <input type="hidden" name="business_id" value="<?php echo $businessId; ?>">
                    <?php endif; ?>
                    <?php if ($taskId): ?>
                        <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                    <?php endif; ?>
                    <?php if (!empty($search)): ?>
                        <input type="hidden" name="search" value="<?php echo $search; ?>">
                    <?php endif; ?>
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="start_date">From:</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>" class="form-control">
                        </div>
                        
                        <div class="filter-group">
                            <label for="end_date">To:</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>" class="form-control">
                        </div>
                        
                        <?php if (checkPermission('view_time_reports') && empty($userId)): ?>
                        <div class="filter-group">
                            <label for="user_filter">User:</label>
                            <select id="user_filter" name="user_id" class="form-control">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo ($userId == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo $user['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="filter-group">
                            <label for="billable">Billable:</label>
                            <select id="billable" name="billable" class="form-control">
                                <option value="">All</option>
                                <option value="yes" <?php echo ($billableFilter === 'yes') ? 'selected' : ''; ?>>Billable Only</option>
                                <option value="no" <?php echo ($billableFilter === 'no') ? 'selected' : ''; ?>>Non-billable Only</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="index.php" class="btn btn-text">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
            <div class="alert alert-success">Time entry deleted successfully.</div>
        <?php endif; ?>
        
        <?php if (count($timeEntries) > 0): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <?php if (empty($userId) || checkPermission('view_time_reports')): ?>
                                <th>User</th>
                            <?php endif; ?>
                            <?php if (empty($taskId)): ?>
                                <th>Task</th>
                            <?php endif; ?>
                            <?php if (empty($projectId)): ?>
                                <th>Project</th>
                            <?php endif; ?>
                            <?php if (empty($businessId)): ?>
                                <th>Business</th>
                            <?php endif; ?>
                            <th>Description</th>
                            <th>Billable</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timeEntries as $entry): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($entry['start_time'])); ?></td>
                                <td><?php echo date('H:i', strtotime($entry['start_time'])); ?> - 
                                    <?php echo $entry['end_time'] ? date('H:i', strtotime($entry['end_time'])) : 'Running'; ?>
                                </td>
                                <td><?php echo $entry['duration'] ? formatTime($entry['duration']) : 'Running'; ?></td>
                                <?php if (empty($userId) || checkPermission('view_time_reports')): ?>
                                    <td>
                                        <a href="?user_id=<?php echo $entry['user_id']; ?>">
                                            <?php echo $entry['user_name']; ?>
                                        </a>
                                    </td>
                                <?php endif; ?>
                                <?php if (empty($taskId)): ?>
                                    <td>
                                        <?php if ($entry['task_id']): ?>
                                            <a href="?task_id=<?php echo $entry['task_id']; ?>">
                                                <?php echo $entry['task_title']; ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if (empty($projectId)): ?>
                                    <td>
                                        <?php if ($entry['project_id']): ?>
                                            <a href="?project_id=<?php echo $entry['project_id']; ?>">
                                                <?php echo $entry['project_name']; ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if (empty($businessId)): ?>
                                    <td>
                                        <?php if ($entry['business_id']): ?>
                                            <a href="?business_id=<?php echo $entry['business_id']; ?>">
                                                <?php echo $entry['business_name']; ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td><?php echo $entry['description'] ? $entry['description'] : '-'; ?></td>
                                <td>
                                    <span class="billable-badge <?php echo $entry['is_billable'] ? 'billable' : 'non-billable'; ?>">
                                        <?php echo $entry['is_billable'] ? 'Billable' : 'Non-billable'; ?>
                                    </span>
                                </td>
                                <td class="actions-cell">
                                    <?php if (checkPermission('edit_time') && ($entry['user_id'] == $_SESSION['user_id'] || checkPermission('view_time_reports'))): ?>
                                        <a href="edit.php?id=<?php echo $entry['id']; ?>" class="btn btn-icon btn-text" title="Edit">
                                            <span class="material-icons">edit</span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (checkPermission('delete_time') && ($entry['user_id'] == $_SESSION['user_id'] || checkPermission('view_time_reports'))): ?>
                                        <a href="delete.php?id=<?php echo $entry['id']; ?>" class="btn btn-icon btn-text delete-item" 
                                           title="Delete" data-confirm="Are you sure you want to delete this time entry?">
                                            <span class="material-icons">delete</span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?><?php 
                            if (!empty($search)) echo '&search=' . urlencode($search);
                            if ($userId) echo '&user_id=' . $userId;
                            if ($projectId) echo '&project_id=' . $projectId;
                            if ($businessId) echo '&business_id=' . $businessId;
                            if ($taskId) echo '&task_id=' . $taskId;
                            if (!empty($billableFilter)) echo '&billable=' . urlencode($billableFilter);
                            echo '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate);
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
                        if ($userId) $url .= '&user_id=' . $userId;
                        if ($projectId) $url .= '&project_id=' . $projectId;
                        if ($businessId) $url .= '&business_id=' . $businessId;
                        if ($taskId) $url .= '&task_id=' . $taskId;
                        if (!empty($billableFilter)) $url .= '&billable=' . urlencode($billableFilter);
                        $url .= '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate);
                        
                        echo "<a href='{$url}' class='pagination-item {$activeClass}'>{$i}</a>";
                    }
                    
                    if ($endPage < $totalPages) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo ($page + 1); ?><?php 
                            if (!empty($search)) echo '&search=' . urlencode($search);
                            if ($userId) echo '&user_id=' . $userId;
                            if ($projectId) echo '&project_id=' . $projectId;
                            if ($businessId) echo '&business_id=' . $businessId;
                            if ($taskId) echo '&task_id=' . $taskId;
                            if (!empty($billableFilter)) echo '&billable=' . urlencode($billableFilter);
                            echo '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate);
                        ?>" class="pagination-item">
                            <span class="material-icons">navigate_next</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-icons">timer</span>
                </div>
                <h3>No time entries found</h3>
                <?php if (!empty($search) || !empty($billableFilter) || $userId || $projectId || $businessId || $taskId): ?>
                    <p>
                        No time entries match your filters.
                    </p>
                    <a href="index.php" class="btn btn-primary">View All Time Entries</a>
                <?php else: ?>
                    <p>Get started by tracking your time on tasks and projects.</p>
                    <?php if (checkPermission('add_time')): ?>
                        <div class="empty-actions">
                            <a href="add.php" class="btn btn-primary">Add Time Entry</a>
                            <a href="timer.php" class="btn btn-secondary">Start Timer</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Time tracking specific styles */
.time-summary {
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--grey-200);
}

.summary-stats {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.stat-card {
    background-color: var(--grey-50);
    border-radius: var(--border-radius-md);
    padding: var(--spacing-md);
    flex: 1;
    min-width: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.stat-label {
    font-size: var(--font-size-sm);
    color: var(--grey-600);
    margin-bottom: var(--spacing-xs);
}

.stat-value {
    font-size: var(--font-size-xl);
    font-weight: var(--font-weight-bold);
    color: var(--primary-color);
}

.time-filters {
    margin-top: var(--spacing-md);
}

.filter-form {
    background-color: var(--grey-50);
    border-radius: var(--border-radius-md);
    padding: var(--spacing-md);
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    min-width: 150px;
}

.filter-group label {
    font-size: var(--font-size-sm);
    margin-bottom: var(--spacing-xs);
    font-weight: var(--font-weight-medium);
}

.billable-badge {
    display: inline-flex;
    align-items: center;
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: 16px;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
}

.billable {
    background-color: rgba(46, 196, 182, 0.15);
    color: #0d6962;
}

.non-billable {
    background-color: rgba(230, 57, 70, 0.15);
    color: #a61a24;
}

.empty-actions {
    display: flex;
    gap: var(--spacing-md);
    justify-content: center;
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    
    .filter-group {
        width: 100%;
    }
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