<?php

// Add these lines at the top of config.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// config.php - Database and system configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'scrimbailleris_illeris');
define('DB_PASS', '*3lk0pHxAU5@');
define('DB_NAME', 'scrimbailleris_illeris');
define('SITE_URL', 'https://scrimba.illeris.no');
define('MASTER_ADMIN_EMAIL', 'marcus@illeris.no');
define('MASTER_ADMIN_NAME', 'Marcus Bahrawy');
define('APP_NAME', 'WEBERIS CRM');
define('APP_VERSION', '1.0.0');

// Database connection
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Session management
function startSecureSession() {
    if (session_status() == PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_only_cookies', 1);
        session_start();
    }
}

// Authentication functions
function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function checkPermission($permission) {
    startSecureSession();
    if (!isLoggedIn()) {
        return false;
    }
    
    if ($_SESSION['email'] === MASTER_ADMIN_EMAIL) {
        return true; // Master admin has all permissions
    }
    
    return in_array($permission, $_SESSION['permissions'] ?? []);
}

function redirectToLogin() {
    header("Location: " . SITE_URL . "/login.php");
    exit;
}

// Security functions
function sanitizeInput($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitizeInput($value);
        }
        return $input;
    }
    
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    startSecureSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Globalt array for å cache innstillinger
$GLOBALS['app_settings'] = [];

/**
 * Hent en systeminnstilling fra databasen
 * 
 * @param string $key Innstillingsnøkkel
 * @param mixed $default Standardverdi hvis innstillingen ikke finnes
 * @return mixed Innstillingsverdien eller standardverdien
 */
function getSetting($key, $default = null) {
    // Sjekk om innstillingen er cachet
    if (isset($GLOBALS['app_settings'][$key])) {
        return $GLOBALS['app_settings'][$key];
    }
    
    // Hvis ikke, hent fra databasen
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    if (!$stmt) {
        return $default;  // Returnerer default hvis spørringen mislykkes
    }
    
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $value = $result->fetch_assoc()['setting_value'];
        // Cache innstillingen
        $GLOBALS['app_settings'][$key] = $value;
        $stmt->close();
        return $value;
    }
    
    $stmt->close();
    
    // Hvis innstillingen ikke finnes, lagre og returner standardverdien
    if ($default !== null) {
        saveSetting($key, $default);
        return $default;
    }
    
    return $default;
}

/**
 * Lagre en systeminnstilling i databasen
 * 
 * @param string $key Innstillingsnøkkel
 * @param mixed $value Innstillingsverdi
 * @param string $description Beskrivelse av innstillingen (valgfritt)
 * @param bool $isPublic Om innstillingen skal være synlig for brukere (valgfritt)
 * @return bool True hvis vellykket, false ellers
 */
function saveSetting($key, $value, $description = '', $isPublic = false) {
    $conn = connectDB();
    
    // Sjekk om innstillingen allerede finnes
    $stmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Oppdater eksisterende innstilling
        $stmt->close();
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param('ss', $value, $key);
        $success = $stmt->execute();
    } else {
        // Legg til ny innstilling
        $stmt->close();
        $isPublicInt = $isPublic ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_description, is_public) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('sssi', $key, $value, $description, $isPublicInt);
        $success = $stmt->execute();
    }
    
    if ($success) {
        // Oppdater cachen
        $GLOBALS['app_settings'][$key] = $value;
    }
    
    $stmt->close();
    return $success;
}

/**
 * Hent alle systeminnstillinger
 * 
 * @param bool $onlyPublic Hvis true, returner bare offentlige innstillinger
 * @return array Array med alle innstillinger
 */
function getAllSettings($onlyPublic = false) {
    $conn = connectDB();
    
    $query = "SELECT setting_key, setting_value, setting_description, is_public FROM settings";
    if ($onlyPublic) {
        $query .= " WHERE is_public = 1";
    }
    
    $result = $conn->query($query);
    $settings = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'description' => $row['setting_description'],
                'is_public' => (bool)$row['is_public']
            ];
            
            // Cache innstillingen
            $GLOBALS['app_settings'][$row['setting_key']] = $row['setting_value'];
        }
    }
    
    return $settings;
}

/**
 * Formater valuta
 * 
 * @param float $amount Beløp som skal formateres
 * @return string Formatert beløp med valutasymbol
 */
function formatCurrency($amount) {
    $currencySymbol = getSetting('currency_symbol', 'NOK');
    $currencyPosition = getSetting('currency_position', 'after');
    $decimalSeparator = getSetting('decimal_separator', ',');
    $thousandsSeparator = getSetting('thousands_separator', ' ');
    
    $formattedAmount = number_format((float)$amount, 2, $decimalSeparator, $thousandsSeparator);
    
    if ($currencyPosition === 'before') {
        return $currencySymbol . ' ' . $formattedAmount;
    } else {
        return $formattedAmount . ' ' . $currencySymbol;
    }
}

