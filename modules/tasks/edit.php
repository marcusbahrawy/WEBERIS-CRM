<?php
// modules/tasks/edit.php - Edit an existing task
require_once '../../config.php';

// Check permissions
if (!checkPermission('edit_task')) {
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

// Get task data
$stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->bind_param('i', $taskId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$task = $result->fetch_assoc();

// Check if user can edit this task
// Only task creator, assignee, or admin can edit
if ($task['created_by'] != $_SESSION['user_id'] && $task['assigned_to'] != $_SESSION['user_id'] && $_SESSION['role_name'] !== 'admin') {
    header("Location: view.php?id=" . $taskId);
    exit;
}

// Get all businesses for dropdown
$businessesResult = $conn->query("SELECT id, name FROM businesses ORDER BY name ASC");
$businesses = [];
if ($businessesResult->num_rows > 0) {
    while ($business = $businessesResult->fetch_assoc()) {
        $businesses[] = $business;
    }
}

// Get all projects for dropdown
$projectsResult = $conn->query("SELECT id, name, business_id FROM projects ORDER BY name ASC");
$projects = [];
if ($projectsResult->num_rows > 0) {
    while ($project = $projectsResult->fetch_assoc()) {
        $projects[] = $project;
    }
}

// Get all users for assignment dropdown
$usersResult = $conn->query("SELECT id, name, email FROM users ORDER BY name ASC");
$users = [];
if ($usersResult->num_rows > 0) {
    while ($user = $usersResult->fetch_assoc()) {
        $users[] = $user;
    }
}

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Get and sanitize form data
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $status = sanitizeInput($_POST['status']);
        $priority = sanitizeInput($_POST['priority']);
        $dueDate = !empty($_POST['due_date']) ? sanitizeInput($_POST['due_date']) : null;
        $businessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
        
        // Check if assignment has changed
        $assignmentChanged = $assignedTo != $task['assigned_to'];
        
        // Validate required fields
        if (empty($title)) {
            $error = "Task title is required.";
        } else {
            // Update task
            $stmt = $conn->prepare("UPDATE tasks SET 
                title = ?, 
                description = ?, 
                status = ?, 
                priority = ?, 
                due_date = ?, 
                business_id = ?, 
                project_id = ?, 
                assigned_to = ? 
                WHERE id = ?");
            $stmt->bind_param('sssssiiis', $title, $description, $status, $priority, $dueDate, $businessId, $projectId, $assignedTo, $taskId);
            
            if ($stmt->execute()) {
                $success = "Task updated successfully.";
                
                // Add a comment about the update
                $systemComment = "Task details updated by " . $_SESSION['name'];
                $stmt = $conn->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
                $stmt->bind_param('iis', $taskId, $_SESSION['user_id'], $systemComment);
                $stmt->execute();
                
                // If assignment changed, send notification to the new assignee
                if ($assignmentChanged && $assignedTo && $assignedTo != $_SESSION['user_id']) {
                    // Create notification
                    $notificationTitle = "Task assigned to you";
                    $notificationMessage = $_SESSION['name'] . " assigned you a task: " . $title;
                    $notificationLink = "/modules/tasks/view.php?id=" . $taskId;
                    
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, related_id) VALUES (?, 'task_assigned', ?, ?, ?, ?)");
                    $stmt->bind_param('isssi', $assignedTo, $notificationTitle, $notificationMessage, $notificationLink, $taskId);
                    $stmt->execute();
                }
                
                // If the task was created by someone else, notify them of the update
                if ($task['created_by'] != $_SESSION['user_id']) {
                    $notificationTitle = "Task updated";
                    $notificationMessage = $_SESSION['name'] . " updated your task: " . $title;
                    $notificationLink = "/modules/tasks/view.php?id=" . $taskId;
                    
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, related_id) VALUES (?, 'task_update', ?, ?, ?, ?)");
                    $stmt->bind_param('isssi', $task['created_by'], $notificationTitle, $notificationMessage, $notificationLink, $taskId);
                    $stmt->execute();
                }
                
                // If status changed to completed and there was no completed_at date
                if ($status === 'completed' && $task['status'] !== 'completed') {
                    $completedAt = date('Y-m-d H:i:s');
                    $stmt = $conn->prepare("UPDATE tasks SET completed_at = ? WHERE id = ?");
                    $stmt->bind_param('si', $completedAt, $taskId);
                    $stmt->execute();
                    
                    // Add a comment about completion
                    $completionComment = "Task marked as completed by " . $_SESSION['name'];
                    $stmt = $conn->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
                    $stmt->bind_param('iis', $taskId, $_SESSION['user_id'], $completionComment);
                    $stmt->execute();
                } 
                // If status changed from completed to something else
                else if ($status !== 'completed' && $task['status'] === 'completed') {
                    $stmt = $conn->prepare("UPDATE tasks SET completed_at = NULL WHERE id = ?");
                    $stmt->bind_param('i', $taskId);
                    $stmt->execute();
                    
                    // Add a comment about reopening
                    $reopenComment = "Task reopened by " . $_SESSION['name'];
                    $stmt = $conn->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
                    $stmt->bind_param('iis', $taskId, $_SESSION['user_id'], $reopenComment);
                    $stmt->execute();
                }
                
                // Refresh task data
                $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
                $stmt->bind_param('i', $taskId);
                $stmt->execute();
                $result = $stmt->get_result();
                $task = $result->fetch_assoc();
            } else {
                $error = "Error updating task: " . $conn->error;
            }
        }
    }
}

