<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$db = getDB();

// Delete
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("SELECT media_id FROM downloads WHERE id = ?");
    $stmt->execute([$id]);
    $media = $stmt->fetch();
    if ($media) {
        deleteMedia($media['media_id']);
    }
    $db->prepare("DELETE FROM downloads WHERE id = ?")->execute([$id]);
    logActivity('Download Deleted', "Deleted download ID: $id");
    redirect('downloads.php');
}

// AJAX handler must run before any HTML output
$isAjax = $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');

    $response = ['success' => false, 'message' => 'Unknown error'];

    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('CSRF validation failed. Please refresh the page.');
        }

        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $order = (int)($_POST['display_order'] ?? 0);
        $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

        if ($edit_id > 0) {
            // Update existing download
            $stmt = $db->prepare("SELECT media_id FROM downloads WHERE id = ?");
            $stmt->execute([$edit_id]);
            $old = $stmt->fetch();

            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                // Upload new file first
                $upload = uploadFile($_FILES['file'], 'document');

                if (!$upload['success']) {
                    throw new Exception($upload['error']);
                }

                $newMediaId = (int)$upload['media_id'];

                // Update DB first
                $stmt = $db->prepare("UPDATE downloads SET title=?, description=?, media_id=?, display_order=? WHERE id=?");
                $stmt->execute([$title, $description, $newMediaId, $order, $edit_id]);

                // Only remove old file after successful update
                if ($old && (int)$old['media_id'] !== $newMediaId) {
                    deleteMedia($old['media_id']);
                }

                logActivity('Download Updated', "Updated download: $title");
                $response = ['success' => true, 'message' => 'Download updated successfully.'];
            } else {
                // No new file, keep current media_id
                $stmt = $db->prepare("UPDATE downloads SET title=?, description=?, display_order=? WHERE id=?");
                $stmt->execute([$title, $description, $order, $edit_id]);

                logActivity('Download Updated', "Updated download: $title");
                $response = ['success' => true, 'message' => 'Download updated successfully.'];
            }
        } else {
            // Add new download
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['file'], 'document');

                if ($upload['success']) {
                    $stmt = $db->prepare("INSERT INTO downloads (title, description, media_id, display_order) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $upload['media_id'], $order]);

                    logActivity('Download Added', "Added download: $title");
                    $response = ['success' => true, 'message' => 'Download added successfully.'];
                } else {
                    throw new Exception($upload['error']);
                }
            } else {
                throw new Exception('No file uploaded or upload error.');
            }
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }

    echo json_encode($response);
    exit;
}

// Normal page load
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$downloads = $db->query("SELECT d.*, m.file_path, m.original_name FROM downloads d JOIN media m ON d.media_id = m.id ORDER BY d.display_order")->fetchAll();

$editDownload = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM downloads WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editDownload = $stmt->fetch();
}

