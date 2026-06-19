<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();

if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM navigation_items WHERE id = ?")->execute([$id]);
    logActivity('Navigation Deleted', "Deleted nav item ID: $id");
    redirect('navigation.php');
}

if (isset($_GET['toggle']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['toggle'];
    $db->prepare("UPDATE navigation_items SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
    redirect('navigation.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $title = sanitize($_POST['title']);
    $url = sanitize($_POST['url']);
    $target = sanitize($_POST['target']);
    $order = (int)$_POST['display_order'];
    $active = isset($_POST['is_active']) ? 1 : 0;

    if (isset($_POST['edit_id']) && $_POST['edit_id']) {
        $stmt = $db->prepare("UPDATE navigation_items SET title=?, url=?, target=?, display_order=?, is_active=? WHERE id=?");
        $stmt->execute([$title, $url, $target, $order, $active, $_POST['edit_id']]);
        logActivity('Navigation Updated', "Updated nav: $title");
    } else {
        $stmt = $db->prepare("INSERT INTO navigation_items (title, url, target, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $url, $target, $order, $active]);
        logActivity('Navigation Added', "Added nav: $title");
    }
    redirect('navigation.php');
}

$navItems = $db->query("SELECT * FROM navigation_items ORDER BY display_order")->fetchAll();
$editNav = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM navigation_items WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editNav = $stmt->fetch();
}
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-6">Navigation Menu</h1>
    
    <form method="POST" class="mb-8 p-4 border rounded">
        <?= csrfField() ?>
        <input type="hidden" name="edit_id" value="<?= $editNav['id'] ?? '' ?>">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <input type="text" name="title" placeholder="Menu Title" value="<?= htmlspecialchars($editNav['title'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <input type="text" name="url" placeholder="URL (e.g., /#about)" value="<?= htmlspecialchars($editNav['url'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <select name="target" class="border rounded px-3 py-2">
                <option value="_self" <?= isset($editNav) && $editNav['target'] == '_self' ? 'selected' : '' ?>>Same Window</option>
                <option value="_blank" <?= isset($editNav) && $editNav['target'] == '_blank' ? 'selected' : '' ?>>New Window</option>
            </select>
            <input type="number" name="display_order" placeholder="Order" value="<?= $editNav['display_order'] ?? 0 ?>" class="border rounded px-3 py-2">
            <label class="flex items-center space-x-2"><input type="checkbox" name="is_active" <?= isset($editNav) && $editNav['is_active'] ? 'checked' : '' ?>> <span>Active</span></label>
        </div>
        <div class="flex flex-wrap gap-3 mt-4">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= isset($editNav) ? 'Update' : 'Add' ?></button>
            <?php if (isset($editNav)): ?>
                <a href="navigation.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Cancel</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[600px]">
            <thead><tr class="bg-gray-100"><th class="p-2 text-left">Title</th><th class="p-2 text-left">URL</th><th class="p-2 text-left">Order</th><th class="p-2 text-left">Status</th><th class="p-2 text-left">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($navItems as $item): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2"><?= htmlspecialchars($item['title']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($item['url']) ?></td>
                    <td class="p-2"><?= $item['display_order'] ?></td>
                    <td class="p-2"><?= $item['is_active'] ? '<span class="text-green-600">Active</span>' : '<span class="text-red-600">Inactive</span>' ?></td>
                    <td class="p-2 whitespace-nowrap">
                        <a href="?edit=<?= $item['id'] ?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                        <a href="?toggle=<?= $item['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-yellow-600 hover:underline mr-2">Toggle</a>
                        <a href="?delete=<?= $item['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this menu item?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($navItems)): ?>
                <tr><td colspan="5" class="p-4 text-center text-gray-500">No navigation items added yet.<?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>