// Page title
$pageTitle = "Edit Task: " . $task['title'];

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Edit Task</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $taskId; ?>" class="btn btn-text">
                <span class="material-icons">visibility</span> View Task
            </a>
            <a href="index.php" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Tasks
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" data-validate="true">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="title">Task Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo $task['title']; ?>" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="pending" <?php echo ($task['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo ($task['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo ($task['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="canceled" <?php echo ($task['status'] === 'canceled') ? 'selected' : ''; ?>>Canceled</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4"><?php echo $task['description']; ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="priority">Priority <span class="required">*</span></label>
                        <select id="priority" name="priority" class="form-control" required>
                            <option value="low" <?php echo ($task['priority'] === 'low') ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo ($task['priority'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo ($task['priority'] === 'high') ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo ($task['priority'] === 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="date" id="due_date" name="due_date" class="form-control" value="<?php echo $task['due_date']; ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="business_id">Business</label>
                        <select id="business_id" name="business_id" class="form-control">
                            <option value="">-- No Business --</option>
                            <?php foreach ($businesses as $business): ?>
                                <option value="<?php echo $business['id']; ?>" <?php echo ($task['business_id'] == $business['id']) ? 'selected' : ''; ?>>
                                    <?php echo $business['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="project_id">Project</label>
                        <select id="project_id" name="project_id" class="form-control">
                            <option value="">-- No Project --</option>
                            <?php foreach ($projects as $project): ?>
                                <option 
                                    value="<?php echo $project['id']; ?>" 
                                    data-business-id="<?php echo $project['business_id']; ?>"
                                    <?php echo ($task['project_id'] == $project['id']) ? 'selected' : ''; ?>
                                    <?php echo (!$task['project_id'] && $task['business_id'] && $project['business_id'] != $task['business_id']) ? 'style="display:none;"' : ''; ?>
                                >
                                    <?php echo $project['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="assigned_to">Assign To</label>
                <select id="assigned_to" name="assigned_to" class="form-control">
                    <option value="">-- Unassigned --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo ($task['assigned_to'] == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo $user['name']; ?> <?php echo ($user['id'] == $_SESSION['user_id']) ? '(Me)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <a href="view.php?id=<?php echo $taskId; ?>" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Task</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Filter projects based on selected business
        const businessSelect = document.getElementById('business_id');
        const projectSelect = document.getElementById('project_id');
        const projectOptions = projectSelect.querySelectorAll('option');
        
        businessSelect.addEventListener('change', function() {
            const selectedBusinessId = this.value;
            
            // Reset project select if business changes and current project doesn't match the new business
            const selectedProjectOption = projectSelect.options[projectSelect.selectedIndex];
            const selectedProjectBusinessId = selectedProjectOption.getAttribute('data-business-id');
            
            if (selectedProjectBusinessId && selectedBusinessId && selectedProjectBusinessId != selectedBusinessId) {
                projectSelect.value = '';
            }
            
            // Show/hide project options based on business
            projectOptions.forEach(option => {
                if (option.value === '') {
                    // Always show the "No Project" option
                    option.style.display = '';
                } else {
                    const businessId = option.getAttribute('data-business-id');
                    if (!selectedBusinessId || businessId == selectedBusinessId) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                }
            });
        });
        
        // When project is selected, update business if not already set
        projectSelect.addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                const businessId = selectedOption.getAttribute('data-business-id');
                
                if (businessId && !businessSelect.value) {
                    businessSelect.value = businessId;
                }
            }
        });
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>