/**
 * Formater dato i henhold til systeminnstillinger
 * 
 * @param string $dateString Dato som skal formateres
 * @return string Formatert dato
 */
function formatDate($dateString) {
    if (empty($dateString)) {
        return 'N/A';
    }
    
    $dateFormat = getSetting('date_format', 'd.m.Y');
    $date = new DateTime($dateString);
    return $date->format($dateFormat);
}

/**
 * Formater tidspunkt i henhold til systeminnstillinger
 * 
 * @param string $timeString Tidspunkt som skal formateres
 * @return string Formatert tidspunkt
 */
function formatTime($timeString) {
    if (empty($timeString)) {
        return 'N/A';
    }
    
    $timeFormat = getSetting('time_format', 'H:i');
    $time = new DateTime($timeString);
    return $time->format($timeFormat);
}

/**
 * Formater dato og tidspunkt i henhold til systeminnstillinger
 * 
 * @param string $dateTimeString Dato og tidspunkt som skal formateres
 * @return string Formatert dato og tidspunkt
 */
function formatDateTime($dateTimeString) {
    if (empty($dateTimeString)) {
        return 'N/A';
    }
    
    $dateFormat = getSetting('date_format', 'd.m.Y');
    $timeFormat = getSetting('time_format', 'H:i');
    $dateTime = new DateTime($dateTimeString);
    return $dateTime->format($dateFormat . ' ' . $timeFormat);
}

// Last inn alle innstillinger ved oppstart for å minimere database-spørringer
function loadAllSettings() {
    // Sjekk om settings-tabellen eksisterer
    $conn = connectDB();
    $tableExists = false;
    
    $result = $conn->query("SHOW TABLES LIKE 'settings'");
    if ($result && $result->num_rows > 0) {
        $tableExists = true;
        // Last inn alle innstillinger
        getAllSettings();
    }
    
    return $tableExists;
}

