<?php
// modules/offers/add.php - Add a new offer
require_once '../../config.php';

// Check permissions
if (!checkPermission('add_offer')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Page title
$pageTitle = "Add Offer";

// Get parameters from query
$businessId = isset($_GET['business_id']) && is_numeric($_GET['business_id']) ? (int)$_GET['business_id'] : null;
$contactId = isset($_GET['contact_id']) && is_numeric($_GET['contact_id']) ? (int)$_GET['contact_id'] : null;
$leadId = isset($_GET['lead_id']) && is_numeric($_GET['lead_id']) ? (int)$_GET['lead_id'] : null;
$businessName = '';
$contactName = '';
$leadTitle = '';

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

// Check if lead exists and get info
if ($leadId) {
    $stmt = $conn->prepare("SELECT title, business_id, contact_id FROM leads WHERE id = ?");
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $leadData = $result->fetch_assoc();
        $leadTitle = $leadData['title'];
        
        // If no business ID or contact ID was provided but lead has them, use those
        if (!$businessId && $leadData['business_id']) {
            $businessId = $leadData['business_id'];
            
            // Get business name
            $stmt = $conn->prepare("SELECT name FROM businesses WHERE id = ?");
            $stmt->bind_param('i', $businessId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $businessName = $result->fetch_assoc()['name'];
            }
        }
        
        if (!$contactId && $leadData['contact_id']) {
            $contactId = $leadData['contact_id'];
            
            // Get contact name
            $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM contacts WHERE id = ?");
            $stmt->bind_param('i', $contactId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $contactName = $result->fetch_assoc()['name'];
            }
        }
    } else {
        $leadId = null;
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

// Get all leads for dropdown
$leadsResult = $conn->query("SELECT id, title FROM leads WHERE status != 'lost' ORDER BY title ASC");
$leads = [];
if ($leadsResult->num_rows > 0) {
    while ($lead = $leadsResult->fetch_assoc()) {
        $leads[] = $lead;
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
        $amount = !empty($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $status = sanitizeInput($_POST['status']);
        $validUntil = !empty($_POST['valid_until']) ? sanitizeInput($_POST['valid_until']) : null;
        $selectedBusinessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        $selectedContactId = !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null;
        $selectedLeadId = !empty($_POST['lead_id']) ? (int)$_POST['lead_id'] : null;
        
        // Validate required fields
        if (empty($title)) {
            $error = "Offer title is required.";
        } elseif ($amount <= 0) {
            $error = "Amount must be greater than zero.";
        } else {
            // Insert new offer
            $stmt = $conn->prepare("INSERT INTO offers (title, description, amount, status, valid_until, business_id, contact_id, lead_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssdssiiii', $title, $description, $amount, $status, $validUntil, $selectedBusinessId, $selectedContactId, $selectedLeadId, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $offerId = $conn->insert_id;
                $success = "Offer added successfully.";
                
                // Redirect to the new offer page
                header("Location: view.php?id=" . $offerId . "&success=created");
                exit;
            } else {
                $error = "Error adding offer: " . $conn->error;
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
            $title = "Create New Offer";
            
            if ($leadId) {
                $title = "Create Offer for Lead: " . $leadTitle;
            } elseif ($businessId && $contactId) {
                $title = "Create Offer for " . $contactName . " at " . $businessName;
            } elseif ($businessId) {
                $title = "Create Offer for " . $businessName;
            } elseif ($contactId) {
                $title = "Create Offer for " . $contactName;
            }
            
            echo $title;
            ?>
        </h2>
        <div class="card-header-actions">
            <a href="index.php" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Offers
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
                        <label for="title">Offer Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-col">
                                        <div class="form-group">
                        <label for="amount">Amount (<?php echo getSetting('currency_symbol', 'NOK'); ?>) <span class="required">*</span></label>
                        <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0.01" required>
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
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="draft" selected>Draft</option>
                            <option value="sent">Sent</option>
                            <option value="negotiation">Negotiation</option>
                            <option value="accepted">Accepted</option>
                            <option value="rejected">Rejected</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="valid_until">Valid Until</label>
                        <input type="date" id="valid_until" name="valid_until" class="form-control">
                        <span class="form-hint">Leave empty if no expiration date</span>
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
                <label for="lead_id">Associated Lead</label>
                <select id="lead_id" name="lead_id" class="form-control">
                    <option value="">-- No Lead --</option>
                    <?php foreach ($leads as $lead): ?>
                        <option value="<?php echo $lead['id']; ?>" <?php echo ($leadId == $lead['id']) ? 'selected' : ''; ?>>
                            <?php echo $lead['title']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="reset" class="btn btn-text">Reset</button>
                <button type="submit" class="btn btn-primary">Create Offer</button>
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
        
        // Set default valid until date (30 days from now)
        const validUntilField = document.getElementById('valid_until');
        if (validUntilField && !validUntilField.value) {
            const today = new Date();
            today.setDate(today.getDate() + 30);
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            validUntilField.value = `${year}-${month}-${day}`;
        }
    });
</script>

<?php
// Include footer
include '../../includes/footer.php';
$conn->close();
?>