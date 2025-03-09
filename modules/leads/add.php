<?php
// modules/leads/add.php - Add a new lead
require_once '../../config.php';

// Check permissions
if (!checkPermission('add_lead')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Add Lead";

// Get business_id and contact_id from query parameter
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;
$contactId = isset($_GET['contact_id']) && is_numeric($_GET['contact_id']) ? (int)$_GET['contact_id'] : null;
$businessName = '';
$contactName = '';

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

// Check if contact exists and get name
if ($contactId) {
    $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, business_id FROM contacts WHERE id = ?");
    $stmt->bind_param('i', $contactId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $contactData = $result->fetch_assoc();
        $contactName = $contactData['name'];
        
        // If no business ID was provided but contact has a business, use that
        if (!$businessId && $contactData['business_id']) {
            $businessId = $contactData['business_id'];
            
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
        $contactId = null;
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

// Get all users who can be assigned to leads
$usersResult = $conn->query("SELECT u.id, u.name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.name ASC");
$users = [];
if ($usersResult->num_rows > 0) {
    while ($user = $usersResult->fetch_assoc()) {
        $users[] = $user;
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
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $source = sanitizeInput($_POST['source']);
        $status = sanitizeInput($_POST['status']);
        $value = !empty($_POST['value']) ? (float)$_POST['value'] : 0;
        $selectedBusinessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        $selectedContactId = !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null;
        $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
        
        // Validate required fields
        if (empty($title)) {
            $error = "Lead title is required.";
        } else {
            // Insert new lead
            $stmt = $conn->prepare("INSERT INTO leads (title, description, source, status, value, business_id, contact_id, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssdiiii', $title, $description, $source, $status, $value, $selectedBusinessId, $selectedContactId, $assignedTo, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $leadId = $conn->insert_id;
                $success = "Lead added successfully.";
                
                // Redirect to the new lead page
                header("Location: view.php?id=" . $leadId . "&success=created");
                exit;
            } else {
                $error = "Error adding lead: " . $conn->error;
            }
        }
    }
}

// Get contacts for the selected business for dropdown
$contacts = [];
if ($businessId) {
    $stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM contacts WHERE business_id = ? ORDER BY first_name, last_name ASC");
    $stmt->bind_param('i', $businessId);
    $stmt->execute();
    $contactsResult = $stmt->get_result();
    
    if ($contactsResult->num_rows > 0) {
        while ($contact = $contactsResult->fetch_assoc()) {
            $contacts[] = $contact;
        }
    }
} else {
    // Get all contacts if no business is selected
    $contactsResult = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM contacts ORDER BY first_name, last_name ASC");
    
    if ($contactsResult->num_rows > 0) {
        while ($contact = $contactsResult->fetch_assoc()) {
            $contacts[] = $contact;
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
            if ($businessId && $contactId) {
                echo "Add Lead for " . $contactName . " (" . $businessName . ")";
            } elseif ($businessId) {
                echo "Add Lead for " . $businessName;
            } elseif ($contactId) {
                echo "Add Lead for " . $contactName;
            } else {
                echo "Add New Lead";
            }
            ?>
        </h2>
        <div class="card-header-actions">
            <a href="index.php" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Leads
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
                        <label for="title">Lead Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="source">Source</label>
                        <select id="source" name="source" class="form-control">
                            <option value="">-- Select Source --</option>
                            <option value="website">Website</option>
                            <option value="referral">Referral</option>
                            <option value="social_media">Social Media</option>
                            <option value="direct_contact">Direct Contact</option>
                            <option value="email_campaign">Email Campaign</option>
                            <option value="phone">Phone</option>
                            <option value="other">Other</option>
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
                        <label for="value">Value (<?php echo getSetting('currency_symbol', 'NOK'); ?>)</label>
                        <input type="number" id="value" name="value" class="form-control" step="0.01" min="0">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="new" selected>New</option>
                            <option value="qualified">Qualified</option>
                            <option value="proposal">Proposal</option>
                            <option value="negotiation">Negotiation</option>
                            <option value="won">Won</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
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
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="contact_id">Contact</label>
                        <select id="contact_id" name="contact_id" class="form-control">
                            <option value="">-- No Contact --</option>
                            <?php foreach ($contacts as $contact): ?>
                                <option value="<?php echo $contact['id']; ?>" <?php echo ($contactId == $contact['id']) ? 'selected' : ''; ?>>
                                    <?php echo $contact['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="assigned_to">Assigned To</label>
                <select id="assigned_to" name="assigned_to" class="form-control">
                    <option value="">-- Unassigned --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo ($_SESSION['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo $user['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="reset" class="btn btn-text">Reset</button>
                <button type="submit" class="btn btn-primary">Add Lead</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Dynamic contact loading based on selected business
    document.addEventListener('DOMContentLoaded', function() {
        const businessSelect = document.getElementById('business_id');
        const contactSelect = document.getElementById('contact_id');
        
        if (businessSelect && contactSelect) {
            businessSelect.addEventListener('change', function() {
                const businessId = this.value;
                
                // Clear contacts
                contactSelect.innerHTML = '<option value="">-- No Contact --</option>';
                
                if (businessId) {
                    // Fetch contacts for this business
                    fetch('../../ajax/get_contacts.php?business_id=' + businessId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                data.contacts.forEach(contact => {
                                    const option = document.createElement('option');
                                    option.value = contact.id;
                                    option.textContent = contact.name;
                                    contactSelect.appendChild(option);
                                });
                            }
                        })
                        .catch(error => console.error('Error fetching contacts:', error));
                }
            });
        }
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>