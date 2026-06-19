<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();

if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM skills WHERE id = ?")->execute([$id]);
    logActivity('Skill Deleted', "Deleted skill ID: $id");
    redirect('skills.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $name = sanitize($_POST['name']);
    $level = sanitize($_POST['level']);
    $icon = sanitize($_POST['icon']);
    if ($icon === '__custom__' && isset($_POST['custom_icon']) && !empty($_POST['custom_icon'])) {
        $icon = sanitize($_POST['custom_icon']);
    } elseif ($icon === '__custom__') {
        $icon = '';
    }
    $order = (int)$_POST['display_order'];

    if (isset($_POST['edit_id']) && $_POST['edit_id']) {
        $stmt = $db->prepare("UPDATE skills SET name=?, level=?, icon=?, display_order=? WHERE id=?");
        $stmt->execute([$name, $level, $icon, $order, $_POST['edit_id']]);
        logActivity('Skill Updated', "Updated skill: $name");
    } else {
        $stmt = $db->prepare("INSERT INTO skills (name, level, icon, display_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $level, $icon, $order]);
        logActivity('Skill Added', "Added skill: $name");
    }
    redirect('skills.php');
}

$skills = $db->query("SELECT * FROM skills ORDER BY display_order")->fetchAll();
$editSkill = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM skills WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editSkill = $stmt->fetch();
}
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-6">Manage Skills</h1>
    
    <form method="POST" class="mb-8 p-4 border rounded">
        <?= csrfField() ?>
        <input type="hidden" name="edit_id" value="<?= $editSkill['id'] ?? '' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="name" placeholder="Skill Name" value="<?= htmlspecialchars($editSkill['name'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <input type="text" name="level" placeholder="Level (e.g., Expert)" value="<?= htmlspecialchars($editSkill['level'] ?? '') ?>" class="border rounded px-3 py-2">
            <?= iconDropdown($editSkill['icon'] ?? '', 'icon') ?>
            <input type="number" name="display_order" placeholder="Display Order" value="<?= $editSkill['display_order'] ?? 0 ?>" class="border rounded px-3 py-2">
        </div>
        <div class="flex flex-wrap gap-3 mt-4">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= isset($editSkill) ? 'Update Skill' : 'Add Skill' ?></button>
            <?php if (isset($editSkill)): ?>
                <a href="skills.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 text-center">Cancel</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[500px]">
            <thead><tr class="bg-gray-100"><th class="p-2 text-left">Name</th><th>Level</th><th>Icon</th><th>Order</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($skills as $skill): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2"><?= htmlspecialchars($skill['name']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($skill['level']) ?></td>
                    <td class="p-2"><?= $skill['icon'] ? "<i class='" . htmlspecialchars($skill['icon']) . "'></i> " . htmlspecialchars($skill['icon']) : '-' ?></td>
                    <td class="p-2"><?= $skill['display_order'] ?></td>
                    <td class="p-2">
                        <a href="?edit=<?= $skill['id'] ?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                        <a href="?delete=<?= $skill['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this skill?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($skills)): ?>
                <tr><td colspan="5" class="p-4 text-center text-gray-500">No skills added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>