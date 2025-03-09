<?php
// modules/projects/view.php - View project details
require_once '../../config.php';

// Check permissions
if (!checkPermission('view_project')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$projectId = (int)$_GET['id'];

// Database connection
$conn = connectDB();

// Get project data with related information
$stmt = $conn->prepare("SELECT p.*, 
                      b.name as business_name, 
                      o.title as offer_title,
                      o.amount as offer_amount,
                      u.name as created_by_name
                      FROM projects p
                      LEFT JOIN businesses b ON p.business_id = b.id
                      LEFT JOIN offers o ON p.offer_id = o.id
                      LEFT JOIN users u ON p.created_by = u.id
                      WHERE p.id = ?");
$stmt->bind_param('i', $projectId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$project = $result->fetch_assoc();

// Calculate project timeline information
$today = new DateTime();
$startDate = $project['start_date'] ? new DateTime($project['start_date']) : null;
$endDate = $project['end_date'] ? new DateTime($project['end_date']) : null;

$projectStarted = $startDate && $today >= $startDate;
$projectEnded = $endDate && $today > $endDate;
$projectActive = $projectStarted && !$projectEnded && $project['status'] == 'in_progress';
$projectOverdue = $endDate && $today > $endDate && $project['status'] != 'completed' && $project['status'] != 'cancelled';

$daysPassed = $startDate ? $today->diff($startDate)->days : 0;
$totalDays = ($startDate && $endDate) ? $startDate->diff($endDate)->days : 0;
$daysLeft = ($endDate && $today <= $endDate) ? $today->diff($endDate)->days : 0;

$progressPercentage = 0;
if ($totalDays > 0 && $projectStarted) {
    if ($projectEnded) {
        $progressPercentage = 100;
    } else {
        $progressPercentage = min(round(($daysPassed / $totalDays) * 100), 100);
    }
}

// Page title
$pageTitle = $project['name'];
$pageActions = '';

// Check edit permission
if (checkPermission('edit_project')) {
    $pageActions .= '<a href="edit.php?id=' . $projectId . '" class="btn btn-primary"><span class="material-icons">edit</span> Edit</a>';
}

// Check delete permission
if (checkPermission('delete_project')) {
    $pageActions .= '<a href="delete.php?id=' . $projectId . '" class="btn btn-danger delete-item" data-confirm="Are you sure you want to delete this project?"><span class="material-icons">delete</span> Delete</a>';
}

// Include header
include '../../includes/header.php';

// Check for success message
$successMessage = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $successMessage = 'Project created successfully.';
            break;
        case 'updated':
            $successMessage = 'Project updated successfully.';
            break;
    }
}
?>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success"><?php echo $successMessage; ?></div>
<?php endif; ?>

<div class="project-details">
    <div class="card">
        <div class="card-header">
            <h2>Project Details</h2>
            <div class="card-header-actions">
                <a href="index.php" class="btn btn-text">
                    <span class="material-icons">arrow_back</span> Back to Projects
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <div class="project-header mb-xl">
                <h2 class="project-name"><?php echo $project['name']; ?></h2>
                <div class="project-status">
                    <span class="status-badge status-<?php echo $project['status']; ?>">
                        <?php echo str_replace('_', ' ', $project['status']); ?>
                    </span>
                    
                    <?php if ($projectOverdue): ?>
                        <span class="status-badge status-overdue">
                            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">warning</span>
                            Overdue
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($startDate && $endDate): ?>
                <div class="project-progress mb-xl">
                    <div class="progress-header">
                        <div class="progress-label">Project Timeline</div>
                        <div class="progress-percentage"><?php echo $progressPercentage; ?>% Complete</div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?php echo $progressPercentage; ?>%"></div>
                    </div>
                    <div class="progress-details">
                        <?php if ($projectStarted): ?>
                            <div class="progress-stat">
                                <div class="stat-label">Days Passed</div>
                                <div class="stat-value"><?php echo $daysPassed; ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$projectEnded): ?>
                            <div class="progress-stat">
                                <div class="stat-label">Days Left</div>
                                <div class="stat-value"><?php echo $daysLeft; ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="progress-stat">
                            <div class="stat-label">Total Days</div>
                            <div class="stat-value"><?php echo $totalDays; ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="detail-grid">
                <div class="detail-item full-width">
                    <div class="detail-label">Description</div>
                    <div class="detail-value"><?php echo nl2br($project['description'] ?? 'N/A'); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Budget</div>
                    <div class="detail-value"><?php echo '' . formatCurrency($project['budget'], 2); ?></div>
                </div>
                
                <?php if ($project['offer_id']): ?>
                <div class="detail-item">
                    <div class="detail-label">Offer Amount</div>
                    <div class="detail-value">
                        <?php echo '' . formatCurrency($project['offer_amount'], 2); ?>
                        <?php if ($project['budget'] != $project['offer_amount']): ?>
                            <span class="text-muted">(Budget differs from original offer)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="detail-item">
                    <div class="detail-label">Start Date</div>
                    <div class="detail-value">
                        <?php echo $project['start_date'] ? date('M j, Y', strtotime($project['start_date'])) : 'N/A'; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">End Date</div>
                    <div class="detail-value">
                        <?php if ($project['end_date']): ?>
                            <span <?php echo $projectOverdue ? 'class="text-danger"' : ''; ?>>
                                <?php echo date('M j, Y', strtotime($project['end_date'])); ?>
                                <?php if ($projectOverdue): ?>
                                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">warning</span>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Business</div>
                    <div class="detail-value">
                        <?php if ($project['business_id']): ?>
                            <a href="../businesses/view.php?id=<?php echo $project['business_id']; ?>">
                                <?php echo $project['business_name']; ?>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Based on Offer</div>
                    <div class="detail-value">
                        <?php if ($project['offer_id']): ?>
                            <a href="../offers/view.php?id=<?php echo $project['offer_id']; ?>">
                                <?php echo $project['offer_title']; ?>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created By</div>
                    <div class="detail-value"><?php echo $project['created_by_name']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Created Date</div>
                    <div class="detail-value"><?php echo date('M j, Y, g:i a', strtotime($project['created_at'])); ?></div>
                </div>
            </div>
            
            <div class="form-actions mt-xl">
                <?php if (checkPermission('edit_project')): ?>
                    <a href="edit.php?id=<?php echo $projectId; ?>" class="btn btn-primary">
                        <span class="material-icons">edit</span> Edit Project
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Project-specific styles */
.project-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--grey-200);
    padding-bottom: var(--spacing-md);
}

.project-name {
    font-size: var(--font-size-2xl);
    margin: 0;
}

.project-progress {
    background-color: var(--grey-50);
    border-radius: var(--border-radius-md);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.progress-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: var(--spacing-sm);
}

.progress-label {
    font-weight: var(--font-weight-medium);
}

.progress-percentage {
    font-weight: var(--font-weight-semibold);
    color: var(--primary-color);
}

.progress-bar-container {
    height: 8px;
    background-color: var(--grey-200);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: var(--spacing-md);
}

.progress-bar {
    height: 100%;
    background-color: var(--primary-color);
    border-radius: 4px;
}

.progress-details {
    display: flex;
    justify-content: space-between;
}

.progress-stat {
    text-align: center;
    flex: 1;
}

.stat-label {
    font-size: var(--font-size-xs);
    color: var(--grey-600);
    margin-bottom: var(--spacing-xs);
}

.stat-value {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
}

.status-overdue {
    background-color: rgba(230, 57, 70, 0.15);
    color: var(--danger-color);
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Setup delete confirmation
        const deleteLinks = document.querySelectorAll('.delete-item');
        deleteLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm(this.dataset.confirm)) {
                    e.preventDefault();
                }
            });
        });
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>