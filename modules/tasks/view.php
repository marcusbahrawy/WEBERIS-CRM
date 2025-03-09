<?php
// modules/tasks/view.php - View task details
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_task')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$taskId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Get task data with related information
$stmt = $conn->prepare("SELECT t.*, 
                      b.name as business_name, 
                      p.name as project_name,
                      u.name as assigned_to_name,
                      u.email as assigned_to_email,
                      creator.name as created_by_name
                      FROM tasks t
                      LEFT JOIN businesses b ON t.business_id = b.id
                      LEFT JOIN projects p ON t.project_id = p.id
                      LEFT JOIN users u ON t.assigned_to = u.id
                      LEFT JOIN users creator ON t.created_by = creator.id
                      WHERE t.id = ?");
$stmt->bind_param('i', $taskId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$task = $result->fetch_assoc();

// Check if task is editable by current user
$isTaskEditable = checkPermission('edit_task') && ($task['assigned_to'] == $_SESSION['user_id'] || $task['created_by'] == $_SESSION['user_id']);

// Handle task comment submission
$commentError = '';
$commentSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $commentError = "Invalid request. Please try again.";
    } else {
        $comment = sanitizeInput($_POST['comment']);
        
        if (empty($comment)) {
            $commentError = "Comment cannot be empty.";
        } else {
            // Insert comment
            $stmt = $conn->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->bind_param('iis', $taskId, $_SESSION['user_id'], $comment);
            
            if ($stmt->execute()) {
                $commentSuccess = "Comment added successfully.";
                
                // Send notification to task owner if commenter is not the task owner
                if ($task['created_by'] != $_SESSION['user_id']) {
                    // Create notification
                    $notificationTitle = "New comment on your task";
                    $notificationMessage = $_SESSION['name'] . " commented on your task: " . $task['title'];
                    $notificationLink = "/modules/tasks/view.php?id=" . $taskId;
                    
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, related_id) VALUES (?, 'task_comment', ?, ?, ?, ?)");
                    $stmt->bind_param('isssi', $task['created_by'], $notificationTitle, $notificationMessage, $notificationLink, $taskId);
                    $stmt->execute();
                }
                
                // Send notification to assigned user if different from commenter and task owner
                if ($task['assigned_to'] && $task['assigned_to'] != $_SESSION['user_id'] && $task['assigned_to'] != $task['created_by']) {
                    // Create notification
                    $notificationTitle = "New comment on assigned task";
                    $notificationMessage = $_SESSION['name'] . " commented on a task assigned to you: " . $task['title'];
                    $notificationLink = "/modules/tasks/view.php?id=" . $taskId;
                    
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, related_id) VALUES (?, 'task_comment', ?, ?, ?, ?)");
                    $stmt->bind_param('isssi', $task['assigned_to'], $notificationTitle, $notificationMessage, $notificationLink, $taskId);
                    $stmt->execute();
                }
                
                // Redirect to prevent form resubmission
                header("Location: view.php?id=" . $taskId . "&comment_success=1");
                exit;
            } else {
                $commentError = "Error adding comment: " . $conn->error;
            }
        }
    }
}

// Get task comments
$stmt = $conn->prepare("SELECT c.*, u.name as user_name 
                      FROM task_comments c
                      JOIN users u ON c.user_id = u.id
                      WHERE c.task_id = ?
                      ORDER BY c.created_at ASC");
$stmt->bind_param('i', $taskId);
$stmt->execute();
$commentsResult = $stmt->get_result();
$comments = [];

while ($comment = $commentsResult->fetch_assoc()) {
    $comments[] = $comment;
}

// Handle task status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $newStatus = sanitizeInput($_POST['status']);
        $validStatuses = ['pending', 'in_progress', 'completed', 'canceled'];
        
        if (!in_array($newStatus, $validStatuses)) {
            $error = "Invalid status.";
        } else {
            // Update status
            $completedAt = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;
            
            $stmt = $conn->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?");
            $stmt->bind_param('ssi', $newStatus, $completedAt, $taskId);
            
            if ($stmt->execute()) {
                // Create a system comment about status change
                $statusComment = "Status changed to: " . ucfirst($newStatus);
                if ($newStatus === 'completed') {
                    $statusComment .= " (Task completed)";
                }
                
                $stmt = $conn->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
                $stmt->bind_param('iis', $taskId, $_SESSION['user_id'], $statusComment);
                $stmt->execute();
                
                // Send notification to task owner if updater is not the task owner
                if ($task['created_by'] != $_SESSION['user_id']) {
                    // Create notification
                    $notificationTitle = "Task status updated";
                    $notificationMessage = $_SESSION['name'] . " changed the status of your task to " . ucfirst($newStatus) . ": " . $task['title'];
                    $notificationLink = "/modules/tasks/view.php?id=" . $taskId;
                    
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, related_id) VALUES (?, 'task_update', ?, ?, ?, ?)");
                    $stmt->bind_param('isssi', $task['created_by'], $notificationTitle, $notificationMessage, $notificationLink, $taskId);
                    $stmt->execute();
                }
                
                // Redirect to refresh page
                header("Location: view.php?id=" . $taskId . "&status_success=1");
                exit;
            } else {
                $error = "Error updating status: " . $conn->error;
            }
        }
    }
}

