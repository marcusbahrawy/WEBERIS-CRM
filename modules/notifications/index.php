<?php
// modules/notifications/index.php - View all notifications
require_once '../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirectToLogin();
}

// Page title
$pageTitle = "Notifications";

// Database connection
$conn = connectDB();

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total notifications count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $perPage);

// Get notifications with pagination
$stmt = $conn->prepare("SELECT * FROM notifications 
                     WHERE user_id = ?
                     ORDER BY created_at DESC
                     LIMIT ? OFFSET ?");
$stmt->bind_param('iii', $_SESSION['user_id'], $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];

while ($notification = $result->fetch_assoc()) {
    $notifications[] = $notification;
}

// Mark all as read when viewing this page
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Your Notifications</h2>
    </div>
    
    <div class="card-body">
        <?php if (count($notifications) > 0): ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                        <div class="notification-icon">
                            <?php
                            // Choose icon based on notification type
                            $icon = 'notifications';
                            switch ($notification['type']) {
                                case 'task_assigned':
                                    $icon = 'assignment_ind';
                                    break;
                                case 'task_completed':
                                    $icon = 'task_alt';
                                    break;
                                case 'task_comment':
                                    $icon = 'comment';
                                    break;
                                case 'task_update':
                                    $icon = 'update';
                                    break;
                                case 'task_deleted':
                                    $icon = 'delete';
                                    break;
                            }
                            ?>
                            <span class="material-icons"><?php echo $icon; ?></span>
                        </div>
                        
                        <div class="notification-content">
                            <div class="notification-header">
                                <h3 class="notification-title"><?php echo $notification['title']; ?></h3>
                                <span class="notification-time"><?php echo formatTimeAgo($notification['created_at']); ?></span>
                            </div>
                            <div class="notification-message"><?php echo $notification['message']; ?></div>
                        </div>
                        
                        <?php if (!empty($notification['link'])): ?>
                            <div class="notification-action">
                                <a href="<?php echo SITE_URL . $notification['link']; ?>" class="btn btn-text">View</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?>" class="pagination-item">
                            <span class="material-icons">navigate_before</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $startPage + 4);
                    
                    if ($startPage > 1) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $activeClass = ($i === $page) ? 'active' : '';
                        echo "<a href='?page={$i}' class='pagination-item {$activeClass}'>{$i}</a>";
                    }
                    
                    if ($endPage < $totalPages) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo ($page + 1); ?>" class="pagination-item">
                            <span class="material-icons">navigate_next</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-icons">notifications_none</span>
                </div>
                <h3>No notifications</h3>
                <p>You don't have any notifications yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.notifications-list {
    display: flex;
    flex-direction: column;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--grey-200);
    transition: background-color var(--transition-fast);
}

.notification-item:hover {
    background-color: var(--grey-50);
}

.notification-item.unread {
    background-color: rgba(67, 97, 238, 0.05);
}

.notification-icon {
    margin-right: var(--spacing-md);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--primary-light);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-dark);
}

.notification-content {
    flex: 1;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--spacing-xs);
}

.notification-title {
    margin: 0;
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-semibold);
}

.notification-time {
    font-size: var(--font-size-sm);
    color: var(--grey-500);
}

.notification-message {
    color: var(--grey-700);
    margin-bottom: var(--spacing-xs);
}

.notification-action {
    margin-left: var(--spacing-md);
}
</style>

<?php
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

// Include footer
include '../../includes/footer.php';
$conn->close();
?>