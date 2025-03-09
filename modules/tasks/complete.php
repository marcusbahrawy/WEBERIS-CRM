<?php
// modules/tasks/complete.php - Mark a task as completed
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
$stmt = $conn->prepare("SELECT t.*, 
                      u.name as created_by_name
                      FROM tasks t
                      LEFT JOIN users u ON t.created_by = u.id
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

// Check if user can complete this task
// Only the assigned user or admin can complete it
if ($task['assigned_to'] != $_SESSION['user_id'] && $_SESSION['role_name'] !== 'admin') {
    header("Location: view.php?id=" . $taskId);
    exit;
}

// Check if task is already completed
if ($task['status'] === 'completed') {
    // Already completed, redirect back to task
    header("Location: view.php?id=" . $taskId);
    exit;
}

// Complete the task
$completedAt = date('Y-m-d H:i:s');
$stmt = $conn->prepare("UPDATE tasks SET status = 'completed', completed_at = ? WHERE id = ?");
$stmt->bind_param('si', $completedAt, $taskId);

if ($stmt->execute()) {
    // Add a comment about completion
    $completionComment = "Task marked as completed by " . $_SESSION['name'];
    $stmt = $conn->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $taskId, $_SESSION['user_id'], $completionComment);
    $stmt->execute();
    
    // Notify task creator if not the same person
    if ($task['created_by'] != $_SESSION['user_id']) {
        $notificationTitle = "Task completed";
        $notificationMessage = $_SESSION['name'] . " completed the task: " . $task['title'];
        $notificationLink = "/modules/tasks/view.php?id=" . $taskId;
        
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, related_id) VALUES (?, 'task_completed', ?, ?, ?, ?)");
        $stmt->bind_param('isssi', $task['created_by'], $notificationTitle, $notificationMessage, $notificationLink, $taskId);
        $stmt->execute();
    }
    
    // Redirect back to task or return to previous page if specified
    if (isset($_GET['redirect']) && $_GET['redirect']) {
        $redirect = urldecode($_GET['redirect']);
        header("Location: " . $redirect);
    } else {
        header("Location: view.php?id=" . $taskId . "&status_success=1");
    }
    exit;
} else {
    // Failed to update, redirect with error
    header("Location: view.php?id=" . $taskId . "&error=update_failed");
    exit;
}

// We should never reach here, but just in case
header("Location: index.php");
exit;
?>