// Page title
$pageTitle = $task['title'];
$pageActions = '';

// Add edit button if user has permission
if ($isTaskEditable) {
    $pageActions .= '<a href="edit.php?id=' . $taskId . '" class="btn btn-primary"><span class="material-icons">edit</span> Edit</a>';
}

// Add delete button if user created the task and has permission
if (checkPermission('delete_task') && $task['created_by'] == $_SESSION['user_id']) {
    $pageActions .= '<a href="delete.php?id=' . $taskId . '" class="btn btn-danger delete-task" data-confirm="Are you sure you want to delete this task?"><span class="material-icons">delete</span> Delete</a>';
}

// Include header
include '../../includes/header.php';

// Check for success message
$successMessage = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $successMessage = 'Task created successfully.';
            break;
        case 'updated':
            $successMessage = 'Task updated successfully.';
            break;
    }
}

if (isset($_GET['comment_success'])) {
    $successMessage = 'Comment added successfully.';
}

if (isset($_GET['status_success'])) {
    $successMessage = 'Task status updated successfully.';
}
?>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success"><?php echo $successMessage; ?></div>
<?php endif; ?>

<div class="task-details">
    <div class="card">
        <div class="card-header">
            <h2>Task Details</h2>
            <div class="card-header-actions">
                <a href="index.php" class="btn btn-text">
                    <span class="material-icons">arrow_back</span> Back to Tasks
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <div class="task-header mb-xl">
                <div class="task-title-section">
                    <h2 class="task-name"><?php echo $task['title']; ?></h2>
                    <div class="task-labels">
                        <span class="status-badge status-<?php echo $task['status']; ?>">
                            <?php echo ucfirst($task['status']); ?>
                        </span>
                        <span class="priority-badge priority-<?php echo $task['priority']; ?>">
                            <?php echo ucfirst($task['priority']); ?> Priority
                        </span>
                    </div>
                </div>
                
                <?php if ($task['assigned_to'] == $_SESSION['user_id'] && $task['status'] != 'completed'): ?>
                <div class="task-status-actions">
                    <form method="POST" action="" class="status-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="update_status" value="1">
                        <select name="status" class="form-control status-select">
                            <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="canceled" <?php echo $task['status'] === 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($task['status'] == 'completed' && $task['completed_at']): ?>
            <div class="task-complete-info mb-lg">
                <div class="info-box success">
                    <span class="material-icons">check_circle</span>
                    <span>This task was completed on <?php echo date('F j, Y \a\t g:i a', strtotime($task['completed_at'])); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="detail-grid">
                <div class="detail-item full-width">
                    <div class="detail-label">Description</div>
                    <div class="detail-value"><?php echo nl2br($task['description'] ?? 'N/A'); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Assigned To</div>
                    <div class="detail-value"><?php echo $task['assigned_to'] ? $task['assigned_to_name'] : 'Unassigned'; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Due Date</div>
                    <div class="detail-value">
                        <?php if ($task['due_date']): ?>
                            <?php 
                            $dueDate = new DateTime($task['due_date']);
                            $today = new DateTime();
                            $isOverdue = $task['status'] !== 'completed' && $dueDate < $today;
                            ?>
                            <span <?php echo $isOverdue ? 'class="text-danger"' : ''; ?>>
                                <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                <?php if ($isOverdue): ?>
                                    <span class="badge badge-danger">Overdue</span>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            No due date
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Business</div>
                    <div class="detail-value">
                        <?php if ($task['business_id']): ?>
                            <a href="../businesses/view.php?id=<?php echo $task['business_id']; ?>">
                                <?php echo $task['business_name']; ?>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Project</div>
                    <div class="detail-value">
                        <?php if ($task['project_id']): ?>
                            <a href="../projects/view.php?id=<?php echo $task['project_id']; ?>">
                                <?php echo $task['project_name']; ?>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created By</div>
                    <div class="detail-value"><?php echo $task['created_by_name']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created On</div>
                    <div class="detail-value"><?php echo date('M j, Y, g:i a', strtotime($task['created_at'])); ?></div>
                </div>
            </div>
            
            <?php if ($isTaskEditable): ?>
            <div class="form-actions mt-xl">
                <a href="edit.php?id=<?php echo $taskId; ?>" class="btn btn-primary">
                    <span class="material-icons">edit</span> Edit Task
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Task Comments Section -->
    <div class="card mt-lg">
        <div class="card-header">
            <h2>Comments</h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($commentError)): ?>
                <div class="alert alert-danger"><?php echo $commentError; ?></div>
            <?php endif; ?>
            
            <div class="comments-list">
                <?php if (count($comments) > 0): ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment-item">
                            <div class="comment-header">
                                <div class="comment-author"><?php echo $comment['user_name']; ?></div>
                                <div class="comment-date"><?php echo date('M j, Y, g:i a', strtotime($comment['created_at'])); ?></div>
                            </div>
                            <div class="comment-content">
                                <?php echo nl2br($comment['comment']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-comments">No comments yet.</div>
                <?php endif; ?>
            </div>
            
            <!-- Add Comment Form -->
            <div class="comment-form mt-xl">
                <h3>Add a Comment</h3>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="add_comment" value="1">
                    
                    <div class="form-group">
                        <textarea name="comment" class="form-control" rows="3" placeholder="Write your comment here..." required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Add Comment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Task details specific styles */
.task-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid var(--grey-200);
    padding-bottom: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.task-title-section {
    flex: 1;
}

.task-name {
    font-size: var(--font-size-2xl);
    margin: 0 0 var(--spacing-xs) 0;
}

.task-labels {
    display: flex;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-xs);
}

