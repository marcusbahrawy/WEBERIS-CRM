<?php
// modules/projects/edit.php - Edit an existing project
require_once '../../config.php';

// Check permissions
if (!checkPermission('edit_project')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$projectId = (int)$_GET['id'];

// Page title
$pageTitle = "Edit Project";

// Database connection
$conn = connectDB();

// Get project data
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param('i', $projectId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$project = $result->fetch_assoc();

// Get all businesses for dropdown
$businessesResult = $conn->query("SELECT id, name FROM businesses ORDER BY name ASC");
$businesses = [];
if ($businessesResult->num_rows > 0) {
    while ($business = $businessesResult->fetch_assoc()) {
        $businesses[] = $business;
    }
}

// Get all accepted offers for dropdown
$offersResult = $conn->query("SELECT id, title, business_id, amount FROM offers WHERE status = 'accepted' ORDER BY title ASC");
$offers = [];
if ($offersResult->num_rows > 0) {
    while ($offer = $offersResult->fetch_assoc()) {
        $offers[] = $offer;
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
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $status = sanitizeInput($_POST['status']);
        $startDate = !empty($_POST['start_date']) ? sanitizeInput($_POST['start_date']) : null;
        $endDate = !empty($_POST['end_date']) ? sanitizeInput($_POST['end_date']) : null;
        $budget = !empty($_POST['budget']) ? (float)$_POST['budget'] : 0;
        $businessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        $offerId = !empty($_POST['offer_id']) ? (int)$_POST['offer_id'] : null;
        
        // Validate required fields
        if (empty($name)) {
            $error = "Project name is required.";
        } elseif (!empty($startDate) && !empty($endDate) && strtotime($endDate) < strtotime($startDate)) {
            $error = "End date cannot be earlier than start date.";
        } else {
            // Update project
            $stmt = $conn->prepare("UPDATE projects SET name = ?, description = ?, status = ?, start_date = ?, end_date = ?, budget = ?, business_id = ?, offer_id = ? WHERE id = ?");
            $stmt->bind_param('sssssdiiii', $name, $description, $status, $startDate, $endDate, $budget, $businessId, $offerId, $projectId);
            
            if ($stmt->execute()) {
                $success = "Project updated successfully.";
                
                // Refresh project data
                $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
                $stmt->bind_param('i', $projectId);
                $stmt->execute();
                $result = $stmt->get_result();
                $project = $result->fetch_assoc();
            } else {
                $error = "Error updating project: " . $conn->error;
            }
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Edit Project</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $projectId; ?>" class="btn btn-text">
                <span class="material-icons">visibility</span> View Project
            </a>
            <a href="index.php" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Projects
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" data-validate="true">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="name">Project Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo $project['name']; ?>" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="not_started" <?php echo ($project['status'] === 'not_started') ? 'selected' : ''; ?>>Not Started</option>
                            <option value="in_progress" <?php echo ($project['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="on_hold" <?php echo ($project['status'] === 'on_hold') ? 'selected' : ''; ?>>On Hold</option>
                            <option value="completed" <?php echo ($project['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($project['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4"><?php echo $project['description']; ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $project['start_date']; ?>">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $project['end_date']; ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="budget">Budget (<?php echo getSetting('currency_symbol', 'NOK'); ?>)</label>
                        <input type="number" id="budget" name="budget" class="form-control" step="0.01" min="0" value="<?php echo number_format((float)$project['budget'], 2, '.', ''); ?>">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="business_id">Business</label>
                        <select id="business_id" name="business_id" class="form-control">
                            <option value="">-- No Business --</option>
                            <?php foreach ($businesses as $business): ?>
                                <option value="<?php echo $business['id']; ?>" <?php echo ($project['business_id'] == $business['id']) ? 'selected' : ''; ?>>
                                    <?php echo $business['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="offer_id">Based on Offer</label>
                <select id="offer_id" name="offer_id" class="form-control">
                    <option value="">-- No Offer --</option>
                    <?php foreach ($offers as $offer): ?>
                        <option value="<?php echo $offer['id']; ?>" <?php echo ($project['offer_id'] == $offer['id']) ? 'selected' : ''; ?>>
                            <?php echo $offer['title']; ?>
                            <?php 
                            // Find the business name for this offer
                            if ($offer['business_id']) {
                                foreach ($businesses as $business) {
                                    if ($business['id'] == $offer['business_id']) {
                                        echo ' (' . $business['name'] . ')';
                                        break;
                                    }
                                }
                            }
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <a href="view.php?id=<?php echo $projectId; ?>" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Project</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Link offer with business selection
        const offerSelect = document.getElementById('offer_id');
        const businessSelect = document.getElementById('business_id');
        const budgetField = document.getElementById('budget');
        const currentOfferId = <?php echo $project['offer_id'] ? $project['offer_id'] : 'null'; ?>;
        
        if (offerSelect && businessSelect) {
            offerSelect.addEventListener('change', function() {
                const offerId = this.value;
                
                if (offerId && offerId != currentOfferId) {
                    // Find the selected option
                    const selectedOption = this.options[this.selectedIndex];
                    
                    // Check if we can extract business ID from the option's data
                    <?php
                    // Create a JavaScript object to map offer IDs to business IDs and amounts
                    echo "const offerData = " . json_encode(array_map(function($offer) {
                        return [
                            'businessId' => $offer['business_id'],
                            'amount' => $offer['amount'] ?? 0
                        ];
                    }, $offers), JSON_NUMERIC_CHECK) . ";";
                    ?>
                    
                    // Find the offer data
                    let offerInfo = null;
                    for (let i = 0; i < offerData.length; i++) {
                        if (offerData[i].id == offerId) {
                            offerInfo = offerData[i];
                            break;
                        }
                    }
                    
                    if (offerInfo && offerInfo.businessId) {
                        // Select the corresponding business
                        for (let i = 0; i < businessSelect.options.length; i++) {
                            if (businessSelect.options[i].value == offerInfo.businessId) {
                                businessSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                    
                    // Update budget if available
                    if (offerInfo && offerInfo.amount && budgetField) {
                        budgetField.value = offerInfo.amount;
                    }
                }
            });
        }
        
        // Validate end date is after start date
        const startDateField = document.getElementById('start_date');
        const endDateField = document.getElementById('end_date');
        
        if (startDateField && endDateField) {
            const validateDates = function() {
                if (startDateField.value && endDateField.value) {
                    const startDate = new Date(startDateField.value);
                    const endDate = new Date(endDateField.value);
                    
                    if (endDate < startDate) {
                        endDateField.setCustomValidity('End date cannot be earlier than start date');
                    } else {
                        endDateField.setCustomValidity('');
                    }
                }
            };
            
            startDateField.addEventListener('change', validateDates);
            endDateField.addEventListener('change', validateDates);
        }
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>