<?php
// modules/leads/edit.php - Edit an existing lead
require_once '../../config.php';

// Check permissions
if (!checkPermission('edit_lead')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$leadId = (int)$_GET['id'];

// Page title
$pageTitle = "Edit Lead";

// Database connection
$conn = connectDB();

// Get lead data
$stmt = $conn->prepare("SELECT * FROM leads WHERE id = ?");
$stmt->bind_param('i', $leadId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$lead = $result->fetch_assoc();

// Get all businesses for dropdown
$businessesResult = $conn->query("SELECT id, name FROM businesses ORDER BY name ASC");
$businesses = [];
if ($businessesResult->num_rows > 0) {
    while ($business = $businessesResult->fetch_assoc()) {
        $businesses[] = $business;
    }
}

// Get contacts for the selected business
$contacts = [];
if ($lead['business_id']) {
    $stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM contacts WHERE business_id = ? ORDER BY first_name, last_name ASC");
    $stmt->bind_param('i', $lead['business_id']);
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
        $businessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        $contactId = !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null;
        $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
        
        // Validate required fields
        if (empty($title)) {
            $error = "Lead title is required.";
        } else {
            // Update lead
            $stmt = $conn->prepare("UPDATE leads SET title = ?, description = ?, source = ?, status = ?, value = ?, business_id = ?, contact_id = ?, assigned_to = ? WHERE id = ?");
            $stmt->bind_param('ssssdiiiii', $title, $description, $source, $status, $value, $businessId, $contactId, $assignedTo, $leadId);
            
            if ($stmt->execute()) {
                $success = "Lead updated successfully.";
                
                // Refresh lead data
                $stmt = $conn->prepare("SELECT * FROM leads WHERE id = ?");
                $stmt->bind_param('i', $leadId);
                $stmt->execute();
                $result = $stmt->get_result();
                $lead = $result->fetch_assoc();
                
                // Update contacts list if business changed
                if ($lead['business_id'] != $businessId) {
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
                }
            } else {
                $error = "Error updating lead: " . $conn->error;
            }
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Edit Lead</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $leadId; ?>" class="btn btn-text">
                <span class="material-icons">visibility</span> View Lead
            </a>
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
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo $lead['title']; ?>" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="source">Source</label>
                        <select id="source" name="source" class="form-control">
                            <option value="">-- Select Source --</option>
                            <option value="website" <?php echo ($lead['source'] === 'website') ? 'selected' : ''; ?>>Website</option>
                            <option value="referral" <?php echo ($lead['source'] === 'referral') ? 'selected' : ''; ?>>Referral</option>
                            <option value="social_media" <?php echo ($lead['source'] === 'social_media') ? 'selected' : ''; ?>>Social Media</option>
                            <option value="direct_contact" <?php echo ($lead['source'] === 'direct_contact') ? 'selected' : ''; ?>>Direct Contact</option>
                            <option value="email_campaign" <?php echo ($lead['source'] === 'email_campaign') ? 'selected' : ''; ?>>Email Campaign</option>
                            <option value="phone" <?php echo ($lead['source'] === 'phone') ? 'selected' : ''; ?>>Phone</option>
                            <option value="other" <?php echo ($lead['source'] === 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4"><?php echo $lead['description']; ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="value">Value (<?php echo getSetting('currency_symbol', 'NOK'); ?>)</label>
                        <input type="number" id="value" name="value" class="form-control" step="0.01" min="0" value="<?php echo number_format((float)$lead['value'], 2, '.', ''); ?>">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="new" <?php echo ($lead['status'] === 'new') ? 'selected' : ''; ?>>New</option>
                            <option value="qualified" <?php echo ($lead['status'] === 'qualified') ? 'selected' : ''; ?>>Qualified</option>
                            <option value="proposal" <?php echo ($lead['status'] === 'proposal') ? 'selected' : ''; ?>>Proposal</option>
                            <option value="negotiation" <?php echo ($lead['status'] === 'negotiation') ? 'selected' : ''; ?>>Negotiation</option>
                            <option value="won" <?php echo ($lead['status'] === 'won') ? 'selected' : ''; ?>>Won</option>
                            <option value="lost" <?php echo ($lead['status'] === 'lost') ? 'selected' : ''; ?>>Lost</option>
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
                                <option value="<?php echo $business['id']; ?>" <?php echo ($lead['business_id'] == $business['id']) ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $contact['id']; ?>" <?php echo ($lead['contact_id'] == $contact['id']) ? 'selected' : ''; ?>>
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
                        <option value="<?php echo $user['id']; ?>" <?php echo ($lead['assigned_to'] == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo $user['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <a href="view.php?id=<?php echo $leadId; ?>" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Lead</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Dynamic contact loading based on selected business
    document.addEventListener('DOMContentLoaded', function() {
        const businessSelect = document.getElementById('business_id');
        const contactSelect = document.getElementById('contact_id');
        const currentContactId = <?php echo $lead['contact_id'] ? $lead['contact_id'] : 'null'; ?>;
        
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
                                    if (contact.id == currentContactId) {
                                        option.selected = true;
                                    }
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