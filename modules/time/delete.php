<?php
// modules/time/delete.php - Delete a time entry
require_once '../../config.php';

// Check permissions
if (!checkPermission('delete_time')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$timeEntryId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Check if time entry exists and belongs to the current user
$stmt = $conn->prepare("SELECT * FROM time_entries WHERE id = ?");
$stmt->bind_param('i', $timeEntryId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$timeEntry = $result->fetch_assoc();

// Check if user can delete this time entry
// Users can only delete their own time entries unless they have view_time_reports permission
if ($timeEntry['user_id'] != $_SESSION['user_id'] && !checkPermission('view_time_reports')) {
    header("Location: index.php");
    exit;
}

// Get related data for confirmation display
$taskTitle = '';
$projectName = '';
$businessName = '';
$userName = '';

if ($timeEntry['task_id']) {
    $stmt = $conn->prepare("SELECT title FROM tasks WHERE id = ?");
    $stmt->bind_param('i', $timeEntry['task_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $taskTitle = $result->fetch_assoc()['title'];
    }
}

if ($timeEntry['project_id']) {
    $stmt = $conn->prepare("SELECT name FROM projects WHERE id = ?");
    $stmt->bind_param('i', $timeEntry['project_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $projectName = $result->fetch_assoc()['name'];
    }
}

if ($timeEntry['business_id']) {
    $stmt = $conn->prepare("SELECT name FROM businesses WHERE id = ?");
    $stmt->bind_param('i', $timeEntry['business_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $businessName = $result->fetch_assoc()['name'];
    }
}

// Get user name
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param('i', $timeEntry['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $userName = $result->fetch_assoc()['name'];
}

// Format the time data for display
$startTime = new DateTime($timeEntry['start_time']);
$endTime = $timeEntry['end_time'] ? new DateTime($timeEntry['end_time']) : null;
$duration = $timeEntry['duration'];

// Format duration in hours and minutes
$durationHours = floor($duration / 3600);
$durationMinutes = floor(($duration % 3600) / 60);
$formattedDuration = "$durationHours h $durationMinutes min";

// Handle confirmation
$error = '';
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Delete time entry
        $stmt = $conn->prepare("DELETE FROM time_entries WHERE id = ?");
        $stmt->bind_param('i', $timeEntryId);
        
        if ($stmt->execute()) {
            // Redirect to time entries listing with success message
            header("Location: index.php?success=deleted");
            exit;
        } else {
            $error = "Error deleting time entry: " . $conn->error;
        }
    }
}

// Page title
$pageTitle = "Delete Time Entry";

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Delete Time Entry</h2>
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
        
        <div class="alert alert-warning">
            <span class="material-icons alert-icon">warning</span>
            <div class="alert-content">
                <strong>Warning:</strong> You are about to delete a time entry. This action cannot be undone.
            </div>
        </div>
        
        <div class="time-entry-summary">
            <h3>Time Entry Details</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Date</div>
                    <div class="detail-value"><?php echo $startTime->format('M j, Y'); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Time</div>
                    <div class="detail-value">
                        <?php echo $startTime->format('H:i'); ?> - 
                        <?php echo $endTime ? $endTime->format('H:i') : 'Running'; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Duration</div>
                    <div class="detail-value"><?php echo $formattedDuration; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">User</div>
                    <div class="detail-value"><?php echo $userName; ?></div>
                </div>
                
                <?php if ($taskTitle): ?>
                <div class="detail-item">
                    <div class="detail-label">Task</div>
                    <div class="detail-value"><?php echo $taskTitle; ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($projectName): ?>
                <div class="detail-item">
                    <div class="detail-label">Project</div>
                    <div class="detail-value"><?php echo $projectName; ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($businessName): ?>
                <div class="detail-item">
                    <div class="detail-label">Business</div>
                    <div class="detail-value"><?php echo $businessName; ?></div>
                </div>
                <?php endif; ?>
                
                <div class="detail-item">
                    <div class="detail-label">Billable</div>
                    <div class="detail-value">
                        <?php echo $timeEntry['is_billable'] ? 'Yes' : 'No'; ?>
                    </div>
                </div>
                
                <div class="detail-item full-width">
                    <div class="detail-label">Description</div>
                    <div class="detail-value"><?php echo $timeEntry['description']; ?></div>
                </div>
            </div>
        </div>
        
        <form method="POST" action="" class="mt-xl">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="confirm" value="yes">
            
            <div class="form-actions">
                <a href="index.php" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-danger">Delete Time Entry</button>
            </div>
        </form>
    </div>
</div>

<style>
.time-entry-summary {
    background-color: var(--grey-50);
    border-radius: var(--border-radius-md);
    padding: var(--spacing-lg);
    margin: var(--spacing-md) 0;
}

.time-entry-summary h3 {
    margin-top: 0;
    margin-bottom: var(--spacing-md);
    font-size: var(--font-size-lg);
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