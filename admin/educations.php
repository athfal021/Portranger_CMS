<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();

if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM educations WHERE id = ?")->execute([$id]);
    logActivity('Education Deleted', "Deleted education ID: $id");
    redirect('educations.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $institution = sanitize($_POST['institution']);
    $degree = sanitize($_POST['degree']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'] ?: null;
    $description = $_POST['description'];
    $order = (int)$_POST['display_order'];

    if (isset($_POST['edit_id']) && $_POST['edit_id']) {
        $stmt = $db->prepare("UPDATE educations SET institution=?, degree=?, start_date=?, end_date=?, description=?, display_order=? WHERE id=?");
        $stmt->execute([$institution, $degree, $start_date, $end_date, $description, $order, $_POST['edit_id']]);
        logActivity('Education Updated', "Updated $degree at $institution");
    } else {
        $stmt = $db->prepare("INSERT INTO educations (institution, degree, start_date, end_date, description, display_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$institution, $degree, $start_date, $end_date, $description, $order]);
        logActivity('Education Added', "Added $degree at $institution");
    }
    redirect('educations.php');
}

$educations = $db->query("SELECT * FROM educations ORDER BY display_order")->fetchAll();
$editEdu = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM educations WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editEdu = $stmt->fetch();
}
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-6">Manage Education</h1>
    
    <form method="POST" class="mb-8 p-4 border rounded">
        <?= csrfField() ?>
        <input type="hidden" name="edit_id" value="<?= $editEdu['id'] ?? '' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="institution" placeholder="Institution" value="<?= htmlspecialchars($editEdu['institution'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <input type="text" name="degree" placeholder="Degree/Certificate" value="<?= htmlspecialchars($editEdu['degree'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <input type="date" name="start_date" value="<?= $editEdu['start_date'] ?? '' ?>" class="border rounded px-3 py-2" required>
            <input type="date" name="end_date" value="<?= $editEdu['end_date'] ?? '' ?>" class="border rounded px-3 py-2">
            <input type="number" name="display_order" placeholder="Display Order" value="<?= $editEdu['display_order'] ?? 0 ?>" class="border rounded px-3 py-2">
        </div>
        <textarea name="description" rows="3" placeholder="Description" class="w-full border rounded px-3 py-2 mt-4"><?= htmlspecialchars($editEdu['description'] ?? '') ?></textarea>
        <div class="flex flex-wrap gap-3 mt-4">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= isset($editEdu) ? 'Update' : 'Add' ?> Education</button>
            <?php if (isset($editEdu)): ?>
                <a href="educations.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Cancel</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[500px]">
            <thead><tr class="bg-gray-100"><th class="p-2 text-left">Institution</th><th class="p-2 text-left">Degree</th><th class="p-2 text-left">Year</th><th class="p-2 text-left">Order</th><th class="p-2 text-left">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($educations as $edu): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2"><?= htmlspecialchars($edu['institution']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($edu['degree']) ?></td>
                    <td class="p-2"><?= date('Y', strtotime($edu['start_date'])) ?> - <?= $edu['end_date'] ? date('Y', strtotime($edu['end_date'])) : 'Present' ?></td>
                    <td class="p-2"><?= $edu['display_order'] ?></td>
                    <td class="p-2 whitespace-nowrap">
                        <a href="?edit=<?= $edu['id'] ?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                        <a href="?delete=<?= $edu['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this education?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($educations)): ?>
                <tr><td colspan="5" class="p-4 text-center text-gray-500">No education entries added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>