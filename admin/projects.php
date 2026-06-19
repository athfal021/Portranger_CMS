<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

/**
 * Normalize a single uploaded file field into a standard file array.
 */
function normalizeSingleUpload(array $file): ?array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name']) || empty($file['name'])) {
        return null;
    }

    return $file;
}

/**
 * Normalize a multiple file upload field into a flat array of standard file arrays.
 */
function normalizeMultipleUploads(array $fileInput): array
{
    $files = [];

    if (!isset($fileInput['name'])) {
        return $files;
    }

    if (is_array($fileInput['name'])) {
        foreach ($fileInput['name'] as $index => $name) {
            $error = $fileInput['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            $tmpName = $fileInput['tmp_name'][$index] ?? '';
            $size = $fileInput['size'][$index] ?? 0;
            $type = $fileInput['type'][$index] ?? '';

            if ($error === UPLOAD_ERR_OK && !empty($name) && !empty($tmpName)) {
                $files[] = [
                    'name' => $name,
                    'tmp_name' => $tmpName,
                    'size' => $size,
                    'error' => UPLOAD_ERR_OK,
                    'type' => $type,
                ];
            }
        }
    } else {
        if (($fileInput['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($fileInput['name']) && !empty($fileInput['tmp_name'])) {
            $files[] = $fileInput;
        }
    }

    return $files;
}

/**
 * Delete media IDs safely, ignoring empty values and duplicates.
 */
function deleteMediaIds(array $mediaIds): void
{
    $mediaIds = array_unique(array_filter(array_map('intval', $mediaIds)));
    foreach ($mediaIds as $mediaId) {
        deleteMedia($mediaId);
    }
}

$db = getDB();

// Delete project (delete cover + gallery media too)
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['delete'];

    $stmt = $db->prepare("SELECT thumbnail_media_id FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();

    $stmt = $db->prepare("SELECT media_id FROM project_gallery WHERE project_id = ?");
    $stmt->execute([$id]);
    $galleryMediaIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $mediaIdsToDelete = [];
    if ($project && !empty($project['thumbnail_media_id'])) {
        $mediaIdsToDelete[] = (int)$project['thumbnail_media_id'];
    }
    if (!empty($galleryMediaIds)) {
        foreach ($galleryMediaIds as $mid) {
            $mediaIdsToDelete[] = (int)$mid;
        }
    }

    deleteMediaIds($mediaIdsToDelete);

    $db->prepare("DELETE FROM project_gallery WHERE project_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);

    logActivity('Project Deleted', "Deleted project ID: $id");
    redirect('projects.php?success=' . urlencode('Project deleted successfully.'));
}

// Toggle featured
if (isset($_GET['featured']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['featured'];
    $db->prepare("UPDATE projects SET is_featured = NOT is_featured WHERE id = ?")->execute([$id]);
    redirect('projects.php?success=' . urlencode('Featured status updated.'));
}

// Remove gallery image (delete from media too)
if (isset($_GET['remove_gallery']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $galleryId = (int)$_GET['remove_gallery'];
    $projectId = (int)($_GET['project_id'] ?? 0);

    $stmt = $db->prepare("SELECT media_id FROM project_gallery WHERE id = ?");
    $stmt->execute([$galleryId]);
    $media = $stmt->fetch();

    if ($media) {
        deleteMedia((int)$media['media_id']);
        $db->prepare("DELETE FROM project_gallery WHERE id = ?")->execute([$galleryId]);
    }

    redirect('projects.php?edit=' . $projectId . '&success=' . urlencode('Gallery image removed.'));
}

// Add/Edit Project
$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_gallery'])) {
    if ($isAjax) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
    }

    $response = ['success' => false, 'message' => 'Unknown error'];

    try {
        verifyCSRF();

        $title = sanitize($_POST['title'] ?? '');
        $short_desc = sanitize($_POST['short_description'] ?? '');
        $detailed = $_POST['detailed_description'] ?? '';
        $technologies = sanitize($_POST['technologies'] ?? '');
        $repo_link = sanitize($_POST['repo_link'] ?? '');
        $demo_link = sanitize($_POST['demo_link'] ?? '');
        $order = (int)($_POST['display_order'] ?? 0);

        $editId = isset($_POST['edit_id']) && $_POST['edit_id'] ? (int)$_POST['edit_id'] : 0;

        $existingProject = null;
        $oldThumbnailId = null;

        if ($editId > 0) {
            $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$editId]);
            $existingProject = $stmt->fetch();

            if (!$existingProject) {
                throw new Exception('Project not found.');
            }

            $oldThumbnailId = !empty($existingProject['thumbnail_media_id']) ? (int)$existingProject['thumbnail_media_id'] : null;
        }

        $thumbnail_id = $oldThumbnailId;
        $newThumbnailId = null;

        // Cover image upload (optional on edit, optional on add unless you decide otherwise)
        if (isset($_FILES['thumbnail_image'])) {
            $coverFile = normalizeSingleUpload($_FILES['thumbnail_image']);
            if ($coverFile) {
                $upload = uploadFile($coverFile, 'image');
                if (!$upload['success']) {
                    throw new Exception($upload['error']);
                }
                $newThumbnailId = (int)$upload['media_id'];
                $thumbnail_id = $newThumbnailId;
            }
        }

        if ($editId > 0) {
            $stmt = $db->prepare("UPDATE projects SET title=?, short_description=?, detailed_description=?, technologies=?, thumbnail_media_id=?, repo_link=?, demo_link=?, display_order=? WHERE id=?");
            $stmt->execute([$title, $short_desc, $detailed, $technologies, $thumbnail_id, $repo_link, $demo_link, $order, $editId]);

            $projectId = $editId;
            logActivity('Project Updated', "Updated project: $title");

            if ($newThumbnailId && $oldThumbnailId && $oldThumbnailId !== $newThumbnailId) {
                deleteMedia($oldThumbnailId);
            }

            $actionMessage = 'Project updated successfully.';
        } else {
            $stmt = $db->prepare("INSERT INTO projects (title, short_description, detailed_description, technologies, thumbnail_media_id, repo_link, demo_link, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $short_desc, $detailed, $technologies, $thumbnail_id, $repo_link, $demo_link, $order]);

            $projectId = (int)$db->lastInsertId();
            logActivity('Project Added', "Added project: $title");

            $actionMessage = 'Project added successfully.';
        }

        // Handle gallery uploads (multiple files)
        $galleryFiles = [];
        if (isset($_FILES['gallery_images'])) {
            $galleryFiles = normalizeMultipleUploads($_FILES['gallery_images']);
        }

        if (!empty($galleryFiles)) {
            $orderStmt = $db->prepare("SELECT COALESCE(MAX(display_order), -1) FROM project_gallery WHERE project_id = ?");
            $orderStmt->execute([$projectId]);
            $nextGalleryOrder = ((int)$orderStmt->fetchColumn()) + 1;

            foreach ($galleryFiles as $file) {
                $result = uploadFile($file, 'image');
                if ($result['success']) {
                    $stmt = $db->prepare("INSERT INTO project_gallery (project_id, media_id, display_order) VALUES (?, ?, ?)");
                    $stmt->execute([$projectId, (int)$result['media_id'], $nextGalleryOrder]);
                    $nextGalleryOrder++;
                }
            }
        }

        if ($isAjax) {
            echo json_encode(['success' => true, 'message' => $actionMessage]);
            exit;
        }

        redirect('projects.php?success=' . urlencode($actionMessage));
    } catch (Exception $e) {
        if (!empty($newThumbnailId)) {
            deleteMedia($newThumbnailId);
        }

        $response = ['success' => false, 'message' => $e->getMessage()];

        if ($isAjax) {
            echo json_encode($response);
            exit;
        }

        redirect('projects.php?error=' . urlencode($e->getMessage()));
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$projects = $db->query("SELECT p.*, m.file_path as thumbnail_path FROM projects p LEFT JOIN media m ON p.thumbnail_media_id = m.id ORDER BY p.display_order")->fetchAll();

$editProject = null;
$gallery = [];

if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT p.*, m.file_path as thumbnail_path FROM projects p LEFT JOIN media m ON p.thumbnail_media_id = m.id WHERE p.id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editProject = $stmt->fetch();

    if ($editProject) {
        $galleryStmt = $db->prepare("SELECT pg.*, m.file_path, m.original_name FROM project_gallery pg JOIN media m ON pg.media_id = m.id WHERE pg.project_id = ? ORDER BY pg.display_order");
        $galleryStmt->execute([$editProject['id']]);
        $gallery = $galleryStmt->fetchAll();
    }
}

$successMsg = $_GET['success'] ?? null;
$errorMsg = $_GET['error'] ?? null;
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-6">Manage Projects</h1>

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
    
    <form id="projectForm" method="POST" enctype="multipart/form-data" class="mb-8 p-4 border rounded">
        <?= csrfField() ?>
        <input type="hidden" name="edit_id" value="<?= $editProject['id'] ?? '' ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="title" placeholder="Project Title" value="<?= htmlspecialchars($editProject['title'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <input type="text" name="short_description" placeholder="Short Description" value="<?= htmlspecialchars($editProject['short_description'] ?? '') ?>" class="border rounded px-3 py-2" required>
            <input type="text" name="technologies" placeholder="Technologies (comma separated)" value="<?= htmlspecialchars($editProject['technologies'] ?? '') ?>" class="border rounded px-3 py-2">
            <input type="url" name="repo_link" placeholder="Repository Link" value="<?= htmlspecialchars($editProject['repo_link'] ?? '') ?>" class="border rounded px-3 py-2">
            <input type="url" name="demo_link" placeholder="Live Demo Link" value="<?= htmlspecialchars($editProject['demo_link'] ?? '') ?>" class="border rounded px-3 py-2">
            <input type="number" name="display_order" placeholder="Display Order" value="<?= $editProject['display_order'] ?? 0 ?>" class="border rounded px-3 py-2">

            <div>
                <label class="block text-gray-700 mb-1">Cover Image (upload):</label>
                <input type="file" name="thumbnail_image" accept="image/*" class="border rounded px-3 py-2 w-full">
                <?php if (isset($editProject)): ?>
                    <p class="text-xs text-gray-500 mt-1">Leave empty to keep current cover image.</p>
                <?php endif; ?>

                <?php if (!empty($editProject['thumbnail_path'])): ?>
                    <div class="mt-3">
                        <p class="text-xs text-gray-500 mb-1">Current cover:</p>
                        <img src="<?= BASE_URL . $editProject['thumbnail_path'] ?>" alt="Current cover" class="w-28 h-28 object-cover rounded border">
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-gray-700 mb-1">Gallery Images (upload multiple):</label>
                <input type="file" name="gallery_images[]" multiple accept="image/*" class="border rounded px-3 py-2 w-full">
            </div>
        </div>

        <textarea name="detailed_description" rows="6" placeholder="Detailed Description" class="w-full border rounded px-3 py-2 mt-4"><?= htmlspecialchars($editProject['detailed_description'] ?? '') ?></textarea>

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
            <button type="submit" id="submitBtn" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= isset($editProject) ? 'Update Project' : 'Add Project' ?></button>
            <?php if (isset($editProject)): ?>
                <a href="projects.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Cancel</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (isset($editProject) && !empty($gallery)): ?>
    <div class="mb-8">
        <h2 class="text-xl font-bold mb-3">Project Gallery</h2>
        <div class="flex flex-wrap gap-4">
            <?php foreach ($gallery as $img): ?>
            <div class="relative w-32 h-32 border rounded overflow-hidden">
                <img src="<?= BASE_URL . $img['file_path'] ?>" class="w-full h-full object-cover" alt="">
                <a href="?remove_gallery=<?= $img['id'] ?>&project_id=<?= $editProject['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="absolute top-0 right-0 bg-red-600 text-white p-1 rounded-bl" onclick="return confirm('Remove this image?')">&times;</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px]">
            <thead>
                <tr class="bg-gray-100">
                    <th class="p-2 text-left">Title</th>
                    <th class="p-2 text-left">Technologies</th>
                    <th class="p-2 text-left">Featured</th>
                    <th class="p-2 text-left">Order</th>
                    <th class="p-2 text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $proj): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2"><?= htmlspecialchars($proj['title']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($proj['technologies']) ?></td>
                    <td class="p-2"><?= $proj['is_featured'] ? '⭐ Featured' : '-' ?></td>
                    <td class="p-2"><?= $proj['display_order'] ?></td>
                    <td class="p-2 whitespace-nowrap">
                        <a href="?edit=<?= $proj['id'] ?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                        <a href="?featured=<?= $proj['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-yellow-600 hover:underline mr-2">Toggle Featured</a>
                        <a href="?delete=<?= $proj['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this project? All gallery and cover media will be removed.')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($projects)): ?>
                <tr>
                    <td colspan="5" class="p-4 text-center text-gray-500">No projects added yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('projectForm');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    const progressStatus = document.getElementById('progressStatus');
    const submitBtn = document.getElementById('submitBtn');

    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressPercent.textContent = '0%';
        progressStatus.textContent = 'Preparing upload...';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Uploading...';

        const formData = new FormData(form);
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
                    window.location.href = 'projects.php?success=' + encodeURIComponent(response.message);
                } else {
                    progressStatus.textContent = 'Error: ' + response.message;
                    progressBar.style.width = '0%';
                    progressPercent.textContent = '0%';
                    submitBtn.disabled = false;
                    submitBtn.textContent = '<?= isset($editProject) ? 'Update Project' : 'Add Project' ?>';
                    alert('Operation failed: ' + response.message);
                }
            } catch (err) {
                progressStatus.textContent = 'Unexpected response. Please refresh to see if the upload succeeded.';
                submitBtn.disabled = false;
                submitBtn.textContent = '<?= isset($editProject) ? 'Update Project' : 'Add Project' ?>';
                alert('Unexpected error. Please check the server response.');
            }
        });

        xhr.addEventListener('error', function() {
            progressStatus.textContent = 'Network error. Please check your connection.';
            submitBtn.disabled = false;
            submitBtn.textContent = '<?= isset($editProject) ? 'Update Project' : 'Add Project' ?>';
            alert('Network error. Please try again.');
          });
       
        xhr.open('POST', window.location.href);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(formData);
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>