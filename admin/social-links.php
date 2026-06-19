<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();

if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM social_links WHERE id = ?")->execute([$id]);
    logActivity('Social Link Deleted', "Deleted link ID: $id");
    redirect('social-links.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $platform = sanitize($_POST['platform']);
    $url = sanitize($_POST['url']);
    $icon = sanitize($_POST['icon']);
    if ($icon === '__custom__' && isset($_POST['custom_icon']) && !empty($_POST['custom_icon'])) {
        $icon = sanitize($_POST['custom_icon']);
    } elseif ($icon === '__custom__') {
        $icon = '';
    }
    $order = (int)$_POST['display_order'];

    if (isset($_POST['edit_id']) && $_POST['edit_id']) {
        $stmt = $db->prepare("UPDATE social_links SET platform=?, url=?, icon=?, display_order=? WHERE id=?");
        $stmt->execute([$platform, $url, $icon, $order, $_POST['edit_id']]);
        logActivity('Social Link Updated', "Updated $platform");
    } else {
        $stmt = $db->prepare("INSERT INTO social_links (platform, url, icon, display_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$platform, $url, $icon, $order]);
        logActivity('Social Link Added', "Added $platform");
    }
    redirect('social-links.php');
}

$links = $db->query("SELECT * FROM social_links ORDER BY display_order")->fetchAll();
$editLink = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM social_links WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editLink = $stmt->fetch();
}
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-6">Social Links</h1>
    
    <form method="POST" class="mb-8 p-4 border rounded">
        <?= csrfField() ?>
        <input type="hidden" name="edit_id" value="<?= $editLink['id'] ?? '' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="platform" placeholder="Platform (e.g., GitHub, LinkedIn)" value="<?= htmlspecialchars($editLink['platform'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <input type="url" name="url" placeholder="Profile URL" value="<?= htmlspecialchars($editLink['url'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <?= iconDropdown($editLink['icon'] ?? '', 'icon') ?>
            <input type="number" name="display_order" placeholder="Display Order" value="<?= $editLink['display_order'] ?? 0 ?>" class="border rounded px-3 py-2">
        </div>
        <div class="flex flex-wrap gap-3 mt-4">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= isset($editLink) ? 'Update' : 'Add' ?></button>
            <?php if (isset($editLink)): ?>
                <a href="social-links.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Cancel</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[600px]">
            <thead><tr class="bg-gray-100"><th class="p-2 text-left">Platform</th><th>URL</th><th>Icon</th><th>Order</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($links as $link): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2"><?= htmlspecialchars($link['platform']) ?></td>
                    <td class="p-2"><a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" class="text-blue-600 hover:underline truncate block max-w-[200px] md:max-w-none"><?= htmlspecialchars($link['url']) ?></a></td>
                    <td class="p-2"><?= $link['icon'] ? "<i class='" . htmlspecialchars($link['icon']) . "'></i> " . htmlspecialchars($link['icon']) : '-' ?></td>
                    <td class="p-2"><?= $link['display_order'] ?></td>
                    <td class="p-2 whitespace-nowrap">
                        <a href="?edit=<?= $link['id'] ?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                        <a href="?delete=<?= $link['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this link?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($links)): ?>
                <tr><td colspan="5" class="p-4 text-center text-gray-500">No social links added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>