<?php
// modules/notifications/get_ajax.php - Get notifications for AJAX dropdown
require_once '../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Database connection
$conn = connectDB();

// Get recent notifications
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
$stmt = $conn->prepare("SELECT * FROM notifications 
                     WHERE user_id = ?
                     ORDER BY created_at DESC
                     LIMIT ?");
$stmt->bind_param('ii', $_SESSION['user_id'], $limit);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];

while ($notification = $result->fetch_assoc()) {
    // Format time ago
    $notification['time_ago'] = formatTimeAgo($notification['created_at']);
    $notifications[] = $notification;
}

// Get unread count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$unreadCount = $result->fetch_assoc()['count'];

// Prepare response
$response = [
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unreadCount,
    'has_more' => count($notifications) == $limit
];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;

// Helper function to format time ago
function formatTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' ' . ($mins == 1 ? 'minute' : 'minutes') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' ' . ($days == 1 ? 'day' : 'days') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}
?>