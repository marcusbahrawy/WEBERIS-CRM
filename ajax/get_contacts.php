<?php
// ajax/get_contacts.php - Get contacts for a business
require_once '../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Check if business_id is provided
if (!isset($_GET['business_id']) || !is_numeric($_GET['business_id'])) {
    echo json_encode(['success' => false, 'message' => 'Business ID is required']);
    exit;
}

$businessId = (int)$_GET['business_id'];

// Database connection
$conn = connectDB();

// Get contacts for the business
$stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM contacts WHERE business_id = ? ORDER BY first_name, last_name ASC");
$stmt->bind_param('i', $businessId);
$stmt->execute();
$result = $stmt->get_result();

$contacts = [];
while ($contact = $result->fetch_assoc()) {
    $contacts[] = [
        'id' => $contact['id'],
        'name' => $contact['name']
    ];
}

$conn->close();

// Return contacts as JSON
echo json_encode(['success' => true, 'contacts' => $contacts]);
?>