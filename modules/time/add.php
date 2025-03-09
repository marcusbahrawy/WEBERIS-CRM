<?php
// modules/time/add.php - Add a new time entry
require_once '../../config.php';

// Check permissions
if (!checkPermission('add_time')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Add Time Entry";

// Get parameters from query
$taskId = isset($_GET['task_id']) && is_numeric($_GET['task_id']) ? (int)$_GET['task_id'] : null;
$projectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;

// Default date to today
$defaultDate = date('Y-m-d');

// Database connection
$conn = connectDB();

// Get entity names if provided in URL
$taskTitle = '';
$projectName = '';
$businessName = '';

if ($taskId) {
    $stmt = $conn->prepare("SELECT title, project_id, business_id FROM tasks WHERE id = ?");
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $taskData = $result->fetch_assoc();
        $taskTitle = $taskData['title'];
        
        // If task has project or business and none was provided, use task's values
        if (!$projectId && $taskData['project_id']) {
            $projectId = $taskData['project_id'];
        }
        
        if (!$businessId && $taskData['business_id']) {
            $businessId = $taskData['business_id'];
        }
    }
}

if ($projectId) {
    $stmt = $conn->prepare("SELECT name, business_id FROM projects WHERE id = ?");
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $projectData = $result->fetch_assoc();
        $projectName = $projectData['name'];
        
        // If project has business and none was provided, use project's business
        if (!$businessId && $projectData['business_id']) {
            $businessId = $projectData['business_id'];
        }
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

// Get all tasks for dropdown
$tasksResult = $conn->query("SELECT t.id, t.title, t.business_id, t.project_id, b.name as business_name, p.name as project_name 
                           FROM tasks t 
                           LEFT JOIN businesses b ON t.business_id = b.id 
                           LEFT JOIN projects p ON t.project_id = p.id 
                           WHERE t.status != 'completed' AND t.status != 'canceled'
                           ORDER BY t.title ASC");
$tasks = [];
if ($tasksResult && $tasksResult->num_rows > 0) {
    while ($task = $tasksResult->fetch_assoc()) {
        $tasks[] = $task;
    }
}

// Get all projects for dropdown
$projectsResult = $conn->query("SELECT p.id, p.name, p.business_id, b.name as business_name 
                              FROM projects p 
                              LEFT JOIN businesses b ON p.business_id = b.id 
                              WHERE p.status != 'completed' AND p.status != 'cancelled'
                              ORDER BY p.name ASC");
$projects = [];
if ($projectsResult && $projectsResult->num_rows > 0) {
    while ($project = $projectsResult->fetch_assoc()) {
        $projects[] = $project;
    }
}

// Get all businesses for dropdown
$businessesResult = $conn->query("SELECT id, name FROM businesses ORDER BY name ASC");
$businesses = [];
if ($businessesResult && $businessesResult->num_rows > 0) {
    while ($business = $businessesResult->fetch_assoc()) {
        $businesses[] = $business;
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
        $description = sanitizeInput($_POST['description']);
        $date = sanitizeInput($_POST['date']);
        $startHour = (int)$_POST['start_hour'];
        $startMinute = (int)$_POST['start_minute'];
        $endHour = (int)$_POST['end_hour'];
        $endMinute = (int)$_POST['end_minute'];
        $selectedTaskId = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
        $selectedProjectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $selectedBusinessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        $isBillable = isset($_POST['is_billable']) ? 1 : 0;
        
        // Validate and construct datetime values
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $error = "Invalid date format.";
        } elseif ($startHour < 0 || $startHour > 23 || $endHour < 0 || $endHour > 23) {
            $error = "Invalid hour value.";
        } elseif ($startMinute < 0 || $startMinute > 59 || $endMinute < 0 || $endMinute > 59) {
            $error = "Invalid minute value.";
        } else {
            // Construct datetime strings
            $startTime = $date . ' ' . sprintf('%02d:%02d:00', $startHour, $startMinute);
            $endTime = $date . ' ' . sprintf('%02d:%02d:00', $endHour, $endMinute);
            
            // Calculate duration in seconds
            $startTimestamp = strtotime($startTime);
            $endTimestamp = strtotime($endTime);
            
            if ($endTimestamp <= $startTimestamp) {
                $error = "End time must be after start time.";
            } else {
                $duration = $endTimestamp - $startTimestamp;
                
                // Insert time entry
                $stmt = $conn->prepare("INSERT INTO time_entries 
                    (user_id, task_id, project_id, business_id, description, start_time, end_time, duration, is_billable) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('iiisssiii', $_SESSION['user_id'], $selectedTaskId, $selectedProjectId, $selectedBusinessId, 
                                 $description, $startTime, $endTime, $duration, $isBillable);
                
                if ($stmt->execute()) {
                    $timeEntryId = $conn->insert_id;
                    $success = "Time entry added successfully.";
                    
                    // Redirect to time entries listing
                    header("Location: index.php?success=created");
                    exit;
                } else {
                    $error = "Error adding time entry: " . $conn->error;
                }
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
            $title = "Add Time Entry";
            
            if ($taskId) {
                $title .= " for Task: " . $taskTitle;
            } elseif ($projectId) {
                $title .= " for Project: " . $projectName;
            } elseif ($businessId) {
                $title .= " for " . $businessName;
            }
            
            echo $title;
            ?>
        </h2>
        <div class="card-header-actions">
            <a href="index.php" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Time Entries
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
                        <label for="date">Date <span class="required">*</span></label>
                        <input type="date" id="date" name="date" class="form-control" value="<?php echo $defaultDate; ?>" required>
                    </div>
                </div>
                
                <div class="form-col time-selection">
                    <div class="form-group">
                        <label>Start Time <span class="required">*</span></label>
                        <div class="time-inputs">
                            <select name="start_hour" class="form-control time-select">
                                <?php for ($i = 0; $i < 24; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == 9 ? 'selected' : ''; ?>>
                                        <?php echo sprintf('%02d', $i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <span class="time-separator">:</span>
                            <select name="start_minute" class="form-control time-select">
                                <?php for ($i = 0; $i < 60; $i += 5): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == 0 ? 'selected' : ''; ?>>
                                        <?php echo sprintf('%02d', $i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-col time-selection">
                    <div class="form-group">
                        <label>End Time <span class="required">*</span></label>
                        <div class="time-inputs">
                            <select name="end_hour" class="form-control time-select">
                                <?php for ($i = 0; $i < 24; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == 17 ? 'selected' : ''; ?>>
                                        <?php echo sprintf('%02d', $i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <span class="time-separator">:</span>
                            <select name="end_minute" class="form-control time-select">
                                <?php for ($i = 0; $i < 60; $i += 5): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == 0 ? 'selected' : ''; ?>>
                                        <?php echo sprintf('%02d', $i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description" class="form-control" rows="3" required></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="task_id">Task</label>
                        <select id="task_id" name="task_id" class="form-control">
                            <option value="">-- No Task --</option>
                            <?php foreach ($tasks as $task): ?>
                                <option 
                                    value="<?php echo $task['id']; ?>" 
                                    data-project-id="<?php echo $task['project_id']; ?>"
                                    data-business-id="<?php echo $task['business_id']; ?>"
                                    <?php echo ($taskId == $task['id']) ? 'selected' : ''; ?>
                                >
                                    <?php 
                                    echo $task['title']; 
                                    if ($task['project_name']) {
                                        echo " (" . $task['project_name'] . ")";
                                    } elseif ($task['business_name']) {
                                        echo " (" . $task['business_name'] . ")";
                                    }
                                    ?>
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
                                    <?php echo ($projectId == $project['id']) ? 'selected' : ''; ?>
                                >
                                    <?php 
                                    echo $project['name']; 
                                    if ($project['business_name']) {
                                        echo " (" . $project['business_name'] . ")";
                                    }
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="business_id">Business</label>
                        <select id="business_id" name="business_id" class="form-control">
                            <option value="">-- No Business --</option>
                            <?php foreach ($businesses as $business): ?>
                                <option value="<?php echo $business['id']; ?>" <?php echo ($businessId == $business['id']) ? 'selected' : ''; ?>>
                                    <?php echo $business['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="is_billable" id="is_billable" value="1" checked>
                        Billable time
                    </label>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="reset" class="btn btn-text">Reset</button>
                <button type="submit" class="btn btn-primary">Save Time Entry</button>
            </div>
        </form>
    </div>
</div>

<style>
.time-selection {
    max-width: 180px;
}

.time-inputs {
    display: flex;
    align-items: center;
}

.time-select {
    width: 70px;
}

.time-separator {
    margin: 0 5px;
    font-weight: bold;
}

.checkbox {
    display: flex;
    align-items: center;
    margin-top: var(--spacing-xs);
}

.checkbox input {
    margin-right: var(--spacing-xs);
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const taskSelect = document.getElementById('task_id');
        const projectSelect = document.getElementById('project_id');
        const businessSelect = document.getElementById('business_id');
        
        // When task changes, update project and business selects
        taskSelect.addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                const projectId = selectedOption.getAttribute('data-project-id');
                const businessId = selectedOption.getAttribute('data-business-id');
                
                // Set project if task has project
                if (projectId) {
                    projectSelect.value = projectId;
                    
                    // Trigger project change to update business
                    const event = new Event('change');
                    projectSelect.dispatchEvent(event);
                } else if (businessId) {
                    // If task has business but no project, set business directly
                    businessSelect.value = businessId;
                }
            }
        });
        
        // When project changes, update business select
        projectSelect.addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                const businessId = selectedOption.getAttribute('data-business-id');
                
                // Set business if project has business
                if (businessId) {
                    businessSelect.value = businessId;
                }
            }
        });
        
        // Add shortcuts for start and end times
        function addTimeShortcuts() {
            const timeLabels = document.querySelectorAll('.time-selection label');
            
            timeLabels.forEach(label => {
                // Create shortcut buttons container
                const shortcuts = document.createElement('div');
                shortcuts.className = 'time-shortcuts';
                shortcuts.style.marginTop = '5px';
                shortcuts.style.display = 'flex';
                shortcuts.style.gap = '5px';
                shortcuts.style.flexWrap = 'wrap';
                
                // Common time shortcuts
                const times = [
                    {label: 'Now', value: 'now'},
                    {label: '9:00', value: '09:00'},
                    {label: '12:00', value: '12:00'},
                    {label: '13:00', value: '13:00'},
                    {label: '17:00', value: '17:00'}
                ];
                
                // Create shortcut buttons
                times.forEach(time => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-text btn-sm';
                    btn.textContent = time.label;
                    btn.style.padding = '2px 8px';
                    btn.style.fontSize = '12px';
                    
                    btn.addEventListener('click', function() {
                        const formGroup = label.closest('.form-group');
                        const hourSelect = formGroup.querySelector('[name$="hour"]');
                        const minuteSelect = formGroup.querySelector('[name$="minute"]');
                        
                        if (time.value === 'now') {
                            const now = new Date();
                            hourSelect.value = now.getHours();
                            // Round to nearest 5 minutes
                            minuteSelect.value = Math.round(now.getMinutes() / 5) * 5;
                            if (minuteSelect.value === 60) {
                                minuteSelect.value = 0;
                                hourSelect.value = (parseInt(hourSelect.value) + 1) % 24;
                            }
                        } else {
                            const parts = time.value.split(':');
                            hourSelect.value = parseInt(parts[0]);
                            minuteSelect.value = parseInt(parts[1]);
                        }
                    });
                    
                    shortcuts.appendChild(btn);
                });
                
                // Add shortcuts after the time inputs
                const timeInputsContainer = label.parentNode.querySelector('.time-inputs');
                label.parentNode.insertBefore(shortcuts, timeInputsContainer.nextSibling);
            });
        }
        
        addTimeShortcuts();
        
        // Calculate total hours
        function calculateTotal() {
            const startHour = parseInt(document.querySelector('[name="start_hour"]').value);
            const startMinute = parseInt(document.querySelector('[name="start_minute"]').value);
            const endHour = parseInt(document.querySelector('[name="end_hour"]').value);
            const endMinute = parseInt(document.querySelector('[name="end_minute"]').value);
            
            let totalMinutes = (endHour * 60 + endMinute) - (startHour * 60 + startMinute);
            
            if (totalMinutes <= 0) {
                // If end time is before start time, assume next day
                return 'Invalid time range';
            }
            
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;
            
            return `${hours}h ${minutes}m (${(totalMinutes / 60).toFixed(2)} hours)`;
        }
        
        function updateTotalTime() {
            // Create or update total time display
            let totalTimeElement = document.getElementById('total-time-display');
            if (!totalTimeElement) {
                totalTimeElement = document.createElement('div');
                totalTimeElement.id = 'total-time-display';
                totalTimeElement.className = 'alert alert-info mt-sm';
                totalTimeElement.style.marginTop = '10px';
                
                // Insert before form actions
                const formActions = document.querySelector('.form-actions');
                formActions.parentNode.insertBefore(totalTimeElement, formActions);
            }
            
            totalTimeElement.innerHTML = `<strong>Total Time:</strong> ${calculateTotal()}`;
        }
        
        // Update total time when any time select changes
        document.querySelectorAll('.time-select').forEach(select => {
            select.addEventListener('change', updateTotalTime);
        });
        
        // Initial calculation
        updateTotalTime();
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>