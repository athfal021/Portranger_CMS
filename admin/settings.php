<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();
$message = '';
$error = '';

// Get current admin details
$stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    
    $current_password = $_POST['current_password'];
    $new_username = sanitize($_POST['new_username']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $changes_made = false;
    
    // Validate current password
    if (!password_verify($current_password, $admin['password_hash'])) {
        $error = 'Current password is incorrect.';
    } 
    // Validate new username
    elseif (empty($new_username)) {
        $error = 'Username cannot be empty.';
    }
    // Check if new username already exists (if changed)
    elseif ($new_username !== $admin['username']) {
        $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$new_username, $admin['id']]);
        if ($check->fetch()) {
            $error = 'Username already taken. Please choose another.';
        } else {
            $changes_made = true;
        }
    }
    // Validate new password (if provided)
    elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters.';
    }
    elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    }
    elseif (!empty($new_password)) {
        $changes_made = true;
    }
    
    // If no errors, update
    if (empty($error)) {
        $updates = [];
        $params = [];
        
        // Update username if changed
        if ($new_username !== $admin['username']) {
            $updates[] = "username = ?";
            $params[] = $new_username;
        }
        
        // Update password if provided
        if (!empty($new_password)) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $updates[] = "password_hash = ?";
            $params[] = $new_hash;
        }
        
        if (!empty($updates)) {
            $params[] = $admin['id'];
            $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            // Update session username if changed
            if ($new_username !== $admin['username']) {
                $_SESSION['admin_username'] = $new_username;
            }
            
            // Log generic message (no sensitive details)
            logActivity('Admin Settings Updated', 'Admin credentials updated');
            $message = 'Settings updated successfully!';
            
            // Refresh admin data
            $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin = $stmt->fetch();
        } else {
            $message = 'No changes were made.';
        }
    }
}
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-6">Account Settings</h1>
    
    <?php if ($message): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST" class="max-w-md">
        <?= csrfField() ?>
        
        <div class="mb-4">
            <label class="block text-gray-700 mb-2">Current Username</label>
            <input type="text" value="<?= htmlspecialchars($admin['username']) ?>" disabled class="w-full border rounded px-3 py-2 bg-gray-100 cursor-not-allowed">
            <p class="text-xs text-gray-500 mt-1">Your current username (read-only)</p>
        </div>
        
        <div class="mb-4">
            <label class="block text-gray-700 mb-2">New Username</label>
            <input type="text" name="new_username" value="<?= htmlspecialchars($admin['username']) ?>" class="w-full border rounded px-3 py-2" required>
            <p class="text-xs text-gray-500 mt-1">Leave same to keep current username</p>
        </div>
        
        <hr class="my-6">
        
        <div class="mb-4">
            <label class="block text-gray-700 mb-2">Current Password <span class="text-red-500">*</span></label>
            <input type="password" name="current_password" class="w-full border rounded px-3 py-2" required>
            <p class="text-xs text-gray-500 mt-1">Required to confirm your identity</p>
        </div>
        
        <div class="mb-4">
            <label class="block text-gray-700 mb-2">New Password</label>
            <input type="password" name="new_password" class="w-full border rounded px-3 py-2">
            <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password (min 6 characters)</p>
        </div>
        
        <div class="mb-6">
            <label class="block text-gray-700 mb-2">Confirm New Password</label>
            <input type="password" name="confirm_password" class="w-full border rounded px-3 py-2">
        </div>
        
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Save Changes</button>
            <a href="dashboard.php" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">Cancel</a>
        </div>
    </form>
</div>
<?php require_once 'includes/footer.php'; ?>