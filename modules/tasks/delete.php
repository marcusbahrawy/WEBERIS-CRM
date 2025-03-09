<?php
// modules/tasks/delete.php - Delete a task
require_once '../../config.php';

// Check permissions
if (!checkPermission('delete_task')) {
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

// Check if task exists and get basic info
$stmt = $conn->prepare("SELECT title, created_by FROM tasks WHERE id = ?");
$stmt->bind_param('i', $taskId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$task = $result->fetch_assoc();

// Check if user can delete this task
// Only task creator or admin can delete
if ($task['created_by'] != $_SESSION['user_id'] && $_SESSION['role_name'] !== 'admin') {
    header("Location: view.php?id=" . $taskId);
    exit;
}

// Get additional task data for confirmation
$stmt = $conn->prepare("SELECT t.*, 
                      u.name as assigned_to_name,
                      b.name as business_name,
                      p.name as project_name
                      FROM tasks t
                      LEFT JOIN users u ON t.assigned_to = u.id
                      LEFT JOIN businesses b ON t.business_id = b.id
                      LEFT JOIN projects p ON t.project_id = p.id
                      WHERE t.id = ?");
$stmt->bind_param('i', $taskId);
$stmt->execute();
$result = $stmt->get_result();
$taskDetails = $result->fetch_assoc();

// Handle confirmation
$error = '';
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Before deleting the task, get the assignee to notify them
        $assigneeId = $taskDetails['assigned_to'];
        
        // First delete task comments
        $stmt = $conn->prepare("DELETE FROM task_comments WHERE task_id = ?");
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        
        // Delete related notifications
        $stmt = $conn->prepare("DELETE FROM notifications WHERE related_id = ? AND type LIKE 'task_%'");
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        
        // Now delete task
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->bind_param('i', $taskId);
        
        if ($stmt->execute()) {
            // If task was assigned to someone else, notify them of deletion
            if ($assigneeId && $assigneeId != $_SESSION['user_id']) {
                $notificationTitle = "Task deleted";
                $notificationMessage = $_SESSION['name'] . " deleted a task assigned to you: " . $task['title'];
                $notificationLink = "/modules/tasks/index.php?assigned_to=" . $assigneeId;
                
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, related_id) VALUES (?, 'task_deleted', ?, ?, ?, NULL)");
                $stmt->bind_param('iss', $assigneeId, $notificationTitle, $notificationMessage);
                $stmt->execute();
            }
            
            // Redirect to tasks listing with success message
            header("Location: index.php?success=deleted");
            exit;
        } else {
            $error = "Error deleting task: " . $conn->error;
        }
    }
}

// Page title
$pageTitle = "Delete Task";

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Delete Task</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $taskId; ?>" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Task
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="confirm-delete">
            <div class="alert alert-warning">
                <span class="material-icons alert-icon">warning</span>
                <div class="alert-content">
                    <strong>Warning:</strong> You are about to delete a task. This action cannot be undone.
                </div>
            </div>
            
            <div class="task-summary">
                <h3>Task Details</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Title</div>
                        <div class="detail-value"><strong><?php echo $taskDetails['title']; ?></strong></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <span class="status-badge status-<?php echo $taskDetails['status']; ?>">
                                <?php echo ucfirst($taskDetails['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Priority</div>
                        <div class="detail-value">
                            <span class="priority-badge priority-<?php echo $taskDetails['priority']; ?>">
                                <?php echo ucfirst($taskDetails['priority']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Assigned To</div>
                        <div class="detail-value"><?php echo $taskDetails['assigned_to'] ? $taskDetails['assigned_to_name'] : 'Unassigned'; ?></div>
                    </div>
                    
                    <?php if ($taskDetails['business_id']): ?>
                        <div class="detail-item">
                            <div class="detail-label">Business</div>
                            <div class="detail-value"><?php echo $taskDetails['business_name']; ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($taskDetails['project_id']): ?>
                        <div class="detail-item">
                            <div class="detail-label">Project</div>
                            <div class="detail-value"><?php echo $taskDetails['project_name']; ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($taskDetails['due_date']): ?>
                        <div class="detail-item">
                            <div class="detail-label">Due Date</div>
                            <div class="detail-value"><?php echo date('M j, Y', strtotime($taskDetails['due_date'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <form method="POST" action="" class="mt-xl">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="confirm" value="yes">
                
                <div class="form-actions">
                    <a href="view.php?id=<?php echo $taskId; ?>" class="btn btn-text">Cancel</a>
                    <button type="submit" class="btn btn-danger">Delete Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.task-summary {
    background-color: var(--grey-50);
    border-radius: var(--border-radius-md);
    padding: var(--spacing-lg);
    margin: var(--spacing-md) 0;
}

.task-summary h3 {
    margin-top: 0;
    margin-bottom: var(--spacing-md);
    font-size: var(--font-size-lg);
}

.confirm-delete .alert {
    margin-bottom: var(--spacing-lg);
}

.status-badge, .priority-badge {
    display: inline-flex;
    align-items: center;
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: 16px;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
}

.mt-xl {
    margin-top: var(--spacing-xl);
}
</style>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>