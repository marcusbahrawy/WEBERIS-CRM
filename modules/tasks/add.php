<?php
// modules/tasks/add.php - Add a new task
require_once '../../config.php';

// Check permissions
if (!checkPermission('add_task')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Add Task";

// Get parameters from query
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;
$projectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$businessName = '';
$projectName = '';

$conn = connectDB();

// Check if business exists and get name
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

// Check if project exists and get name
if ($projectId) {
    $stmt = $conn->prepare("SELECT name, business_id FROM projects WHERE id = ?");
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $projectData = $result->fetch_assoc();
        $projectName = $projectData['name'];
        
        // If project has a business and no business was provided, use the project's business
        if (!$businessId && $projectData['business_id']) {
            $businessId = $projectData['business_id'];
            
            // Get business name
            $stmt = $conn->prepare("SELECT name FROM businesses WHERE id = ?");
            $stmt->bind_param('i', $businessId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $businessName = $result->fetch_assoc()['name'];
            }
        }
    } else {
        $projectId = null;
    }
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
        $selectedBusinessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        $selectedProjectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
        
        // Validate required fields
        if (empty($title)) {
            $error = "Task title is required.";
        } else {
            // Insert new task
            $stmt = $conn->prepare("INSERT INTO tasks (title, description, status, priority, due_date, business_id, project_id, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssssiiis', $title, $description, $status, $priority, $dueDate, $selectedBusinessId, $selectedProjectId, $assignedTo, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $taskId = $conn->insert_id;
                $success = "Task created successfully.";
                
                // Send notification to assignee if different from creator
                if ($assignedTo && $assignedTo != $_SESSION['user_id']) {
                    // Create notification
                    $notificationTitle = "New task assigned to you";
                    $notificationMessage = $_SESSION['name'] . " assigned you a task: " . $title;
                    $notificationLink = "/modules/tasks/view.php?id=" . $taskId;
                    
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, related_id) VALUES (?, 'task_assigned', ?, ?, ?, ?)");
                    $stmt->bind_param('isssi', $assignedTo, $notificationTitle, $notificationMessage, $notificationLink, $taskId);
                    $stmt->execute();
                }
                
                // Redirect to the new task page
                header("Location: view.php?id=" . $taskId . "&success=created");
                exit;
            } else {
                $error = "Error creating task: " . $conn->error;
            }
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>
            <?php
            $title = "Create New Task";
            
            if ($projectId) {
                $title = "Create Task for Project: " . $projectName;
                if ($businessId) {
                    $title .= " (" . $businessName . ")";
                }
            } elseif ($businessId) {
                $title = "Create Task for " . $businessName;
            }
            
            echo $title;
            ?>
        </h2>
        <div class="card-header-actions">
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
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="pending" selected>Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="canceled">Canceled</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="priority">Priority <span class="required">*</span></label>
                        <select id="priority" name="priority" class="form-control" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="date" id="due_date" name="due_date" class="form-control">
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="business_id">Business</label>
                        <select id="business_id" name="business_id" class="form-control">
                            <option value="">-- Select Business --</option>
                            <?php foreach ($businesses as $business): ?>
                                <option value="<?php echo $business['id']; ?>" <?php echo ($businessId == $business['id']) ? 'selected' : ''; ?>>
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
                            <option value="">-- Select Project --</option>
                            <?php foreach ($projects as $project): ?>
                                <option 
                                    value="<?php echo $project['id']; ?>" 
                                    data-business-id="<?php echo $project['business_id']; ?>"
                                    <?php echo ($projectId == $project['id']) ? 'selected' : ''; ?>
                                    <?php echo (!$projectId && $businessId && $project['business_id'] != $businessId) ? 'style="display:none;"' : ''; ?>
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
                    <option value="<?php echo $_SESSION['user_id']; ?>" selected>Me (<?php echo $_SESSION['name']; ?>)</option>
                    <?php foreach ($users as $user): ?>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo $user['name']; ?> (<?php echo $user['email']; ?>)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="reset" class="btn btn-text">Reset</button>
                <button type="submit" class="btn btn-primary">Create Task</button>
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
            
            // Reset project select
            projectSelect.value = '';
            
            // Show/hide project options based on business
            projectOptions.forEach(option => {
                if (option.value === '') {
                    // Always show the "Select Project" option
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
        
        // Set default due date to 7 days from now
        const dueDateField = document.getElementById('due_date');
        if (!dueDateField.value) {
            const today = new Date();
            today.setDate(today.getDate() + 7);
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            dueDateField.value = `${year}-${month}-${day}`;
        }
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>