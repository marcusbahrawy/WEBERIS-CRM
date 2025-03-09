<?php
// includes/header.php - Header template
require_once dirname(__DIR__) . '/config.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    redirectToLogin();
}

// Determine active page
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Get notification count for the current user
$notificationCount = 0;
if (isLoggedIn()) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $notificationCount = $row['count'];
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/modern-styles.css">
</head>
<body>
    <div class="app-container">
        <button class="mobile-menu-toggle" id="mobileMenuToggle">
    <span class="material-icons">menu</span>
</button>
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1><?php echo APP_NAME; ?></h1>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/dashboard.php">
                            <span class="material-icons">dashboard</span>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    
                    <?php if (checkPermission('view_business')): ?>
                    <li class="<?php echo $currentDir === 'businesses' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/modules/businesses/index.php">
                            <span class="material-icons">business</span>
                            <span>Businesses</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (checkPermission('view_contact')): ?>
                    <li class="<?php echo $currentDir === 'contacts' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/modules/contacts/index.php">
                            <span class="material-icons">contacts</span>
                            <span>Contacts</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (checkPermission('view_lead')): ?>
                    <li class="<?php echo $currentDir === 'leads' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/modules/leads/index.php">
                            <span class="material-icons">lightbulb</span>
                            <span>Leads</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (checkPermission('view_offer')): ?>
                    <li class="<?php echo $currentDir === 'offers' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/modules/offers/index.php">
                            <span class="material-icons">description</span>
                            <span>Offers</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (checkPermission('view_project')): ?>
                    <li class="<?php echo $currentDir === 'projects' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/modules/projects/index.php">
                            <span class="material-icons">assignment</span>
                            <span>Projects</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (checkPermission('view_task')): ?>
                    <li class="<?php echo $currentDir === 'tasks' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/modules/tasks/index.php">
                            <span class="material-icons">check_box</span>
                            <span>Tasks</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (checkPermission('view_service_agreement')): ?>
                    <li class="<?php echo $currentDir === 'service_agreements' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/modules/service_agreements/index.php">
                            <span class="material-icons">verified</span>
                            <span>Service Agreements</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="main-header">
                <div class="header-search">
                    <form action="<?php echo SITE_URL; ?>/search.php" method="GET">
                        <div class="search-input">
                            <span class="material-icons">search</span>
                            <input type="text" name="q" placeholder="Search...">
                        </div>
                    </form>
                </div>
                
                <div class="header-actions">
                    <!-- Notifications -->
                    <div class="notifications">
                        <button class="notifications-icon" id="notificationsToggle">
                            <span class="material-icons">notifications</span>
                            <?php if ($notificationCount > 0): ?>
                                <span class="notifications-badge"><?php echo $notificationCount; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="notifications-dropdown" id="notificationsDropdown">
                            <div class="notifications-header">
                                <h3>Notifications</h3>
                                <?php if ($notificationCount > 0): ?>
                                    <a href="<?php echo SITE_URL; ?>/modules/notifications/mark_all_read.php" class="mark-all-read">Mark all as read</a>
                                <?php endif; ?>
                            </div>
                            <div class="notifications-content" id="notificationsContent">
                                <!-- Notifications will be loaded via AJAX -->
                                <div class="loading-spinner">
                                    <div class="spinner"></div>
                                </div>
                            </div>
                            <div class="notifications-footer">
                                <a href="<?php echo SITE_URL; ?>/modules/notifications/index.php">View all notifications</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-menu">
                        <button class="user-dropdown-toggle">
                            <div class="user-info">
                                <span class="user-name"><?php echo $_SESSION['name']; ?></span>
                                <span class="user-role"><?php echo $_SESSION['role_name']; ?></span>
                            </div>
                            <span class="material-icons">arrow_drop_down</span>
                        </button>
                        <div class="user-dropdown-menu">
    <a href="<?php echo SITE_URL; ?>/profile.php">
        <span class="material-icons">person</span>
        <span>Profile</span>
    </a>
    
    <!-- System Settings link for admin users -->
    <?php if ($_SESSION['role_name'] === 'admin'): ?>
    <a href="<?php echo SITE_URL; ?>/modules/settings/index.php">
        <span class="material-icons">tune</span>
        <span>System Settings</span>
    </a>
    <?php endif; ?>
    
    <div class="dropdown-divider"></div>
    <a href="<?php echo SITE_URL; ?>/logout.php">
        <span class="material-icons">logout</span>
        <span>Logout</span>
    </a>
</div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <div class="content">
                <?php if (isset($pageTitle)): ?>
                <div class="page-header">
                    <h1><?php echo $pageTitle; ?></h1>
                    <?php if (isset($pageActions)): ?>
                    <div class="page-actions">
                        <?php echo $pageActions; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>