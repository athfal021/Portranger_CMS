<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();

// Save profile text data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    verifyCSRF();
    $full_name = sanitize($_POST['full_name']);
    $professional_title = sanitize($_POST['professional_title']);
    $short_intro = sanitize($_POST['short_intro']);
    $about_description = $_POST['about_description'];
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $location = sanitize($_POST['location']);
    
    $stmt = $db->prepare("UPDATE profile SET full_name=?, professional_title=?, short_intro=?, about_description=?, email=?, phone=?, location=? WHERE id=1");
    $stmt->execute([$full_name, $professional_title, $short_intro, $about_description, $email, $phone, $location]);
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['profile_image'], 'image');
        if ($result['success']) {
            $stmt = $db->prepare("UPDATE profile SET profile_image_id = ? WHERE id=1");
            $stmt->execute([$result['media_id']]);
        }
    }
    if (isset($_FILES['about_image']) && $_FILES['about_image']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['about_image'], 'image');
        if ($result['success']) {
            $stmt = $db->prepare("UPDATE profile SET about_image_id = ? WHERE id=1");
            $stmt->execute([$result['media_id']]);
        }
    }
    logActivity('Profile Updated', 'Admin updated profile information');
    redirect('profile.php?success=1');
}

// Cover slide upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_cover'])) {
    verifyCSRF();
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['cover_image'], 'image');
        if ($result['success']) {
            // Check if cover_slides table exists, if not create it
            try {
                $db->query("SELECT 1 FROM cover_slides LIMIT 1");
            } catch (PDOException $e) {
                $db->exec("CREATE TABLE IF NOT EXISTS cover_slides (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    media_id INT NOT NULL,
                    title VARCHAR(255),
                    caption VARCHAR(500),
                    display_order INT DEFAULT 0,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
                )");
            }
            $maxOrder = $db->query("SELECT MAX(display_order) as max FROM cover_slides")->fetchColumn();
            $order = ($maxOrder ? $maxOrder + 1 : 1);
            $stmt = $db->prepare("INSERT INTO cover_slides (media_id, display_order) VALUES (?, ?)");
            $stmt->execute([$result['media_id'], $order]);
            logActivity('Cover Slide Added', 'Uploaded new cover slide');
        } else {
            $_SESSION['upload_error'] = $result['error'];
        }
    }
    redirect('profile.php#covers');
}