$successMsg = $_SESSION['download_success'] ?? ($_GET['success'] ?? null);
$errorMsg = $_SESSION['download_error'] ?? ($_GET['error'] ?? null);
unset($_SESSION['download_success'], $_SESSION['download_error']);
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-6">Manage Downloads</h1>

    <?php if ($successMsg): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($successMsg) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <form id="uploadForm" method="POST" enctype="multipart/form-data" class="mb-8 p-4 border rounded">
        <?= csrfField() ?>
        <input type="hidden" name="edit_id" value="<?= $editDownload['id'] ?? '' ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="title" placeholder="Title (e.g., Resume, CV)"
                value="<?= htmlspecialchars($editDownload['title'] ?? '') ?>"
                class="border rounded px-3 py-2" required>

            <input type="text" name="description" placeholder="Description"
                value="<?= htmlspecialchars($editDownload['description'] ?? '') ?>"
                class="border rounded px-3 py-2">

            <input type="number" name="display_order" placeholder="Display Order"
                value="<?= $editDownload['display_order'] ?? 0 ?>"
                class="border rounded px-3 py-2">

            <div>
                <label class="block text-gray-700 mb-1">File (PDF, DOC, ZIP):</label>
                <input type="file" name="file" id="fileInput" accept=".pdf,.doc,.docx,.zip"
                    class="border rounded px-3 py-2 w-full" <?= isset($editDownload) ? '' : 'required' ?>>
                <?php if (isset($editDownload)): ?>
                    <p class="text-xs text-gray-500 mt-1">Leave empty to keep current file.</p>
                <?php endif; ?>
            </div>
        </div>

        <div id="progressContainer" style="display:none;" class="mt-4">
            <div class="flex justify-between text-sm text-gray-600 mb-1">
                <span>Uploading...</span>
                <span id="progressPercent">0%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                <div id="progressBar" class="bg-blue-600 h-4 rounded-full transition-all duration-300" style="width: 0%;"></div>
            </div>
            <p id="progressStatus" class="text-xs text-gray-500 mt-1"></p>
        </div>

        <div class="flex flex-wrap gap-3 mt-4">
            <button type="submit" id="submitBtn"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                <?= isset($editDownload) ? 'Update' : 'Upload' ?> Download
            </button>

            <?php if (isset($editDownload)): ?>
                <a href="downloads.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Cancel</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[600px]">
            <thead>
                <tr class="bg-gray-100">
                    <th class="p-2 text-left">Title</th>
                    <th class="p-2 text-left">Description</th>
                    <th class="p-2 text-left">File</th>
                    <th class="p-2 text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($downloads as $dl): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-2"><?= htmlspecialchars($dl['title']) ?></td>
                        <td class="p-2"><?= htmlspecialchars($dl['description']) ?></td>
                        <td class="p-2">
                            <a href="<?= BASE_URL . $dl['file_path'] ?>" target="_blank" class="text-blue-600 hover:underline">
                                Download
                            </a>
                        </td>
                        <td class="p-2 whitespace-nowrap">
                            <a href="?edit=<?= $dl['id'] ?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                            <a href="?delete=<?= $dl['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                               class="text-red-600 hover:underline"
                               onclick="return confirm('Delete this download?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($downloads)): ?>
                    <tr>
                        <td colspan="4" class="p-4 text-center text-gray-500">No downloads added yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('uploadForm');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    const progressStatus = document.getElementById('progressStatus');
    const submitBtn = document.getElementById('submitBtn');

    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const fileInput = document.getElementById('fileInput');
        const editId = document.querySelector('input[name="edit_id"]').value;

        if (!editId && (!fileInput.files || fileInput.files.length === 0)) {
            alert('Please select a file to upload.');
            return;
        }

        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressPercent.textContent = '0%';
        progressStatus.textContent = 'Preparing upload...';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Uploading...';

        const formData = new FormData(form);
        formData.append('ajax', '1');

        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', function(event) {
            if (event.lengthComputable) {
                const percent = Math.round((event.loaded / event.total) * 100);
                progressBar.style.width = percent + '%';
                progressPercent.textContent = percent + '%';
                progressStatus.textContent = 'Uploading... ' + percent + '%';
            }
        });

        xhr.addEventListener('loadstart', function() {
            progressStatus.textContent = 'Starting upload...';
        });

        xhr.addEventListener('load', function() {
            try {
                const response = JSON.parse(xhr.responseText);

                if (response.success) {
                    progressBar.style.width = '100%';
                    progressPercent.textContent = '100%';
                    progressStatus.textContent = 'Upload complete!';
                    window.location.href = 'downloads.php?success=' + encodeURIComponent(response.message);
                } else {
                    progressStatus.textContent = 'Error: ' + response.message;
                    progressBar.style.width = '0%';
                    progressPercent.textContent = '0%';
                    submitBtn.disabled = false;
                    submitBtn.textContent = '<?= isset($editDownload) ? 'Update' : 'Upload' ?> Download';
                    alert('Upload failed: ' + response.message);
                }
            } catch (e) {
                progressStatus.textContent = 'Unexpected response. Please refresh to see if upload succeeded.';
                submitBtn.disabled = false;
                submitBtn.textContent = '<?= isset($editDownload) ? 'Update' : 'Upload' ?> Download';

                if (xhr.responseText.includes('success') || xhr.responseText.includes('uploaded')) {
                    alert('Upload may have succeeded. Please refresh the page to confirm.');
                    window.location.reload();
                } else {
                    alert('Unexpected error. Please check the server response.\n\n' + xhr.responseText.substring(0, 200));
                }
            }
        });

        xhr.addEventListener('error', function() {
            progressStatus.textContent = 'Network error. Please check your connection.';
            submitBtn.disabled = false;
            submitBtn.textContent = '<?= isset($editDownload) ? 'Update' : 'Upload' ?> Download';
            alert('Network error. Please try again.');
        });

        xhr.open('POST', window.location.href);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(formData);
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>