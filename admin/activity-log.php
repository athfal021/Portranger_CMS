<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();

if (isset($_POST['clear_all']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $db->exec("DELETE FROM activity_logs");
    logActivity('Activity Log Cleared', 'All activity logs were deleted');
    redirect('activity-log.php?cleared=1');
}

// Fetch logs with the stored username (historical)
$logs = $db->query("SELECT al.* FROM activity_logs al ORDER BY al.created_at DESC LIMIT 200")->fetchAll();
$cleared = isset($_GET['cleared']);
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold">Activity Log</h1>
        <?php if (count($logs) > 0): ?>
        <button id="clearAllBtn" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 inline-flex items-center gap-2">
            <i class="fas fa-trash-alt"></i> Clear All Logs
        </button>
        <?php endif; ?>
    </div>
    <?php if ($cleared): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4">All activity logs have been cleared.</div>
    <?php endif; ?>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[600px]">
            <thead><tr class="bg-gray-100"><th class="p-2 text-left">Time</th><th class="p-2 text-left">User</th><th class="p-2 text-left">Action</th><th class="p-2 text-left">Details</th></tr></thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2 text-sm"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                    <td class="p-2"><?= htmlspecialchars($log['username'] ?? 'Unknown') ?></td>
                    <td class="p-2"><?= htmlspecialchars($log['action']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($log['details']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <tr><td colspan="4" class="p-4 text-center text-gray-500">No activity logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Clear All Activity Logs</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">Are you sure you want to delete all activity logs? This action cannot be undone.</p>
            </div>
            <div class="flex justify-center gap-4 mt-4">
                <button id="modalConfirmBtn" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Yes, Clear All</button>
                <button id="modalCancelBtn" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('confirmModal');
    const clearBtn = document.getElementById('clearAllBtn');
    const confirmBtn = document.getElementById('modalConfirmBtn');
    const cancelBtn = document.getElementById('modalCancelBtn');

    if (clearBtn) {
        clearBtn.onclick = () => modal.classList.remove('hidden');
    }
    function closeModal() { modal.classList.add('hidden'); }
    if (cancelBtn) cancelBtn.onclick = closeModal;
    if (confirmBtn) {
        confirmBtn.onclick = () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = 'csrf_token';
            csrf.value = '<?= $_SESSION['csrf_token'] ?>';
            const action = document.createElement('input');
            action.type = 'hidden';
            action.name = 'clear_all';
            action.value = '1';
            form.appendChild(csrf);
            form.appendChild(action);
            document.body.appendChild(form);
            form.submit();
        };
    }
    window.onclick = (event) => { if (event.target === modal) closeModal(); };
</script>
<?php require_once 'includes/footer.php'; ?>