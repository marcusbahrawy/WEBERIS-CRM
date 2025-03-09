<?php
// dashboard.php - Main dashboard
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirectToLogin();
}

// Header
include 'includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-summary">
        <div class="summary-card">
            <div class="summary-icon">
                <span class="material-icons">business</span>
            </div>
            <div class="summary-data">
                <h3>Businesses</h3>
                <?php
                $conn = connectDB();
                $result = $conn->query("SELECT COUNT(*) as count FROM businesses");
                $businessCount = $result->fetch_assoc()['count'];
                ?>
                <span class="count"><?php echo $businessCount; ?></span>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon">
                <span class="material-icons">contacts</span>
            </div>
            <div class="summary-data">
                <h3>Contacts</h3>
                <?php
                $result = $conn->query("SELECT COUNT(*) as count FROM contacts");
                $contactCount = $result->fetch_assoc()['count'];
                ?>
                <span class="count"><?php echo $contactCount; ?></span>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon">
                <span class="material-icons">lightbulb</span>
            </div>
            <div class="summary-data">
                <h3>Leads</h3>
                <?php
                $result = $conn->query("SELECT COUNT(*) as count FROM leads");
                $leadCount = $result->fetch_assoc()['count'];
                ?>
                <span class="count"><?php echo $leadCount; ?></span>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon">
                <span class="material-icons">description</span>
            </div>
            <div class="summary-data">
                <h3>Offers</h3>
                <?php
                $result = $conn->query("SELECT COUNT(*) as count FROM offers");
                $offerCount = $result->fetch_assoc()['count'];
                ?>
                <span class="count"><?php echo $offerCount; ?></span>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon">
                <span class="material-icons">assignment</span>
            </div>
            <div class="summary-data">
                <h3>Projects</h3>
                <?php
                $result = $conn->query("SELECT COUNT(*) as count FROM projects");
                $projectCount = $result->fetch_assoc()['count'];
                $conn->close();
                ?>
                <span class="count"><?php echo $projectCount; ?></span>
            </div>
        </div>
    </div>
    
    <div class="dashboard-recent">
        <div class="dashboard-section">
            <h2>Recent Leads</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Business</th>
                            <th>Value</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conn = connectDB();
                        $result = $conn->query("SELECT l.id, l.title, l.value, l.status, l.created_at, b.name as business_name 
                                               FROM leads l 
                                               LEFT JOIN businesses b ON l.business_id = b.id 
                                               ORDER BY l.created_at DESC LIMIT 5");
                        
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>{$row['id']}</td>";
                                echo "<td>{$row['title']}</td>";
                                echo "<td>{$row['business_name']}</td>";
                                echo "<td>" . formatCurrency($row['value'], 2) . "</td>";
                                echo "<td><span class='status-badge status-{$row['status']}'>{$row['status']}</span></td>";
                                echo "<td>" . date('M j, Y', strtotime($row['created_at'])) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6'>No leads found.</td></tr>";
                        }
                        $conn->close();
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="view-all">
                <a href="modules/leads/index.php" class="btn btn-text">View all leads</a>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h2>Recent Projects</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Business</th>
                            <th>Status</th>
                            <th>Deadline</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conn = connectDB();
                        $result = $conn->query("SELECT p.id, p.name, p.status, p.end_date, b.name as business_name 
                                               FROM projects p 
                                               LEFT JOIN businesses b ON p.business_id = b.id 
                                               ORDER BY p.created_at DESC LIMIT 5");
                        
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>{$row['id']}</td>";
                                echo "<td>{$row['name']}</td>";
                                echo "<td>{$row['business_name']}</td>";
                                echo "<td><span class='status-badge status-{$row['status']}'>" . str_replace('_', ' ', $row['status']) . "</span></td>";
                                echo "<td>" . ($row['end_date'] ? date('M j, Y', strtotime($row['end_date'])) : 'N/A') . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5'>No projects found.</td></tr>";
                        }
                        $conn->close();
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="view-all">
                <a href="modules/projects/index.php" class="btn btn-text">View all projects</a>
            </div>
        </div>
    </div>
    
    <div class="dashboard-stats">
        <div class="dashboard-section">
            <h2>Lead Status</h2>
            <div class="stats-container">
                <?php
                $conn = connectDB();
                
                // Get lead status counts
                $leadStatusResult = $conn->query("SELECT status, COUNT(*) as count FROM leads GROUP BY status ORDER BY FIELD(status, 'new', 'qualified', 'proposal', 'negotiation', 'won', 'lost')");
                
                $totalLeads = 0;
                $leadStatusCounts = [];
                
                if ($leadStatusResult->num_rows > 0) {
                    while ($statusRow = $leadStatusResult->fetch_assoc()) {
                        $leadStatusCounts[$statusRow['status']] = $statusRow['count'];
                        $totalLeads += $statusRow['count'];
                    }
                }
                
                if ($totalLeads > 0):
                ?>
                    <div class="chart-container">
                        <div class="horizontal-bar-chart">
                            <?php foreach ($leadStatusCounts as $status => $count): 
                                $percentage = round(($count / $totalLeads) * 100);
                            ?>
                                <div class="chart-row">
                                    <div class="chart-label"><?php echo ucfirst($status); ?></div>
                                    <div class="chart-bar-container">
                                        <div class="chart-bar status-<?php echo $status; ?>" style="width: <?php echo $percentage; ?>%;">
                                            <span class="chart-value"><?php echo $count; ?></span>
                                        </div>
                                    </div>
                                    <div class="chart-percentage"><?php echo $percentage; ?>%</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">No lead data available.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h2>Project Status</h2>
            <div class="stats-container">
                <?php
                // Get project status counts
                $projectStatusResult = $conn->query("SELECT status, COUNT(*) as count FROM projects GROUP BY status ORDER BY FIELD(status, 'not_started', 'in_progress', 'on_hold', 'completed', 'cancelled')");
                
                $totalProjects = 0;
                $projectStatusCounts = [];
                
                if ($projectStatusResult->num_rows > 0) {
                    while ($statusRow = $projectStatusResult->fetch_assoc()) {
                        $projectStatusCounts[$statusRow['status']] = $statusRow['count'];
                        $totalProjects += $statusRow['count'];
                    }
                }
                
                if ($totalProjects > 0):
                ?>
                    <div class="chart-container">
                        <div class="horizontal-bar-chart">
                            <?php foreach ($projectStatusCounts as $status => $count): 
                                $percentage = round(($count / $totalProjects) * 100);
                            ?>
                                <div class="chart-row">
                                    <div class="chart-label"><?php echo ucfirst(str_replace('_', ' ', $status)); ?></div>
                                    <div class="chart-bar-container">
                                        <div class="chart-bar status-<?php echo $status; ?>" style="width: <?php echo $percentage; ?>%;">
                                            <span class="chart-value"><?php echo $count; ?></span>
                                        </div>
                                    </div>
                                    <div class="chart-percentage"><?php echo $percentage; ?>%</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">No project data available.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h2>Recent Activity</h2>
            <div class="activity-feed">
                <?php
                // Recent activity - collects latest entries from leads, offers, and projects
                $query = "
                    (SELECT 'lead' as type, id, title as name, created_at, 'was created' as action FROM leads ORDER BY created_at DESC LIMIT 5)
                    UNION
                    (SELECT 'offer' as type, id, title as name, created_at, 'was created' as action FROM offers ORDER BY created_at DESC LIMIT 5)
                    UNION
                    (SELECT 'project' as type, id, name, created_at, 'was created' as action FROM projects ORDER BY created_at DESC LIMIT 5)
                    ORDER BY created_at DESC
                    LIMIT 10";
                
                $activityResult = $conn->query($query);
                
                if ($activityResult->num_rows > 0):
                ?>
                    <ul class="activity-list">
                        <?php while ($activity = $activityResult->fetch_assoc()): ?>
                            <li class="activity-item">
                                <span class="activity-icon status-<?php 
                                    if ($activity['type'] === 'lead') echo 'new';
                                    elseif ($activity['type'] === 'offer') echo 'draft';
                                    else echo 'not_started';
                                ?>">
                                    <span class="material-icons">
                                        <?php 
                                        if ($activity['type'] === 'lead') echo 'lightbulb';
                                        elseif ($activity['type'] === 'offer') echo 'description';
                                        else echo 'assignment';
                                        ?>
                                    </span>
                                </span>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <a href="modules/<?php echo $activity['type']; ?>s/view.php?id=<?php echo $activity['id']; ?>">
                                            <?php echo $activity['name']; ?>
                                        </a>
                                        <span class="activity-action"><?php echo $activity['action']; ?></span>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-center text-muted">No recent activity found.</p>
                <?php endif;
                $conn->close();
                ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Chart and Activity Styles */
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--spacing-xl);
    margin-top: var(--spacing-xl);
}

