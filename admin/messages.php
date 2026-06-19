<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();

// Delete single message
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
    logActivity('Message Deleted', "Deleted message ID: $id");
    redirect('messages.php');
}

// Mark single message as read
if (isset($_GET['read']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['read'];
    $db->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$id]);
    logActivity('Message Marked Read', "Marked message ID: $id as read");
    redirect('messages.php');
}

// Mark all messages as read
if (isset($_POST['mark_all_read']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $stmt = $db->prepare("UPDATE contact_messages SET is_read = 1 WHERE is_read = 0");
    $stmt->execute();
    $count = $stmt->rowCount();
    logActivity('All Messages Marked Read', "Marked $count messages as read");
    $_SESSION['bulk_read_success'] = "All messages marked as read ($count message(s)).";
    redirect('messages.php');
}

$messages = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
$unreadCount = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
$bulkSuccess = isset($_SESSION['bulk_read_success']) ? $_SESSION['bulk_read_success'] : null;
unset($_SESSION['bulk_read_success']);
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold">Contact Messages</h1>
        <?php if ($unreadCount > 0): ?>
        <button id="markAllReadBtn" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 inline-flex items-center gap-2">
            <i class="fas fa-check-double"></i> Mark All as Read (<?= $unreadCount ?>)
        </button>
        <?php endif; ?>
    </div>
    <?php if ($bulkSuccess): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($bulkSuccess) ?></div>
    <?php endif; ?>
    <div class="space-y-4">
        <?php foreach ($messages as $msg): ?>
        <div class="border rounded p-4 <?= $msg['is_read'] ? 'bg-gray-50' : 'bg-blue-50 border-blue-200' ?>">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                <div>
                    <strong><?= htmlspecialchars($msg['name']) ?></strong> (<?= htmlspecialchars($msg['email']) ?>)
                    <span class="text-sm text-gray-500 ml-2"><?= date('F j, Y g:i A', strtotime($msg['created_at'])) ?></span>
                </div>
                <div class="flex gap-2">
                    <?php if (!$msg['is_read']): ?>
                        <a href="?read=<?= $msg['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-green-600 text-sm hover:underline">Mark Read</a>
                    <?php endif; ?>
                    <a href="?delete=<?= $msg['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-red-600 text-sm hover:underline" onclick="return confirm('Delete this message?')">Delete</a>
                </div>
            </div>
            <p class="mt-2 text-gray-700 break-words"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (empty($messages)): ?>
            <p class="text-gray-500 text-center py-8">No messages yet.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Confirmation Modal for Mark All Read -->
<div id="markAllModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                <i class="fas fa-check-double text-green-600 text-xl"></i>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Mark All Messages as Read</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">Are you sure you want to mark all unread messages as read? This action cannot be undone.</p>
            </div>
            <div class="flex justify-center gap-4 mt-4">
                <button id="modalConfirmBtn" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Yes, Mark All</button>
                <button id="modalCancelBtn" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
    const markAllBtn = document.getElementById('markAllReadBtn');
    const modal = document.getElementById('markAllModal');
    const confirmBtn = document.getElementById('modalConfirmBtn');
    const cancelBtn = document.getElementById('modalCancelBtn');

    if (markAllBtn) {
        markAllBtn.onclick = () => modal.classList.remove('hidden');
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
            action.name = 'mark_all_read';
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