// Delete cover slide
if (isset($_GET['delete_cover']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['delete_cover'];
    $stmt = $db->prepare("SELECT media_id FROM cover_slides WHERE id = ?");
    $stmt->execute([$id]);
    $media = $stmt->fetch();
    if ($media) {
        deleteMedia($media['media_id']);
    }
    $db->prepare("DELETE FROM cover_slides WHERE id = ?")->execute([$id]);
    logActivity('Cover Slide Deleted', "Deleted cover slide ID: $id");
    redirect('profile.php#covers');
}

// Toggle cover active
if (isset($_GET['toggle_cover']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['toggle_cover'];
    $db->prepare("UPDATE cover_slides SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
    redirect('profile.php#covers');
}

// Move cover up/down
if (isset($_GET['move_cover']) && isset($_GET['dir']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = (int)$_GET['move_cover'];
    $dir = $_GET['dir'];
    $currentOrder = $db->prepare("SELECT display_order FROM cover_slides WHERE id = ?");
    $currentOrder->execute([$id]);
    $order = $currentOrder->fetchColumn();
    if ($dir === 'up') {
        $swap = $db->prepare("SELECT id FROM cover_slides WHERE display_order < ? ORDER BY display_order DESC LIMIT 1");
        $swap->execute([$order]);
        $swapId = $swap->fetchColumn();
        if ($swapId) {
            $db->prepare("UPDATE cover_slides SET display_order = display_order + 1 WHERE id = ?")->execute([$swapId]);
            $db->prepare("UPDATE cover_slides SET display_order = display_order - 1 WHERE id = ?")->execute([$id]);
        }
    } elseif ($dir === 'down') {
        $swap = $db->prepare("SELECT id FROM cover_slides WHERE display_order > ? ORDER BY display_order ASC LIMIT 1");
        $swap->execute([$order]);
        $swapId = $swap->fetchColumn();
        if ($swapId) {
            $db->prepare("UPDATE cover_slides SET display_order = display_order - 1 WHERE id = ?")->execute([$swapId]);
            $db->prepare("UPDATE cover_slides SET display_order = display_order + 1 WHERE id = ?")->execute([$id]);
        }
    }
    redirect('profile.php#covers');
}

// Fetch profile data with images
$stmt = $db->prepare("
    SELECT p.*, 
           prof.file_path as profile_path, 
           about.file_path as about_path 
    FROM profile p
    LEFT JOIN media prof ON p.profile_image_id = prof.id
    LEFT JOIN media about ON p.about_image_id = about.id
    WHERE p.id = 1
");
$stmt->execute();
$profileData = $stmt->fetch();

// Fetch cover slides
$covers = [];
try {
    $covers = $db->query("SELECT cs.*, m.file_path, m.original_name FROM cover_slides cs JOIN media m ON cs.media_id = m.id ORDER BY cs.display_order")->fetchAll();
} catch (PDOException $e) {
    // Table doesn't exist yet
}

$success = isset($_GET['success']);
$uploadError = isset($_SESSION['upload_error']) ? $_SESSION['upload_error'] : null;
unset($_SESSION['upload_error']);
?>
<div class="bg-white rounded-lg shadow p-6">
    <h1 class="text-2xl font-bold mb-6">Profile Management</h1>
    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4">Profile updated successfully!</div>
    <?php endif; ?>
    <?php if ($uploadError): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4">Upload error: <?= htmlspecialchars($uploadError) ?></div>
    <?php endif; ?>
    
    <!-- Basic Info Form -->
    <form method="POST" enctype="multipart/form-data" class="mb-10 border-b pb-8">
        <input type="hidden" name="save_profile" value="1">
        <?= csrfField() ?>
        <h2 class="text-xl font-bold mb-4">Basic Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div><label class="block text-gray-700 mb-2">Full Name</label><input type="text" name="full_name" value="<?= htmlspecialchars($profileData['full_name']) ?>" class="w-full border rounded px-3 py-2" required></div>
            <div><label class="block text-gray-700 mb-2">Professional Title</label><input type="text" name="professional_title" value="<?= htmlspecialchars($profileData['professional_title']) ?>" class="w-full border rounded px-3 py-2" required></div>
            <div class="md:col-span-2"><label class="block text-gray-700 mb-2">Short Introduction</label><textarea name="short_intro" rows="3" class="w-full border rounded px-3 py-2"><?= htmlspecialchars($profileData['short_intro']) ?></textarea></div>
            <div class="md:col-span-2"><label class="block text-gray-700 mb-2">About Description</label><textarea name="about_description" rows="5" class="w-full border rounded px-3 py-2"><?= htmlspecialchars($profileData['about_description']) ?></textarea></div>
            <div><label class="block text-gray-700 mb-2">Email</label><input type="email" name="email" value="<?= htmlspecialchars($profileData['email']) ?>" class="w-full border rounded px-3 py-2"></div>
            <div><label class="block text-gray-700 mb-2">Phone</label><input type="text" name="phone" value="<?= htmlspecialchars($profileData['phone']) ?>" class="w-full border rounded px-3 py-2"></div>
            <div><label class="block text-gray-700 mb-2">Location</label><input type="text" name="location" value="<?= htmlspecialchars($profileData['location']) ?>" class="w-full border rounded px-3 py-2"></div>
            <div>
                <label class="block text-gray-700 mb-2">Profile Image</label>
                <?php if ($profileData['profile_path']): ?>
                    <img src="<?= BASE_URL . $profileData['profile_path'] ?>" class="w-32 h-32 object-cover rounded mb-2">
                <?php endif; ?>
                <input type="file" name="profile_image" accept="image/*" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">About Section Image</label>
                <?php if ($profileData['about_path']): ?>
                    <img src="<?= BASE_URL . $profileData['about_path'] ?>" class="w-32 h-32 object-cover rounded mb-2">
                <?php endif; ?>
                <input type="file" name="about_image" accept="image/*" class="w-full border rounded px-3 py-2">
            </div>
        </div>
        <div class="mt-6"><button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Save Basic Info</button></div>
    </form>

    <!-- Cover Slideshow Manager -->
    <div id="covers">
        <h2 class="text-xl font-bold mb-4">Cover Image Slideshow</h2>
        <form method="POST" enctype="multipart/form-data" class="mb-6 p-4 border rounded">
            <input type="hidden" name="upload_cover" value="1">
            <?= csrfField() ?>
            <div class="flex flex-col md:flex-row gap-4">
                <input type="file" name="cover_image" accept="image/*" required class="border rounded px-3 py-2 flex-1">
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded">Upload New Cover Slide</button>
            </div>
        </form>

        <?php if (count($covers) > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($covers as $slide): ?>
            <div class="border rounded p-2 relative <?= $slide['is_active'] ? '' : 'opacity-50' ?>">
                <img src="<?= BASE_URL . $slide['file_path'] ?>" class="w-full h-40 object-cover rounded mb-2">
                <div class="flex justify-between items-center mt-2">
                    <span class="text-sm"><?= $slide['is_active'] ? 'Active' : 'Inactive' ?></span>
                    <div class="flex space-x-1">
                        <a href="?move_cover=<?= $slide['id'] ?>&dir=up&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-gray-600 hover:text-blue-600" title="Move Up"><i class="fas fa-arrow-up"></i></a>
                        <a href="?move_cover=<?= $slide['id'] ?>&dir=down&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-gray-600 hover:text-blue-600" title="Move Down"><i class="fas fa-arrow-down"></i></a>
                        <a href="?toggle_cover=<?= $slide['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-yellow-600 hover:text-yellow-800" title="Toggle Active"><i class="fas fa-eye-slash"></i></a>
                        <a href="?delete_cover=<?= $slide['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-red-600 hover:text-red-800" onclick="return confirm('Delete this cover slide?')"><i class="fas fa-trash"></i></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-gray-500">No cover slides yet. Upload one above.</p>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>