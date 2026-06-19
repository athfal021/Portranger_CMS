<?php
require_once __DIR__ . '/db.php';

// Activity logging
function logActivity($action, $details = null) {
    if (!isset($_SESSION['admin_id'])) return;
    $db = getDB();
    $username = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Unknown';
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, username, action, details) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['admin_id'], $username, $action, $details]);
}

// File upload handler
function uploadFile($file, $type = 'image') {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension'
        ];
        $errorMsg = isset($errors[$file['error']]) ? $errors[$file['error']] : 'Unknown upload error';
        return ['success' => false, 'error' => $errorMsg];
    }

    $allowedTypes = ($type === 'image') ? ALLOWED_IMAGES : ALLOWED_DOCS;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        return ['success' => false, 'error' => 'File type not allowed. Allowed: ' . implode(', ', $allowedTypes)];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File too large. Max: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB'];
    }

    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create upload directory'];
        }
    }

    $newFileName = uniqid() . '.' . $ext;
    $filePath = 'uploads/media/' . $newFileName;
    $fullPath = UPLOAD_DIR . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file. Check folder permissions.'];
    }

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO media (file_name, original_name, file_path, file_type, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $newFileName,
        $file['name'],
        $filePath,
        $type,
        $file['size'],
        $file['type']
    ]);

    return ['success' => true, 'media_id' => $db->lastInsertId(), 'file_path' => $filePath];
}

// Delete media file and record
function deleteMedia($mediaId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT file_path FROM media WHERE id = ?");
    $stmt->execute([$mediaId]);
    $media = $stmt->fetch();
    if ($media) {
        $filePath = dirname(__DIR__, 2) . '/' . $media['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $stmt = $db->prepare("DELETE FROM media WHERE id = ?");
        $stmt->execute([$mediaId]);
        return true;
    }
    return false;
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// CSRF token field
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

function verifyCSRF() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF validation failed');
    }
}

// *** FIXED REDIRECT FUNCTION ***
function redirect($url) {
    // Clear all output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    // Send redirect header
    header("Location: $url");
    exit;
}

// Get profile with images (with fallback)
function getProfile() {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, 
               prof.file_path as profile_image_path, 
               about.file_path as about_image_path, 
               cover.file_path as cover_image_path 
        FROM profile p
        LEFT JOIN media prof ON p.profile_image_id = prof.id
        LEFT JOIN media about ON p.about_image_id = about.id
        LEFT JOIN media cover ON p.cover_image_id = cover.id
        WHERE p.id = 1
    ");
    $stmt->execute();
    $profile = $stmt->fetch();
    if (!$profile) {
        // Fallback if no profile exists (should not happen, but safe)
        return [
            'id' => 1,
            'full_name' => 'Your Name',
            'professional_title' => 'Professional Title',
            'short_intro' => '',
            'about_description' => '',
            'email' => '',
            'phone' => '',
            'location' => '',
            'profile_image_id' => null,
            'about_image_id' => null,
            'cover_image_id' => null,
            'profile_image_path' => null,
            'about_image_path' => null,
            'cover_image_path' => null
        ];
    }
    return $profile;
}

// Get appearance with logo/favicon paths (with fallback)
function getAppearance() {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT a.*, 
               logo.file_path as logo_path, 
               favicon.file_path as favicon_path 
        FROM appearance a
        LEFT JOIN media logo ON a.logo_media_id = logo.id
        LEFT JOIN media favicon ON a.favicon_media_id = favicon.id
        WHERE a.id = 1
    ");
    $stmt->execute();
    $appearance = $stmt->fetch();
    if (!$appearance) {
        return [
            'id' => 1,
            'primary_color' => '#3b82f6',
            'secondary_color' => '#1e293b',
            'logo_media_id' => null,
            'favicon_media_id' => null,
            'logo_path' => null,
            'favicon_path' => null
        ];
    }
    return $appearance;
}

