<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$db = getDB();
$message = '';

// Create backup
if (isset($_GET['create']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $filename = createDatabaseBackup();
    logActivity('Backup Created', "Created backup: $filename");
    $message = "Backup created: $filename";
}

// Download backup
if (isset($_GET['download']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $file = basename($_GET['download']);
    $filepath = BACKUP_DIR . $file;
    if (file_exists($filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        readfile($filepath);
        exit;
    }
}

// Delete backup
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $file = basename($_GET['delete']);
    $filepath = BACKUP_DIR . $file;
    if (file_exists($filepath)) unlink($filepath);
    logActivity('Backup Deleted', "Deleted backup: $file");
    redirect('backup.php');
}

// Restore backup
if (isset($_GET['restore']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $file = basename($_GET['restore']);
    $filepath = BACKUP_DIR . $file;
    if (file_exists($filepath)) {
        restoreDatabaseBackup($filepath);
        logActivity('Backup Restored', "Restored from: $file");
        $message = "Database restored from $file";
    }
}

$backups = glob(BACKUP_DIR . '*.sql');
rsort($backups);
?>
<div class="bg-white rounded-lg shadow p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-6">Backup Management</h1>
    <?php if ($message): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <div class="mb-6">
        <a href="?create=1&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="bg-blue-600 text-white px-4 py-2 rounded inline-block hover:bg-blue-700" onclick="return confirm('Create a new database backup?')">Create New Backup</a>
    </div>

    <h2 class="text-xl font-bold mb-3">Existing Backups</h2>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[600px]">
            <thead><tr class="bg-gray-100"><th class="p-2 text-left">Filename</th><th class="p-2 text-left">Size</th><th class="p-2 text-left">Date</th><th class="p-2 text-left">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($backups as $backup): ?>
                <?php $filename = basename($backup); ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2"><?= htmlspecialchars($filename) ?></td>
                    <td class="p-2"><?= round(filesize($backup) / 1024, 2) ?> KB</td>
                    <td class="p-2"><?= date('Y-m-d H:i:s', filemtime($backup)) ?></td>
                    <td class="p-2 whitespace-nowrap">
                        <a href="?download=<?= urlencode($filename) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-blue-600 hover:underline mr-2">Download</a>
                        <a href="?restore=<?= urlencode($filename) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-yellow-600 hover:underline mr-2" onclick="return confirm('Restore database from this backup? Current data will be overwritten.')">Restore</a>
                        <a href="?delete=<?= urlencode($filename) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this backup file?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($backups)): ?>
                <tr><td colspan="4" class="p-4 text-center text-gray-500">No backups found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>