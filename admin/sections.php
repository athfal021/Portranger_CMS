<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();

if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM sections WHERE id = ?")->execute([$id]);
    logActivity('Section Deleted', "Deleted section ID: $id");
    redirect('sections.php');
}

if (isset($_GET['toggle']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['toggle'];
    $db->prepare("UPDATE sections SET is_visible = NOT is_visible WHERE id = ?")->execute([$id]);
    redirect('sections.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $title = sanitize($_POST['title']);
    $description = $_POST['description'];
    $icon = sanitize($_POST['icon']);
    if ($icon === '__custom__' && isset($_POST['custom_icon']) && !empty($_POST['custom_icon'])) {
        $icon = sanitize($_POST['custom_icon']);
    } elseif ($icon === '__custom__') {
        $icon = '';
    }
    $order = (int)$_POST['display_order'];
    $visible = isset($_POST['is_visible']) ? 1 : 0;

    if (isset($_POST['edit_id']) && $_POST['edit_id']) {
        $stmt = $db->prepare("UPDATE sections SET title=?, description=?, icon=?, display_order=?, is_visible=? WHERE id=?");
        $stmt->execute([$title, $description, $icon, $order, $visible, $_POST['edit_id']]);
        logActivity('Section Updated', "Updated section: $title");
    } else {
        $stmt = $db->prepare("INSERT INTO sections (title, description, icon, display_order, is_visible) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $icon, $order, $visible]);
        logActivity('Section Added', "Added section: $title");
    }
    redirect('sections.php');
}

$sections = $db->query("SELECT * FROM sections ORDER BY display_order")->fetchAll();
$editSection = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM sections WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editSection = $stmt->fetch();
}
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-6">Manage Dynamic Sections</h1>
    
    <form method="POST" class="mb-8 p-4 border rounded">
        <?= csrfField() ?>
        <input type="hidden" name="edit_id" value="<?= $editSection['id'] ?? '' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="title" placeholder="Section Title" value="<?= htmlspecialchars($editSection['title'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <?= iconDropdown($editSection['icon'] ?? '', 'icon') ?>
            <input type="number" name="display_order" placeholder="Display Order" value="<?= $editSection['display_order'] ?? 0 ?>" class="border rounded px-3 py-2">
            <label class="flex items-center space-x-2"><input type="checkbox" name="is_visible" <?= isset($editSection) && $editSection['is_visible'] ? 'checked' : '' ?>> <span>Visible on frontend</span></label>
        </div>
        <textarea name="description" rows="4" placeholder="Section Description (optional)" class="w-full border rounded px-3 py-2 mt-4"><?= htmlspecialchars($editSection['description'] ?? '') ?></textarea>
        <div class="flex flex-wrap gap-3 mt-4">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= isset($editSection) ? 'Update Section' : 'Add Section' ?></button>
            <?php if (isset($editSection)): ?>
                <a href="sections.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Cancel</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[600px]">
            <thead><tr class="bg-gray-100"><th class="p-2 text-left">Title</th><th>Icon</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($sections as $section): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2"><?= htmlspecialchars($section['title']) ?></td>
                    <td class="p-2"><?= $section['icon'] ? "<i class='" . htmlspecialchars($section['icon']) . "'></i> " . htmlspecialchars($section['icon']) : '-' ?></td>
                    <td class="p-2"><?= $section['display_order'] ?></td>
                    <td class="p-2"><?= $section['is_visible'] ? '<span class="text-green-600">Visible</span>' : '<span class="text-red-600">Hidden</span>' ?></td>
                    <td class="p-2 whitespace-nowrap">
                        <a href="?edit=<?= $section['id'] ?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                        <a href="?toggle=<?= $section['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-yellow-600 hover:underline mr-2">Toggle</a>
                        <a href="?delete=<?= $section['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this section?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($sections)): ?>
                <tr><td colspan="5" class="p-4 text-center text-gray-500">No custom sections added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>