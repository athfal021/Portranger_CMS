<?php
require_once 'admin/includes/config.php';
require_once 'admin/includes/db.php';
require_once 'admin/includes/functions.php';

$db = getDB();
$profile = getProfile();
$seo = getSEO();
$appearance = getAppearance();
$navigation = $db->query("SELECT * FROM navigation_items WHERE is_active = 1 ORDER BY display_order")->fetchAll();

// Sorting logic
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

switch ($sort) {
    case 'title':
        $orderBy = "d.title $order";
        break;
    case 'size':
        $orderBy = "m.file_size $order";
        break;
    case 'date':
    default:
        $orderBy = "d.created_at $order";
        break;
}

// Search logic
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$searchCondition = '';
$params = [];
if (!empty($search)) {
    $searchCondition = "WHERE d.title LIKE ? OR d.description LIKE ?";
    $params = ["%$search%", "%$search%"];
}

// Build query with search
$query = "
    SELECT d.*, m.file_path, m.original_name, m.file_size, m.created_at as file_uploaded 
    FROM downloads d 
    JOIN media m ON d.media_id = m.id 
    $searchCondition
    ORDER BY $orderBy
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$downloads = $stmt->fetchAll();

function formatSize($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// Fetch footer links
$footerLinks = $db->query("SELECT * FROM footer_links WHERE is_active = 1 ORDER BY display_order")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloads - <?= htmlspecialchars($seo['site_title']) ?></title>
    <meta name="description" content="Downloadable resources from <?= htmlspecialchars($profile['full_name']) ?>">
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
        .ring-primary { --tw-ring-color: var(--primary); }

        /* Mobile navigation */
        .mobile-nav { position: fixed; top: 0; left: -280px; width: 280px; height: 100%; background: white; box-shadow: 2px 0 10px rgba(0,0,0,0.1); transition: left 0.3s ease; z-index: 1000; overflow-y: auto; }
        .mobile-nav.open { left: 0; }
        .menu-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .menu-overlay.active { display: block; }
        body.menu-open { overflow: hidden; }

        /* Table horizontal scroll */
        .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .downloads-table { min-width: 700px; width: 100%; border-collapse: collapse; }
        .downloads-table thead { background-color: #f3f4f6; }
        .downloads-table th, .downloads-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .downloads-table tbody tr:hover { background-color: #f9fafb; }
        .btn-download { background-color: var(--primary); color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; display: inline-flex; align-items: center; gap: 0.5rem; transition: background-color 0.2s; }
        .btn-download:hover { background-color: var(--secondary); }
        .action-cell { text-align: center; }

        .search-box:focus { outline: none; --tw-ring-color: var(--primary); }

        /* Flex layout to keep footer at bottom */
        html, body { height: 100%; margin: 0; }
        body { display: flex; flex-direction: column; background-color: #f9fafb; }
        .main-content { flex: 1 0 auto; }
        .main-footer { flex-shrink: 0; }
    </style>
</head>
<body>
    <!-- Mobile Overlay & Menu -->
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

    <!-- Desktop Navbar -->
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
    <div class="main-content pt-24 pb-12">
        <!-- Page Header -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 py-12">
            <div class="container mx-auto px-6">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800">Downloads</h1>
                <p class="text-gray-600 mt-2">Download resources from the table below.</p>
            </div>
        </div>

        <!-- Downloads Table -->
        <div class="container mx-auto px-6 py-12">
            <!-- Search Box -->
            <div class="mb-6">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <input type="text" name="search" placeholder="Search by title or description..." value="<?= htmlspecialchars($search) ?>" 
                               class="search-box w-full border rounded px-4 py-2 focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <button type="submit" class="bg-primary text-white px-6 py-2 rounded hover:bg-secondary transition whitespace-nowrap">
                        <i class="fas fa-search mr-2"></i> Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="<?= BASE_URL ?>downloads.php" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition text-center">Clear</a>
                    <?php endif; ?>
                </form>
                <?php if (!empty($search)): ?>
                    <p class="text-sm text-gray-500 mt-2">Showing results for: "<strong><?= htmlspecialchars($search) ?></strong>" (<?= count($downloads) ?> found)</p>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="table-wrapper">
                    <table class="downloads-table">
                        <thead>
                            <tr>
                                <th><a href="?<?= !empty($search) ? 'search=' . urlencode($search) . '&' : '' ?>sort=title&order=<?= $sort === 'title' && $order === 'DESC' ? 'asc' : 'desc' ?>" class="hover:text-secondary transition">Title <?= $sort === 'title' ? ($order === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                <th>Description</th>
                                <th><a href="?<?= !empty($search) ? 'search=' . urlencode($search) . '&' : '' ?>sort=size&order=<?= $sort === 'size' && $order === 'DESC' ? 'asc' : 'desc' ?>" class="hover:text-secondary transition">Size <?= $sort === 'size' ? ($order === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                <th><a href="?<?= !empty($search) ? 'search=' . urlencode($search) . '&' : '' ?>sort=date&order=<?= $sort === 'date' && $order === 'DESC' ? 'asc' : 'desc' ?>" class="hover:text-secondary transition">Date <?= $sort === 'date' ? ($order === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                <th class="action-cell">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($downloads) > 0): ?>
                                <?php foreach ($downloads as $dl): ?>
                                <tr>
                                    <td><?= htmlspecialchars($dl['title']) ?></td>
                                    <td><?= htmlspecialchars($dl['description']) ?></td>
                                    <td><?= formatSize($dl['file_size']) ?></td>
                                    <td><?= date('M d, Y', strtotime($dl['file_uploaded'])) ?></td>
                                    <td class="action-cell"><a href="<?= BASE_URL . $dl['file_path'] ?>" download class="btn-download"><i class="fas fa-download"></i> Download</a></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="p-8 text-center text-gray-500"><?= !empty($search) ? 'No files match your search.' : 'No downloadable files available yet.' ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-8 text-center">
                <a href="<?= BASE_URL ?>" class="text-primary hover:text-secondary transition">&larr; Back to Home</a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="main-footer bg-gray-900 text-white py-6">
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