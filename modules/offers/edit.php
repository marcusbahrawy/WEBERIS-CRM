<?php
// modules/offers/edit.php - Edit an existing offer
require_once '../../config.php';

// Check permissions
if (!checkPermission('edit_offer')) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$offerId = (int)$_GET['id'];

// Page title
$pageTitle = "Edit Offer";

// Database connection
$conn = connectDB();

// Get offer data
$stmt = $conn->prepare("SELECT * FROM offers WHERE id = ?");
$stmt->bind_param('i', $offerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: index.php");
    exit;
}

$offer = $result->fetch_assoc();

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
if ($offer['business_id']) {
    $stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM contacts WHERE business_id = ? ORDER BY first_name, last_name ASC");
    $stmt->bind_param('i', $offer['business_id']);
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

// Get all leads for dropdown
$leadsResult = $conn->query("SELECT id, title FROM leads ORDER BY title ASC");
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
        $businessId = !empty($_POST['business_id']) ? (int)$_POST['business_id'] : null;
        $contactId = !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null;
        $leadId = !empty($_POST['lead_id']) ? (int)$_POST['lead_id'] : null;
        
        // Validate required fields
        if (empty($title)) {
            $error = "Offer title is required.";
        } elseif ($amount <= 0) {
            $error = "Amount must be greater than zero.";
        } else {
            // Update offer
            $stmt = $conn->prepare("UPDATE offers SET title = ?, description = ?, amount = ?, status = ?, valid_until = ?, business_id = ?, contact_id = ?, lead_id = ? WHERE id = ?");
            $stmt->bind_param('ssdssiiiii', $title, $description, $amount, $status, $validUntil, $businessId, $contactId, $leadId, $offerId);
            
            if ($stmt->execute()) {
                $success = "Offer updated successfully.";
                
                // Refresh offer data
                $stmt = $conn->prepare("SELECT * FROM offers WHERE id = ?");
                $stmt->bind_param('i', $offerId);
                $stmt->execute();
                $result = $stmt->get_result();
                $offer = $result->fetch_assoc();
                
                // Update contacts list if business changed
                if ($offer['business_id'] != $businessId) {
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
                $error = "Error updating offer: " . $conn->error;
            }
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Edit Offer</h2>
        <div class="card-header-actions">
            <a href="view.php?id=<?php echo $offerId; ?>" class="btn btn-text">
                <span class="material-icons">visibility</span> View Offer
            </a>
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
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo $offer['title']; ?>" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="amount">Amount (<?php echo getSetting('currency_symbol', 'NOK'); ?>) <span class="required">*</span></label>
                        <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0.01" value="<?php echo number_format((float)$offer['amount'], 2, '.', ''); ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4"><?php echo $offer['description']; ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="draft" <?php echo ($offer['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="sent" <?php echo ($offer['status'] === 'sent') ? 'selected' : ''; ?>>Sent</option>
                            <option value="negotiation" <?php echo ($offer['status'] === 'negotiation') ? 'selected' : ''; ?>>Negotiation</option>
                            <option value="accepted" <?php echo ($offer['status'] === 'accepted') ? 'selected' : ''; ?>>Accepted</option>
                            <option value="rejected" <?php echo ($offer['status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            <option value="expired" <?php echo ($offer['status'] === 'expired') ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="valid_until">Valid Until</label>
                        <input type="date" id="valid_until" name="valid_until" class="form-control" value="<?php echo $offer['valid_until']; ?>">
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
                                <option value="<?php echo $business['id']; ?>" <?php echo ($offer['business_id'] == $business['id']) ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $contact['id']; ?>" <?php echo ($offer['contact_id'] == $contact['id']) ? 'selected' : ''; ?>>
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
                        <option value="<?php echo $lead['id']; ?>" <?php echo ($offer['lead_id'] == $lead['id']) ? 'selected' : ''; ?>>
                            <?php echo $lead['title']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <a href="view.php?id=<?php echo $offerId; ?>" class="btn btn-text">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Offer</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Dynamic contact loading based on selected business
    document.addEventListener('DOMContentLoaded', function() {
        const businessSelect = document.getElementById('business_id');
        const contactSelect = document.getElementById('contact_id');
        const currentContactId = <?php echo $offer['contact_id'] ? $offer['contact_id'] : 'null'; ?>;
        
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