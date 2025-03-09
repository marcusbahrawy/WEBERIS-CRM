<?php
// modules/notifications/mark_all_read.php - Mark all notifications as read
require_once '../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirectToLogin();
}

// Database connection
$conn = connectDB();

// Mark all as read
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();

// Redirect back to referring page or notifications page
$redirectTo = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : SITE_URL . '/modules/notifications/index.php';
header("Location: " . $redirectTo);
exit;
?>