// Get SEO (with fallback)
function getSEO() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM seo LIMIT 1");
    $seo = $stmt->fetch();
    if (!$seo) {
        return [
            'id' => 1,
            'site_title' => 'Portfolio CMS',
            'meta_description' => '',
            'meta_keywords' => '',
            'social_image_media_id' => null
        ];
    }
    return $seo;
}

// Icon dropdown with custom option
function iconDropdown($selected = '', $name = 'icon') {
    $commonIcons = [
        'fas fa-code' => 'Code (fas fa-code)',
        'fas fa-laptop-code' => 'Laptop Code (fas fa-laptop-code)',
        'fas fa-database' => 'Database (fas fa-database)',
        'fas fa-cloud' => 'Cloud (fas fa-cloud)',
        'fas fa-server' => 'Server (fas fa-server)',
        'fas fa-mobile-alt' => 'Mobile (fas fa-mobile-alt)',
        'fas fa-palette' => 'Palette (fas fa-palette)',
        'fas fa-chart-line' => 'Analytics (fas fa-chart-line)',
        'fas fa-bullhorn' => 'Bullhorn (fas fa-bullhorn)',
        'fas fa-users' => 'Users (fas fa-users)',
        'fas fa-cog' => 'Settings (fas fa-cog)',
        'fas fa-shield-alt' => 'Security (fas fa-shield-alt)',
        'fas fa-lock' => 'Lock (fas fa-lock)',
        'fas fa-key' => 'Key (fas fa-key)',
        'fas fa-envelope' => 'Envelope (fas fa-envelope)',
        'fas fa-phone' => 'Phone (fas fa-phone)',
        'fas fa-map-marker-alt' => 'Location (fas fa-map-marker-alt)',
        'fas fa-calendar' => 'Calendar (fas fa-calendar)',
        'fas fa-clock' => 'Clock (fas fa-clock)',
        'fas fa-user' => 'User (fas fa-user)',
        'fas fa-user-tie' => 'User Tie (fas fa-user-tie)',
        'fas fa-briefcase' => 'Briefcase (fas fa-briefcase)',
        'fas fa-graduation-cap' => 'Graduation (fas fa-graduation-cap)',
        'fas fa-book' => 'Book (fas fa-book)',
        'fas fa-award' => 'Award (fas fa-award)',
        'fas fa-certificate' => 'Certificate (fas fa-certificate)',
        'fas fa-medal' => 'Medal (fas fa-medal)',
        'fas fa-trophy' => 'Trophy (fas fa-trophy)',
        'fas fa-star' => 'Star (fas fa-star)',
        'fas fa-heart' => 'Heart (fas fa-heart)',
        'fas fa-thumbs-up' => 'Thumbs Up (fas fa-thumbs-up)',
        'fas fa-check-circle' => 'Check Circle (fas fa-check-circle)',
        'fas fa-file-alt' => 'File (fas fa-file-alt)',
        'fas fa-folder-open' => 'Folder (fas fa-folder-open)',
        'fas fa-download' => 'Download (fas fa-download)',
        'fas fa-upload' => 'Upload (fas fa-upload)',
        'fas fa-link' => 'Link (fas fa-link)',
        'fas fa-external-link-alt' => 'External Link (fas fa-external-link-alt)',
        'fab fa-github' => 'GitHub (fab fa-github)',
        'fab fa-linkedin' => 'LinkedIn (fab fa-linkedin)',
        'fab fa-twitter' => 'Twitter (fab fa-twitter)',
        'fab fa-facebook' => 'Facebook (fab fa-facebook)',
        'fab fa-instagram' => 'Instagram (fab fa-instagram)',
        'fab fa-youtube' => 'YouTube (fab fa-youtube)',
        'fab fa-wordpress' => 'WordPress (fab fa-wordpress)',
        'fab fa-react' => 'React (fab fa-react)',
        'fab fa-vuejs' => 'Vue.js (fab fa-vuejs)',
        'fab fa-angular' => 'Angular (fab fa-angular)',
        'fab fa-node' => 'Node.js (fab fa-node)',
        'fab fa-python' => 'Python (fab fa-python)',
        'fab fa-java' => 'Java (fab fa-java)',
        'fab fa-php' => 'PHP (fab fa-php)',
        'fab fa-laravel' => 'Laravel (fab fa-laravel)',
        'fab fa-symfony' => 'Symfony (fab fa-symfony)',
        'fab fa-docker' => 'Docker (fab fa-docker)',
        'fab fa-aws' => 'AWS (fab fa-aws)',
        'fab fa-git' => 'Git (fab fa-git)',
        'fab fa-gitlab' => 'GitLab (fab fa-gitlab)'
    ];
    
    $isCustomSelected = ($selected !== '' && !in_array($selected, array_keys($commonIcons)));
    $html = '<select name="' . $name . '" id="icon_select_' . $name . '" class="border rounded px-3 py-2 w-full" onchange="toggleCustomIcon(this)">';
    $html .= '<option value="">-- No Icon --</option>';
    foreach ($commonIcons as $iconClass => $label) {
        $selectedAttr = ($selected == $iconClass) ? 'selected' : '';
        $html .= '<option value="' . htmlspecialchars($iconClass) . '" ' . $selectedAttr . '>' . $label . '</option>';
    }
    $html .= '<option value="__custom__" ' . ($isCustomSelected ? 'selected' : '') . '>-- Custom (paste full icon code) --</option>';
    $html .= '</select>';
    $html .= '<div id="custom_icon_' . $name . '" style="margin-top:8px; ' . ($isCustomSelected ? 'display:block' : 'display:none') . '">';
    $html .= '<input type="text" name="custom_icon" placeholder="e.g., fab fa-linkedin, fas fa-rocket, far fa-heart" value="' . ($isCustomSelected ? htmlspecialchars($selected) : '') . '" class="border rounded px-3 py-2 w-full">';
    $html .= '<p class="text-xs text-gray-500 mt-1">';
    $html .= '<a href="https://fontawesome.com" target="_blank" class="text-blue-600 hover:underline">📋 Click to get icon codes from Font Awesome.</a> ';
    $html .= '- Copy the full class like <code class="bg-gray-100 px-1">fab fa-github</code> or <code class="bg-gray-100 px-1">fas fa-code</code></p>';
    $html .= '</div>';
    $html .= '<script>
        function toggleCustomIcon(selectEl) {
            var customDiv = document.getElementById("custom_icon_" + selectEl.name);
            if (selectEl.value === "__custom__") {
                customDiv.style.display = "block";
            } else {
                customDiv.style.display = "none";
            }
        }
    </script>';
    return $html;
}

