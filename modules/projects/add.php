<?php
// modules/projects/add.php - Add a new project
require_once '../../config.php';

// Check permissions
if (!checkPermission('add_project')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Add Project";

// Get parameters from query
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;
$offerId = isset($_GET['offer_id']) && is_numeric($_GET['offer_id']) ? (int)$_GET['offer_id'] : null;
$businessName = '';
$offerTitle = '';
$offerAmount = 0;
$offerBusinessId = null;

$conn = connectDB();

// Check if business exists and get name
if ($businessId) {
    $stmt = $conn->prepare("SELECT name FROM businesses WHERE id = ?");
    $stmt->bind_param('i', $businessId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $businessName = $result->fetch_assoc()['name'];
    } else {
        $businessId = null;
    }
}

// Check if offer exists and get info
if ($offerId) {
    $stmt = $conn->prepare("SELECT title, amount, business_id FROM offers WHERE id = ? AND status = 'accepted'");
    $stmt->bind_param('i', $offerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $offerData = $result->fetch_assoc();
        $offerTitle = $offerData['title'];
        $offerAmount = $offerData['amount'];
        $offerBusinessId = $offerData['business_id'];
        
        // If no business ID was provided but offer has one, use that
        if (!$businessId && $offerBusinessId) {
            $businessId = $offerBusinessId;
            
            // Get business name
            $stmt = $conn->prepare("SELECT name FROM businesses WHERE id = ?");
            $stmt->bind_param('i', $businessId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $businessName = $result->fetch_assoc()['name'];
            }
        }
    } else {
        // If offer not found or not accepted, clear the offer ID
        $offerId = null;
    }
}

// Get all businesses for dropdown
$businessesResult = $conn->query("SELECT id, name FROM businesses ORDER BY name ASC");
$businesses = [];
if ($businessesResult->num_rows > 0) {
    while ($business = $businessesResult->fetch_assoc()) {
        $businesses[] = $business;
    }
}

// Get all accepted offers for dropdown
$offersResult = $conn->query("SELECT id, title, business_id FROM offers WHERE status = 'accepted' ORDER BY title ASC");
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
        $selectedBusinessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        $selectedOfferId = !empty($_POST['offer_id']) ? (int)$_POST['offer_id'] : null;
        
        // Validate required fields
        if (empty($name)) {
            $error = "Project name is required.";
        } elseif (!empty($startDate) && !empty($endDate) && strtotime($endDate) < strtotime($startDate)) {
            $error = "End date cannot be earlier than start date.";
        } else {
            // Insert new project
            $stmt = $conn->prepare("INSERT INTO projects (name, description, status, start_date, end_date, budget, business_id, offer_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssssdiii', $name, $description, $status, $startDate, $endDate, $budget, $selectedBusinessId, $selectedOfferId, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $projectId = $conn->insert_id;
                $success = "Project created successfully.";
                
                // Redirect to the new project page
                header("Location: view.php?id=" . $projectId . "&success=created");
                exit;
            } else {
                $error = "Error creating project: " . $conn->error;
            }
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>
            <?php
            $title = "Create New Project";
            
            if ($offerId) {
                $title = "Create Project for Offer: " . $offerTitle;
                if ($businessId) {
                    $title .= " (" . $businessName . ")";
                }
            } elseif ($businessId) {
                $title = "Create Project for " . $businessName;
            }
            
            echo $title;
            ?>
        </h2>
        <div class="card-header-actions">
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
                        <input type="text" id="name" name="name" class="form-control" required 
                               value="<?php echo $offerTitle ? 'Project: ' . $offerTitle : ''; ?>">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="not_started" selected>Not Started</option>
                            <option value="in_progress">In Progress</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control">
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
    <label for="budget">Budget (<?php echo getSetting('currency_symbol', 'NOK'); ?>)</label>
    <input type="number" id="budget" name="budget" class="form-control" step="0.01" min="0" value="<?php echo $offerAmount; ?>">
</div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="business_id">Business</label>
                        <select id="business_id" name="business_id" class="form-control">
                            <option value="">-- No Business --</option>
                            <?php foreach ($businesses as $business): ?>
                                <option value="<?php echo $business['id']; ?>" <?php echo ($businessId == $business['id']) ? 'selected' : ''; ?>>
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
                        <option value="<?php echo $offer['id']; ?>" <?php echo ($offerId == $offer['id']) ? 'selected' : ''; ?>>
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
                <button type="reset" class="btn btn-text">Reset</button>
                <button type="submit" class="btn btn-primary">Create Project</button>
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
        
        if (offerSelect && businessSelect) {
            offerSelect.addEventListener('change', function() {
                const offerId = this.value;
                
                if (offerId) {
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
                    
                    const offerInfo = offerData[offerId - 1]; // Assuming IDs are sequential
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
        
        // Set end date to be 30 days after start date by default
        const startDateField = document.getElementById('start_date');
        const endDateField = document.getElementById('end_date');
        
        if (startDateField && endDateField && !endDateField.value) {
            const setEndDate = function() {
                if (startDateField.value) {
                    const startDate = new Date(startDateField.value);
                    startDate.setDate(startDate.getDate() + 30);
                    const year = startDate.getFullYear();
                    const month = String(startDate.getMonth() + 1).padStart(2, '0');
                    const day = String(startDate.getDate()).padStart(2, '0');
                    endDateField.value = `${year}-${month}-${day}`;
                }
            };
            
            // Set initial end date if start date is already filled
            setEndDate();
            
            // Update end date when start date changes
            startDateField.addEventListener('change', setEndDate);
        }
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>