.stats-container {
    padding: var(--spacing-md);
}

.chart-container {
    margin-top: var(--spacing-md);
}

.horizontal-bar-chart {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.chart-row {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.chart-label {
    width: 100px;
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    text-align: right;
}

.chart-bar-container {
    flex: 1;
    height: 24px;
    background-color: var(--grey-100);
    border-radius: var(--border-radius-sm);
    overflow: hidden;
}

.chart-bar {
    height: 100%;
    min-width: 30px;
    display: flex;
    align-items: center;
    padding: 0 var(--spacing-sm);
    color: white;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    transition: width 0.5s ease-in-out;
}

.chart-bar.status-new, 
.chart-bar.status-not_started {
    background-color: var(--info-color);
}

.chart-bar.status-qualified,
.chart-bar.status-in_progress,
.chart-bar.status-proposal {
    background-color: var(--warning-color);
}

.chart-bar.status-won,
.chart-bar.status-completed,
.chart-bar.status-accepted {
    background-color: var(--success-color);
}

.chart-bar.status-lost,
.chart-bar.status-cancelled,
.chart-bar.status-rejected {
    background-color: var(--danger-color);
}

.chart-bar.status-negotiation,
.chart-bar.status-on_hold {
    background-color: var(--secondary-color);
}

.chart-percentage {
    width: 50px;
    font-size: var(--font-size-sm);
    color: var(--grey-600);
    text-align: left;
}

.activity-feed {
    padding: var(--spacing-md);
}

.activity-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-md);
    padding: var(--spacing-md) 0;
    border-bottom: 1px solid var(--grey-200);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.activity-icon .material-icons {
    font-size: 18px;
}

.activity-content {
    flex: 1;
}

.activity-title {
    margin-bottom: var(--spacing-xs);
}

.activity-title a {
    font-weight: var(--font-weight-medium);
}

.activity-action {
    color: var(--grey-600);
    font-size: var(--font-size-sm);
    margin-left: var(--spacing-xs);
}

.activity-time {
    font-size: var(--font-size-xs);
    color: var(--grey-500);
}
</style>

<?php
// Footer
include 'includes/footer.php';
?>