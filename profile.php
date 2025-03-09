<?php
// profile.php - User profile and settings page
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirectToLogin();
}

// Page title
$pageTitle = "My Profile";

// Database connection
$conn = connectDB();

// Create uploads directory if it doesn't exist
$uploadsDir = 'uploads/avatars';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Check if users table has avatar column, if not add it
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
}

// Get user data
$stmt = $conn->prepare("SELECT u.*, r.name as role_name 
                      FROM users u 
                      LEFT JOIN roles r ON u.role_id = r.id 
                      WHERE u.id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // This should not happen, but just in case
    session_destroy();
    header("Location: login.php");
    exit;
}

$user = $result->fetch_assoc();

// Special protection for master admin
$isMasterAdmin = ($user['email'] === MASTER_ADMIN_EMAIL);

// Handle profile update
$profileError = '';
$profileSuccess = '';

if (isset($_POST['update_profile']) && $_POST['update_profile'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $profileError = "Invalid request. Please try again.";
    } else {
        // Get and sanitize form data
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        
        // Validate required fields
        if (empty($name) || empty($email)) {
            $profileError = "Name and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileError = "Please enter a valid email address.";
        } else {
            // Check if email already exists (excluding current user)
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param('si', $email, $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $profileError = "Email address is already in use by another user.";
            } else {
                // Process avatar upload if provided
                $avatarPath = $user['avatar']; // Keep existing avatar by default
                
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    $maxSize = 2 * 1024 * 1024; // 2MB
                    
                    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($fileInfo, $_FILES['avatar']['tmp_name']);
                    finfo_close($fileInfo);
                    
                    if (!in_array($mimeType, $allowedTypes)) {
                        $profileError = "Only JPG, PNG and GIF image files are allowed.";
                    } elseif ($_FILES['avatar']['size'] > $maxSize) {
                        $profileError = "Image file size exceeds 2MB limit.";
                    } else {
                        // Generate a unique filename
                        $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                        $newFilename = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
                        $targetFile = $uploadsDir . '/' . $newFilename;
                        
                        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
                            // Delete old avatar if exists and is not the default
                            if ($avatarPath && file_exists($avatarPath) && $avatarPath != 'assets/img/default-avatar.png') {
                                @unlink($avatarPath);
                            }
                            
                            $avatarPath = $targetFile;
                        } else {
                            $profileError = "Failed to upload avatar image.";
                        }
                    }
                } elseif (isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === 'yes') {
                    // If remove avatar checkbox is checked
                    if ($avatarPath && file_exists($avatarPath) && $avatarPath != 'assets/img/default-avatar.png') {
                        @unlink($avatarPath);
                    }
                    $avatarPath = null;
                }
                
                if (empty($profileError)) {
                    // Update profile
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, avatar = ? WHERE id = ?");
                    $stmt->bind_param('sssi', $name, $email, $avatarPath, $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $profileSuccess = "Profile updated successfully.";
                        
                        // Update session data
                        $_SESSION['name'] = $name;
                        $_SESSION['email'] = $email;
                        
                        // Refresh user data
                        $stmt = $conn->prepare("SELECT u.*, r.name as role_name 
                                              FROM users u 
                                              LEFT JOIN roles r ON u.role_id = r.id 
                                              WHERE u.id = ?");
                        $stmt->bind_param('i', $_SESSION['user_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                    } else {
                        $profileError = "Error updating profile: " . $conn->error;
                    }
                }
            }
        }
    }
}

// Handle password change
$passwordError = '';
$passwordSuccess = '';

if (isset($_POST['change_password']) && $_POST['change_password'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $passwordError = "Invalid request. Please try again.";
    } else {
        // Get form data
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate required fields
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $passwordError = "All password fields are required.";
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = "New passwords do not match.";
        } elseif (strlen($newPassword) < 8) {
            $passwordError = "New password must be at least 8 characters long.";
        } else {
            // Verify current password
            if (!password_verify($currentPassword, $user['password'])) {
                $passwordError = "Current password is incorrect.";
            } else {
                // Hash new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update password
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param('si', $hashedPassword, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $passwordSuccess = "Password changed successfully.";
                } else {
                    $passwordError = "Error changing password: " . $conn->error;
                }
            }
        }
    }
}

