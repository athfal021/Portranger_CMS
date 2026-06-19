<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();
$seo = getSEO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $site_title = sanitize($_POST['site_title']);
    $meta_description = sanitize($_POST['meta_description']);
    $meta_keywords = sanitize($_POST['meta_keywords']);

    $stmt = $db->prepare("UPDATE seo SET site_title=?, meta_description=?, meta_keywords=? WHERE id=1");
    $stmt->execute([$site_title, $meta_description, $meta_keywords]);

    if (isset($_FILES['social_image']) && $_FILES['social_image']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['social_image'], 'image');
        if ($result['success']) {
            $stmt = $db->prepare("UPDATE seo SET social_image_media_id = ? WHERE id=1");
            $stmt->execute([$result['media_id']]);
            $_SESSION['seo_success'] = 'Social image uploaded successfully.';
        } else {
            $_SESSION['seo_error'] = 'Upload failed: ' . $result['error'];
        }
    } else {
        $_SESSION['seo_success'] = 'SEO settings saved.';
    }
    logActivity('SEO Updated', 'Changed SEO settings');
    redirect('seo.php');
}

$success = isset($_SESSION['seo_success']) ? $_SESSION['seo_success'] : null;
$error = isset($_SESSION['seo_error']) ? $_SESSION['seo_error'] : null;
unset($_SESSION['seo_success'], $_SESSION['seo_error']);

// Fetch current social image path
$socialImagePath = null;
if ($seo['social_image_media_id']) {
    $stmt = $db->prepare("SELECT file_path FROM media WHERE id = ?");
    $stmt->execute([$seo['social_image_media_id']]);
    $img = $stmt->fetch();
    if ($img) $socialImagePath = $img['file_path'];
}
?>
<div class="bg-white rounded-lg shadow p-6">
    <h1 class="text-2xl font-bold mb-6">SEO Settings</h1>
    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" class="space-y-4">
        <?= csrfField() ?>
        <div>
            <label class="block text-gray-700 mb-2">Site Title</label>
            <input type="text" name="site_title" value="<?= htmlspecialchars($seo['site_title']) ?>" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-gray-700 mb-2">Meta Description</label>
            <textarea name="meta_description" rows="3" class="w-full border rounded px-3 py-2"><?= htmlspecialchars($seo['meta_description']) ?></textarea>
        </div>
        <div>
            <label class="block text-gray-700 mb-2">Meta Keywords (comma separated)</label>
            <input type="text" name="meta_keywords" value="<?= htmlspecialchars($seo['meta_keywords']) ?>" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-gray-700 mb-2">Social Sharing Image</label>
            <?php if ($socialImagePath): ?>
                <img src="<?= BASE_URL . $socialImagePath ?>" class="h-32 w-auto mb-2 rounded">
            <?php endif; ?>
            <input type="file" name="social_image" accept="image/*" class="border rounded px-3 py-2 w-full">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded">Save SEO</button>
    </form>
</div>
<?php require_once 'includes/footer.php'; ?>