<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();
$appearance = getAppearance();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $primary = sanitize($_POST['primary_color']);
    $secondary = sanitize($_POST['secondary_color']);

    // Update colors
    $stmt = $db->prepare("UPDATE appearance SET primary_color=?, secondary_color=? WHERE id=1");
    $stmt->execute([$primary, $secondary]);

    $messages = [];

    // Logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['logo'], 'image');
        if ($result['success']) {
            $stmt = $db->prepare("UPDATE appearance SET logo_media_id = ? WHERE id=1");
            $stmt->execute([$result['media_id']]);
            $messages[] = 'Logo uploaded successfully.';
        } else {
            $messages[] = 'Logo upload failed: ' . $result['error'];
        }
    }
    // Favicon upload
    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['favicon'], 'image');
        if ($result['success']) {
            $stmt = $db->prepare("UPDATE appearance SET favicon_media_id = ? WHERE id=1");
            $stmt->execute([$result['media_id']]);
            $messages[] = 'Favicon uploaded successfully.';
        } else {
            $messages[] = 'Favicon upload failed: ' . $result['error'];
        }
    }

    // Always add a general success message if no errors
    if (empty(array_filter($messages, function($m) { return strpos($m, 'failed') !== false; }))) {
        $messages[] = 'Appearance settings saved successfully.';
    }

    $_SESSION['appearance_messages'] = $messages;
    logActivity('Appearance Updated', 'Changed site colors/logo/favicon');
    redirect('appearance.php');
}

// Retrieve and clear session messages
$messages = isset($_SESSION['appearance_messages']) ? $_SESSION['appearance_messages'] : [];
unset($_SESSION['appearance_messages']);

// Refetch appearance after possible update
$appearance = getAppearance();
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-6">Appearance Settings</h1>

    <!-- Display messages -->
    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg): ?>
            <?php if (strpos($msg, 'failed') !== false || strpos($msg, 'error') !== false): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($msg) ?></div>
            <?php else: ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-8">
        <?= csrfField() ?>

        <!-- Color Pickers -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <label class="block text-gray-700 font-medium mb-2">Primary Color</label>
                <div class="flex items-center gap-4">
                    <input type="color" name="primary_color" value="<?= $appearance['primary_color'] ?>" 
                           class="w-16 h-16 rounded-full cursor-pointer border-2 border-gray-300 p-1">
                    <input type="text" name="primary_color_hex" value="<?= $appearance['primary_color'] ?>" 
                           class="border rounded px-3 py-2 w-32 text-sm font-mono" 
                           onchange="document.querySelector('[name=primary_color]').value = this.value;">
                </div>
                <p class="text-xs text-gray-500 mt-1">Used for buttons, links, and highlights.</p>
            </div>

            <div>
                <label class="block text-gray-700 font-medium mb-2">Secondary Color</label>
                <div class="flex items-center gap-4">
                    <input type="color" name="secondary_color" value="<?= $appearance['secondary_color'] ?>" 
                           class="w-16 h-16 rounded-full cursor-pointer border-2 border-gray-300 p-1">
                    <input type="text" name="secondary_color_hex" value="<?= $appearance['secondary_color'] ?>" 
                           class="border rounded px-3 py-2 w-32 text-sm font-mono"
                           onchange="document.querySelector('[name=secondary_color]').value = this.value;">
                </div>
                <p class="text-xs text-gray-500 mt-1">Used for accent elements and backgrounds.</p>
            </div>
        </div>

        <!-- Live Preview -->
        <div class="border-t border-gray-200 pt-6">
            <h2 class="text-lg font-semibold mb-3">Live Preview</h2>
            <div class="flex flex-wrap gap-4 p-4 rounded-lg" style="background: <?= $appearance['secondary_color'] ?>20;">
                <div class="px-6 py-3 rounded-full text-white font-medium" style="background: <?= $appearance['primary_color'] ?>;">
                    Primary Button
                </div>
                <div class="px-6 py-3 rounded-full text-white font-medium" style="background: <?= $appearance['secondary_color'] ?>;">
                    Secondary Button
                </div>
                <div class="px-6 py-3 rounded-full border-2 font-medium" style="border-color: <?= $appearance['primary_color'] ?>; color: <?= $appearance['primary_color'] ?>;">
                    Outlined Button
                </div>
                <div class="px-4 py-2 rounded-lg text-white" style="background: <?= $appearance['primary_color'] ?>;">
                    Primary Badge
                </div>
                <div class="px-4 py-2 rounded-lg text-white" style="background: <?= $appearance['secondary_color'] ?>;">
                    Secondary Badge
                </div>
            </div>
        </div>

        <!-- Logo & Favicon Uploads -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-gray-200 pt-6">
            <div>
                <label class="block text-gray-700 font-medium mb-2">Logo Image</label>
                <?php if ($appearance['logo_path']): ?>
                    <div class="mb-2 p-2 border rounded inline-block">
                        <img src="<?= BASE_URL . $appearance['logo_path'] ?>" class="h-12 w-auto">
                    </div>
                <?php endif; ?>
                <input type="file" name="logo" accept="image/*" class="w-full border rounded px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">Recommended: PNG with transparent background, max height 60px.</p>
            </div>

            <div>
                <label class="block text-gray-700 font-medium mb-2">Favicon</label>
                <?php if ($appearance['favicon_path']): ?>
                    <div class="mb-2">
                        <img src="<?= BASE_URL . $appearance['favicon_path'] ?>" class="h-8 w-auto">
                    </div>
                <?php endif; ?>
                <input type="file" name="favicon" accept="image/*" class="w-full border rounded px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">Recommended: 32×32 or 64×64 PNG/ICO.</p>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Save Changes</button>
        </div>
    </form>
</div>

<script>
    // Sync color inputs: when color picker changes, update text field
    document.querySelectorAll('input[type="color"]').forEach(function(picker) {
        picker.addEventListener('input', function() {
            const hexInput = this.closest('div').querySelector('input[type="text"]');
            if (hexInput) hexInput.value = this.value;
        });
    });
    // Sync text field changes back to color picker (already done with onchange)
</script>
<?php require_once 'includes/footer.php'; ?>