<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();

// Delete
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM experiences WHERE id = ?");
    $stmt->execute([$id]);
    logActivity('Experience Deleted', "Deleted experience ID: $id");
    redirect('experiences.php');
}

// Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $company = sanitize($_POST['company']);
    $position = sanitize($_POST['position']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'] ?: null;
    $is_current = isset($_POST['is_current']) ? 1 : 0;
    $description = $_POST['description'];
    $order = (int)$_POST['display_order'];

    if ($is_current) $end_date = null;

    if (isset($_POST['edit_id']) && $_POST['edit_id']) {
        $stmt = $db->prepare("UPDATE experiences SET company=?, position=?, start_date=?, end_date=?, is_current=?, description=?, display_order=? WHERE id=?");
        $stmt->execute([$company, $position, $start_date, $end_date, $is_current, $description, $order, $_POST['edit_id']]);
        logActivity('Experience Updated', "Updated experience at $company");
    } else {
        $stmt = $db->prepare("INSERT INTO experiences (company, position, start_date, end_date, is_current, description, display_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$company, $position, $start_date, $end_date, $is_current, $description, $order]);
        logActivity('Experience Added', "Added experience at $company");
    }
    redirect('experiences.php');
}

$experiences = $db->query("SELECT * FROM experiences ORDER BY display_order")->fetchAll();
$editExp = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM experiences WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editExp = $stmt->fetch();
}
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-6">Manage Work Experience</h1>
    
    <form method="POST" class="mb-8 p-4 border rounded">
        <?= csrfField() ?>
        <input type="hidden" name="edit_id" value="<?= $editExp['id'] ?? '' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="company" placeholder="Company Name" value="<?= htmlspecialchars($editExp['company'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <input type="text" name="position" placeholder="Position Title" value="<?= htmlspecialchars($editExp['position'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <input type="date" name="start_date" value="<?= $editExp['start_date'] ?? '' ?>" class="border rounded px-3 py-2" required>
            <input type="date" name="end_date" value="<?= $editExp['end_date'] ?? '' ?>" class="border rounded px-3 py-2" <?= isset($editExp) && $editExp['is_current'] ? 'disabled' : '' ?>>
            <label class="flex items-center space-x-2"><input type="checkbox" name="is_current" id="is_current" <?= isset($editExp) && $editExp['is_current'] ? 'checked' : '' ?>> <span>Current Position</span></label>
            <input type="number" name="display_order" placeholder="Display Order" value="<?= $editExp['display_order'] ?? 0 ?>" class="border rounded px-3 py-2">
        </div>
        <textarea name="description" rows="4" placeholder="Job Description" class="w-full border rounded px-3 py-2 mt-4"><?= htmlspecialchars($editExp['description'] ?? '') ?></textarea>
        <div class="flex flex-wrap gap-3 mt-4">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= isset($editExp) ? 'Update' : 'Add' ?> Experience</button>
            <?php if (isset($editExp)): ?>
                <a href="experiences.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Cancel</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[600px]">
            <thead><tr class="bg-gray-100"><th class="p-2 text-left">Company</th><th class="p-2 text-left">Position</th><th class="p-2 text-left">Period</th><th class="p-2 text-left">Order</th><th class="p-2 text-left">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($experiences as $exp): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2"><?= htmlspecialchars($exp['company']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($exp['position']) ?></td>
                    <td class="p-2"><?= date('M Y', strtotime($exp['start_date'])) ?> - <?= $exp['is_current'] ? 'Present' : date('M Y', strtotime($exp['end_date'])) ?></td>
                    <td class="p-2"><?= $exp['display_order'] ?></td>
                    <td class="p-2 whitespace-nowrap">
                        <a href="?edit=<?= $exp['id'] ?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                        <a href="?delete=<?= $exp['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this experience?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($experiences)): ?>
                <tr><td colspan="5" class="p-4 text-center text-gray-500">No work experience added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    document.getElementById('is_current')?.addEventListener('change', function() {
        let endDateField = document.querySelector('[name="end_date"]');
        if (endDateField) {
            endDateField.disabled = this.checked;
            if (this.checked) endDateField.value = '';
        }
    });
</script>
<?php require_once 'includes/footer.php'; ?>