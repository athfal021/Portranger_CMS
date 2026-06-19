<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Get unread messages count
$unreadCount = 0;
if (function_exists('getDB')) {
    try {
        $db = getDB();
        $unreadCount = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
    } catch (Exception $e) {
        // Silent fail
    }
}
?>
<aside id="admin-sidebar" class="sidebar-desktop fixed md:relative w-64 bg-gray-900 text-white flex-shrink-0 h-full md:h-auto transition-transform duration-300 z-40">
    <div class="p-4 text-xl font-bold border-b border-gray-700 hidden md:block">
        <?= htmlspecialchars($seo['site_title'] ?? 'Portfolio CMS') ?>
    </div>
    <nav class="p-4 space-y-2 overflow-y-auto h-full pb-20">
        <a href="dashboard.php" class="block py-2 px-4 rounded <?= $current_page == 'dashboard.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-tachometer-alt mr-2"></i> Dashboard</a>
        <a href="profile.php" class="block py-2 px-4 rounded <?= $current_page == 'profile.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-user mr-2"></i> Profile</a>
        <a href="sections.php" class="block py-2 px-4 rounded <?= $current_page == 'sections.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-layer-group mr-2"></i> Sections</a>
        <a href="skills.php" class="block py-2 px-4 rounded <?= $current_page == 'skills.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-code mr-2"></i> Skills</a>
        <a href="experiences.php" class="block py-2 px-4 rounded <?= $current_page == 'experiences.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-briefcase mr-2"></i> Experiences</a>
        <a href="educations.php" class="block py-2 px-4 rounded <?= $current_page == 'educations.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-graduation-cap mr-2"></i> Education</a>
        <a href="projects.php" class="block py-2 px-4 rounded <?= $current_page == 'projects.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-folder-open mr-2"></i> Projects</a>
        <a href="downloads.php" class="block py-2 px-4 rounded <?= $current_page == 'downloads.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-download mr-2"></i> Downloads</a>
        <a href="social-links.php" class="block py-2 px-4 rounded <?= $current_page == 'social-links.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-share-alt mr-2"></i> Social Links</a>
        <a href="navigation.php" class="block py-2 px-4 rounded <?= $current_page == 'navigation.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-bars mr-2"></i> Navigation</a>
        <a href="footer-links.php" class="block py-2 px-4 rounded <?= $current_page == 'footer-links.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-link mr-2"></i> Footer Links</a>
        <a href="appearance.php" class="block py-2 px-4 rounded <?= $current_page == 'appearance.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-palette mr-2"></i> Appearance</a>
        <a href="seo.php" class="block py-2 px-4 rounded <?= $current_page == 'seo.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-search mr-2"></i> SEO</a>
        <a href="media.php" class="block py-2 px-4 rounded <?= $current_page == 'media.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-images mr-2"></i> Media</a>
        <a href="messages.php" class="block py-2 px-4 rounded <?= $current_page == 'messages.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?> relative">
            <i class="fas fa-envelope mr-2"></i> Messages
            <?php if ($unreadCount > 0): ?>
                <span class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $unreadCount ?></span>
            <?php endif; ?>
        </a>
        <a href="activity-log.php" class="block py-2 px-4 rounded <?= $current_page == 'activity-log.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-history mr-2"></i> Activity Log</a>
        <a href="backup.php" class="block py-2 px-4 rounded <?= $current_page == 'backup.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-database mr-2"></i> Backup</a>
        <a href="settings.php" class="block py-2 px-4 rounded <?= $current_page == 'settings.php' ? 'bg-blue-600' : 'hover:bg-gray-800' ?>"><i class="fas fa-cog mr-2"></i> Settings</a>
        <hr class="border-gray-700 my-4">
        <a href="logout.php" class="block py-2 px-4 rounded hover:bg-red-600"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
    </nav>
</aside>
<main class="flex-1 overflow-y-auto">
    <div class="p-4 md:p-6">