// Handle interface preferences
$preferencesError = '';
$preferencesSuccess = '';

if (isset($_POST['update_preferences']) && $_POST['update_preferences'] === 'yes') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $preferencesError = "Invalid request. Please try again.";
    } else {
        // Get and sanitize form data
        $itemsPerPage = (int)$_POST['items_per_page'];
        
        // Validate values
        if ($itemsPerPage < 5 || $itemsPerPage > 100) {
            $preferencesError = "Items per page must be between 5 and 100.";
        } else {
            // Save user preferences
            // You can store these in a user_preferences table or in a JSON column
            // This is a simplified example using settings table
            if (saveSetting('items_per_page_user_' . $_SESSION['user_id'], $itemsPerPage, '', true)) {
                $preferencesSuccess = "Preferences updated successfully.";
            } else {
                $preferencesError = "Error updating preferences.";
            }
        }
    }
}

// Get current preferences
$itemsPerPage = getSetting('items_per_page_user_' . $_SESSION['user_id'], 10);

// Include header
include 'includes/header.php';
?>

<div class="profile-container">
    <!-- Profile Information -->
    <div class="card">
        <div class="card-header">
            <h2>My Profile</h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($profileError)): ?>
                <div class="alert alert-danger"><?php echo $profileError; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($profileSuccess)): ?>
                <div class="alert alert-success"><?php echo $profileSuccess; ?></div>
            <?php endif; ?>
            
            <div class="profile-header">
                <div class="profile-avatar-container">
                    <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                        <img src="<?php echo $user['avatar']; ?>" alt="Profile Picture" class="profile-avatar-img">
                    <?php else: ?>
                        <div class="profile-avatar">
                            <?php 
                            // Display initials as avatar
                            $initials = strtoupper(substr($user['name'], 0, 1));
                            if (strpos($user['name'], ' ') !== false) {
                                $parts = explode(' ', $user['name']);
                                $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
                            }
                            echo $initials;
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h3><?php echo $user['name']; ?></h3>
                    <p class="profile-role"><?php echo $user['role_name']; ?></p>
                    <p class="profile-email"><?php echo $user['email']; ?></p>
                </div>
            </div>
            
            <form method="POST" action="" class="mt-lg" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="update_profile" value="yes">
                
                <div class="form-group">
                    <label for="avatar">Profile Picture</label>
                    <div class="avatar-upload-container">
                        <input type="file" id="avatar" name="avatar" class="form-control" accept="image/jpeg,image/png,image/gif">
                        <span class="form-hint">Maximum file size: 2MB. Allowed formats: JPG, PNG, GIF.</span>
                        
                        <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                            <div class="avatar-actions mt-sm">
                                <label class="checkbox">
                                    <input type="checkbox" name="remove_avatar" value="yes"> Remove current profile picture
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="name">Full Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?php echo $user['name']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo $user['email']; ?>" required 
                                   <?php echo $isMasterAdmin ? 'readonly' : ''; ?>>
                            <?php if ($isMasterAdmin): ?>
                                <span class="form-hint">The email for the master admin account cannot be changed.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="role">Role</label>
                    <input type="text" id="role" class="form-control" 
                           value="<?php echo $user['role_name']; ?>" readonly>
                    <span class="form-hint">Your role can only be changed by an administrator.</span>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Change Password -->
    <div class="card mt-lg">
        <div class="card-header">
            <h2>Change Password</h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($passwordError)): ?>
                <div class="alert alert-danger"><?php echo $passwordError; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($passwordSuccess)): ?>
                <div class="alert alert-success"><?php echo $passwordSuccess; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="change_password" value="yes">
                
                <div class="form-group">
                    <label for="current_password">Current Password <span class="required">*</span></label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="new_password">New Password <span class="required">*</span></label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            <span class="form-hint">Password must be at least 8 characters long</span>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Interface Preferences -->
    <div class="card mt-lg">
        <div class="card-header">
            <h2>Interface Preferences</h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($preferencesError)): ?>
                <div class="alert alert-danger"><?php echo $preferencesError; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($preferencesSuccess)): ?>
                <div class="alert alert-success"><?php echo $preferencesSuccess; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="update_preferences" value="yes">
                
                <div class="form-group">
                    <label for="items_per_page">Items Per Page</label>
                    <select id="items_per_page" name="items_per_page" class="form-control">
                        <option value="5" <?php echo $itemsPerPage == 5 ? 'selected' : ''; ?>>5</option>
                        <option value="10" <?php echo $itemsPerPage == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $itemsPerPage == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $itemsPerPage == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $itemsPerPage == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                    <span class="form-hint">Number of items to display per page in lists.</span>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.profile-container {
    max-width: 800px;
    margin: 0 auto;
}

