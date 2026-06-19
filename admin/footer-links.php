<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();

// Delete
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM footer_links WHERE id = ?")->execute([$id]);
    logActivity('Footer Link Deleted', "Deleted footer link ID: $id");
    redirect('footer-links.php');
}

// Toggle active status
if (isset($_GET['toggle']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['toggle'];
    $db->prepare("UPDATE footer_links SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
    redirect('footer-links.php');
}

// Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $title = sanitize($_POST['title']);
    $url = sanitize($_POST['url']);
    $target = sanitize($_POST['target']);
    $order = (int)$_POST['display_order'];

    if (isset($_POST['edit_id']) && $_POST['edit_id']) {
        $stmt = $db->prepare("UPDATE footer_links SET title=?, url=?, target=?, display_order=? WHERE id=?");
        $stmt->execute([$title, $url, $target, $order, $_POST['edit_id']]);
        logActivity('Footer Link Updated', "Updated footer link: $title");
    } else {
        $stmt = $db->prepare("INSERT INTO footer_links (title, url, target, display_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $url, $target, $order]);
        logActivity('Footer Link Added', "Added footer link: $title");
    }
    redirect('footer-links.php');
}

$links = $db->query("SELECT * FROM footer_links ORDER BY display_order")->fetchAll();
$editLink = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM footer_links WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editLink = $stmt->fetch();
}
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-6">Footer Links</h1>
    
    <form method="POST" class="mb-8 p-4 border rounded">
        <?= csrfField() ?>
        <input type="hidden" name="edit_id" value="<?= $editLink['id'] ?? '' ?>">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="text" name="title" placeholder="Link Title" value="<?= htmlspecialchars($editLink['title'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <input type="text" name="url" placeholder="URL (e.g., /privacy)" value="<?= htmlspecialchars($editLink['url'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <select name="target" class="border rounded px-3 py-2">
                <option value="_self" <?= isset($editLink) && $editLink['target'] == '_self' ? 'selected' : '' ?>>Same Window</option>
                <option value="_blank" <?= isset($editLink) && $editLink['target'] == '_blank' ? 'selected' : '' ?>>New Window</option>
            </select>
            <input type="number" name="display_order" placeholder="Order" value="<?= $editLink['display_order'] ?? 0 ?>" class="border rounded px-3 py-2">
        </div>
        <div class="flex flex-wrap gap-3 mt-4">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= isset($editLink) ? 'Update' : 'Add' ?> Link</button>
            <?php if (isset($editLink)): ?>
                <a href="footer-links.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Cancel</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[600px]">
            <thead><tr class="bg-gray-100"><th class="p-2 text-left">Title</th><th class="p-2 text-left">URL</th><th class="p-2 text-left">Target</th><th class="p-2 text-left">Order</th><th class="p-2 text-left">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($links as $link): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2"><?= htmlspecialchars($link['title']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($link['url']) ?></td>
                    <td class="p-2"><?= $link['target'] == '_blank' ? 'New Window' : 'Same Window' ?></td>
                    <td class="p-2"><?= $link['display_order'] ?></td>
                    <td class="p-2 whitespace-nowrap">
                        <a href="?edit=<?= $link['id'] ?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                        <a href="?toggle=<?= $link['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-yellow-600 hover:underline mr-2"><?= $link['is_active'] ? 'Hide' : 'Show' ?></a>
                        <a href="?delete=<?= $link['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this footer link?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($links)): ?>
                <tr><td colspan="5" class="p-4 text-center text-gray-500">No footer links added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>