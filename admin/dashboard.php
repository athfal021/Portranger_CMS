<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();

// Get counts
$projectCount = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$skillCount = $db->query("SELECT COUNT(*) FROM skills")->fetchColumn();
$experienceCount = $db->query("SELECT COUNT(*) FROM experiences")->fetchColumn();
$educationCount = $db->query("SELECT COUNT(*) FROM educations")->fetchColumn();
$downloadCount = $db->query("SELECT COUNT(*) FROM downloads")->fetchColumn();
$mediaCount = $db->query("SELECT COUNT(*) FROM media")->fetchColumn();
$sectionCount = $db->query("SELECT COUNT(*) FROM sections WHERE is_visible = 1")->fetchColumn();
$messageCount = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6"><div class="text-blue-600 text-3xl mb-2"><?= $projectCount ?></div><div class="text-gray-600">Total Projects</div></div>
    <div class="bg-white rounded-lg shadow p-6"><div class="text-green-600 text-3xl mb-2"><?= $skillCount ?></div><div class="text-gray-600">Total Skills</div></div>
    <div class="bg-white rounded-lg shadow p-6"><div class="text-yellow-600 text-3xl mb-2"><?= $experienceCount ?></div><div class="text-gray-600">Experience Entries</div></div>
    <div class="bg-white rounded-lg shadow p-6"><div class="text-purple-600 text-3xl mb-2"><?= $educationCount ?></div><div class="text-gray-600">Education Entries</div></div>
    <div class="bg-white rounded-lg shadow p-6"><div class="text-red-600 text-3xl mb-2"><?= $downloadCount ?></div><div class="text-gray-600">Downloads</div></div>
    <div class="bg-white rounded-lg shadow p-6"><div class="text-indigo-600 text-3xl mb-2"><?= $mediaCount ?></div><div class="text-gray-600">Media Files</div></div>
    <div class="bg-white rounded-lg shadow p-6"><div class="text-teal-600 text-3xl mb-2"><?= $sectionCount ?></div><div class="text-gray-600">Active Sections</div></div>
    <div class="bg-white rounded-lg shadow p-6"><div class="text-pink-600 text-3xl mb-2"><?= $messageCount ?></div><div class="text-gray-600">Unread Messages</div></div>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-bold mb-4">Quick Actions</h2>
    <div class="flex flex-wrap gap-3">
        <a href="projects.php?action=add" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-plus mr-2"></i>Add Project</a>
        <a href="experiences.php?action=add" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fas fa-plus mr-2"></i>Add Experience</a>
        <a href="media.php" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700"><i class="fas fa-upload mr-2"></i>Upload Media</a>
        <a href="downloads.php?action=add" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"><i class="fas fa-file-pdf mr-2"></i>Upload Resume</a>
        <a href="profile.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-user-edit mr-2"></i>Edit Profile</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>