.profile-header {
    display: flex;
    align-items: center;
    margin-bottom: var(--spacing-xl);
}

.profile-avatar-container {
    margin-right: var(--spacing-lg);
}

.profile-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--font-size-xl);
    font-weight: var(--font-weight-bold);
    color: white;
}

.profile-avatar-img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--grey-200);
}

.profile-info h3 {
    margin: 0 0 var(--spacing-xs) 0;
    font-size: var(--font-size-xl);
}

.profile-role {
    color: var(--grey-600);
    margin: 0 0 var(--spacing-xs) 0;
}

.profile-email {
    color: var(--primary-color);
    margin: 0;
}

.mt-lg {
    margin-top: var(--spacing-lg);
}

.mt-sm {
    margin-top: var(--spacing-sm);
}

.avatar-upload-container {
    max-width: 400px;
}

.avatar-actions {
    display: flex;
    align-items: center;
}

.checkbox {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.checkbox input {
    margin-right: var(--spacing-xs);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Preview uploaded avatar image before submission
    const avatarInput = document.getElementById('avatar');
    const avatarContainer = document.querySelector('.profile-avatar-container');
    
    if (avatarInput && avatarContainer) {
        avatarInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Check if there's already an img element
                    let avatarImg = avatarContainer.querySelector('.profile-avatar-img');
                    
                    if (!avatarImg) {
                        // Remove the initials avatar if it exists
                        const initialsAvatar = avatarContainer.querySelector('.profile-avatar');
                        if (initialsAvatar) {
                            initialsAvatar.remove();
                        }
                        
                        // Create new img element
                        avatarImg = document.createElement('img');
                        avatarImg.className = 'profile-avatar-img';
                        avatarImg.alt = 'Profile Picture Preview';
                        avatarContainer.appendChild(avatarImg);
                    }
                    
                    // Set the src to the loaded file
                    avatarImg.src = e.target.result;
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    // Handle remove avatar checkbox
    const removeAvatarCheckbox = document.querySelector('input[name="remove_avatar"]');
    if (removeAvatarCheckbox) {
        removeAvatarCheckbox.addEventListener('change', function() {
            if (this.checked) {
                // Disable file input
                avatarInput.disabled = true;
                
                // Show placeholder
                const avatarImg = avatarContainer.querySelector('.profile-avatar-img');
                if (avatarImg) {
                    avatarImg.style.opacity = '0.3';
                }
            } else {
                // Enable file input
                avatarInput.disabled = false;
                
                // Restore image
                const avatarImg = avatarContainer.querySelector('.profile-avatar-img');
                if (avatarImg) {
                    avatarImg.style.opacity = '1';
                }
            }
        });
    }
});
</script>

<?php
// Include footer
include 'includes/footer.php';
$conn->close();
?>