<?php
// modules/time/timer.php - Time tracking timer interface
require_once '../../config.php';

// Check permissions
if (!checkPermission('add_time')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Time Tracker";

// Get task, project, business IDs from query parameters
$taskId = isset($_GET['task_id']) && is_numeric($_GET['task_id']) ? (int)$_GET['task_id'] : null;
$projectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;

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

// Check for active timer
$stmt = $conn->prepare("SELECT * FROM time_entries 
                      WHERE user_id = ? AND end_time IS NULL 
                      ORDER BY start_time DESC LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$activeTimer = null;

if ($result->num_rows > 0) {
    $activeTimer = $result->fetch_assoc();
    
    // Get related names
    if ($activeTimer['task_id']) {
        $stmt = $conn->prepare("SELECT title FROM tasks WHERE id = ?");
        $stmt->bind_param('i', $activeTimer['task_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $activeTimer['task_title'] = $result->fetch_assoc()['title'];
        }
    }
    
    if ($activeTimer['project_id']) {
        $stmt = $conn->prepare("SELECT name FROM projects WHERE id = ?");
        $stmt->bind_param('i', $activeTimer['project_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $activeTimer['project_name'] = $result->fetch_assoc()['name'];
        }
    }
    
    if ($activeTimer['business_id']) {
        $stmt = $conn->prepare("SELECT name FROM businesses WHERE id = ?");
        $stmt->bind_param('i', $activeTimer['business_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $activeTimer['business_name'] = $result->fetch_assoc()['name'];
        }
    }
    
    // Calculate elapsed time
    $startTime = new DateTime($activeTimer['start_time']);
    $currentTime = new DateTime();
    $interval = $currentTime->diff($startTime);
    $activeTimer['elapsed'] = [
        'hours' => $interval->h + ($interval->days * 24),
        'minutes' => $interval->i,
        'seconds' => $interval->s
    ];
}

// Handle save timer request
$saveError = '';
$saveSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_timer'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $saveError = "Invalid request. Please try again.";
    } else {
        $timerId = isset($_POST['timer_id']) && is_numeric($_POST['timer_id']) ? (int)$_POST['timer_id'] : null;
        $description = sanitizeInput($_POST['description']);
        $selectedTaskId = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
        $selectedProjectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $selectedBusinessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        $isBillable = isset($_POST['is_billable']) ? 1 : 0;
        
        if (empty($description)) {
            $saveError = "Description is required.";
        } else {
            // Get current time
            $endTime = date('Y-m-d H:i:s');
            
            // Get start time from active timer
            $startTime = $activeTimer['start_time'];
            
            // Calculate duration in seconds
            $duration = strtotime($endTime) - strtotime($startTime);
            
            // Update the timer with end time and duration
            $stmt = $conn->prepare("UPDATE time_entries SET 
                description = ?, 
                task_id = ?, 
                project_id = ?, 
                business_id = ?, 
                is_billable = ?, 
                end_time = ?, 
                duration = ? 
                WHERE id = ? AND user_id = ?");
            $stmt->bind_param('siiissiis', $description, $selectedTaskId, $selectedProjectId, $selectedBusinessId, 
                          $isBillable, $endTime, $duration, $timerId, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $saveSuccess = "Time entry saved successfully.";
                $activeTimer = null; // Clear active timer
                
                // Redirect to time entries list
                header("Location: index.php?success=completed");
                exit;
            } else {
                $saveError = "Error saving time entry: " . $conn->error;
            }
        }
    }
}

// Handle start timer request
$startError = '';
$startSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_timer'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $startError = "Invalid request. Please try again.";
    } else {
        $description = sanitizeInput($_POST['description']);
        $selectedTaskId = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
        $selectedProjectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $selectedBusinessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        $isBillable = isset($_POST['is_billable']) ? 1 : 0;
        
        if (empty($description)) {
            $startError = "Description is required.";
        } else {
            // Check if there's already an active timer
            if ($activeTimer) {
                $startError = "You already have an active timer running. Please stop it before starting a new one.";
            } else {
                // Get current time
                $startTime = date('Y-m-d H:i:s');
                
                // Insert new timer
                $stmt = $conn->prepare("INSERT INTO time_entries 
                    (user_id, task_id, project_id, business_id, description, start_time, is_billable) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('iiissss', $_SESSION['user_id'], $selectedTaskId, $selectedProjectId, 
                              $selectedBusinessId, $description, $startTime, $isBillable);
                
                if ($stmt->execute()) {
                    $startSuccess = "Timer started successfully.";
                    
                    // Reload the page to show active timer
                    header("Location: timer.php?success=started");
                    exit;
                } else {
                    $startError = "Error starting timer: " . $conn->error;
                }
            }
        }
    }
}

// Handle discard timer request
if (isset($_GET['discard']) && $_GET['discard'] == '1' && $activeTimer) {
    $stmt = $conn->prepare("DELETE FROM time_entries WHERE id = ? AND user_id = ? AND end_time IS NULL");
    $stmt->bind_param('ii', $activeTimer['id'], $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        header("Location: timer.php?success=discarded");
        exit;
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Time Tracker</h2>
        <div class="card-header-actions">
            <a href="index.php" class="btn btn-text">
                <span class="material-icons">list</span> View Time Entries
            </a>
            <a href="add.php" class="btn btn-text">
                <span class="material-icons">add</span> Add Time Entry
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (isset($_GET['success'])): ?>
            <?php if ($_GET['success'] === 'started'): ?>
                <div class="alert alert-success">Timer started successfully.</div>
            <?php elseif ($_GET['success'] === 'discarded'): ?>
                <div class="alert alert-success">Timer discarded successfully.</div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (!empty($startError)): ?>
            <div class="alert alert-danger"><?php echo $startError; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($saveError)): ?>
            <div class="alert alert-danger"><?php echo $saveError; ?></div>
        <?php endif; ?>
        
        <?php if ($activeTimer): ?>
            <!-- Active Timer Display -->
            <div class="active-timer">
                <div class="timer-header">
                    <h3>Timer Running</h3>
                    <div class="timer-actions">
                        <a href="?discard=1" class="btn btn-danger discard-timer" data-confirm="Are you sure you want to discard this time entry?">
                            <span class="material-icons">delete</span> Discard
                        </a>
                    </div>
                </div>
                
                <div class="timer-display">
                    <div class="timer-time" id="timer-display">
                        <span id="timer-hours"><?php echo sprintf('%02d', $activeTimer['elapsed']['hours']); ?></span>:
                        <span id="timer-minutes"><?php echo sprintf('%02d', $activeTimer['elapsed']['minutes']); ?></span>:
                        <span id="timer-seconds"><?php echo sprintf('%02d', $activeTimer['elapsed']['seconds']); ?></span>
                    </div>
                    <div class="timer-details">
                        <div class="timer-start-time">
                            Started: <?php echo date('M j, Y g:i:s A', strtotime($activeTimer['start_time'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="timer-content">
                    <form method="POST" action="" data-validate="true">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="save_timer" value="1">
                        <input type="hidden" name="timer_id" value="<?php echo $activeTimer['id']; ?>">
                        
                        <div class="form-group">
                            <label for="description">Description <span class="required">*</span></label>
                            <textarea id="description" name="description" class="form-control" rows="3" required><?php echo $activeTimer['description']; ?></textarea>
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
                                                <?php echo ($activeTimer['task_id'] == $task['id']) ? 'selected' : ''; ?>
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
                                                <?php echo ($activeTimer['project_id'] == $project['id']) ? 'selected' : ''; ?>
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
                                            <option value="<?php echo $business['id']; ?>" <?php echo ($activeTimer['business_id'] == $business['id']) ? 'selected' : ''; ?>>
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
                                    <input type="checkbox" name="is_billable" id="is_billable" value="1" <?php echo $activeTimer['is_billable'] ? 'checked' : ''; ?>>
                                    Billable time
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-icons">stop</span> Stop Timer & Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Start New Timer Form -->
            <div class="timer-form">
                <form method="POST" action="" data-validate="true">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="start_timer" value="1">
                    
                    <div class="form-group">
                        <label for="description">What are you working on? <span class="required">*</span></label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Describe your task..." required></textarea>
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
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons">play_arrow</span> Start Timer
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.active-timer {
    background-color: var(--grey-50);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.timer-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-md);
}

.timer-header h3 {
    margin: 0;
    font-size: var(--font-size-xl);
    color: var(--primary-color);
}

.timer-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: var(--spacing-xl);
    padding: var(--spacing-lg);
    background-color: white;
    border-radius: var(--border-radius-md);
    box-shadow: var(--box-shadow-sm);
}

.timer-time {
    font-size: 3rem;
    font-weight: var(--font-weight-bold);
    font-family: monospace;
    color: var(--grey-800);
    margin-bottom: var(--spacing-sm);
}

.timer-details {
    color: var(--grey-600);
    font-size: var(--font-size-sm);
}

.checkbox {
    display: flex;
    align-items: center;
    margin-top: var(--spacing-xs);
}

.checkbox input {
    margin-right: var(--spacing-xs);
}

.timer-form {
    padding: var(--spacing-lg);
    background-color: white;
    border-radius: var(--border-radius-lg);
    box-shadow: var(--box-shadow);
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($activeTimer): ?>
        // Timer functionality
        let seconds = <?php echo $activeTimer['elapsed']['seconds']; ?>;
        let minutes = <?php echo $activeTimer['elapsed']['minutes']; ?>;
        let hours = <?php echo $activeTimer['elapsed']['hours']; ?>;
        
        const hoursEl = document.getElementById('timer-hours');
        const minutesEl = document.getElementById('timer-minutes');
        const secondsEl = document.getElementById('timer-seconds');
        
        function updateTimer() {
            seconds++;
            
            if (seconds >= 60) {
                seconds = 0;
                minutes++;
                
                if (minutes >= 60) {
                    minutes = 0;
                    hours++;
                }
            }
            
            hoursEl.textContent = hours.toString().padStart(2, '0');
            minutesEl.textContent = minutes.toString().padStart(2, '0');
            secondsEl.textContent = seconds.toString().padStart(2, '0');
        }
        
        // Update timer every second
        const timerInterval = setInterval(updateTimer, 1000);
        
        // Confirm discard
        const discardBtn = document.querySelector('.discard-timer');
        if (discardBtn) {
            discardBtn.addEventListener('click', function(e) {
                if (!confirm(this.dataset.confirm)) {
                    e.preventDefault();
                }
            });
        }
        <?php endif; ?>
        
        // Field relationships
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
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>