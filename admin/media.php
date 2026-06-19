<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();

// Single delete (existing)
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['delete'];
    deleteMedia($id);
    logActivity('Media Deleted', "Deleted media ID: $id");
    redirect('media.php');
}

// Bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    verifyCSRF();
    if (isset($_POST['media_ids']) && is_array($_POST['media_ids'])) {
        $deletedCount = 0;
        foreach ($_POST['media_ids'] as $mediaId) {
            $mediaId = (int)$mediaId;
            if (deleteMedia($mediaId)) {
                $deletedCount++;
            }
        }
        logActivity('Bulk Media Deleted', "Deleted $deletedCount media files");
        $_SESSION['bulk_success'] = "Deleted $deletedCount file(s).";
    }
    redirect('media.php');
}

// Single file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    verifyCSRF();
    $type = isset($_POST['type']) ? $_POST['type'] : 'image';
    $result = uploadFile($_FILES['file'], $type);
    if ($result['success']) {
        logActivity('Media Uploaded', "Uploaded file ID: {$result['media_id']}");
        $_SESSION['upload_success'] = "File uploaded successfully.";
    } else {
        $_SESSION['upload_error'] = $result['error'];
    }
    redirect('media.php');
}

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$query = "SELECT * FROM media WHERE original_name LIKE ? ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute(["%$search%"]);
$media = $stmt->fetchAll();

$uploadSuccess = isset($_SESSION['upload_success']) ? $_SESSION['upload_success'] : null;
$uploadError = isset($_SESSION['upload_error']) ? $_SESSION['upload_error'] : null;
$bulkSuccess = isset($_SESSION['bulk_success']) ? $_SESSION['bulk_success'] : null;
unset($_SESSION['upload_success'], $_SESSION['upload_error'], $_SESSION['bulk_success']);
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-6">Media Manager</h1>
    
    <?php if ($uploadSuccess): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($uploadSuccess) ?></div>
    <?php endif; ?>
    <?php if ($uploadError): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($uploadError) ?></div>
    <?php endif; ?>
    <?php if ($bulkSuccess): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($bulkSuccess) ?></div>
    <?php endif; ?>
    
    <!-- Upload Form -->
    <div class="mb-6 p-4 border rounded">
        <h2 class="font-bold mb-2">Upload New File</h2>
        <form method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-4">
            <?= csrfField() ?>
            <select name="type" class="border rounded px-3 py-2 w-full sm:w-auto">
                <option value="image">Image</option>
                <option value="document">Document</option>
            </select>
            <input type="file" name="file" required class="border rounded px-3 py-2 flex-1">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded w-full sm:w-auto">Upload</button>
        </form>
    </div>

    <!-- Search Form -->
    <div class="mb-4">
        <form method="GET" class="flex flex-col sm:flex-row gap-2">
            <input type="text" name="search" placeholder="Search files..." value="<?= htmlspecialchars($search) ?>" class="border rounded px-3 py-2 flex-1">
            <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded w-full sm:w-auto">Search</button>
            <?php if ($search): ?>
                <a href="media.php" class="bg-gray-400 text-white px-4 py-2 rounded text-center w-full sm:w-auto">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Bulk Actions Bar -->
    <?php if (count($media) > 0): ?>
    <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 p-3 bg-gray-50 rounded">
        <label class="flex items-center space-x-2">
            <input type="checkbox" id="selectAllCheckbox" class="rounded">
            <span class="text-sm font-medium">Select All</span>
        </label>
        <button id="deleteSelectedBtn" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
            <i class="fas fa-trash-alt mr-1"></i> Delete Selected
        </button>
    </div>
    <?php endif; ?>

    <!-- Media Grid with Checkboxes -->
    <form method="POST" id="bulkDeleteForm">
        <?= csrfField() ?>
        <input type="hidden" name="bulk_delete" value="1">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php foreach ($media as $item): ?>
            <div class="border rounded p-2 text-center relative">
                <div class="absolute top-1 left-1 z-10">
                    <input type="checkbox" name="media_ids[]" value="<?= $item['id'] ?>" class="media-checkbox rounded">
                </div>
                <?php if (strpos($item['mime_type'], 'image/') === 0): ?>
                    <img src="<?= BASE_URL . $item['file_path'] ?>" class="w-full h-24 object-cover mb-2 mt-4">
                <?php else: ?>
                    <div class="w-full h-24 bg-gray-200 flex items-center justify-center mb-2 mt-4">
                        <i class="fas fa-file fa-3x text-gray-500"></i>
                    </div>
                <?php endif; ?>
                <p class="text-xs truncate"><?= htmlspecialchars($item['original_name']) ?></p>
                <p class="text-xs text-gray-500">ID: <?= $item['id'] ?></p>
                <div class="mt-2 flex justify-center space-x-2">
                    <a href="<?= BASE_URL . $item['file_path'] ?>" target="_blank" class="text-blue-600 text-sm">View</a>
                    <a href="?delete=<?= $item['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-red-600 text-sm" onclick="return confirm('Delete this file?')">Delete</a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($media)): ?>
                <p class="col-span-full text-center text-gray-500 py-8">No media files found.</p>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Confirmation Modal for Bulk Delete -->
<div id="bulkConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Delete Selected Files</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">Are you sure you want to delete the selected files? This action cannot be undone.</p>
            </div>
            <div class="flex justify-center gap-4 mt-4">
                <button id="modalConfirmBtn" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Yes, Delete</button>
                <button id="modalCancelBtn" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Select All functionality
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const mediaCheckboxes = document.querySelectorAll('.media-checkbox');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const bulkForm = document.getElementById('bulkDeleteForm');
    const modal = document.getElementById('bulkConfirmModal');
    const confirmBtn = document.getElementById('modalConfirmBtn');
    const cancelBtn = document.getElementById('modalCancelBtn');

    function updateDeleteButtonState() {
        const anyChecked = Array.from(mediaCheckboxes).some(cb => cb.checked);
        deleteSelectedBtn.disabled = !anyChecked;
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            mediaCheckboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
            updateDeleteButtonState();
        });
    }

    mediaCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (selectAllCheckbox) {
                const allChecked = Array.from(mediaCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            }
            updateDeleteButtonState();
        });
    });

    function closeModal() {
        modal.classList.add('hidden');
    }

    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const anyChecked = Array.from(mediaCheckboxes).some(cb => cb.checked);
            if (anyChecked) {
                modal.classList.remove('hidden');
            }
        });
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            bulkForm.submit();
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }

    window.onclick = function(event) {
        if (event.target === modal) closeModal();
    };
</script>
<?php require_once 'includes/footer.php'; ?>