// Backup functions
// Backup functions
function createDatabaseBackup() {
    $db = getDB();
    $tables = [];
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    $backupSql = "";
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT * FROM $table");
        $rows = $stmt->fetchAll();
        $backupSql .= "DROP TABLE IF EXISTS $table;\n";
        $createStmt = $db->query("SHOW CREATE TABLE $table");
        $create = $createStmt->fetch(PDO::FETCH_ASSOC);
        $backupSql .= $create['Create Table'] . ";\n\n";
        foreach ($rows as $row) {
            $columns = array_keys($row);
            $values = array_map(function($val) use ($db) {
                if ($val === null) {
                    return 'NULL';
                }
                return $db->quote($val);
            }, array_values($row));
            $backupSql .= "INSERT INTO $table (" . implode(",", $columns) . ") VALUES (" . implode(",", $values) . ");\n";
        }
        $backupSql .= "\n";
    }
    if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0755, true);
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    file_put_contents(BACKUP_DIR . $filename, $backupSql);
    return $filename;
}

function restoreDatabaseBackup($filepath) {
    $db = getDB();
    $sql = file_get_contents($filepath);
    $db->exec("SET FOREIGN_KEY_CHECKS=0");
    foreach (explode(";\n", $sql) as $query) {
        if (trim($query)) {
            $db->exec($query);
        }
    }
    $db->exec("SET FOREIGN_KEY_CHECKS=1");
    return true;
}
?>