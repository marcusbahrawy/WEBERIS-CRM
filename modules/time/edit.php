<?php
// modules/time/edit.php - Edit an existing time entry
require_once '../../config.php';

// Check permissions
if (!checkPermission('edit_time')) {
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

// Get time entry data
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

// Check if user can edit this time entry
// Users can only edit their own time entries unless they have view_time_reports permission
if ($timeEntry['user_id'] != $_SESSION['user_id'] && !checkPermission('view_time_reports')) {
    header("Location: index.php");
    exit;
}

// Get task, project and business names
$taskTitle = '';
$projectName = '';
$businessName = '';

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

// Break down dates and times
$startDateTime = new DateTime($timeEntry['start_time']);
$date = $startDateTime->format('Y-m-d');
$startHour = $startDateTime->format('H');
$startMinute = $startDateTime->format('i');

$endDateTime = null;
$endHour = '';
$endMinute = '';
if ($timeEntry['end_time']) {
    $endDateTime = new DateTime($timeEntry['end_time']);
    $endHour = $endDateTime->format('H');
    $endMinute = $endDateTime->format('i');
}

// Get all tasks for dropdown
$tasksResult = $conn->query("SELECT t.id, t.title, t.business_id, t.project_id, b.name as business_name, p.name as project_name 
                           FROM tasks t 
                           LEFT JOIN businesses b ON t.business_id = b.id 
                           LEFT JOIN projects p ON t.project_id = p.id 
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
                
                // Update time entry
                $stmt = $conn->prepare("UPDATE time_entries SET 
                    task_id = ?, 
                    project_id = ?, 
                    business_id = ?, 
                    description = ?, 
                    start_time = ?, 
                    end_time = ?, 
                    duration = ?, 
                    is_billable = ? 
                    WHERE id = ?");
                $stmt->bind_param('iiisssiis', $selectedTaskId, $selectedProjectId, $selectedBusinessId, 
                                 $description, $startTime, $endTime, $duration, $isBillable, $timeEntryId);
                
                if ($stmt->execute()) {
                    $success = "Time entry updated successfully.";
                    
                    // Refresh time entry data
                    $stmt = $conn->prepare("SELECT * FROM time_entries WHERE id = ?");
                    $stmt->bind_param('i', $timeEntryId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $timeEntry = $result->fetch_assoc();
                    
                    // Update the displayed time values
                    $startDateTime = new DateTime($timeEntry['start_time']);
                    $date = $startDateTime->format('Y-m-d');
                    $startHour = $startDateTime->format('H');
                    $startMinute = $startDateTime->format('i');
                    
                    if ($timeEntry['end_time']) {
                        $endDateTime = new DateTime($timeEntry['end_time']);
                        $endHour = $endDateTime->format('H');
                        $endMinute = $endDateTime->format('i');
                    }
                } else {
                    $error = "Error updating time entry: " . $conn->error;
                }