.priority-badge {
    display: inline-flex;
    align-items: center;
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: 16px;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
}

.priority-low {
    background-color: rgba(58, 191, 248, 0.15);
    color: #085783;
}

.priority-medium {
    background-color: rgba(251, 189, 35, 0.15);
    color: #946000;
}

.priority-high {
    background-color: rgba(255, 159, 28, 0.15);
    color: #E58C00;
}

.priority-urgent {
    background-color: rgba(230, 57, 70, 0.15);
    color: #a61a24;
}

.task-status-actions {
    margin-left: var(--spacing-lg);
}

.status-form {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.status-select {
    min-width: 140px;
}

.task-complete-info {
    margin-bottom: var(--spacing-lg);
}

.info-box {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-md);
    border-radius: var(--border-radius-md);
    font-weight: var(--font-weight-medium);
}

.info-box.success {
    background-color: rgba(46, 196, 182, 0.1);
    color: #0d6962;
}

.info-box .material-icons {
    color: var(--success-color);
}

/* Comments styles */
.comments-list {
    margin-bottom: var(--spacing-xl);
}

.comment-item {
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--grey-200);
}

.comment-item:last-child {
    border-bottom: none;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: var(--spacing-xs);
}

.comment-author {
    font-weight: var(--font-weight-semibold);
    color: var(--grey-800);
}

.comment-date {
    font-size: var(--font-size-sm);
    color: var(--grey-500);
}

.comment-content {
    color: var(--grey-700);
}

.no-comments {
    padding: var(--spacing-md);
    color: var(--grey-600);
    text-align: center;
    font-style: italic;
}

.badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px 6px;
    border-radius: var(--border-radius-sm);
    font-size: 10px;
    font-weight: var(--font-weight-medium);
    margin-left: var(--spacing-xs);
}

.badge-danger {
    background-color: var(--danger-color);
    color: white;
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Setup delete confirmation
        const deleteButton = document.querySelector('.delete-task');
        if (deleteButton) {
            deleteButton.addEventListener('click', function(e) {
                if (!confirm(this.dataset.confirm)) {
                    e.preventDefault();
                }
            });
        }
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>