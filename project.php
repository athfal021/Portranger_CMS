<?php
require_once 'admin/includes/config.php';
require_once 'admin/includes/db.php';
require_once 'admin/includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$appearance = getAppearance();
$profile = getProfile();
$seo = getSEO();
$navigation = $db->query("SELECT * FROM navigation_items WHERE is_active = 1 ORDER BY display_order")->fetchAll();

// Fetch project details
$stmt = $db->prepare("SELECT p.*, m.file_path as thumbnail_path FROM projects p LEFT JOIN media m ON p.thumbnail_media_id = m.id WHERE p.id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();
if (!$project) {
    header('Location: index.php');
    exit;
}

// Fetch gallery
$galleryStmt = $db->prepare("SELECT m.file_path, m.original_name FROM project_gallery pg JOIN media m ON pg.media_id = m.id WHERE pg.project_id = ? ORDER BY pg.display_order");
$galleryStmt->execute([$id]);
$gallery = $galleryStmt->fetchAll();

// Add Downloads link if needed
$downloadCount = $db->query("SELECT COUNT(*) FROM downloads")->fetchColumn();
$hasDownloadsLink = false;
foreach ($navigation as $nav) {
    if (strpos($nav['url'], 'downloads.php') !== false) {
        $hasDownloadsLink = true;
        break;
    }
}
if ($downloadCount > 0 && !$hasDownloadsLink) {
    $navigation[] = [
        'title' => 'Downloads',
        'url' => BASE_URL . 'downloads.php',
        'target' => '_self',
        'id' => 999,
        'is_active' => 1,
        'display_order' => 999
    ];
}

// Fetch footer links
$footerLinks = $db->query("SELECT * FROM footer_links WHERE is_active = 1 ORDER BY display_order")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['title']) ?> - <?= htmlspecialchars($seo['site_title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($project['short_description']) ?>">
    <?php if ($appearance['favicon_path']): ?>
        <link rel="icon" type="image/x-icon" href="<?= BASE_URL . $appearance['favicon_path'] ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        :root {
            --primary: <?= $appearance['primary_color'] ?>;
            --secondary: <?= $appearance['secondary_color'] ?>;
        }
        .bg-primary { background-color: var(--primary); }
        .bg-secondary { background-color: var(--secondary); }
        .text-primary { color: var(--primary); }
        .text-secondary { color: var(--secondary); }
        .border-primary { border-color: var(--primary); }
        .border-secondary { border-color: var(--secondary); }
        .hover\:bg-primary:hover { background-color: var(--primary); }
        .hover\:bg-secondary:hover { background-color: var(--secondary); }
        .hover\:text-primary:hover { color: var(--primary); }
        .hover\:text-secondary:hover { color: var(--secondary); }
        .hover\:border-primary:hover { border-color: var(--primary); }
        .hover\:border-secondary:hover { border-color: var(--secondary); }
        
        .mobile-nav { position: fixed; top: 0; left: -280px; width: 280px; height: 100%; background: white; box-shadow: 2px 0 10px rgba(0,0,0,0.1); transition: left 0.3s ease; z-index: 1000; overflow-y: auto; }
        .mobile-nav.open { left: 0; }
        .menu-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .menu-overlay.active { display: block; }
        body.menu-open { overflow: hidden; }
        
        .min-h-screen { min-height: 100vh; display: flex; flex-direction: column; }
        .flex-grow { flex: 1; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="menu-overlay" id="menu-overlay"></div>
    <div class="mobile-nav" id="mobile-nav">
        <div class="p-4 border-b flex justify-between items-center">
            <?php if ($appearance['logo_path']): ?>
                <img src="<?= BASE_URL . $appearance['logo_path'] ?>" class="h-8 w-auto" alt="Logo">
            <?php else: ?>
                <span class="font-bold text-xl text-primary"><?= htmlspecialchars($profile['full_name']) ?></span>
            <?php endif; ?>
            <button id="close-mobile-menu" class="text-gray-600 hover:text-primary"><i class="fas fa-times text-2xl"></i></button>
        </div>
        <nav class="p-4 space-y-3">
            <?php foreach ($navigation as $nav): ?>
                <a href="<?= htmlspecialchars($nav['url']) ?>" class="block py-2 text-gray-700 hover:text-primary transition"><?= htmlspecialchars($nav['title']) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>

    <nav class="bg-white shadow-md fixed w-full z-20">
        <div class="container mx-auto px-6 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <?php if ($appearance['logo_path']): ?>
                    <img src="<?= BASE_URL . $appearance['logo_path'] ?>" class="h-10 w-auto" alt="Logo">
                <?php endif; ?>
                <div class="text-xl font-bold text-primary"><?= htmlspecialchars($profile['full_name']) ?></div>
            </div>
            <div class="hidden md:flex space-x-6">
                <?php foreach ($navigation as $nav): ?>
                    <a href="<?= htmlspecialchars($nav['url']) ?>" class="text-gray-700 hover:text-primary transition"><?= htmlspecialchars($nav['title']) ?></a>
                <?php endforeach; ?>
            </div>
            <button id="mobile-menu-btn" class="md:hidden text-gray-700 hover:text-primary"><i class="fas fa-bars text-2xl"></i></button>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="flex-grow container mx-auto px-6 py-24">
        <a href="<?= BASE_URL ?>#projects" class="text-primary hover:text-secondary transition inline-flex items-center gap-2 mb-6">
            <i class="fas fa-arrow-left"></i> Back to Projects
        </a>

        <h1 class="text-4xl font-bold text-gray-800 mb-4"><?= htmlspecialchars($project['title']) ?></h1>
        <p class="text-gray-600 text-lg mb-6"><?= htmlspecialchars($project['short_description']) ?></p>

        <?php if ($project['thumbnail_path']): ?>
            <img src="<?= BASE_URL . $project['thumbnail_path'] ?>" alt="<?= htmlspecialchars($project['title']) ?>" class="w-full max-w-3xl rounded-lg shadow-lg mb-8 object-cover">
        <?php endif; ?>

        <div class="prose max-w-none text-gray-700 leading-relaxed mb-8">
            <?= nl2br(htmlspecialchars($project['detailed_description'])) ?>
        </div>

        <?php if ($project['technologies']): ?>
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-800 mb-2">Technologies Used</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (explode(',', $project['technologies']) as $tech): ?>
                        <span class="bg-gray-200 px-3 py-1 rounded-full text-sm text-gray-700"><?= htmlspecialchars(trim($tech)) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($gallery)): ?>
    <div class="mb-8">
        <h3 class="text-xl font-bold text-gray-800 mb-3">Project Gallery</h3>

        <div class="
            flex overflow-x-auto gap-4 pb-2 snap-x snap-mandatory
            md:grid md:grid-cols-3 md:overflow-visible
        ">
            <?php foreach ($gallery as $img): ?>
                <div class="flex-shrink-0 w-72 snap-start md:w-auto">
                    <img
                        src="<?= BASE_URL . $img['file_path'] ?>"
                        alt="<?= htmlspecialchars($img['original_name']) ?>"
                        class="rounded-lg shadow w-full h-48 object-cover hover:shadow-lg transition"
                    >
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

        <div class="flex flex-wrap gap-4 mt-6">
            <?php if ($project['demo_link']): ?>
                <a href="<?= $project['demo_link'] ?>" target="_blank" class="bg-primary text-white px-6 py-3 rounded-lg hover:bg-secondary transition inline-flex items-center gap-2">
                    <i class="fas fa-external-link-alt"></i> Live Demo
                </a>
            <?php endif; ?>
            <?php if ($project['repo_link']): ?>
                <a href="<?= $project['repo_link'] ?>" target="_blank" class="border-2 border-primary text-primary px-6 py-3 rounded-lg hover:bg-primary hover:text-white transition inline-flex items-center gap-2">
                    <i class="fab fa-github"></i> Repository
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>#projects" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 transition inline-flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Footer with Links -->
    <footer class="bg-gray-900 text-white py-6">
        <div class="container mx-auto px-6">
            <?php if (count($footerLinks) > 0): ?>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-3 mb-4">
                <?php foreach ($footerLinks as $link): ?>
                    <a href="<?= htmlspecialchars($link['url']) ?>" target="<?= $link['target'] ?>" class="text-gray-300 hover:text-primary transition text-sm"><?= htmlspecialchars($link['title']) ?></a>
                <?php endforeach; ?>
            </div>
            <hr class="border-gray-700 max-w-md mx-auto mb-4">
            <?php endif; ?>
            <div class="<?= count($footerLinks) > 0 ? '' : 'border-0 pt-0' ?>">
                <p class="text-center text-gray-400 text-sm">&copy; <?= date('Y') ?> <?= htmlspecialchars($profile['full_name']) ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="<?= BASE_URL ?>assets/js/frontend.js"></script>
</body>
</html>