// Database schema setup functions
function setupDatabase() {
    $conn = connectDB();
    
    // Users table
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role_id INT(11) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Roles table
    $conn->query("CREATE TABLE IF NOT EXISTS roles (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Permissions table
    $conn->query("CREATE TABLE IF NOT EXISTS permissions (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Role permissions junction table
    $conn->query("CREATE TABLE IF NOT EXISTS role_permissions (
        role_id INT(11) NOT NULL,
        permission_id INT(11) NOT NULL,
        PRIMARY KEY (role_id, permission_id),
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
    )");
    
    // Businesses table
    $conn->query("CREATE TABLE IF NOT EXISTS businesses (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        registration_number VARCHAR(50),
        address TEXT,
        phone VARCHAR(20),
        email VARCHAR(100),
        website VARCHAR(100),
        industry VARCHAR(100),
        description TEXT,
        created_by INT(11) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    
    // Contacts table
    $conn->query("CREATE TABLE IF NOT EXISTS contacts (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        position VARCHAR(100),
        business_id INT(11),
        notes TEXT,
        created_by INT(11) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    
    // Leads table
    $conn->query("CREATE TABLE IF NOT EXISTS leads (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        source VARCHAR(50),
        status VARCHAR(50) NOT NULL DEFAULT 'new',
        value DECIMAL(10,2),
        business_id INT(11),
        contact_id INT(11),
        assigned_to INT(11),
        created_by INT(11) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    
    // Offers table
    $conn->query("CREATE TABLE IF NOT EXISTS offers (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'draft',
        valid_until DATE,
        business_id INT(11),
        contact_id INT(11),
        lead_id INT(11),
        created_by INT(11) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
        FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    
    // Projects table
    $conn->query("CREATE TABLE IF NOT EXISTS projects (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        status VARCHAR(50) NOT NULL DEFAULT 'not_started',
        start_date DATE,
        end_date DATE,
        budget DECIMAL(10,2),
        business_id INT(11),
        offer_id INT(11),
        created_by INT(11) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
        FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    
    // Service Agreements table
    $conn->query("CREATE TABLE IF NOT EXISTS service_agreements (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        business_id INT(11) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'active',
        start_date DATE NOT NULL,
        end_date DATE,
        renewal_date DATE,
        price DECIMAL(10,2) NOT NULL,
        billing_cycle VARCHAR(50) NOT NULL DEFAULT 'monthly',
        agreement_type VARCHAR(50) NOT NULL DEFAULT 'standard',
        created_by INT(11) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    
    // Agreement Types table
    $conn->query("CREATE TABLE IF NOT EXISTS agreement_types (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        label VARCHAR(100) NOT NULL,
        description TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Insert default agreement types
    $conn->query("INSERT IGNORE INTO agreement_types (name, label, description) VALUES 
        ('standard', 'Standard', 'Standard service agreement'),
        ('premium', 'Premium', 'Premium service agreement with additional benefits'),
        ('custom', 'Custom', 'Custom tailored service agreement'),
        ('maintenance', 'Maintenance', 'System maintenance agreement'),
        ('support', 'Support', 'Technical support agreement'),
        ('hosting', 'Hosting', 'Web hosting service agreement')");
    
    // Settings table
    $conn->query("CREATE TABLE IF NOT EXISTS settings (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_description VARCHAR(255),
        is_public TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Insert default roles
    $conn->query("INSERT IGNORE INTO roles (name, description) VALUES 
        ('admin', 'Administrator with full access'),
        ('manager', 'Manager with access to most features'),
        ('user', 'Regular user with limited access')");
    
    // Insert default permissions
    $conn->query("INSERT IGNORE INTO permissions (name, description) VALUES 
        ('view_business', 'View business records'),
        ('add_business', 'Create new business records'),
        ('edit_business', 'Edit existing business records'),
        ('delete_business', 'Delete business records'),
        
        ('view_contact', 'View contact records'),
        ('add_contact', 'Create new contact records'),
        ('edit_contact', 'Edit existing contact records'),
        ('delete_contact', 'Delete contact records'),
        
        ('view_lead', 'View lead records'),
        ('add_lead', 'Create new lead records'),
        ('edit_lead', 'Edit existing lead records'),
        ('delete_lead', 'Delete lead records'),
        
        ('view_offer', 'View offer records'),
        ('add_offer', 'Create new offer records'),
        ('edit_offer', 'Edit existing offer records'),
        ('delete_offer', 'Delete offer records'),
        
        ('view_project', 'View project records'),
        ('add_project', 'Create new project records'),
        ('edit_project', 'Edit existing project records'),
        ('delete_project', 'Delete project records'),
        
        ('view_service_agreement', 'View service agreement records'),
        ('add_service_agreement', 'Create new service agreement records'),
        ('edit_service_agreement', 'Edit existing service agreement records'),
        ('delete_service_agreement', 'Delete service agreement records'),
        
        ('view_user', 'View user accounts'),
        ('add_user', 'Create new user accounts'),
        ('edit_user', 'Edit user accounts'),
        ('delete_user', 'Delete user accounts'),
        
        ('view_role', 'View roles'),
        ('add_role', 'Create new roles'),
        ('edit_role', 'Edit roles'),
        ('delete_role', 'Delete roles')");
    
    // Assign permissions to roles
    // Admin role - all permissions
    $permissions = $conn->query("SELECT id FROM permissions");
    while ($permission = $permissions->fetch_assoc()) {
        $adminRoleId = $conn->query("SELECT id FROM roles WHERE name = 'admin'")->fetch_assoc()['id'];
        $conn->query("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES 
            ({$adminRoleId}, {$permission['id']})");
    }
    
    // Manager role - all except user/role management
    $managerRoleId = $conn->query("SELECT id FROM roles WHERE name = 'manager'")->fetch_assoc()['id'];
    $conn->query("INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT {$managerRoleId}, id FROM permissions 
        WHERE name NOT LIKE '%user' AND name NOT LIKE '%role'");
    
    // User role - view permissions only
    $userRoleId = $conn->query("SELECT id FROM roles WHERE name = 'user'")->fetch_assoc()['id'];
    $conn->query("INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT {$userRoleId}, id FROM permissions 
        WHERE name LIKE 'view_%'");
    
    // Create master admin user if not exists
    $adminRoleId = $conn->query("SELECT id FROM roles WHERE name = 'admin'")->fetch_assoc()['id'];
    $hashedPassword = password_hash('change_this_password', PASSWORD_DEFAULT);
    
    $masterAdminName = MASTER_ADMIN_NAME;
    $masterAdminEmail = MASTER_ADMIN_EMAIL;
    
    $stmt = $conn->prepare("INSERT IGNORE INTO users (name, email, password, role_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $masterAdminName, $masterAdminEmail, $hashedPassword, $adminRoleId);
    $stmt->execute();
    
    // Insert default settings
    $defaultSettings = [
        ['currency_symbol', 'NOK', 'Currency symbol used throughout the application', 1],
        ['currency_position', 'after', 'Position of currency symbol: before or after', 1],
        ['decimal_separator', ',', 'Decimal separator for numbers', 1],
        ['thousands_separator', ' ', 'Thousands separator for numbers', 1],
        ['app_name', APP_NAME, 'Application name shown in the UI', 1],
        ['date_format', 'd.m.Y', 'PHP date format string', 1],
        ['time_format', 'H:i', 'PHP time format string', 1]
    ];

    foreach ($defaultSettings as $setting) {
        $stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value, setting_description, is_public) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('sssi', $setting[0], $setting[1], $setting[2], $setting[3]);
        $stmt->execute();
    }
    
    $conn->close();
}

// Try to load settings at startup
loadAllSettings();
?>